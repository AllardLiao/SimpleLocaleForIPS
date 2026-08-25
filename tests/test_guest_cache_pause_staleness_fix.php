<?php
declare(strict_types=1);
// Standalone replica test for build 77 (2026-08-20):
// Live reported (screenshot): the guest tile had English actively selected, but the
// paused-notice ("Übersetzung pausiert bis...") and the stats hint
// ("138 Übersetzungen/h, 2554 Zeichen/h") stayed in raw German. Root cause: while
// ALL translation providers were paused, EnsureGuestLanguageNamesFresh() still ran
// its own-texts TranslateBatch() call, got back all-empty results (provider chain
// paused), correctly fell back to raw German via orFallback() - but THEN still
// wrote 'fetchedAt' => time() into the cache regardless, marking the (entirely
// German-fallback) result as "fresh for up to 24 hours". Once the pause ended,
// nothing re-triggered a retry until the NEXT natural 24h cache expiry - guests
// could see stale German text for most of a day after a brief provider pause.

const GLOBAL_PAUSE_ACTIVE = true;
const GLOBAL_PAUSE_INACTIVE = false;

function ensureGuestLanguageNamesFreshReplica(array $cache, string $language, bool $globallyPaused, array $translationResult): array
{
    // Build 77 guard 1: short-circuit entirely while globally paused - existing
    // cache (from the last REAL success) is returned untouched.
    if ($globallyPaused) {
        return $cache;
    }

    $anyTranslated = $language === 'de' || array_filter($translationResult, fn (string $t): bool => $t !== '') !== [];

    return [
        'language' => $language,
        'pausedNoticePrefix' => ($translationResult['pausedNoticePrefix'] ?? '') !== '' ? $translationResult['pausedNoticePrefix'] : 'Übersetzung pausiert bis',
        // Build 77 guard 2: only bump fetchedAt on genuine (partial) success.
        'fetchedAt' => $anyTranslated ? time() : ($cache['fetchedAt'] ?? 0),
    ];
}

// Test 1: THE reported bug - while globally paused, a refresh attempt must not
// even run, so the cache keeps whatever was last genuinely translated instead of
// being overwritten with an all-German-fallback result marked "fresh".
$lastGoodCache = ['language' => 'en', 'pausedNoticePrefix' => 'Translation paused until', 'fetchedAt' => time() - 3600];
$duringPause = ensureGuestLanguageNamesFreshReplica($lastGoodCache, 'en', GLOBAL_PAUSE_ACTIVE, []);
assert($duringPause === $lastGoodCache, 'While globally paused, the existing (last genuinely successful) cache must be returned completely untouched, not overwritten with a fallback result');
echo "Test 1 (a refresh attempt during a global pause is skipped entirely, old cache preserved) OK\n";

// Test 2: a genuine total failure NOT caused by a global pause (e.g. a transient
// provider error) must still avoid marking the cache fresh for 24h - fetchedAt
// stays at its old value so the next call retries soon.
$staleCache = ['language' => 'en', 'fetchedAt' => 100];
$afterTotalFailure = ensureGuestLanguageNamesFreshReplica($staleCache, 'en', GLOBAL_PAUSE_INACTIVE, ['pausedNoticePrefix' => '']);
assert($afterTotalFailure['fetchedAt'] === 100, 'A total translation failure must NOT bump fetchedAt to now - the old value must be kept so the next call retries instead of waiting up to 24h');
echo "Test 2 (a non-pause total failure does not poison the cache as \"fresh\" either) OK\n";

// Test 3: a genuine success DOES bump fetchedAt to now, as before.
$beforeSuccess = time();
$afterSuccess = ensureGuestLanguageNamesFreshReplica(['fetchedAt' => 0], 'en', GLOBAL_PAUSE_INACTIVE, ['pausedNoticePrefix' => 'Translation paused until']);
assert($afterSuccess['fetchedAt'] >= $beforeSuccess, 'A genuinely successful translation attempt must bump fetchedAt to now, exactly as before this fix');
echo "Test 3 (a genuine translation success still marks the cache fresh, unaffected by the fix) OK\n";

// Test 4: German itself never needs translation and always counts as success
// (matches the existing 'language === de' shortcut elsewhere in this function).
$germanResult = ensureGuestLanguageNamesFreshReplica(['fetchedAt' => 0], 'de', GLOBAL_PAUSE_INACTIVE, []);
assert($germanResult['fetchedAt'] > 0, 'German source language must always count as a translation success (no API call needed)');
echo "Test 4 (German source language always counts as success, no translation needed) OK\n";

echo "\nAll tests passed.\n";
