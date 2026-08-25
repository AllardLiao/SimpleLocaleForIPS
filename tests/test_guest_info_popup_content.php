<?php
declare(strict_types=1);
// Standalone replica test for build 77 (2026-08-20):
// User request: bring the config-form stats block (BuildTranslationStatsValue/
// form.json stats rows) AND the pause-status info into the GUEST-facing Info-Popup
// (the ⓘ icon in the tile) - previously only visible to the admin in the config
// form. Also: replace the "Hinweise" heading with the app name + license edition
// (e.g. "Simple Locale - Pro Edition"), and only the app/edition NAME - never
// translated per guest language, same as $licenseInfo['edition'] is already a raw,
// untranslated value in the admin panel.

function buildInfoAlertHeadingReplica(string $edition): string
{
    $edition = trim($edition);

    return 'Simple Locale' . ($edition !== '' ? ' - ' . $edition . ' Edition' : '');
}

// Test 1: a licensed edition produces "Simple Locale - <Edition> Edition".
assert(buildInfoAlertHeadingReplica('Pro') === 'Simple Locale - Pro Edition', 'A named edition must produce "Simple Locale - <Edition> Edition"');
echo "Test 1 (licensed edition heading matches the requested \"Simple Locale - Pro Edition\" format) OK\n";

// Test 2: no edition (trial/no license) falls back to just the app name.
assert(buildInfoAlertHeadingReplica('') === 'Simple Locale', 'Without a named edition (trial/no license), the heading must be just the app name');
echo "Test 2 (no edition falls back to the bare app name) OK\n";

// --- Guest-facing stats paragraph ---

function formatStatsCountReplica(float $value): string
{
    return (string) (int) round($value);
}

function buildGuestStatsInfoTextReplica(array $stats, bool $showStatsEnabled, array $guestCache): string
{
    if (!$showStatsEnabled) {
        return '';
    }
    if ($stats['since'] === 0) {
        return '';
    }

    $daysSince = max(0, (int) floor((1755700000 - $stats['since']) / 86400));
    $sincePrefix = $guestCache['statsSincePrefix'] ?? 'Seit Inbetriebnahme am';
    $daysSuffix = $guestCache['statsDaysSuffix'] ?? 'Tag(e).';
    $hourlyLabel = $guestCache['statsHourlyLabel'] ?? 'Stündlich:';
    $requestsUnit = $guestCache['statsRequestsUnit'] ?? 'Anfrage(n),';
    $charsUnit = $guestCache['statsCharsUnit'] ?? 'Zeichen.';
    $totalLabel = $guestCache['statsTotalLabel'] ?? 'Insgesamt:';
    $cacheSavedLabel = $guestCache['statsCacheSavedLabel'] ?? 'Durch den Cache eingespart:';

    return $sincePrefix . ' ' . date('d.m.Y', $stats['since']) . ', ' . $daysSince . ' ' . $daysSuffix . "\n"
        . $hourlyLabel . ' ' . formatStatsCountReplica($stats['requestsPerHour']) . ' ' . $requestsUnit
            . ' ' . formatStatsCountReplica($stats['charsPerHour']) . ' ' . $charsUnit . "\n"
        . $totalLabel . ' ' . $stats['requestCount'] . ' ' . $requestsUnit
            . ' ' . $stats['characterCount'] . ' ' . $charsUnit . "\n"
        . $cacheSavedLabel . ' ' . $stats['cacheSavedRequestCount'] . ' ' . $requestsUnit
            . ' ' . $stats['cacheSavedCharacterCount'] . ' ' . $charsUnit;
}

$exampleStats = [
    'since' => 1755550000, 'requestCount' => 6860, 'characterCount' => 127238,
    'requestsPerHour' => 138.0, 'charsPerHour' => 2563.0,
    'cacheSavedRequestCount' => 7598, 'cacheSavedCharacterCount' => 527883,
];

// Test 3: disabled "show stats" setting produces no paragraph at all.
assert(buildGuestStatsInfoTextReplica($exampleStats, false, []) === '', 'With "show translation stats" disabled, the guest popup must not show a stats paragraph');
echo "Test 3 (stats paragraph is empty when the admin has disabled the feature) OK\n";

// Test 4: no stats yet (since=0) also produces nothing.
assert(buildGuestStatsInfoTextReplica(['since' => 0], true, []) === '', 'Before any translation has ever happened (since=0), there must be no stats paragraph');
echo "Test 4 (stats paragraph is empty before any translation has occurred) OK\n";

// Test 5: with stats enabled and real data, all four lines from the admin panel's
// own layout must appear, using guest-translated (or German-fallback) labels.
$statsText = buildGuestStatsInfoTextReplica($exampleStats, true, [
    'statsSincePrefix' => 'In operation since', 'statsDaysSuffix' => 'day(s).',
    'statsHourlyLabel' => 'Hourly:', 'statsRequestsUnit' => 'request(s),', 'statsCharsUnit' => 'character(s).',
    'statsTotalLabel' => 'Total:', 'statsCacheSavedLabel' => 'Saved by the cache:',
]);
assert(str_contains($statsText, 'Hourly: 138 request(s), 2563 character(s).'), 'The hourly line must match the admin panel\'s own wording exactly, just guest-translated');
assert(str_contains($statsText, 'Total: 6860 request(s), 127238 character(s).'), 'The total line must reflect the real counters');
assert(str_contains($statsText, 'Saved by the cache: 7598 request(s), 527883 character(s).'), 'The cache-saved line must reflect the real counters');
echo "Test 5 (all four stats lines render correctly with guest-translated labels, matching the admin panel's exact wording) OK\n";

// --- Guest-facing pause paragraph ---

function buildGuestPauseInfoTextReplica(?int $globalPauseUntil, array $guestCache): string
{
    if ($globalPauseUntil === null) {
        return '';
    }

    $pausedPrefix = $guestCache['pausedNoticePrefix'] ?? 'Übersetzung pausiert bis';
    $reassurance = $guestCache['pausedReassurance'] ?? 'Bereits vorhandene Übersetzungen bleiben nutzbar.';

    return $pausedPrefix . ' ' . date('d.m. H:i', $globalPauseUntil) . "\n" . $reassurance;
}

// Test 6: no active pause produces no paragraph.
assert(buildGuestPauseInfoTextReplica(null, []) === '', 'Without an active global pause, there must be no pause paragraph in the guest popup');
echo "Test 6 (no pause paragraph when nothing is currently paused) OK\n";

// Test 7: an active pause produces the prefix+time plus the reassurance line.
$pauseText = buildGuestPauseInfoTextReplica(1755720000, ['pausedNoticePrefix' => 'Translation paused until', 'pausedReassurance' => 'Existing translations remain usable.']);
assert(str_starts_with($pauseText, 'Translation paused until'), 'An active pause must show the guest-translated prefix');
assert(str_contains($pauseText, 'Existing translations remain usable.'), 'An active pause must include the reassurance that existing translations still work');
echo "Test 7 (an active pause shows the guest-translated prefix, time, and reassurance) OK\n";

echo "\nAll tests passed.\n";
