<?php
declare(strict_types=1);
// Standalone replica verifying the 2026-08-15 "stale pre-fix cache entry
// served after an algorithm change" bug: TranslateBatch() caches the
// REASSEMBLED result of a full HTML blob (before it gets split into text
// nodes internally). Without a schema version in the cache key, a raw text
// that was translated (badly) BEFORE the HTML-tokenizer fix stays cached
// under the exact same key forever - and gets served again, unchanged,
// even after the algorithm is fixed, whenever that exact raw text recurs.

function buildKeyUnversioned(string $source, string $target, string $text): string
{
    return $source . '|' . $target . '|' . hash('sha256', $text);
}

function buildKeyVersioned(int $version, string $source, string $target, string $text): string
{
    return $version . '|' . $source . '|' . $target . '|' . hash('sha256', $text);
}

// Simulate: a weather-widget blob was translated (badly, pre-fix) at build
// 45 and cached under the OLD (unversioned) key scheme.
$rawText = '<span class="txt fall">0 % Regen</span><span class="txt humi">78 % Luftfeuchte</span>';
$badCachedResult = '<span class="txt fall">0% </span><span class="txt humi">chance of rain, 78% humidity</span>'; // the actual reported corruption

$cache = [];
$cache[buildKeyUnversioned('de', 'en', $rawText)] = $badCachedResult;

// Post-fix (build 47): TranslateBatch() looks up using the VERSIONED key.
$lookupKey = buildKeyVersioned(2, 'de', 'en', $rawText);
assert(!isset($cache[$lookupKey]), 'A pre-fix cache entry (unversioned key) must NOT satisfy a post-fix (versioned) lookup - must be a cache miss, forcing a fresh, correct translation');
echo "Test 1 (old unversioned cache entry is unreachable after the version bump - forces fresh translation) OK\n";

// Confirm the SAME-VERSION cache still works normally (no unnecessary API
// calls for content already correctly cached under the current scheme).
$cache2 = [];
$goodResult = '<span class="txt fall">0% chance of rain</span><span class="txt humi">78% humidity</span>';
$cache2[buildKeyVersioned(2, 'de', 'en', $rawText)] = $goodResult;
$lookupKey2 = buildKeyVersioned(2, 'de', 'en', $rawText);
assert(($cache2[$lookupKey2] ?? null) === $goodResult, 'A cache entry written under the CURRENT version must still be found normally');
echo "Test 2 (current-version cache entries still hit normally) OK\n";

echo "\nAll tests passed.\n";
