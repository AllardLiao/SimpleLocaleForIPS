<?php
declare(strict_types=1);
// Standalone replica of TranslateBatchUncached()'s NEW isHtml-aware flow
// (segments -> per-node tokenization -> flat batch -> chunked "API" calls ->
// per-node reassembly -> per-segment reassembly), using the exact
// SplitProtectedSegments/SplitHtmlIntoTextNodes logic from module.php,
// verifying: (1) plain text is never DOM-parsed, (2) HTML text nodes never
// cross span boundaries, (3) style/script blocks never reach "the API",
// (4) chunk boundaries on the flattened node list are respected.

function splitProtectedSegments(string $Text): array
{
    $segments = [];
    $offset = 0;
    if (preg_match_all('/<(style|script)\b[^>]*>.*?<\/\1>/is', $Text, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as [$blockText, $blockOffset]) {
            if ($blockOffset > $offset) {
                $segments[] = ['protected' => false, 'text' => substr($Text, $offset, $blockOffset - $offset)];
            }
            $segments[] = ['protected' => true, 'text' => $blockText];
            $offset = $blockOffset + strlen($blockText);
        }
    }
    if ($offset < strlen($Text)) {
        $segments[] = ['protected' => false, 'text' => substr($Text, $offset)];
    }
    return $segments === [] ? [['protected' => false, 'text' => $Text]] : $segments;
}

function splitHtmlIntoTextNodes(string $Html): array
{
    $fallback = ['nodes' => [$Html], 'reassemble' => function (array $translated) use ($Html) {
        return $translated[0] ?? $Html;
    }];
    if (trim($Html) === '') {
        return $fallback;
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prevErrors = libxml_use_internal_errors(true);
    $wrapped = '<?xml encoding="UTF-8"><sloc-root>' . $Html . '</sloc-root>';
    $loaded = @$doc->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);
    if (!$loaded) {
        return $fallback;
    }
    $root = $doc->getElementsByTagName('sloc-root')->item(0);
    if ($root === null) {
        return $fallback;
    }
    $textNodes = [];
    $walk = function (DOMNode $node) use (&$walk, &$textNodes) {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                if (trim($child->nodeValue) !== '') {
                    $textNodes[] = $child;
                }
            } elseif ($child instanceof DOMElement) {
                $walk($child);
            }
        }
    };
    $walk($root);
    if ($textNodes === []) {
        return $fallback;
    }
    $originalTexts = array_map(fn (DOMText $n) => $n->nodeValue, $textNodes);
    $reassemble = function (array $translatedTexts) use ($doc, $root, $textNodes) {
        foreach ($textNodes as $i => $node) {
            $node->nodeValue = $translatedTexts[$i] ?? $node->nodeValue;
        }
        $inner = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $inner .= $doc->saveHTML($child);
        }
        return $inner;
    };
    return ['nodes' => $originalTexts, 'reassemble' => $reassemble];
}

function translateBatchUncached(array $Texts, bool $IsHtml, callable $translateChunkApi, int $maxPerRequest = 128): array
{
    $segmentsPerText = [];
    foreach ($Texts as $text) {
        $rawSegments = splitProtectedSegments($text);
        if (!$IsHtml) {
            $segmentsPerText[] = $rawSegments;
            continue;
        }
        $tokenized = [];
        foreach ($rawSegments as $segment) {
            if ($segment['protected']) {
                $tokenized[] = $segment;
                continue;
            }
            $split = splitHtmlIntoTextNodes($segment['text']);
            $tokenized[] = ['protected' => false, 'nodes' => $split['nodes'], 'reassemble' => $split['reassemble']];
        }
        $segmentsPerText[] = $tokenized;
    }

    $translatable = [];
    foreach ($segmentsPerText as $segments) {
        foreach ($segments as $segment) {
            if ($segment['protected']) {
                continue;
            }
            if ($IsHtml) {
                foreach ($segment['nodes'] as $node) {
                    $translatable[] = $node;
                }
            } else {
                $translatable[] = $segment['text'];
            }
        }
    }

    $translatedFlat = [];
    foreach (array_chunk($translatable, $maxPerRequest) as $chunk) {
        $translatedFlat = array_merge($translatedFlat, $translateChunkApi($chunk));
    }

    $result = [];
    $cursor = 0;
    foreach ($segmentsPerText as $segments) {
        $rebuilt = '';
        foreach ($segments as $segment) {
            if ($segment['protected']) {
                $rebuilt .= $segment['text'];
                continue;
            }
            if ($IsHtml) {
                $count = count($segment['nodes']);
                $translatedNodes = array_slice($translatedFlat, $cursor, $count);
                $cursor += $count;
                $rebuilt .= ($segment['reassemble'])($translatedNodes);
            } else {
                $rebuilt .= $translatedFlat[$cursor++] ?? '';
            }
        }
        $result[] = $rebuilt;
    }
    return $result;
}

