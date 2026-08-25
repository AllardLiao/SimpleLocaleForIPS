<?php
declare(strict_types=1);
// Standalone replica tests for the 2026-08-18 escalating-backoff fix (build 57):
// live observed that Google's "User Rate Limit Exceeded" (no "day"/"quota"
// keyword, so classified as the SHORT 15-minute burst cooldown) kept failing
// for HOURS, causing a confusing "flicker" - the instance status banner said
// "Active, but paused" (set the moment ALL providers were briefly paused
// together) while the config-form breakdown, read moments later, no longer
// listed Google because its short pause had already expired and it was mid-
// retry again. Fix: each CONSECUTIVE failure (no successful call in between)
// doubles the provider's own cooldown, capped at the daily-quota ceiling -
// self-correcting without depending on a provider's exact wording.

const RATE_LIMIT_COOLDOWN_SECONDS = 900;
const DAILY_QUOTA_COOLDOWN_SECONDS = 86400;

function recordProviderPaused(array &$state, string $provider, int $baseCooldownSeconds, int $now): void
{
    $streak = (int) ($state[$provider]['streak'] ?? 0) + 1;

    $escalated = $baseCooldownSeconds >= DAILY_QUOTA_COOLDOWN_SECONDS
        ? DAILY_QUOTA_COOLDOWN_SECONDS
        : min(RATE_LIMIT_COOLDOWN_SECONDS * (2 ** ($streak - 1)), DAILY_QUOTA_COOLDOWN_SECONDS);

    $state[$provider] = [
        'until'  => max((int) ($state[$provider]['until'] ?? 0), $now + $escalated),
        'streak' => $streak,
    ];
}

function clearProviderPause(array &$state, string $provider): void
{
    unset($state[$provider]);
}

function getProviderPausedUntilMap(array $state, int $now): array
{
    $result = [];
    foreach ($state as $provider => $entry) {
        // Migration fallback: pre-escalation (build 56 and earlier) stored a
        // plain timestamp instead of {until, streak}.
        $until = is_array($entry) ? (int) ($entry['until'] ?? 0) : (int) $entry;
        if ($until > $now) {
            $result[$provider] = $until;
        }
    }
    return $result;
}

// ---------------------------------------------------------------------------
// Test 1: first failure gets the short base cooldown, exactly as before.
$state = [];
$now = 1000000;
recordProviderPaused($state, 'google', RATE_LIMIT_COOLDOWN_SECONDS, $now);
assert($state['google']['until'] === $now + RATE_LIMIT_COOLDOWN_SECONDS, 'First failure must get exactly the base (15min) cooldown');
assert($state['google']['streak'] === 1, 'First failure starts the streak at 1');
echo "Test 1 (first failure = base cooldown, streak=1) OK\n";

// ---------------------------------------------------------------------------
// Test 2: THE core scenario - Google keeps failing on every retry after each
// short pause expires. Each consecutive failure must double the cooldown
// instead of resetting back to 15 minutes every time.
$now2 = $now + RATE_LIMIT_COOLDOWN_SECONDS + 1; // previous 15min pause just expired, retried, failed again
recordProviderPaused($state, 'google', RATE_LIMIT_COOLDOWN_SECONDS, $now2);
assert($state['google']['streak'] === 2, 'Second consecutive failure (no success in between) must increment the streak');
assert($state['google']['until'] === $now2 + (RATE_LIMIT_COOLDOWN_SECONDS * 2), 'Second consecutive failure must DOUBLE the cooldown (30min), not reset to 15min again');

$now3 = $state['google']['until'] + 1;
recordProviderPaused($state, 'google', RATE_LIMIT_COOLDOWN_SECONDS, $now3);
assert($state['google']['streak'] === 3, 'Third consecutive failure increments streak again');
assert($state['google']['until'] === $now3 + (RATE_LIMIT_COOLDOWN_SECONDS * 4), 'Third consecutive failure must be 4x the base (1h)');
echo "Test 2 (consecutive failures escalate 15min -> 30min -> 1h, no more flicker) OK\n";

