<?php
declare(strict_types=1);
// Standalone replica of DetectWeekdayAbbreviationOverrides() + its wiring into
// TranslateBatchUncached(), verifying the 2026-08-15 "weekday abbreviations
// mistranslate" bug AND the user's sharp objection: "SO" is ambiguous within
// the SAME weather widget (Sonntag vs. Süd-Ost wind direction) - only a
// CONSECUTIVE RUN of >=4 distinct weekday codes should trigger the override,
// never an isolated single occurrence.

const GERMAN_WEEKDAY_ABBREVIATIONS = ['MO', 'DI', 'MI', 'DO', 'FR', 'SA', 'SO'];
const GERMAN_WEEKDAY_ABBREVIATION_OVERRIDES = [
    'en' => ['MO' => 'Mon', 'DI' => 'Tue', 'MI' => 'Wed', 'DO' => 'Thu', 'FR' => 'Fri', 'SA' => 'Sat', 'SO' => 'Sun'],
];
const GERMAN_WEEKDAY_RUN_MIN_LENGTH = 4;

function detectWeekdayAbbreviationOverrides(array $Nodes, string $Source, string $Target): array
{
    $normalizedSource = strtolower(substr($Source, 0, 2));
    $normalizedTarget = strtolower(substr($Target, 0, 2));

    if ($normalizedSource !== 'de' || !isset(GERMAN_WEEKDAY_ABBREVIATION_OVERRIDES[$normalizedTarget])) {
        return [];
    }

    $overrideTable = GERMAN_WEEKDAY_ABBREVIATION_OVERRIDES[$normalizedTarget];
    $overrides = [];
    $runIndexes = [];

    $flushRun = function () use (&$runIndexes, &$overrides, $overrideTable, $Nodes) {
        if (count($runIndexes) >= GERMAN_WEEKDAY_RUN_MIN_LENGTH) {
            foreach ($runIndexes as $index) {
                $code = mb_strtoupper(trim($Nodes[$index]), 'UTF-8');
                $overrides[$index] = $overrideTable[$code];
            }
        }
        $runIndexes = [];
    };

    $runCodes = [];
    foreach ($Nodes as $index => $node) {
        $code = mb_strtoupper(trim($node), 'UTF-8');
        if (in_array($code, GERMAN_WEEKDAY_ABBREVIATIONS, true) && !in_array($code, $runCodes, true)) {
            $runIndexes[] = $index;
            $runCodes[] = $code;
        } else {
            $flushRun();
            $runCodes = [];
        }
    }
    $flushRun();

    return $overrides;
}

// Test 1: the actual reported weather widget - 7 consecutive distinct
// weekday codes must ALL get the correct, deterministic English mapping.
$weatherWidgetNodes = [
    0 => 'Überwiegend Klar', 1 => '35°', 2 => '☀️ 05:52', 3 => '20:34 🌓',
    4 => '0 % Regen', 5 => '25 % Luftfeuchte', 6 => '0.82 m/s SO', // <- isolated "SO" = Süd-Ost wind direction, NOT a weekday!
    7 => '0.00/Tag',
    8 => 'SO', 9 => 'MO', 10 => 'DI', 11 => 'MI', 12 => 'DO', 13 => 'FR', 14 => 'SA', // <- real weekday run
    15 => '23°', 16 => '20°',
];
$overrides = detectWeekdayAbbreviationOverrides($weatherWidgetNodes, 'de', 'en');

assert(count($overrides) === 7, 'All 7 real weekday nodes must be overridden');
assert($overrides[8] === 'Sun', 'Index 8 ("SO" in the weekday run) must become "Sun"');
assert($overrides[9] === 'Mon', 'Index 9 ("MO") must become "Mon"');
assert($overrides[10] === 'Tue', 'Index 10 ("DI") must become "Tue"');
assert($overrides[11] === 'Wed', 'Index 11 ("MI") must become "Wed"');
assert($overrides[12] === 'Thu', 'Index 12 ("DO") must become "Thu"');
assert($overrides[13] === 'Fri', 'Index 13 ("FR") must become "Fri"');
assert($overrides[14] === 'Sat', 'Index 14 ("SA") must become "Sat"');
echo "Test 1 (real 7-day run gets correct deterministic overrides) OK\n";

// THE critical case the user flagged: index 6 ("0.82 m/s SO" - wind
// direction sentence, NOT the bare code "SO") must NEVER be touched by the
// override - it's not even an exact match to "SO" (it's a longer sentence
// containing it), so this also implicitly proves substring/partial matches
// don't trigger it.
assert(!isset($overrides[6]), 'Wind-direction text (containing "SO" as part of a longer string) must NOT be overridden');
echo "Test 2 (wind-direction text is not a weekday code, correctly excluded) OK\n";

// Test 3: a genuinely ISOLATED single "SO" (bare, exact match, but with NO
// adjacent weekday siblings - e.g. wind direction rendered as just the bare
// abbreviation elsewhere in the same widget) must NOT be overridden either -
// this is the core of the user's objection.
$isolatedSO = [0 => 'Wind aus', 1 => 'SO', 2 => 'bei 12 km/h'];
$overridesIsolated = detectWeekdayAbbreviationOverrides($isolatedSO, 'de', 'en');
assert($overridesIsolated === [], 'A single isolated "SO" (e.g. wind direction, no weekday siblings) must NEVER be overridden - ambiguous, must fall through to normal translation');
echo "Test 3 (isolated single \"SO\" - the user's exact concern - is correctly left alone) OK\n";

// Test 4: a short run of only 3 consecutive codes (below the threshold)
// must also NOT trigger the override (conservative margin).
$shortRun = [0 => 'MO', 1 => 'DI', 2 => 'MI'];
$overridesShort = detectWeekdayAbbreviationOverrides($shortRun, 'de', 'en');
assert($overridesShort === [], 'A run of only 3 (below MIN_LENGTH=4) must not trigger the override');
echo "Test 4 (run below minimum length does not trigger) OK\n";

// Test 5: wrong source/target language must never apply the override
// (e.g. source is English, or target has no entry in the table).
$overridesWrongSource = detectWeekdayAbbreviationOverrides($weatherWidgetNodes, 'en', 'de');
assert($overridesWrongSource === [], 'Non-German source must never trigger the override');
$overridesNoTargetEntry = detectWeekdayAbbreviationOverrides($weatherWidgetNodes, 'de', 'ja');
assert($overridesNoTargetEntry === [], 'Target language without a table entry must fall through untouched');
echo "Test 5 (wrong source/target language never triggers) OK\n";

// Test 6: DeepL-style uppercase codes ("DE" source, "EN-GB" target) must
// still match via normalization.
$overridesDeepL = detectWeekdayAbbreviationOverrides($weatherWidgetNodes, 'DE', 'EN-GB');
assert(count($overridesDeepL) === 7, 'DeepL-style uppercase language codes must still match via 2-char normalization');
echo "Test 6 (DeepL-style language codes normalize correctly) OK\n";

echo "\nAll tests passed.\n";
