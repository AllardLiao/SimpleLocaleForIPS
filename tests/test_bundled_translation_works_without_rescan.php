<?php
declare(strict_types=1);
// Standalone replica test for build 86 (2026-08-20):
// User confirmed live: right after build 85, switching the tile to English still
// showed German text ("Übersetzung pausiert bis" instead of "Translation paused
// until"), even though English is a bundled language. Root cause: build 85's
// bundled-translation seeding only ran inside MergeOwnUiTextRows(), which itself
// only runs inside ScanRootTree() - i.e. only during an actual Rescan. Before the
// first-ever Rescan (or right after upgrading, before the next Rescan), the
// persisted row either doesn't exist yet or has an empty cell for that language, so
// GetOwnUiText() fell straight through to the hardcoded German constant - exactly
// the opposite of build 85's promise ("ready immediately, no rescan needed"). Fix:
// GetOwnUiText() (the actual read path used by every guest-facing text builder) now
// falls back to the bundled translation table directly, independent of whether a
// row exists or a rescan has ever run.

const BUNDLED = [
    'en' => ['pausedNoticePrefix' => 'Translation paused until'],
    'nl' => ['pausedNoticePrefix' => 'Vertaling gepauzeerd tot'],
];

function resolveRowValueReplica(?array $row, string $selectedLanguage, string $sourceLanguage, string $rawField): string
{
    if ($row === null) {
        return '';
    }
    if ($selectedLanguage === $sourceLanguage) {
        return $row[$rawField] ?? '';
    }
    return $row[$selectedLanguage] ?? $row[$rawField] ?? '';
}

function getOwnUiTextReplica(?array $row, string $key, string $language, string $fallback): string
{
    if ($row !== null) {
        $value = resolveRowValueReplica($row, $language, 'de', 'ORIGINAL_IMPORT');
        if ($value !== '') {
            return $value;
        }
    }

    return BUNDLED[$language][$key] ?? $fallback;
}

// Test 1: THE REPORTED BUG - no row exists at all yet (brand-new instance, or a
// pre-build-85 instance that hasn't been rescanned since upgrading). Selecting a
// bundled language (English) must still show the bundled translation, not the
// German fallback constant.
$result = getOwnUiTextReplica(null, 'pausedNoticePrefix', 'en', 'Übersetzung pausiert bis');
assert($result === 'Translation paused until', 'THE FIX: with no persisted row at all, a bundled language must still resolve via the bundled table, not fall through to the German constant');
echo "Test 1 (no persisted row yet: bundled language still resolves correctly, no rescan required) OK\n";

// Test 2: a row EXISTS (e.g. from build 78) but its 'en' cell happens to be empty
// (never live-translated, and no rescan has run since upgrading to build 85 to seed
// it) - must still fall back to the bundled table, not the German raw text.
$rowWithEmptyEnglish = ['ORIGINAL_IMPORT' => 'Übersetzung pausiert bis', 'en' => ''];
$result2 = getOwnUiTextReplica($rowWithEmptyEnglish, 'pausedNoticePrefix', 'en', 'Übersetzung pausiert bis');
assert($result2 === 'Translation paused until', 'A persisted row with an empty cell for a bundled language must still resolve via the bundled table');
echo "Test 2 (existing row with an empty cell for a bundled language: still resolves via the bundled table) OK\n";

// Test 3: a row with an ALREADY-FILLED cell (either a real provider translation, or
// a value a rescan already seeded per build 85) takes priority over the bundled
// table - the persisted value must never be silently replaced.
$rowWithRealTranslation = ['ORIGINAL_IMPORT' => 'Übersetzung pausiert bis', 'en' => 'Manually corrected wording'];
$result3 = getOwnUiTextReplica($rowWithRealTranslation, 'pausedNoticePrefix', 'en', 'Übersetzung pausiert bis');
assert($result3 === 'Manually corrected wording', 'An already-filled persisted cell must take priority over the bundled table fallback');
echo "Test 3 (an already-filled persisted cell takes priority over the bundled fallback) OK\n";

// Test 4: an unbundled language (e.g. Japanese) with no row/cell falls through to
// the German constant exactly as before - no regression for languages outside the
// bundled set.
$result4 = getOwnUiTextReplica(null, 'pausedNoticePrefix', 'ja', 'Übersetzung pausiert bis');
assert($result4 === 'Übersetzung pausiert bis', 'An unbundled language with no persisted data must still fall back to the German constant, unchanged from before');
echo "Test 4 (unbundled languages still fall back to the German constant as before - no regression) OK\n";

echo "\nAll tests passed.\n";
