<?php
declare(strict_types=1);
// Standalone replica test for the 2026-08-18 fix: the "paused until HH:MM"
// guest-tile notice showed only the time with no leading text - live
// screenshot confirmed. Root cause: TranslateBatch() returns an empty STRING
// (not a missing array index) when the whole provider chain is paused/fails -
// "??" alone does not fall back on an empty string, only on a missing/null
// value, so the translated prefix silently became "".

function orFallback($value, string $fallback): string
{
    return ($value ?? '') !== '' ? $value : $fallback;
}

// Test 1: THE bug - translation returned an empty string (present in the
// array, not missing) because the provider chain was paused. Plain "??" would
// NOT catch this (array key exists), the explicit empty-string check must.
$translatedOwnTexts = ['', '']; // both translations failed/paused
$prefix = orFallback($translatedOwnTexts[1] ?? null, 'Uebersetzung pausiert bis');
assert($prefix === 'Uebersetzung pausiert bis', 'An empty-string translation result must fall back to the German original, not silently disappear');
echo "Test 1 (empty-string translation result falls back correctly, plain ?? would have missed this) OK\n";

// Test 2: sanity - a genuinely missing array index (never even attempted)
// must also fall back correctly (this already worked before the fix, must
// keep working).
$missingIndexArray = [];
$prefix2 = orFallback($missingIndexArray[5] ?? null, 'Uebersetzung pausiert bis');
assert($prefix2 === 'Uebersetzung pausiert bis', 'A missing array index must still fall back correctly');
echo "Test 2 (missing array index still falls back correctly) OK\n";

// Test 3: a real, successful translation must be used as-is, never replaced
// by the fallback.
$prefix3 = orFallback('Translation paused until', 'Uebersetzung pausiert bis');
assert($prefix3 === 'Translation paused until', 'A real successful translation must never be overridden by the fallback');
echo "Test 3 (real translation is used normally) OK\n";

echo "\nAll tests passed.\n";
