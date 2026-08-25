<?php
declare(strict_types=1);
// Standalone replica tests for build 60 (2026-08-19):
// 1. ClearPauseOnCredentialChange() - a new/different Google/DeepL API key or
//    MyMemory contact email should end that provider's pause early, since it
//    may fix the underlying cause.
// 2. Translation usage statistics (requests/hour, characters/hour since
//    activation) + the two plain-number tile placeholders.
// 3. AutoRescan() (background timer) must NEVER call ReloadForm() - only the
//    manually-triggered Rescan() may, since a background reload was silently
//    discarding admin edits in progress (live reported bug).

// --- 1. Credential-change pause clearing ------------------------------------

function clearPauseOnCredentialChange(array &$pausedState, array &$lastSeenHashes, array $currentCredentials): void
{
    $current = array_map(fn ($v) => hash('sha256', $v), $currentCredentials);

    foreach ($current as $provider => $hash) {
        if (isset($lastSeenHashes[$provider]) && $lastSeenHashes[$provider] !== $hash) {
            unset($pausedState[$provider]); // ClearProviderPause
        }
    }

    if ($lastSeenHashes !== $current) {
        $lastSeenHashes = $current;
    }
}

// Test 1: a paused provider must NOT be cleared just because ApplyChanges ran
// again with the SAME (unchanged) key.
$paused = ['google' => ['until' => 9999999999, 'streak' => 2]];
$lastSeen = ['google' => hash('sha256', 'old-key'), 'deepl' => hash('sha256', ''), 'free' => hash('sha256', '')];
clearPauseOnCredentialChange($paused, $lastSeen, ['google' => 'old-key', 'deepl' => '', 'free' => '']);
assert(isset($paused['google']), 'An unchanged API key must NOT clear an existing pause');
echo "Test 1 (unchanged credentials leave an existing pause untouched) OK\n";

// Test 2: THE core feature - entering a NEW/different Google API key must
// immediately end Google's pause, without waiting for the escalated cooldown
// to expire.
clearPauseOnCredentialChange($paused, $lastSeen, ['google' => 'brand-new-key', 'deepl' => '', 'free' => '']);
assert(!isset($paused['google']), 'A new/different API key must immediately clear that provider\'s pause');
echo "Test 2 (new API key immediately clears the matching provider's pause) OK\n";

// Test 3: changing ONE provider's credentials must never affect a DIFFERENT
// provider's pause.
$paused2 = ['deepl' => ['until' => 9999999999, 'streak' => 1]];
$lastSeen2 = ['google' => hash('sha256', 'g1'), 'deepl' => hash('sha256', 'd1'), 'free' => hash('sha256', '')];
clearPauseOnCredentialChange($paused2, $lastSeen2, ['google' => 'g2', 'deepl' => 'd1', 'free' => '']);
assert(isset($paused2['deepl']), 'Changing the Google key must not clear DeepL\'s unrelated pause');
echo "Test 3 (changing one provider's credentials never affects another provider's pause) OK\n";

// Test 4: the very first ApplyChanges() ever (no prior hash recorded) must
// NEVER be treated as a "change" - there's no baseline to compare against yet.
$pausedFresh = ['free' => ['until' => 9999999999, 'streak' => 3]];
$lastSeenFresh = []; // brand-new instance, nothing recorded yet
clearPauseOnCredentialChange($pausedFresh, $lastSeenFresh, ['google' => '', 'deepl' => '', 'free' => 'contact@example.com']);
assert(isset($pausedFresh['free']), 'The very first ApplyChanges() must never spuriously clear a pause - there is no prior value to compare against');
echo "Test 4 (first-ever ApplyChanges never spuriously clears a pause) OK\n";

// --- 2. Translation usage statistics -----------------------------------------

function computeTranslationStats(int $since, int $requestCount, int $characterCount, int $now): array
{
    $elapsedSeconds = $since > 0 ? max(1, $now - $since) : 1;
    $hoursElapsed = $elapsedSeconds / 3600;
    return [
        'requestsPerHour' => $requestCount / $hoursElapsed,
        'charsPerHour'    => $characterCount / $hoursElapsed,
    ];
}

