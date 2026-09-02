<?php
declare(strict_types=1);
// Standalone prototype + test for the 2026-08-15 "cross-span translation
// shuffling" bug: Google/DeepL's format=html mode can move translated text
// across adjacent <span> boundaries when spans sit directly next to each
// other with no separating whitespace (exactly the weather-widget report).
// Fix: extract each text node ourselves via DOMDocument, translate each as
// an independent unit, reinsert by node identity - structurally impossible
// for one node's translation to land in another's slot.

function splitHtmlIntoTextNodes(string $Html): array
{
    if (trim($Html) === '') {
        return ['nodes' => [], 'reassemble' => fn () => $Html];
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prevErrors = libxml_use_internal_errors(true);
    // mb_convert_encoding trick so DOMDocument doesn't mangle multi-byte UTF-8
    // (well-known PHP DOMDocument quirk with loadHTML + UTF-8).
    $wrapped = '<?xml encoding="UTF-8"><sloc-root>' . $Html . '</sloc-root>';
    $loaded = $doc->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);

    if (!$loaded) {
        return ['nodes' => [], 'reassemble' => fn () => $Html];
    }

    $root = $doc->getElementsByTagName('sloc-root')->item(0);
    if ($root === null) {
        return ['nodes' => [], 'reassemble' => fn () => $Html];
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

$html = file_get_contents(__DIR__ . '/weather_widget_sample.html');

// Test 1: identity round-trip (no translation) must not corrupt structure -
// specifically must not lose/reorder any <span>/<div>/<img> or drop the
// "0 % Regen" / "78 % Luftfeuchte" etc. text, and img self-closing tags with
// their src attributes must survive.
$split = splitHtmlIntoTextNodes($html);
echo "Extracted " . count($split['nodes']) . " text nodes:\n";
foreach ($split['nodes'] as $i => $t) {
    echo "  [$i] " . json_encode($t, JSON_UNESCAPED_UNICODE) . "\n";
}

$roundTripped = ($split['reassemble'])($split['nodes']); // identity - feed original texts back in

echo "\n--- round-tripped output ---\n";
echo $roundTripped . "\n";

// Structural checks (not byte-identical - DOMDocument WILL normalize some
// formatting/whitespace/quoting - but content and img src attributes must
// survive intact).
assert(substr_count($roundTripped, '<img') === 7, 'All 7 <img> tags must survive');
assert(str_contains($roundTripped, 'src="https://basmilius.github.io/weather-icons/production/line/all/rain.svg"'), 'img src attributes must survive unchanged');
assert(substr_count($roundTripped, 'class="txt fall"') === 1, 'span classes must survive');
assert(str_contains($roundTripped, '0 % Regen'), 'Original German text must survive round-trip when fed back unchanged');
assert(str_contains($roundTripped, '78 % Luftfeuchte'), 'Adjacent span content must not merge');
echo "\nTest 1 (identity round-trip preserves structure) OK\n";

// Test 2: simulate translation - each text node gets ITS OWN isolated
// translated value. Cross-contamination (like the real bug: "Regen" text
// ending up inside the humidity span) must be structurally impossible now,
// since each node is addressed by object identity, not string position.
$translationMap = [
    'Klar' => 'Clear',
    '13°' => '13°',
    '☀️ 05:50' => '☀️ 5:50 a.m.',
    '20:34 🌓' => '8:34 p.m. 🌓',
    '0 % Regen' => '0% chance of rain',
    '78 % Luftfeuchte' => '78% humidity',
    '0.00 m/s SO' => '0.00 m/s SE',
    '0.00/Tag' => '0.00/day',
    'SO' => 'SU',
    'MO' => 'MO',
    'DI' => 'TU',
    'MI' => 'WE',
    'DO' => 'TH',
    'FR' => 'FR',
    'SA' => 'SA',
    '23°' => '23°',
    '20°' => '20°',
    '19°' => '19°',
    '21°' => '21°',
    '22°' => '22°',
];
$translatedNodes = array_map(function ($t) use ($translationMap) {
    $normalized = str_replace("\xc2\xa0", ' ', $t); // &nbsp; -> space, matches how a translator would receive it
    return $translationMap[$normalized] ?? $translationMap[trim($normalized)] ?? $t;
}, $split['nodes']);

$translated = ($split['reassemble'])($translatedNodes);
echo "\n--- translated output ---\n";
echo $translated . "\n";

assert(str_contains($translated, '<span class="txt fall">0% chance of rain</span>'), 'Rain percentage must stay fully inside its OWN span, not bleed into the humidity span');
assert(str_contains($translated, '<span class="txt humi">78% humidity</span>'), 'Humidity span must contain ONLY the humidity text, nothing from the rain span');
assert(str_contains($translated, '<span class="txt wind">0.00 m/s SE</span>'), 'Wind span must be independently correct');
assert(str_contains($translated, '<span class="txt rain">0.00/day</span>'), 'Rain-amount span must be independently correct');
assert(!str_contains($translated, 'chance of rain, 78%'), 'The exact corruption pattern from the bug report must NOT reappear (rain text merging into humidity span)');
echo "\nTest 2 (isolated per-node translation prevents cross-span contamination) OK\n";

echo "\nAll tests passed.\n";
