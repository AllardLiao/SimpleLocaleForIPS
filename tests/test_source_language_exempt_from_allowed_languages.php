<?php
declare(strict_types=1);
// Standalone replica test for build 80 (2026-08-20):
// Second bug found while the user was live-testing build 79: on a license with a
// restrictive "allowedLanguages" list (targeted promo licenses, e.g. "Finnisch zu
// Nikolaus" -> allowedLanguages=["fi"], or the "Nachbarlaender" campaign), the source
// language EnsureSourceLanguageIsTarget() just added would get immediately stripped
// back out again by EnforceLicensedLanguageLimit()'s allowedLanguages filter, since an
// admin's own native/scan language almost never appears in a narrow, topic-specific
// promo language list. Net effect for these licenses: build 79 silently had NO
// effect at all, no matter how many times ApplyChanges() ran - which matches what the
// user reported ("tested it executing a separate applychanges - no change").

function enforceLicensedLanguageLimitReplica(array $rows, array $allowed, int $limit, string $sourceLanguage): array
{
    $filtered = $allowed === []
        ? $rows
        : array_values(array_filter($rows, function ($row) use ($allowed, $sourceLanguage) {
            $code = $row['code'] ?? '';

            return $code === $sourceLanguage || in_array($code, $allowed, true);
        }));

    if ($limit > 0 && count($filtered) > $limit) {
        $filtered = array_slice($filtered, 0, $limit);
    }

    return $filtered;
}

// Test 1: reproduces the exact reported symptom - a promo license restricted to
// ["en", "es"] (matching the screenshot: Target languages showed only English and
// Español) with source language "de". Before the fix, "de" would be stripped right
// back out on every single ApplyChanges() call, making Build 79 a permanent no-op for
// this license.
$rowsWithSourceAdded = [['code' => 'en'], ['code' => 'es'], ['code' => 'de']];
$afterEnforcement = enforceLicensedLanguageLimitReplica($rowsWithSourceAdded, ['en', 'es'], 0, 'de');
assert(in_array('de', array_column($afterEnforcement, 'code'), true), 'THE FIX: the current source language must survive the allowedLanguages filter even when it is not itself in that promo list');
echo "Test 1 (source language survives a restrictive allowedLanguages promo license - the reported bug) OK\n";

// Test 2: without the fix, the old behavior would have silently dropped it - confirms
// this is a real, previously-broken interaction, not a hypothetical.
function enforceLicensedLanguageLimitReplicaOldBuggyBehavior(array $rows, array $allowed, int $limit): array
{
    $filtered = $allowed === []
        ? $rows
        : array_values(array_filter($rows, function ($row) use ($allowed) {
            return in_array($row['code'] ?? '', $allowed, true);
        }));
    if ($limit > 0 && count($filtered) > $limit) {
        $filtered = array_slice($filtered, 0, $limit);
    }

    return $filtered;
}
$oldBuggyResult = enforceLicensedLanguageLimitReplicaOldBuggyBehavior($rowsWithSourceAdded, ['en', 'es'], 0);
assert(!in_array('de', array_column($oldBuggyResult, 'code'), true), 'Confirms the root cause: the pre-fix filter genuinely strips a source language absent from a restrictive promo allowedLanguages list');
echo "Test 2 (confirms root cause: pre-fix behavior really did silently strip the source language on every ApplyChanges) OK\n";

// Test 3: the numeric limit (a DIFFERENT, deliberately UNCHANGED mechanism per the
// user's explicit design decision) still applies normally to the source-language
// entry once it has passed the allowedLanguages check - the exemption is scoped
// ONLY to the allowedLanguages filter, not to the count cap.
$rowsAtNumericCap = [['code' => 'fr'], ['code' => 'de']]; // limit=1, source=de
$afterNumericLimit = enforceLicensedLanguageLimitReplica($rowsAtNumericCap, [], 1, 'de');
assert(count($afterNumericLimit) === 1, 'The numeric limit must still cap total count normally - only the ALLOWED-LANGUAGES-LIST check exempts the source language, not the numeric limit (per the users explicit anti-abuse requirement)');
echo "Test 3 (numeric language-count limit is unaffected by the allowedLanguages exemption - still trims the source-language entry like any other when over the numeric cap) OK\n";

// Test 4: an unrestricted license (allowedLanguages=[], the normal case) behaves
// identically to before - no regression for the common case.
$unrestricted = enforceLicensedLanguageLimitReplica([['code' => 'en'], ['code' => 'de']], [], 0, 'de');
assert($unrestricted === [['code' => 'en'], ['code' => 'de']], 'An unrestricted license (the normal/common case) must be completely unaffected by this fix');
echo "Test 4 (unrestricted licenses - the common case - are unaffected by this fix) OK\n";

echo "\nAll tests passed.\n";
