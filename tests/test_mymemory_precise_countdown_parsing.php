<?php
declare(strict_types=1);
// Standalone replica test for build 85 (2026-08-20):
// User spotted that MyMemory's own response text already states the precise
// remaining wait time ("NEXT AVAILABLE IN  02 HOURS 51 MINUTES 23 SECONDS") - far
// more accurate than build 83's "next UTC midnight" assumption, since MyMemory's
// quota window apparently isn't strictly pinned to UTC midnight. Parse that exact
// countdown when present; fall back to the UTC-midnight estimate only if the pattern
// is missing (defensive, in case MyMemory changes wording).

function parseMyMemoryNextAvailableTimestampReplica(?string $response, int $now): ?int
{
    if ($response === null) {
        return null;
    }
    if (preg_match('/NEXT AVAILABLE IN\s+(?:(\d+)\s*HOURS?\s*)?(?:(\d+)\s*MINUTES?\s*)?(?:(\d+)\s*SECONDS?\s*)?/i', $response, $matches) !== 1) {
        return null;
    }
    $totalSeconds = ((int) ($matches[1] ?? 0)) * 3600 + ((int) ($matches[2] ?? 0)) * 60 + ((int) ($matches[3] ?? 0));
    if ($totalSeconds <= 0) {
        return null;
    }

    return $now + $totalSeconds;
}

$now = strtotime('2026-08-20 21:17:00 UTC');

// Test 1: THE REPORTED CASE - the exact real-world response text from the user's
// debug log must parse to precisely 2h51m23s from now.
$realResponse = '{"responseData":{"translatedText":"MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY. NEXT AVAILABLE IN  02 HOURS 51 MINUTES 23 SECONDS VISIT HTTPS:\/\/MYMEMORY.TRANSLATED.NET\/DOC\/USAGELIMITS.PHP TO TRANSLATE MORE"},"quotaFinished":null,"responseStatus":429}';
$result = parseMyMemoryNextAvailableTimestampReplica($realResponse, $now);
$expected = $now + 2 * 3600 + 51 * 60 + 23;
assert($result === $expected, 'THE FIX: the exact real-world MyMemory response text must parse to precisely 2h51m23s from now, not a generic 24h/UTC-midnight guess');
echo "Test 1 (real-world response text parses to the exact stated countdown) OK\n";

// Test 2: double-space and mixed-case robustness (the actual response used two
// spaces after "IN" and all-caps wording).
assert(parseMyMemoryNextAvailableTimestampReplica('next available in 1 hours 2 minutes 3 seconds', $now) === $now + 3723, 'Lowercase wording and single spacing must parse identically to the uppercase/double-space real-world variant');
echo "Test 2 (case-insensitive, spacing-tolerant parsing) OK\n";

// Test 3: a response missing the pattern entirely (defensive fallback case) returns
// null, so the caller falls back to the UTC-midnight estimate.
assert(parseMyMemoryNextAvailableTimestampReplica('MYMEMORY WARNING: SOME OTHER MESSAGE WITHOUT A COUNTDOWN', $now) === null, 'A response without the countdown pattern must return null so the caller can fall back to the UTC-midnight estimate');
echo "Test 3 (missing pattern falls back to null, triggering the defensive UTC-midnight fallback) OK\n";

// Test 4: a null response (e.g. cURL failure, no body at all) also returns null.
assert(parseMyMemoryNextAvailableTimestampReplica(null, $now) === null, 'A null response must return null, not throw or produce a bogus timestamp');
echo "Test 4 (a null response returns null safely) OK\n";

// Test 5: a "0 hours 0 minutes 0 seconds" (or completely absent numbers) must not
// resolve to time() + 0 (a pause that expires the instant it's recorded) - must
// return null so the caller falls back to a sane estimate instead.
assert(parseMyMemoryNextAvailableTimestampReplica('NEXT AVAILABLE IN', $now) === null, 'A countdown resolving to zero total seconds must return null, not a already-expired pause');
echo "Test 5 (a zero-second countdown returns null instead of an instantly-expired pause) OK\n";

// Test 6: hours/minutes/seconds are each independently optional - a response with
// only minutes and seconds (no hours mentioned) must still parse correctly.
assert(parseMyMemoryNextAvailableTimestampReplica('NEXT AVAILABLE IN 45 MINUTES 10 SECONDS', $now) === $now + 45 * 60 + 10, 'A countdown missing the hours component must still parse the minutes/seconds correctly');
echo "Test 6 (partial countdowns without an hours component parse correctly) OK\n";

echo "\nAll tests passed.\n";
