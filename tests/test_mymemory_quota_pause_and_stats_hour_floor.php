<?php
declare(strict_types=1);
// Standalone replica tests for build 62 (2026-08-19):
// 1. MyMemory's "quotaFinished" JSON field (HTTP 200, no error code) must now
//    trigger the same provider-pause bookkeeping as an HTTP-level rate limit -
//    previously it silently returned null forever without ever marking 'free'
//    as paused, so Rescan looked like it "did nothing" once the daily quota
//    was exhausted but Google/DeepL were also already paused.
// 2. ComputeTranslationStats() must never show a per-hour rate that EXCEEDS
//    the actual total count during the first hour after activation - the
//    previous 1-second floor let a short burst (e.g. via "Übersetzungsanbieter
//    prüfen") extrapolate to an absurd, confusing "per hour" figure.

// --- 1. MyMemory quotaFinished must now pause the provider -------------------

function translateSingleFreeSimulated(array $decodedResponse, array &$pauseState): ?string
{
    if (($decodedResponse['quotaFinished'] ?? false) === true) {
        // RecordProviderPaused('free', DAILY_QUOTA_COOLDOWN_SECONDS) equivalent
        $pauseState['free'] = ['until' => time() + 86400, 'streak' => 1];

        return null;
    }
    if (!is_array($decodedResponse)) {
        return null;
    }

    return $decodedResponse['responseData']['translatedText'] ?? null;
}

// Test 1: THE bug - a quotaFinished response (HTTP 200, no error code) must
// now mark 'free' as paused, not leave it silently "available forever".
$pauseState = [];
$result = translateSingleFreeSimulated(['quotaFinished' => true, 'responseData' => ['translatedText' => '']], $pauseState);
assert($result === null, 'A quotaFinished response must still be treated as a failed translation');
assert(isset($pauseState['free']), 'A quotaFinished response must now mark the free provider as paused - this was the actual root cause of "Rescan does nothing"');
echo "Test 1 (quotaFinished now pauses the free provider, closing the silent-failure gap) OK\n";

// Test 2: a normal, successful MyMemory response must NOT pause anything.
$pauseState2 = [];
$result2 = translateSingleFreeSimulated(['quotaFinished' => false, 'responseData' => ['translatedText' => 'Hello']], $pauseState2);
assert($result2 === 'Hello', 'A normal successful response must return the translated text');
assert(!isset($pauseState2['free']), 'A normal successful response must never pause the provider');
echo "Test 2 (a normal successful MyMemory response never pauses the provider) OK\n";

// Test 3: a malformed/non-array response (not quotaFinished, just garbage)
// must still fail safely without touching the pause state - only an EXPLICIT
// quotaFinished:true is a pause signal, not "any old failure".
$pauseState3 = [];
$result3 = translateSingleFreeSimulated([], $pauseState3);
// empty array: quotaFinished is falsy via ?? default, falls through to is_array check (true, it IS an array) -> responseData missing -> null
assert($result3 === null, 'A response without responseData must return null');
assert(!isset($pauseState3['free']), 'A generic malformed response (not quotaFinished) must not pause the provider - only the explicit quotaFinished signal should');
echo "Test 3 (a malformed-but-not-quotaFinished response fails without pausing) OK\n";

// --- 2. Stats: per-hour rate must never exceed the total during the first hour ---

function computeTranslationStats(int $since, int $requestCount, int $characterCount, int $now): array
{
    $elapsedSeconds = $since > 0 ? max(3600, $now - $since) : 3600;
    $hoursElapsed = $elapsedSeconds / 3600;

    return [
        'requestsPerHour' => $requestCount / $hoursElapsed,
        'charsPerHour'    => $characterCount / $hoursElapsed,
    ];
}

// Test 4: THE reported bug - 783 requests in just 28 minutes must NOT
// extrapolate to "1698 Anfragen/h" (a rate that looks bizarrely higher than
// the total and reads as a bug to the user) - it must show exactly 783/h,
// i.e. never exceed the actual total, until a full hour has really passed.
$now = 1000000 + (28 * 60); // 28 minutes later
$stats = computeTranslationStats(1000000, 783, 12624, $now);
assert((int) round($stats['requestsPerHour']) === 783, 'Within the first hour, the per-hour rate must equal the total, never exceed it');
echo "Test 4 (783 requests in 28 minutes shows exactly 783/h, not an inflated extrapolation) OK\n";

// Test 5: once a genuine full hour (or more) has passed, the rate must
// reflect a real hourly average again (extrapolation resumes being useful).
$now2 = 1000000 + 7200; // 2 hours later
$stats2 = computeTranslationStats(1000000, 60, 1000, $now2);
assert((int) round($stats2['requestsPerHour']) === 30, 'After 2 real hours, 60 requests must still average to exactly 30/h');
echo "Test 5 (after a genuine 2 hours, the per-hour average is computed normally) OK\n";

// Test 6: right at activation (elapsed = 0), the rate must equal the total
// (not an astronomically inflated number) - same floor applies.
$stats3 = computeTranslationStats($now, 5, 100, $now);
assert((int) round($stats3['requestsPerHour']) === 5, 'Right at activation, the per-hour rate must equal the (small) total, not spike to an inflated extrapolation');
echo "Test 6 (immediately at activation, the rate equals the total, no inflated spike) OK\n";

echo "\nAll tests passed.\n";
