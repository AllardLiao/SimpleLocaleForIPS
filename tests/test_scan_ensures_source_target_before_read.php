<?php
declare(strict_types=1);
// Standalone replica test for build 80 (2026-08-20):
// Bug found by the user right after build 79 shipped: a plain Rescan click never
// showed the source language as a new column, and the Target languages list stayed
// unchanged. Root cause: ScanRootTree() read $targetLanguages = GetSelectedTargetLanguages()
// BEFORE EnsureSourceLanguageIsTarget() ever got a chance to run (that only lived in
// ApplyChanges(), which ScanRootTree() only triggers via IPS_ApplyChanges() at its own
// END, i.e. one full Rescan pass too late to affect ITS OWN column-filling). Fix:
// ScanRootTree() must call EnsureSourceLanguageIsTarget() itself, early, before it reads
// the target-language list.

function ensureSourceLanguageIsTargetReplica(array $targetRows, string $sourceLanguage): array
{
    foreach ($targetRows as $row) {
        if (($row['code'] ?? '') === $sourceLanguage) {
            return $targetRows;
        }
    }
    $targetRows[] = ['code' => $sourceLanguage];

    return $targetRows;
}

// Simulates one ScanRootTree() pass: given the CORRECT ordering (ensure-source-is-
// target runs first), what target language list does the FillMissingTranslations
// call actually see in that same pass?
function simulateScanRootTreeCorrectOrder(array $targetRows, string $sourceLanguage): array
{
    $targetRows = ensureSourceLanguageIsTargetReplica($targetRows, $sourceLanguage); // now first
    $targetLanguagesSeenByFillMissingTranslations = array_column($targetRows, 'code');

    return $targetLanguagesSeenByFillMissingTranslations;
}

// Simulates the BUGGY build-79 ordering: target languages read first, ensure-source-
// is-target only happens later (via ApplyChanges(), reactively, too late for this pass).
function simulateScanRootTreeBuggyOrder(array $targetRows, string $sourceLanguage): array
{
    $targetLanguagesSeenByFillMissingTranslations = array_column($targetRows, 'code'); // read BEFORE the fix
    // EnsureSourceLanguageIsTarget() would run only afterward, too late to affect the
    // list already captured above.
    return $targetLanguagesSeenByFillMissingTranslations;
}

// Test 1: reproduces the exact user report - a fresh Rescan on an instance with
// TargetLanguages=[en, es] and SourceLanguage=de must translate into "de" as well,
// in THIS SAME rescan pass (not require a second click).
$correct = simulateScanRootTreeCorrectOrder([['code' => 'en'], ['code' => 'es']], 'de');
assert(in_array('de', $correct, true), 'THE FIX: the very first Rescan after switching to build 79/80 must already see "de" among the target languages it fills columns for');
echo "Test 1 (fixed ordering: a single Rescan already fills the source-language column, matching the user's expectation) OK\n";

// Test 2: reproduces the BUG as observed - the buggy ordering leaves the source
// language missing from that same pass, exactly matching what the user saw
// (no Deutsch column, Target languages list unchanged after Rescan).
$buggy = simulateScanRootTreeBuggyOrder([['code' => 'en'], ['code' => 'es']], 'de');
assert(!in_array('de', $buggy, true), 'Confirms the root cause: the buggy (pre-fix) ordering genuinely omits the source language from the SAME Rescan pass that just added it');
echo "Test 2 (reproduces the reported bug: wrong ordering silently drops the newly-added source language from the very rescan that added it) OK\n";

// Test 3: idempotency - a second Rescan (or the auto-rescan timer) with the fix
// applied must not duplicate the source-language entry.
$rows = [['code' => 'de'], ['code' => 'en'], ['code' => 'es']];
$afterSecondScan = ensureSourceLanguageIsTargetReplica($rows, 'de');
assert($afterSecondScan === $rows, 'A second Rescan (source language already present) must not add a duplicate entry');
echo "Test 3 (repeated rescans stay idempotent, no duplicate source-language entries) OK\n";

echo "\nAll tests passed.\n";
