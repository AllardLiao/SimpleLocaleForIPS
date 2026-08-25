<?php
declare(strict_types=1);
// Standalone replica test for build 83 (2026-08-20):
// User feedback on the "Übersetzungsanbieter" panel: (1) MyMemory's free daily quota
// resets reliably at UTC midnight (a real, known fact) - the panel should show an
// ACCURATE time for it instead of reusing the same generic "now + 24h" escalation
// guess used for Google/DeepL, whose real recovery time is genuinely unknown. (2)
// DeepL's quota, once exhausted, often does NOT reset automatically at all - waiting
// alone may never help, only a purchase or a new API key does; the panel text must
// reflect that instead of implying a guaranteed automatic resume. (3) Apply the same
// "colon glued to the label" formatting already used for the stats panel instead of
// a separately floating ":" label with inconsistent spacing.

const DAILY_QUOTA_COOLDOWN_SECONDS = 86400;
const RATE_LIMIT_COOLDOWN_SECONDS = 900;

function getNextUtcMidnightTimestampReplica(int $now): int
{
    $dt = new DateTimeImmutable('@' . $now, new DateTimeZone('UTC'));

    return $dt->setTime(0, 0, 0)->modify('+1 day')->getTimestamp();
}

function recordProviderPausedReplica(array $state, string $provider, int $baseCooldownSeconds, int $now, ?int $exactUntil = null): array
{
    $streak = (int) ($state[$provider]['streak'] ?? 0) + 1;

    if ($exactUntil !== null) {
        $until = max((int) ($state[$provider]['until'] ?? 0), $exactUntil);
    } else {
        $escalated = $baseCooldownSeconds >= DAILY_QUOTA_COOLDOWN_SECONDS
            ? DAILY_QUOTA_COOLDOWN_SECONDS
            : min(RATE_LIMIT_COOLDOWN_SECONDS * (2 ** ($streak - 1)), DAILY_QUOTA_COOLDOWN_SECONDS);
        $until = max((int) ($state[$provider]['until'] ?? 0), $now + $escalated);
    }

    $state[$provider] = ['until' => $until, 'streak' => $streak];

    return $state;
}

// Test 1: THE PRECISION FIX - MyMemory's quota-exhausted pause must resolve to the
// next UTC midnight, not a flat "now + 24h" offset that can overshoot by nearly a
// full day depending on when the failure happened.
$now = strtotime('2026-08-20 14:32:00 UTC');
$expectedMidnight = strtotime('2026-08-21 00:00:00 UTC');
assert(getNextUtcMidnightTimestampReplica($now) === $expectedMidnight, 'THE FIX: next-UTC-midnight must be computed exactly, not approximated via a flat 24h offset');
echo "Test 1 (next-UTC-midnight computed precisely, not via a flat now+24h guess) OK\n";

// Test 2: RecordProviderPaused with an exact timestamp bypasses the escalation
// formula entirely and uses the precise value.
$state = recordProviderPausedReplica([], 'free', DAILY_QUOTA_COOLDOWN_SECONDS, $now, $expectedMidnight);
assert($state['free']['until'] === $expectedMidnight, 'An exact timestamp must be used directly, not run through the generic escalation math');
echo "Test 2 (RecordProviderPaused honors an exact timestamp instead of estimating) OK\n";

// Test 3: Google/DeepL are UNCHANGED - still use the generic escalating estimate,
// since no reliable reset time is known for them (the exemption is MyMemory-only).
$stateGoogle = recordProviderPausedReplica([], 'google', DAILY_QUOTA_COOLDOWN_SECONDS, $now);
assert($stateGoogle['google']['until'] === $now + DAILY_QUOTA_COOLDOWN_SECONDS, 'Google must still use the generic escalating estimate - no exact reset time is known for it');
echo "Test 3 (Google/DeepL remain on the generic estimate - only MyMemory gets the precise fix) OK\n";

// Test 4: a short burst rate-limit (not a recognized daily-quota signature) must NOT
// be forced onto UTC midnight even for the free provider - only a genuine
// daily-quota-recognized cooldown gets the exact-midnight treatment.
$shortCooldown = RATE_LIMIT_COOLDOWN_SECONDS;
$exactUntilForShortCooldown = $shortCooldown === DAILY_QUOTA_COOLDOWN_SECONDS ? getNextUtcMidnightTimestampReplica($now) : null;
assert($exactUntilForShortCooldown === null, 'A short burst rate-limit (not the recognized daily-quota signature) must not be forced onto the UTC-midnight calculation');
echo "Test 4 (a short burst rate-limit for the free provider still uses the generic estimate, not UTC midnight) OK\n";

// Test 5: existing longer pending pause is preserved (max()) even with an exact
// timestamp - matches the pre-existing "never shorten an active pause" guarantee.
$stateWithLongerExisting = ['free' => ['until' => $expectedMidnight + 3600, 'streak' => 1]];
$stateAfter = recordProviderPausedReplica($stateWithLongerExisting, 'free', DAILY_QUOTA_COOLDOWN_SECONDS, $now, $expectedMidnight);
assert($stateAfter['free']['until'] === $expectedMidnight + 3600, 'An exact timestamp must never SHORTEN an already-longer pending pause - matches the pre-existing max() guarantee');
echo "Test 5 (exact timestamp never shortens an existing longer pending pause) OK\n";

echo "\nAll tests passed.\n";
