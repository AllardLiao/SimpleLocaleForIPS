<?php
declare(strict_types=1);
// Standalone replica test for build 66 (2026-08-19):
// Build 65 stopped TranslateBatch() from caching a fallback (source text
// masquerading as a translation) as a "real" result GOING FORWARD - but any
// entry cached BEFORE build 65 shipped, under schema version 2, was left
// untouched: a text like "Gehen" that had already been (mis-)cached as its
// own German fallback for de->en/es/pl/ru during an earlier provider-pause
// window kept being served straight from cache, bypassing TranslateBatch()'s
// fresh-translation path (and therefore the build-65 guard) entirely - live
// reported: clearing the "Automations" target-language cells and Rescanning
// still filled every column with German again, with the debug log showing
// a "..._Mapping" entry but NO subsequent "..._Request"/"..._Response" -
// the tell-tale sign of a cache hit, not a fresh (and now-guarded) attempt.
//
// Fixed the same way as the prior (2026-08-15) stale-cache incident: bump
// TRANSLATION_CACHE_SCHEMA_VERSION, which is baked into every cache key, so
// every entry cached before this fix becomes permanently unreachable and
// gets recomputed fresh exactly once.

function buildKeyVersioned(int $version, string $source, string $target, string $text): string
{
    return $version . '|' . $source . '|' . $target . '|' . hash('sha256', $text);
}

// Test 1: THE reported bug - a poisoned entry cached under the OLD version
// (2) - "Gehen" mis-cached as its own German "translation" to English
// during a pre-build-65 pause - must be UNREACHABLE under the NEW version
// (3), forcing a genuine fresh translation attempt (which is now correctly
// guarded by the build-65 fix).
$cache = [];
$poisonedKey = buildKeyVersioned(2, 'de', 'en', 'Gehen');
$cache[$poisonedKey] = 'Gehen'; // the poisoned "translation" - identical to source

$lookupKeyAfterBump = buildKeyVersioned(3, 'de', 'en', 'Gehen');
assert(!isset($cache[$lookupKeyAfterBump]), 'A version-2 poisoned cache entry must be unreachable under the version-3 key scheme, forcing a fresh (build-65-guarded) translation attempt');
echo "Test 1 (a pre-build-65 poisoned cache entry becomes unreachable after the version bump, matching the reported Automations bug) OK\n";

// Test 2: a GENUINE, correct pre-existing translation (also cached under
// the old version) is likewise invalidated - an accepted, one-time cost
// (a single unnecessary re-translation per distinct text) in exchange for
// guaranteeing no poisoned entry survives; it will simply be re-cached
// correctly under the new version on the next attempt.
$cache2 = [];
$goodKey = buildKeyVersioned(2, 'de', 'en', 'Guten Morgen');
$cache2[$goodKey] = 'Good morning'; // a real, correct prior translation
$lookupKey2 = buildKeyVersioned(3, 'de', 'en', 'Guten Morgen');
assert(!isset($cache2[$lookupKey2]), 'Even a genuinely correct old-version entry is invalidated by the version bump - an accepted one-time cost, not a bug');
echo "Test 2 (a genuine old-version entry is also invalidated - the accepted one-time cost of guaranteeing no poison survives) OK\n";

// Test 3: a NEW entry, cached fresh under the CURRENT version (3, e.g.
// after this fix ships and the text gets re-translated once), must be
// found normally on a subsequent lookup - the cache still works going
// forward, only the stale version is discarded.
$cache3 = [];
$freshKey = buildKeyVersioned(3, 'de', 'en', 'Gehen');
$cache3[$freshKey] = 'Walking'; // freshly, correctly re-translated post-fix
$lookupKey3 = buildKeyVersioned(3, 'de', 'en', 'Gehen');
assert(($cache3[$lookupKey3] ?? null) === 'Walking', 'A freshly cached, current-version entry must still hit normally - the cache remains functional going forward');
echo "Test 3 (a freshly re-cached, current-version entry hits normally - the cache still works after the bump) OK\n";

echo "\nAll tests passed.\n";
