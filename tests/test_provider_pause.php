<?php
declare(strict_types=1);
// Standalone replica tests for the auto-pause feature (2026-08-17, build 55):
// when ALL configured translation providers report a rate-limit/quota error at
// the same time, further requests are pointless (they'd just fail again) - the
// instance should stop trying entirely until the earliest provider recovers,
// and surface that both to guests (small red tile notice) and admins (config
// form panel + instance status).

const RATE_LIMIT_COOLDOWN_SECONDS = 900;
const DAILY_QUOTA_COOLDOWN_SECONDS = 86400;

function detectRateLimitCooldown(int $httpCode, ?string $response): ?int
{
    $isRateLimitSignature = $httpCode === 429
        || ($httpCode === 403 && $response !== null && stripos($response, 'rate limit') !== false)
        || $httpCode === 456;

    if (!$isRateLimitSignature) {
        return null;
    }

    $isDailyOrQuota = $response !== null && preg_match('/\b(day|today|daily|quota)\b/i', $response) === 1;

    return $isDailyOrQuota ? DAILY_QUOTA_COOLDOWN_SECONDS : RATE_LIMIT_COOLDOWN_SECONDS;
}

// ---------------------------------------------------------------------------
// Test 1: classification of live-observed real-world signatures.
assert(detectRateLimitCooldown(403, '{"error":{"code":403,"message":"User Rate Limit Exceeded"}}') === RATE_LIMIT_COOLDOWN_SECONDS, 'Google "User Rate Limit Exceeded" (403) must be a SHORT burst cooldown, not daily');
assert(detectRateLimitCooldown(429, '{"responseData":{"translatedText":"MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY"}}') === DAILY_QUOTA_COOLDOWN_SECONDS, 'MyMemory daily-quota message must trigger the LONG cooldown');
assert(detectRateLimitCooldown(456, 'Quota Exceeded') === DAILY_QUOTA_COOLDOWN_SECONDS, 'DeepL 456 "Quota Exceeded" must trigger the LONG cooldown (contains "quota")');
assert(detectRateLimitCooldown(401, 'Invalid API key') === null, 'An invalid API key must NEVER be treated as a rate limit - it will keep failing forever, pausing would be misleading');
assert(detectRateLimitCooldown(0, null) === null, 'A network/timeout failure (no HTTP code, no response) must not be treated as a rate limit either');
assert(detectRateLimitCooldown(500, 'Internal Server Error') === null, 'A generic server error must not trigger a pause');
echo "Test 1 (rate-limit signature classification: burst vs daily vs not-a-limit-at-all) OK\n";

// ---------------------------------------------------------------------------
// Per-provider pause tracking replica.
function recordProviderPaused(array &$paused, string $provider, int $cooldownSeconds, int $now): void
{
    $paused[$provider] = max($paused[$provider] ?? 0, $now + $cooldownSeconds);
}

function getProviderPausedUntilMap(array $paused, int $now): array
{
    return array_filter($paused, fn ($until) => (int) $until > $now);
}

function isProviderPaused(array $paused, string $provider, int $now): bool
{
    return isset(getProviderPausedUntilMap($paused, $now)[$provider]);
}

function getGlobalPauseUntil(array $paused, array $chain, int $now): ?int
{
    if ($chain === []) {
        return null;
    }
    $activePauses = getProviderPausedUntilMap($paused, $now);
    $latest = null;
    foreach ($chain as $provider) {
        if (!isset($activePauses[$provider])) {
            return null;
        }
        $latest = $latest === null ? $activePauses[$provider] : min($latest, $activePauses[$provider]);
    }
    return $latest;
}

// ---------------------------------------------------------------------------
// Test 2: a single paused provider (out of several configured) does NOT count
// as a global pause - the others must keep being tried normally.
$now = 1000000;
$paused = [];
recordProviderPaused($paused, 'google', RATE_LIMIT_COOLDOWN_SECONDS, $now);
$chain = ['google', 'deepl', 'free'];
assert(isProviderPaused($paused, 'google', $now) === true, 'Google must be reported as paused right after recording it');
assert(isProviderPaused($paused, 'deepl', $now) === false, 'DeepL was never paused and must not be affected');
assert(getGlobalPauseUntil($paused, $chain, $now) === null, 'Only ONE of three chain providers paused - NOT a global pause (user explicitly asked: only when ALL report a limit)');
echo "Test 2 (single paused provider != global pause, others keep being tried) OK\n";

