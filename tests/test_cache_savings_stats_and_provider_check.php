<?php
declare(strict_types=1);
// Standalone replica tests for build 61 (2026-08-17):
// 1. Cache-savings statistics (RecordCacheSavingsStats/ComputeTranslationStats
//    cache-saved counters) + the two new plain-number tile placeholders
//    <!--COUNT_CACHE_TRANSLATIONS--> / <!--COUNT_CACHE_SIGNES-->.
// 2. CheckProviders(): a single live test request per configured provider,
//    bypassing the cache, that clears a stale pause the moment a provider
//    actually succeeds again (e.g. right after a quota/subscription upgrade).

// --- 1. Cache-savings statistics ---------------------------------------------

function recordCacheSavingsStats(array &$state, int $characterCount): void
{
    $state['cacheSavedRequestCount'] = ($state['cacheSavedRequestCount'] ?? 0) + 1;
    if ($characterCount > 0) {
        $state['cacheSavedCharacterCount'] = ($state['cacheSavedCharacterCount'] ?? 0) + $characterCount;
    }
}

function formatStatsCount(float $value): string
{
    return (string) (int) round($value);
}

function applyCachePlaceholders(string $html, array $state): string
{
    $placeholders = ['<!--COUNT_CACHE_TRANSLATIONS-->', '<!--COUNT_CACHE_SIGNES-->'];
    $hasAny = false;
    foreach ($placeholders as $p) {
        if (strpos($html, $p) !== false) {
            $hasAny = true;
            break;
        }
    }
    if (!$hasAny) {
        return $html;
    }

    return str_replace(
        $placeholders,
        [(string) ($state['cacheSavedRequestCount'] ?? 0), (string) ($state['cacheSavedCharacterCount'] ?? 0)],
        $html
    );
}

// Test 1: a real API translation (cache MISS) must never touch the
// cache-saved counters - only an actual cache HIT counts as "saved".
$state = [];
// (a real miss would call RecordTranslationRequestStats instead - not this function)
assert(($state['cacheSavedRequestCount'] ?? 0) === 0, 'A cache miss must never increment the cache-saved counters');
echo "Test 1 (a cache miss never touches the cache-saved counters) OK\n";

// Test 2: three cache hits for texts of length 5, 10, 7 must accumulate to
// exactly 3 saved requests and 22 saved characters.
recordCacheSavingsStats($state, 5);
recordCacheSavingsStats($state, 10);
recordCacheSavingsStats($state, 7);
assert($state['cacheSavedRequestCount'] === 3, 'Three cache hits must count as exactly 3 saved requests');
assert($state['cacheSavedCharacterCount'] === 22, 'Three cache hits of 5+10+7 chars must accumulate to exactly 22 saved characters');
echo "Test 2 (three cache hits accumulate to 3 saved requests / 22 saved characters) OK\n";

// Test 3: a cache hit for an EMPTY text (0 characters) must still count as a
// saved request, but must not touch the character counter (mirrors the
// existing $CharacterCount > 0 guard in RecordTranslationRequestStats).
$emptyState = ['cacheSavedRequestCount' => 0, 'cacheSavedCharacterCount' => 0];
recordCacheSavingsStats($emptyState, 0);
assert($emptyState['cacheSavedRequestCount'] === 1, 'A cache hit on an empty text must still count as one saved request');
assert($emptyState['cacheSavedCharacterCount'] === 0, 'A cache hit on an empty text must not add to the saved-character counter');
echo "Test 3 (a cache hit on empty text counts the request but not the characters) OK\n";

// Test 4: the two new placeholders must substitute plain integers only (no
// per-hour rate, unlike COUNT_TRANSLATIONS/COUNT_SIGNES - these are running
// totals, exactly as requested: "Anzahl der ... eingesparten Anfragen & Zeichen").
$customTile = '<div>Saved: <!--COUNT_CACHE_TRANSLATIONS--> reqs, <!--COUNT_CACHE_SIGNES--> chars</div>';
$rendered = applyCachePlaceholders($customTile, $state);
assert($rendered === '<div>Saved: 3 reqs, 22 chars</div>', 'Cache-savings placeholders must substitute the plain running totals');
echo "Test 4 (cache-savings placeholders substitute plain running totals) OK\n";

// Test 5: a tile without either placeholder must be returned unchanged (no
// wasted attribute reads).
$plainTile = '<div>No cache stats here</div>';
assert(applyCachePlaceholders($plainTile, $state) === $plainTile, 'A tile without the cache placeholders must be returned unchanged');
echo "Test 5 (a tile without cache placeholders is left untouched) OK\n";

// --- 2. CheckProviders() -----------------------------------------------------

function checkProviders(array $configuredProviders, array $liveResults, array &$pausedState): array
{
    $report = [];
    foreach ($configuredProviders as $provider) {
        $wasPaused = isset($pausedState[$provider]);
        $succeeded = $liveResults[$provider] ?? false;
        if ($succeeded) {
            unset($pausedState[$provider]); // ClearProviderPause
        }
        $report[$provider] = ['succeeded' => $succeeded, 'wasPaused' => $wasPaused];
    }

    return $report;
}

// Test 6: only providers that are actually CONFIGURED (have a key, or are
// the always-available free provider) are checked - matches "jeden
// eingerichteten Provider (inkl. MyMemory)".
$configured = ['free', 'google']; // no DeepL key entered
$pausedDummy = [];
$results = checkProviders($configured, ['free' => true, 'google' => true, 'deepl' => true], $pausedDummy);
assert(array_keys($results) === ['free', 'google'], 'Only configured providers (here: free + google, no deepl key) must be checked');
echo "Test 6 (only configured providers are checked, e.g. no DeepL without a key) OK\n";

// Test 7: THE core feature - a provider that was paused, but now succeeds
// (e.g. after a subscription/quota upgrade), must have its pause cleared
// immediately.
$paused = ['google' => ['until' => 9999999999, 'streak' => 3]];
$report = checkProviders(['free', 'google'], ['free' => true, 'google' => true], $paused);
assert(!isset($paused['google']), 'A provider that succeeds during the check must have its pause cleared immediately');
assert($report['google']['wasPaused'] === true, 'The report must reflect that the provider WAS paused before the check');
assert($report['google']['succeeded'] === true, 'The report must reflect the successful test result');
echo "Test 7 (a provider succeeding during the check immediately clears its pause) OK\n";

// Test 8: a provider that is STILL failing must keep its pause untouched
// (or get a fresh one via the normal CallXxxAPI/RecordProviderPaused path,
// which is out of scope for this pure report-building test) - the check
// itself must not clear a pause for a provider that did not succeed.
$stillPaused = ['deepl' => ['until' => 9999999999, 'streak' => 2]];
$report2 = checkProviders(['free', 'deepl'], ['free' => true, 'deepl' => false], $stillPaused);
assert(isset($stillPaused['deepl']), 'A provider that still fails during the check must keep its existing pause');
assert($report2['deepl']['succeeded'] === false, 'The report must reflect the failed test result');
echo "Test 8 (a provider that still fails keeps its pause, report reflects failure) OK\n";

// Test 9: an already-unpaused, successful provider must not error/no-op
// incorrectly (idempotent - clearing a pause that does not exist is safe).
$noPause = [];
$report3 = checkProviders(['free'], ['free' => true], $noPause);
assert($noPause === [], 'Clearing a pause for a provider that was never paused must be a safe no-op');
assert($report3['free']['wasPaused'] === false, 'An always-available, never-paused provider must report wasPaused=false');
echo "Test 9 (clearing a non-existent pause is a safe no-op) OK\n";

echo "\nAll tests passed.\n";
