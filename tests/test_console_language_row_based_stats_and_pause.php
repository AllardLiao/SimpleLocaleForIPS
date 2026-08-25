<?php
declare(strict_types=1);
// Standalone replica tests for build 67 (2026-08-19):
// $this->Translate() is bound to the Symcon SYSTEM language, not the
// individual viewer's console language - a runtime-composed sentence (built
// via string concatenation with $this->Translate() calls interspersed with
// dynamic numbers/dates) can therefore never be translated per-viewer: it
// never exactly matches a whole locale.json key, and even the individual
// Translate() fragments inside it resolve against the wrong language.
// Live reported: the usage-stats sentence (build 60/61/64) and the provider
// pause-status text (build 54) both stayed in German even though the
// console's OWN static captions ("Active", "Emergency stop: ...") correctly
// displayed in the viewer's actual English console language - proving the
// console DOES its own per-viewer translation of static captions, but a
// PHP-composed dynamic string bypasses that entirely.
//
// Fixed the same way as the already-confirmed-working License Info panel:
// split the composed sentence into many small RowLayout items, where each
// item is EITHER a fixed, unmodified German string (that the console can
// match against locale.json and translate per-viewer) OR a raw dynamic
// value (number/date, never needing translation) - never both combined in
// one caption.

// --- FormatTranslationStatsValue() replica -----------------------------------

function formatTranslationStatsValue(string $ident, array $stats): string
{
    if ($stats['since'] === 0) {
        return '';
    }

    return match ($ident) {
        'TranslationStatsSinceDateLabel'          => date('d.m.Y', $stats['since']) . ',',
        'TranslationStatsDaysLabel'               => (string) max(0, (int) floor((1755600000 - $stats['since']) / 86400)),
        'TranslationStatsRequestsPerHourLabel'    => (string) (int) round($stats['requestsPerHour']),
        'TranslationStatsCharsPerHourValueLabel'  => ', ' . (string) (int) round($stats['charsPerHour']),
        'TranslationStatsTotalRequestsLabel'      => ': ' . $stats['requestCount'],
        'TranslationStatsTotalCharsLabel'         => ', ' . $stats['characterCount'],
        'TranslationStatsCacheSavedRequestsLabel' => ': ' . $stats['cacheSavedRequestCount'],
        'TranslationStatsCacheSavedCharsLabel'    => ', ' . $stats['cacheSavedCharacterCount'],
        default                                   => '',
    };
}

// Test 1: every stats value element must carry ONLY the raw number/date (no
// German words baked in) - the surrounding text lives in SEPARATE, static
// form.json Labels (never populated by this function), so the console can
// translate them per-viewer independently of these raw values.
$stats = [
    'since' => 1755100000, 'requestCount' => 1807, 'characterCount' => 27031,
    'requestsPerHour' => 1100.0, 'charsPerHour' => 16449.0,
    'cacheSavedRequestCount' => 74, 'cacheSavedCharacterCount' => 100662,
];
assert(formatTranslationStatsValue('TranslationStatsRequestsPerHourLabel', $stats) === '1100', 'The requests-per-hour value must be a bare number, no German word attached');
assert(formatTranslationStatsValue('TranslationStatsTotalRequestsLabel', $stats) === ': 1807', 'The total-requests value carries only its leading punctuation, no translatable word');
assert(formatTranslationStatsValue('TranslationStatsCacheSavedCharsLabel', $stats) === ', 100662', 'The cache-saved-characters value carries only its leading punctuation, no translatable word');
echo "Test 1 (every stats raw-value element contains only numbers/punctuation, no German words needing translation) OK\n";

// Test 2: before the very first activation (since === 0), every element
// must be empty - matches the existing "whole block hidden" behavior.
$freshStats = ['since' => 0, 'requestCount' => 0, 'characterCount' => 0, 'requestsPerHour' => 0.0, 'charsPerHour' => 0.0, 'cacheSavedRequestCount' => 0, 'cacheSavedCharacterCount' => 0];
assert(formatTranslationStatsValue('TranslationStatsSinceDateLabel', $freshStats) === '', 'Before the first activation, every stats value element must be empty');
echo "Test 2 (before first activation, every stats value element is empty, matching the hidden row state) OK\n";

// --- PopulateProviderPauseStatusElement()/FormatProviderPauseUntil() replicas ---

function populateProviderPauseVisibility(string $ident, array $paused, ?int $globalPauseUntil): bool
{
    return match ($ident) {
        'ProviderPauseAllPausedRow', 'ProviderPauseAllPausedFollowupLabel' => $globalPauseUntil !== null,
        'ProviderPausePartialLabel' => $paused !== [] && $globalPauseUntil === null,
        'ProviderPauseGoogleRow'    => isset($paused['google']),
        'ProviderPauseDeepLRow'     => isset($paused['deepl']),
        'ProviderPauseFreeRow'      => isset($paused['free']),
        default                     => false,
    };
}