// Test 3: once EVERY provider in the chain is paused, it IS a global pause,
// and the resume time is the EARLIEST (soonest) of the three - not the latest.
recordProviderPaused($paused, 'deepl', RATE_LIMIT_COOLDOWN_SECONDS, $now);
recordProviderPaused($paused, 'free', DAILY_QUOTA_COOLDOWN_SECONDS, $now);
$globalUntil = getGlobalPauseUntil($paused, $chain, $now);
assert($globalUntil === $now + RATE_LIMIT_COOLDOWN_SECONDS, 'Global pause must resume as soon as the EARLIEST provider recovers (google/deepl at +900s), not wait for the latest (free at +86400s)');
echo "Test 3 (all three paused = global pause, resumes at the EARLIEST provider) OK\n";

// Test 4: pauses expire naturally once their timestamp passes - no manual
// cleanup needed, and the global pause lifts as soon as one provider's cooldown
// elapses.
$later = $now + RATE_LIMIT_COOLDOWN_SECONDS + 1; // google/deepl cooldown just elapsed
assert(isProviderPaused($paused, 'google', $later) === false, 'An expired pause must no longer report as paused');
assert(getGlobalPauseUntil($paused, $chain, $later) === null, 'Once google/deepl recover, the chain is no longer FULLY paused (free is still down) - not a global pause anymore');
echo "Test 4 (pauses expire naturally by timestamp, global pause lifts as soon as one recovers) OK\n";

// Test 5: a single-provider chain (only the free provider configured, no paid
// keys) counts as fully paused the moment that ONE provider is paused - "all
// three" scales down correctly to however many are actually configured.
$singleChainPaused = [];
recordProviderPaused($singleChainPaused, 'free', RATE_LIMIT_COOLDOWN_SECONDS, $now);
assert(getGlobalPauseUntil($singleChainPaused, ['free'], $now) === $now + RATE_LIMIT_COOLDOWN_SECONDS, 'With only ONE provider configured, pausing that one IS already a global pause - "all providers" must not be hardcoded to exactly three');
echo "Test 5 (single-provider chain: pausing the only provider is already a global pause) OK\n";

// ---------------------------------------------------------------------------
// Test 6: TranslateChunk-style skip-without-calling-the-API behavior - a
// provider that's currently paused must be skipped entirely (no wasted request
// against a provider we already know is rate-limited), while a non-paused one
// in the same chain is still tried normally.
function translateChunkSimulated(array $chain, array $paused, int $now, array &$actuallyCalledProviders): ?string
{
    if (getGlobalPauseUntil($paused, $chain, $now) !== null) {
        return null; // short-circuit: don't even enter the loop
    }
    foreach ($chain as $provider) {
        if (isProviderPaused($paused, $provider, $now)) {
            continue; // skip WITHOUT calling the API
        }
        $actuallyCalledProviders[] = $provider;
        // simulate: only 'deepl' succeeds in this scenario
        if ($provider === 'deepl') {
            return 'translated-via-deepl';
        }
    }
    return null;
}

$actuallyCalled = [];
$mixedPaused = ['google' => $now + RATE_LIMIT_COOLDOWN_SECONDS]; // only google paused
$result = translateChunkSimulated(['google', 'deepl', 'free'], $mixedPaused, $now, $actuallyCalled);
assert($result === 'translated-via-deepl', 'DeepL must still be tried and succeed even while google is paused');
assert(!in_array('google', $actuallyCalled, true), 'A paused provider must NEVER actually be called - that would waste a request against a provider already known to be rate-limited');
assert(in_array('deepl', $actuallyCalled, true), 'A non-paused provider must still be called normally');
echo "Test 6 (paused provider skipped without an API call, others still tried normally) OK\n";

// Test 7: when EVERY provider is paused, TranslateChunk short-circuits before
// calling ANY of them at all.
$actuallyCalled2 = [];
$allPaused = ['google' => $now + 900, 'deepl' => $now + 900, 'free' => $now + 900];
$result2 = translateChunkSimulated(['google', 'deepl', 'free'], $allPaused, $now, $actuallyCalled2);
assert($result2 === null, 'A fully-paused chain must return null (no translation attempted)');
assert($actuallyCalled2 === [], 'When the whole chain is paused, ZERO API calls may be made - not even a doomed attempt at the first provider');
echo "Test 7 (fully-paused chain: zero API calls, not even one wasted attempt) OK\n";

echo "\nAll tests passed.\n";
