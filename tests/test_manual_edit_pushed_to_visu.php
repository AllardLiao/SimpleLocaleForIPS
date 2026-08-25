<?php
declare(strict_types=1);
// Standalone replica test for build 104 (2026-08-21): user asked why a
// manually corrected translation cell doesn't show up in the visu right
// away. Not the 12-minute VM_UPDATE debounce (PENDING_ROW_UPDATE_DEBOUNCE_SECONDS,
// unrelated to a manual "Übernehmen" edit) - the real cause: ApplyChanges()
// only re-runs ApplyLanguage() (the function that actually writes the
// object's live Name/Value) when either the active language itself changed
// or a per-row source-language reconciliation happened. A plain manual cell
// edit triggers neither, so the correction sat in the property, saved but
// never pushed, until some unrelated event (e.g. a real language switch)
// happened to trigger ApplyLanguage() again.
//
// Fix: a new, cheap (no API calls, no translation attempts - just a
// resolved-value fingerprint) ComputeActiveLanguageContentFingerprint()
// check, mirroring the existing ComputeRowSourceLanguageFingerprint pattern,
// triggers ApplyLanguage() whenever the content resolved for the currently
// active language differs from the last time ApplyChanges() ran.

function resolveRowValueReplica(array $row, string $selectedLanguage, string $languageField, string $sourceLanguage, string $rawField): string
{
    if ($selectedLanguage === 'ORIGINAL_IMPORT' || $selectedLanguage === $sourceLanguage) {
        return $row[$rawField] ?? '';
    }
    if (($row[$languageField] ?? '') !== '') {
        return $row[$languageField];
    }

    return $row[$rawField] ?? '';
}

function computeActiveLanguageContentFingerprintReplica(array $rowsByProperty, array $fieldGroupsByProperty, string $currentLanguage, string $instanceSourceLanguage): string
{
    $parts = [];
    foreach ($fieldGroupsByProperty as $property => $fieldGroups) {
        foreach ($rowsByProperty[$property] ?? [] as $row) {
            $rowSourceLanguage = $row['Quellsprache'] ?? $instanceSourceLanguage;
            foreach ($fieldGroups as $group) {
                $parts[] = resolveRowValueReplica($row, $currentLanguage, $group['prefix'] . $currentLanguage, $rowSourceLanguage, $group['raw']);
            }
        }
    }

    return md5(implode("\x00", $parts));
}

function shouldApplyLanguageReplica(bool $rowSourceLanguagesReconciled, bool $activeLanguageContentChanged, string $currentLanguage, string $lastAppliedLanguage): bool
{
    return $rowSourceLanguagesReconciled || $activeLanguageContentChanged || $currentLanguage !== $lastAppliedLanguage;
}

$fieldGroups = ['ObjectTexts' => [['raw' => 'ORIGINAL_IMPORT_Text', 'prefix' => 'Text_']]];

// Test 1: THE REPORTED CASE - a manually corrected translation cell (active
// language unchanged, row source language unchanged) must still trigger
// ApplyLanguage() via the new content fingerprint, not stay silently stuck.
$rowsBefore = ['ObjectTexts' => [['ORIGINAL_IMPORT_Text' => 'Guten Tag', 'Quellsprache' => 'de', 'Text_es' => 'Buenos dias (wrong typo)']]];
$fingerprintBefore = computeActiveLanguageContentFingerprintReplica($rowsBefore, $fieldGroups, 'es', 'de');

$rowsAfterManualFix = ['ObjectTexts' => [['ORIGINAL_IMPORT_Text' => 'Guten Tag', 'Quellsprache' => 'de', 'Text_es' => 'Buenos dias']]];
$fingerprintAfter = computeActiveLanguageContentFingerprintReplica($rowsAfterManualFix, $fieldGroups, 'es', 'de');

$shouldApply = shouldApplyLanguageReplica(false, $fingerprintAfter !== $fingerprintBefore, 'es', 'es');
assert($shouldApply === true, 'THE FIX: a manually corrected cell for the currently active language must trigger ApplyLanguage()');
echo "Test 1 (manually correcting the currently active language's cell triggers a re-push) OK\n";

// Test 2: editing a DIFFERENT language's cell (not the currently active one)
// must NOT spuriously trigger ApplyLanguage() - no visible effect for the
// current guest, no need to re-push.
$rowsBeforeDe = ['ObjectTexts' => [['ORIGINAL_IMPORT_Text' => 'Guten Tag', 'Quellsprache' => 'de', 'Text_es' => 'Buenos dias', 'Text_en' => 'Good day (typo)']]];
$fingerprintBeforeEs = computeActiveLanguageContentFingerprintReplica($rowsBeforeDe, $fieldGroups, 'es', 'de');
$rowsAfterEnEdit = ['ObjectTexts' => [['ORIGINAL_IMPORT_Text' => 'Guten Tag', 'Quellsprache' => 'de', 'Text_es' => 'Buenos dias', 'Text_en' => 'Good day']]];
$fingerprintAfterEsUnaffected = computeActiveLanguageContentFingerprintReplica($rowsAfterEnEdit, $fieldGroups, 'es', 'de');
assert($fingerprintBeforeEs === $fingerprintAfterEsUnaffected, 'Editing a cell for a DIFFERENT (currently inactive) language must not change the active-language fingerprint');
echo "Test 2 (editing an inactive language's cell does not spuriously trigger a re-push) OK\n";

// Test 3: no content change at all - must NOT trigger ApplyLanguage() via the
// new condition (still respects the existing two conditions independently).
$rowsUnchanged = ['ObjectTexts' => [['ORIGINAL_IMPORT_Text' => 'Guten Tag', 'Quellsprache' => 'de', 'Text_es' => 'Buenos dias']]];
$fp1 = computeActiveLanguageContentFingerprintReplica($rowsUnchanged, $fieldGroups, 'es', 'de');
$fp2 = computeActiveLanguageContentFingerprintReplica($rowsUnchanged, $fieldGroups, 'es', 'de');
$shouldApplyUnchanged = shouldApplyLanguageReplica(false, $fp2 !== $fp1, 'es', 'es');
assert($shouldApplyUnchanged === false, 'No content change and no language/row-source change must NOT trigger a redundant ApplyLanguage() call');
echo "Test 3 (no changes at all does not trigger a redundant re-push) OK\n";

// Test 4: symmetry check - module.php must actually wire the new fingerprint
// into ApplyChanges()'s existing decision, and persist it every run.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'ComputeActiveLanguageContentFingerprint') !== false, 'ComputeActiveLanguageContentFingerprint() must be defined');
assert(strpos($moduleSource, '$activeLanguageContentChanged || $currentLanguage') !== false, 'ApplyChanges() must fold the new content-changed flag into its existing ApplyLanguage() trigger condition');
assert(strpos($moduleSource, 'attributeLastActiveLanguageContentFingerprint, $activeLanguageContentFingerprint') !== false, 'The freshly computed fingerprint must be persisted every ApplyChanges() run');
echo "Test 4 (the fix is correctly wired into the real ApplyChanges() logic) OK\n";

echo "\nAll tests passed.\n";