// Mock "API": deterministic per-text mapping, tracks every text it was
// asked to translate (to prove style/script content never reaches it, and
// that plain non-HTML text is never split).
$apiCallLog = [];
$translationMap = [
    'Wohnzimmer & Küche' => 'Living room & kitchen', // literal "&" in a PLAIN object name
    'Klar' => 'Clear',
    '13°' => '13°',
    '0 % Regen' => '0% chance of rain',
    '78 % Luftfeuchte' => '78% humidity',
    '0.00 m/s SO' => '0.00 m/s SE',
    '0.00/Tag' => '0.00/day',
];
$mockApi = function (array $chunk) use (&$apiCallLog, $translationMap) {
    $apiCallLog = array_merge($apiCallLog, $chunk);
    return array_map(fn ($t) => $translationMap[$t] ?? ('[' . $t . ']'), $chunk);
};

// Test 1: plain (isHtml=false) text with a literal "&" must NOT be DOM-parsed
// or split - sent to "the API" as exactly one whole unit.
$apiCallLog = [];
$plainResult = translateBatchUncached(['Wohnzimmer & Küche'], false, $mockApi);
assert($plainResult[0] === 'Living room & kitchen', 'Plain text must translate correctly as a single unit');
assert($apiCallLog === ['Wohnzimmer & Küche'], 'Plain text must be sent to the API completely unmodified, not tokenized');
echo "Test 1 (plain text bypasses HTML tokenization) OK\n";

// Test 2: HTML with <style> must never leak style content to the API, and
// adjacent spans must never cross-contaminate (the actual reported bug).
$apiCallLog = [];
$html = '<style>.x{color:red}</style><div class="wdes">Klar</div><span class="txt fall">0 % Regen</span><span class="txt humi">78 % Luftfeuchte</span><span class="txt wind">0.00 m/s SO</span><span class="txt rain">0.00/Tag</span>';
$htmlResult = translateBatchUncached([$html], true, $mockApi);
assert(str_contains($htmlResult[0], '<style>.x{color:red}</style>'), 'style block must survive completely unmodified');
foreach ($apiCallLog as $sent) {
    assert(!str_contains($sent, 'color:red'), 'style content must NEVER be sent to the translation API');
}
assert(str_contains($htmlResult[0], '<span class="txt fall">0% chance of rain</span>'), 'Rain text must stay in its own span');
assert(str_contains($htmlResult[0], '<span class="txt humi">78% humidity</span>'), 'Humidity span must not contain rain text (the actual bug)');
assert(!str_contains($htmlResult[0], 'chance of rain, 78%'), 'Exact reported corruption pattern must not reappear');
echo "Test 2 (HTML tokenization protects style + prevents cross-span bleed) OK\n";

// Test 3: chunk boundary must operate on the FLATTENED node list, not the
// row count - with maxPerRequest=2 and 4 translatable nodes, expect 2 API
// calls of size 2 each.
$apiCallLog = [];
$apiCallCount = 0;
$countingApi = function (array $chunk) use (&$apiCallLog, &$apiCallCount, $translationMap) {
    $apiCallCount++;
    $apiCallLog[] = $chunk;
    return array_map(fn ($t) => $translationMap[$t] ?? $t, $chunk);
};
$htmlSmall = '<span>0 % Regen</span><span>78 % Luftfeuchte</span><span>0.00 m/s SO</span><span>0.00/Tag</span>';
translateBatchUncached([$htmlSmall], true, $countingApi, 2);
assert($apiCallCount === 2, 'Chunking must operate on the flattened text-node list (4 nodes / 2 per request = 2 calls)');
echo "Test 3 (chunk boundary applies to flattened node list) OK\n";

echo "\nAll tests passed.\n";
