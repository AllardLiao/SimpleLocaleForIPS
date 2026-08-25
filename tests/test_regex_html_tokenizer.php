<?php
declare(strict_types=1);
// Standalone replica of the REPLACED, dependency-free (no DOMDocument)
// SplitHtmlIntoTextNodes(), verifying against the user's actual reported
// weather-widget content - including the newly-observed regression where
// <span class="day">MO</span> etc. lost their wrapper tags entirely
// (proof the DOMDocument path was silently never running - ext-dom missing
// on their IP-Symcon PHP build - and the module fell back to translating
// the whole blob as one opaque unit).

function splitHtmlIntoTextNodes(string $Html): array
{
    $fallback = ['nodes' => [$Html], 'reassemble' => function (array $translated) use ($Html) {
        return $translated[0] ?? $Html;
    }];
    if (trim($Html) === '') {
        return $fallback;
    }
    $tokens = preg_split('/(<[^>]*>)/s', $Html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($tokens === false || $tokens === []) {
        return $fallback;
    }
    $nodes = [];
    $textTokenIndexes = [];
    foreach ($tokens as $tokenIndex => $token) {
        if ($token[0] === '<' || trim($token) === '') {
            continue;
        }
        $nodes[] = $token;
        $textTokenIndexes[] = $tokenIndex;
    }
    if ($nodes === []) {
        return $fallback;
    }
    $reassemble = function (array $translatedTexts) use ($tokens, $textTokenIndexes) {
        foreach ($textTokenIndexes as $position => $tokenIndex) {
            $tokens[$tokenIndex] = $translatedTexts[$position] ?? $tokens[$tokenIndex];
        }
        return implode('', $tokens);
    };
    return ['nodes' => $nodes, 'reassemble' => $reassemble];
}

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

// The user's actual second report (fresh weather data: 35°, 25% humidity,
// 0.82 m/s W), including the full <script>/<style> header exactly as
// reported - full content, unmodified.
$html = file_get_contents(__DIR__ . '/weather_widget_full.html');

$segments = splitProtectedSegments($html);
$allNodes = [];
$perSegment = [];
foreach ($segments as $segment) {
    if ($segment['protected']) {
        $perSegment[] = $segment;
        continue;
    }
    $split = splitHtmlIntoTextNodes($segment['text']);
    $perSegment[] = ['protected' => false, 'nodes' => $split['nodes'], 'reassemble' => $split['reassemble']];
    foreach ($split['nodes'] as $n) {
        $allNodes[] = $n;
    }
}

echo "Extracted " . count($allNodes) . " translatable nodes (excluding style/script):\n";
foreach ($allNodes as $i => $n) {
    echo "  [$i] " . json_encode($n, JSON_UNESCAPED_UNICODE) . "\n";
}

// Identity round-trip first - must preserve EVERY <span> tag, including the
// weekday ones (the exact thing that broke: MO/DI/MI/DO/FR/SA lost their
// <span class="day"> wrapper).
$cursor = 0;
$rebuilt = '';
foreach ($perSegment as $segment) {
    if ($segment['protected']) {
        $rebuilt .= $segment['text'];
        continue;
    }
    $count = count($segment['nodes']);
    $slice = array_slice($allNodes, $cursor, $count);
    $cursor += $count;
    $rebuilt .= ($segment['reassemble'])($slice);
}

assert(substr_count($rebuilt, '<span class="day">') === 7, 'ALL 7 weekday <span> tags must survive identity round-trip (this exact tag-loss was the reported regression)');
assert(substr_count($rebuilt, '<style type="text/css">') === 1, 'style block must survive untouched');
assert(str_contains($rebuilt, '.wico {width:100%'), 'style CSS content must be fully intact');
assert(substr_count($rebuilt, '<img') === 7, 'all 7 img tags must survive');
echo "\nTest 1 (identity round-trip preserves every tag, including weekday spans) OK\n";

// Now simulate translation and confirm cross-span immunity + span survival
// under actual translation, not just identity round-trip.
$translationMap = [
    'Überwiegend Klar' => 'Mostly Clear',
    '35°' => '35°',
    '☀️ 05:52' => '☀️ 5:52 a.m.',
    '20:34 🌓' => '8:34 p.m. 🌓',
    '0 % Regen' => '0% chance of rain',
    '25 % Luftfeuchte' => '25% humidity',
    '0.82 m/s W' => '0.82 m/s W',
    '0.00/Tag' => '0.00/day',
    'SO' => 'Sun',
    'MO' => 'Mon',
    'DI' => 'Tue',
    'MI' => 'Wed',
    'DO' => 'Thu',
    'FR' => 'Fri',
    'SA' => 'Sat',
    '23°' => '23°',
    '20°' => '20°',
    '19°' => '19°',
    '22°' => '22°',
];
$translatedNodes = array_map(function ($t) use ($translationMap) {
    $normalized = str_replace("\xc2\xa0", ' ', $t);
    return $translationMap[$normalized] ?? $translationMap[trim($normalized)] ?? $t;
}, $allNodes);

$cursor = 0;
$translated = '';
foreach ($perSegment as $segment) {
    if ($segment['protected']) {
        $translated .= $segment['text'];
        continue;
    }
    $count = count($segment['nodes']);
    $slice = array_slice($translatedNodes, $cursor, $count);
    $cursor += $count;
    $translated .= ($segment['reassemble'])($slice);
}

echo "\n--- translated output ---\n" . $translated . "\n";

assert(substr_count($translated, '<span class="day">') === 7, 'weekday spans must survive translation, not just identity round-trip');
assert(str_contains($translated, '<span class="day">Mon</span>'), 'Monday must be individually wrapped, not bare text');
assert(str_contains($translated, '<span class="day">Sat</span>'), 'Saturday must be individually wrapped');
assert(str_contains($translated, '<span class="txt fall">0% chance of rain</span>'), 'rain % must stay in its own span');
assert(str_contains($translated, '<span class="txt humi">25% humidity</span>'), 'humidity must stay in its own span, not merged with rain text');
assert(!str_contains($translated, 'chance of rain, 25%'), 'the exact reported corruption pattern must not reappear');
assert(!str_contains($translated, ', 0.82 m/s'), 'wind span must not carry a leading comma bled in from the humidity span');
echo "\nTest 2 (translated output: all spans intact, no cross-contamination) OK\n";

echo "\nAll tests passed.\n";
