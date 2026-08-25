<?php
declare(strict_types=1);
// Standalone replica test for build 102 (2026-08-21): user-confirmed live
// finding (Meldungen-Log: "alle Anbieter der Kette (deepl [pausiert], google
// [pausiert], free) haben 'de' -> 'es' abgelehnt") plus a direct follow-up
// from the user: DeepL's free tier is no longer a recurring monthly/daily
// quota - it's now a ONE-TIME 1 million character allowance, after which the
// key is permanently exhausted (HTTP 456 "Quota Exceeded") until manually
// replaced/upgraded.
//
// Previously, DetectRateLimitCooldown() classified DeepL's 456 the same as
// any other "daily quota" signature (keyword match on the response body),
// giving it DAILY_QUOTA_COOLDOWN_SECONDS (24h) - meaning the module would
// retry a PERMANENTLY exhausted DeepL key every single day, forever, always
// failing. Fix: HTTP 456 now always gets the much longer
// DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS (30 days) instead, effectively
// halting automatic retries (the user's own "Übersetzungsanbieter prüfen"
// button still clears the pause immediately on the next real success, e.g.
// after swapping in a fresh key).
//
// A second, easy-to-miss bug was found while implementing this: the
// escalation-cap logic in RecordProviderPaused() unconditionally clamped ANY
// base cooldown >= DAILY_QUOTA_COOLDOWN_SECONDS back down to EXACTLY
// DAILY_QUOTA_COOLDOWN_SECONDS - which would have silently made the new,
// longer constant a no-op. Fixed to use the passed-in base value directly
// once it's already at or above the daily-quota threshold.

const DAILY_QUOTA_COOLDOWN_SECONDS = 86400;
const RATE_LIMIT_COOLDOWN_SECONDS = 900;
const DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS = 2592000;

function detectRateLimitCooldownReplica(int $httpCode, ?string $response): ?int
{
    if ($httpCode === 456) {
        return DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS;
    }

    $isRateLimitSignature = $httpCode === 429
        || ($httpCode === 403 && $response !== null && stripos($response, 'rate limit') !== false);

    if (!$isRateLimitSignature) {
        return null;
    }

    $isDailyOrQuota = $response !== null && preg_match('/\b(day|today|daily|quota)\b/i', $response) === 1;

    return $isDailyOrQuota ? DAILY_QUOTA_COOLDOWN_SECONDS : RATE_LIMIT_COOLDOWN_SECONDS;
}

function recordProviderPausedEscalatedSecondsReplica(int $baseCooldownSeconds, int $streak): int
{
    return $baseCooldownSeconds >= DAILY_QUOTA_COOLDOWN_SECONDS
        ? $baseCooldownSeconds
        : min(RATE_LIMIT_COOLDOWN_SECONDS * (2 ** ($streak - 1)), DAILY_QUOTA_COOLDOWN_SECONDS);
}

// Test 1: THE FIX - DeepL's HTTP 456 must map to the long, one-time-quota
// cooldown, regardless of the response body wording.
$result1 = detectRateLimitCooldownReplica(456, 'Quota Exceeded. The character limit set for this billing period has been reached.');
assert($result1 === DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS, 'DeepL HTTP 456 must map to DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS, not the generic daily cooldown');
echo "Test 1 (DeepL 456 maps to the long one-time-quota cooldown) OK\n";

// Test 2: a genuine daily-resetting quota (MyMemory-style, HTTP 429 with
// "quota"/"day" wording) must still get the shorter, correct daily cooldown -
// no regression from the 456 special-case.
$result2 = detectRateLimitCooldownReplica(429, 'MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY');
assert($result2 === DAILY_QUOTA_COOLDOWN_SECONDS, 'A genuine daily quota signature must still get the standard daily cooldown, unaffected by the new 456 special-case');
echo "Test 2 (genuine daily-quota signatures are unaffected by the DeepL special-case) OK\n";

// Test 3: THE CRITICAL FOLLOW-UP FIX - RecordProviderPaused()'s escalation cap
// must NOT clamp the new, longer DeepL cooldown back down to
// DAILY_QUOTA_COOLDOWN_SECONDS (this would have silently made Test 1's fix a
// no-op in the actual pause duration ever recorded).
$escalated = recordProviderPausedEscalatedSecondsReplica(DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS, 1);
assert($escalated === DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS, 'THE FIX: a base cooldown already at/above the daily threshold must be used directly, not clamped down to DAILY_QUOTA_COOLDOWN_SECONDS');
echo "Test 3 (the escalation cap no longer clamps the longer DeepL cooldown down to 24h) OK\n";

// Test 4: a genuine daily-quota base cooldown (exactly DAILY_QUOTA_COOLDOWN_SECONDS)
// must behave identically to before this fix (no regression) - using it
// directly is a no-op change for this exact value.
$escalatedDaily = recordProviderPausedEscalatedSecondsReplica(DAILY_QUOTA_COOLDOWN_SECONDS, 1);
assert($escalatedDaily === DAILY_QUOTA_COOLDOWN_SECONDS, 'A genuine daily-quota base cooldown must still resolve to exactly 24h, unchanged');
echo "Test 4 (a genuine daily-quota cooldown is unaffected - same 24h as before) OK\n";

// Test 5: the short rate-limit escalation ladder (below the daily threshold)
// must still cap at DAILY_QUOTA_COOLDOWN_SECONDS as before - only cooldowns
// already at/above that threshold bypass the cap now.
$escalatedShort = recordProviderPausedEscalatedSecondsReplica(RATE_LIMIT_COOLDOWN_SECONDS, 10); // streak=10 would overflow without the cap
assert($escalatedShort === DAILY_QUOTA_COOLDOWN_SECONDS, 'The short rate-limit escalation ladder must still be capped at 24h for a long streak');
echo "Test 5 (the short rate-limit escalation ladder is still capped at 24h) OK\n";

echo "\nAll tests passed.\n";