// ---------------------------------------------------------------------------
// Test 3: escalation must be capped at the daily-quota ceiling, never exceed it
// even after many consecutive failures.
$stateLong = [];
$t = 2000000;
for ($i = 0; $i < 10; $i++) {
    recordProviderPaused($stateLong, 'google', RATE_LIMIT_COOLDOWN_SECONDS, $t);
    $t = $stateLong['google']['until'] + 1;
}
$cooldownAtStreak10 = $stateLong['google']['until'] - ($t - $stateLong['google']['until'] - 1);
// Recompute the actual last-applied cooldown directly instead:
$lastUntil = $stateLong['google']['until'];
$secondToLastCallTime = $t - 1; // the 'now' passed into the 10th call
assert(($lastUntil - $secondToLastCallTime) <= DAILY_QUOTA_COOLDOWN_SECONDS, 'Escalating cooldown must never exceed the daily-quota ceiling (24h), no matter how many consecutive failures');
echo "Test 3 (escalation is capped at the daily-quota ceiling, never grows unbounded) OK\n";

// ---------------------------------------------------------------------------
// Test 4: a real success resets the streak - a provider that recovers and
// later fails again ONCE should get the short cooldown again, not continue
// escalating from where it left off.
$state4 = [];
recordProviderPaused($state4, 'deepl', RATE_LIMIT_COOLDOWN_SECONDS, $now);
recordProviderPaused($state4, 'deepl', RATE_LIMIT_COOLDOWN_SECONDS, $now + 1000); // streak=2
assert($state4['deepl']['streak'] === 2, 'Sanity: streak is 2 before the success');
clearProviderPause($state4, 'deepl'); // a real translation succeeds
assert(!isset($state4['deepl']), 'A successful call must fully clear the provider\'s pause/streak state');
recordProviderPaused($state4, 'deepl', RATE_LIMIT_COOLDOWN_SECONDS, $now + 2000); // fails again, fresh streak
assert($state4['deepl']['streak'] === 1, 'After a success, the NEXT failure must start a fresh streak (short cooldown again), not continue escalating');
echo "Test 4 (a real success resets the escalation streak) OK\n";

// ---------------------------------------------------------------------------
// Test 5: a daily-quota-classified failure (base cooldown already at the
// ceiling, e.g. MyMemory's "USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY")
// must NOT be escalated further on repeat - it's already at the maximum.
$state5 = [];
recordProviderPaused($state5, 'free', DAILY_QUOTA_COOLDOWN_SECONDS, $now);
assert($state5['free']['until'] === $now + DAILY_QUOTA_COOLDOWN_SECONDS, 'A daily-quota failure must get the full 24h cooldown immediately');
recordProviderPaused($state5, 'free', DAILY_QUOTA_COOLDOWN_SECONDS, $now + 100); // hypothetically retried again very soon
assert($state5['free']['until'] - ($now + 100) === DAILY_QUOTA_COOLDOWN_SECONDS, 'A repeat daily-quota failure must stay at exactly 24h, never try to double past the ceiling');
echo "Test 5 (daily-quota failures stay at the ceiling, no further escalation attempted) OK\n";

// ---------------------------------------------------------------------------
// Test 6: reading an OLD-format entry (plain timestamp, pre-build-57) must
// still work correctly (migration fallback) instead of crashing or silently
// dropping the pause.
$legacyState = ['google' => $now + 500]; // old format: plain int, not {until, streak}
$map = getProviderPausedUntilMap($legacyState, $now);
assert($map['google'] === $now + 500, 'Reading a pre-escalation plain-timestamp entry must still work via the migration fallback');
echo "Test 6 (legacy plain-timestamp format still reads correctly) OK\n";

echo "\nAll tests passed.\n";