function formatProviderPauseUntil(string $ident, array $paused, ?int $globalPauseUntil): string
{
    if ($ident === 'ProviderPauseAllPausedUntilLabel') {
        return $globalPauseUntil !== null ? date('d.m. H:i', $globalPauseUntil) . '.' : '';
    }
    $provider = match ($ident) {
        'ProviderPauseGoogleUntilLabel' => 'google',
        'ProviderPauseDeepLUntilLabel'  => 'deepl',
        'ProviderPauseFreeUntilLabel'   => 'free',
        default                         => '',
    };
    $until = $paused[$provider] ?? null;

    return $until !== null ? date('d.m. H:i', (int) $until) : '';
}

// Test 3: THE reported bug's second confirmed location - all three
// providers paused (matches the shared screenshot) must show the "all
// paused" intro row, hide the "partial" label, and show all three
// per-provider rows, each carrying only a raw date/time value.
$allPaused = ['google' => 1755550440, 'deepl' => 1755550440, 'free' => 1755550440];
$globalUntil = 1755550440;
assert(populateProviderPauseVisibility('ProviderPauseAllPausedRow', $allPaused, $globalUntil) === true, 'All three providers paused must show the "all paused" intro row');
assert(populateProviderPauseVisibility('ProviderPausePartialLabel', $allPaused, $globalUntil) === false, 'All three providers paused must hide the "partial" label');
assert(populateProviderPauseVisibility('ProviderPauseGoogleRow', $allPaused, $globalUntil) === true, 'Google must show its own row when paused');
assert(populateProviderPauseVisibility('ProviderPauseDeepLRow', $allPaused, $globalUntil) === true, 'DeepL must show its own row when paused');
assert(populateProviderPauseVisibility('ProviderPauseFreeRow', $allPaused, $globalUntil) === true, 'The free provider must show its own row when paused');
echo "Test 3 (all-three-paused state shows the correct intro + all three per-provider rows, matching the reported screenshot) OK\n";

// Test 4: a PARTIAL pause (only one provider paused, matches an earlier
// screenshot in this session) must show the "partial" label, hide the
// "all paused" row, and show only the affected provider's row.
$partialPaused = ['google' => 1755550440];
assert(populateProviderPauseVisibility('ProviderPauseAllPausedRow', $partialPaused, null) === false, 'A partial pause must hide the "all paused" intro row');
assert(populateProviderPauseVisibility('ProviderPausePartialLabel', $partialPaused, null) === true, 'A partial pause must show the "partial" label');
assert(populateProviderPauseVisibility('ProviderPauseGoogleRow', $partialPaused, null) === true, 'The paused provider (Google) must show its own row');
assert(populateProviderPauseVisibility('ProviderPauseDeepLRow', $partialPaused, null) === false, 'A non-paused provider (DeepL) must NOT show its row');
echo "Test 4 (a partial pause shows the correct label + only the affected provider's row) OK\n";

// Test 5: no pause at all - every row/label must be hidden.
assert(populateProviderPauseVisibility('ProviderPauseAllPausedRow', [], null) === false, 'No pause at all must hide the "all paused" row');
assert(populateProviderPauseVisibility('ProviderPausePartialLabel', [], null) === false, 'No pause at all must hide the "partial" label');
assert(populateProviderPauseVisibility('ProviderPauseGoogleRow', [], null) === false, 'No pause at all must hide every per-provider row');
echo "Test 5 (no pause at all hides every row/label) OK\n";

// Test 6: the raw until-values carry ONLY date+time, matching the user's
// explicit request to show the date (not just the time, since a pause can
// cross midnight) - and the "all paused" variant has a trailing period
// (the sentence-ending punctuation that used to be baked into the composed
// intro string) while individual provider rows do not (they're a bullet
// list, not a full sentence).
assert(formatProviderPauseUntil('ProviderPauseAllPausedUntilLabel', $allPaused, $globalUntil) === date('d.m. H:i', $globalUntil) . '.', 'The all-paused until-value must include date+time plus the trailing period');
assert(formatProviderPauseUntil('ProviderPauseGoogleUntilLabel', $allPaused, $globalUntil) === date('d.m. H:i', $allPaused['google']), 'A per-provider until-value must include date+time, matching the "not just the time" request');
echo "Test 6 (until-values carry date+time as requested, with the correct trailing punctuation per context) OK\n";

echo "\nAll tests passed.\n";