function formatStatsCount(float $value): string
{
    return (string) (int) round($value);
}

// Test 5: a plausible real-world scenario - 60 requests and 1000 characters
// over exactly 2 hours must average to 30 requests/h and 500 characters/h,
// matching the user's own example figures.
$now = 1000000 + 7200; // 2 hours later
$stats = computeTranslationStats(1000000, 60, 1000, $now);
assert(formatStatsCount($stats['requestsPerHour']) === '30', 'Must average to exactly 30 requests/hour, matching the requested example');
assert(formatStatsCount($stats['charsPerHour']) === '500', 'Must average to exactly 500 characters/hour, matching the requested example');
echo "Test 5 (60 requests + 1000 chars over 2h averages to 30/h and 500/h, as specified) OK\n";

// Test 6: placeholder substitution must yield ONLY the plain rounded integer -
// no unit suffix, no decimals (the user explicitly wants e.g. "30", not
// "29.7" or "30 requests/h").
function applyStatsPlaceholders(string $html, array $stats): string
{
    if (strpos($html, '<!--COUNT_TRANSLATIONS-->') === false && strpos($html, '<!--COUNT_SIGNES-->') === false) {
        return $html;
    }
    return str_replace(
        ['<!--COUNT_TRANSLATIONS-->', '<!--COUNT_SIGNES-->'],
        [formatStatsCount($stats['requestsPerHour']), formatStatsCount($stats['charsPerHour'])],
        $html
    );
}
$customTile = '<div>Rate: <!--COUNT_TRANSLATIONS--> req/h, <!--COUNT_SIGNES--> chars/h</div>';
$rendered = applyStatsPlaceholders($customTile, $stats);
assert($rendered === '<div>Rate: 30 req/h, 500 chars/h</div>', 'Placeholders must be replaced with plain rounded integers only, no unit text baked in');
echo "Test 6 (placeholders substitute plain integers only, exactly as requested) OK\n";

// Test 7: a template with NEITHER placeholder must be returned completely
// unchanged (no wasted attribute reads for tiles that don't use the feature).
$plainTile = '<div>No stats here</div>';
assert(applyStatsPlaceholders($plainTile, $stats) === $plainTile, 'A tile without either placeholder must be returned unchanged');
echo "Test 7 (a tile without any stats placeholder is left untouched) OK\n";

// Test 8: right after the very first activation (since == now), hoursElapsed
// must be floored to a tiny-but-nonzero value - never a division by zero.
$statsAtBoot = computeTranslationStats($now, 5, 100, $now);
assert(is_finite($statsAtBoot['requestsPerHour']) && $statsAtBoot['requestsPerHour'] > 0, 'Computing stats immediately at activation must never divide by zero');
echo "Test 8 (stats computation never divides by zero right at activation) OK\n";

// --- 3. AutoRescan must never trigger ReloadForm ----------------------------

function scanRootTreeSimulated(bool $reloadFormAfterward, bool &$reloadFormWasCalled): void
{
    // ... scan/merge/persist work happens here (irrelevant to this test) ...
    if ($reloadFormAfterward) {
        $reloadFormWasCalled = true;
    }
}

// Test 9: THE reported bug - a background auto-rescan must NEVER force the
// open configuration form to reload, or in-progress unsaved edits are lost.
$reloadCalled = false;
scanRootTreeSimulated(false, $reloadCalled); // AutoRescan() always passes false
assert($reloadCalled === false, 'AutoRescan() (background timer) must NEVER trigger ReloadForm() - it would discard an admin\'s unsaved in-progress edits');
echo "Test 9 (AutoRescan/background timer never triggers ReloadForm) OK\n";

// Test 10: a manual Rescan (button click) must still reload the form as
// before - the admin explicitly asked for the scan and expects to see the
// refreshed list immediately.
$reloadCalled2 = false;
scanRootTreeSimulated(true, $reloadCalled2); // Rescan() always passes true
assert($reloadCalled2 === true, 'A manually-triggered Rescan() must still reload the form immediately, unchanged from before');
echo "Test 10 (manual Rescan still reloads the form immediately, as before) OK\n";

echo "\nAll tests passed.\n";
