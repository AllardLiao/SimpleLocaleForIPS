<?php
declare(strict_types=1);
// Standalone replica tests for the 2026-08-19 data-loss fix (build 59): live
// reported that "Objektnamen"/"Eigene Texte"/"Beschriftungen"/"Automations"
// rows had their WORKING English/Español/Français/... translation columns
// wiped to completely blank, with only "Original import" (raw text, still in
// German) surviving - across ALL row-holding properties except a few "Eigene
// Texte" rows that happened to escape.
//
// Root cause: TWO different code paths (ReconcileRowFields, used by every
// property via ReconcileRowSourceLanguageChanges, and
// ApplyTrackedVariableUpdate, the VM_UPDATE live-translate path) both
// unconditionally overwrote a row's stored translation column with whatever
// TranslateBatch() returned - INCLUDING an empty string when the provider
// chain failed/was paused (see the whole 2026-08-17/18 auto-pause saga).
// Since providers were down for extended stretches throughout this session,
// any row touched by either path during one of those windows had its
// previously-good translation permanently destroyed with nothing to fall
// back to - the third and worst instance of the "empty result treated as a
// valid translation" bug class (after the HTML text-node fix and the
// paused-notice-prefix fix, both build 58).

// --- Replica of the ReconcileRowFields / ApplyTrackedVariableUpdate fix ----

function applyTranslationResult(array &$row, string $column, string $apiResult, bool &$allSucceeded): void
{
    if ($apiResult !== '') {
        $row[$column] = $apiResult;
    } else {
        $allSucceeded = false;
        // THE FIX: no `else` branch that writes '' - the existing value (if
        // any) is left completely untouched.
    }
}

// ---------------------------------------------------------------------------
// Test 1: THE reported bug - a row with WORKING translations gets a fresh
// (re)translate attempt while the provider chain is down. Every previously
// good column must survive completely intact.
$row = [
    'ORIGINAL_IMPORT' => 'Betriebsart',
    'en' => 'Operating mode',
    'es' => 'Modo de funcionamiento',
    'fr' => 'Mode de fonctionnement',
];
$allSucceeded = true;
foreach (['en', 'es', 'fr'] as $lang) {
    applyTranslationResult($row, $lang, '', $allSucceeded); // every attempt fails (chain paused)
}
assert($row['en'] === 'Operating mode', 'English translation must survive a failed retranslation attempt untouched');
assert($row['es'] === 'Modo de funcionamiento', 'Spanish translation must survive a failed retranslation attempt untouched');
assert($row['fr'] === 'Mode de fonctionnement', 'French translation must survive a failed retranslation attempt untouched');
assert($allSucceeded === false, 'The row must be flagged as NOT fully reconciled, so a later attempt can retry it');
echo "Test 1 (all-failed retranslation attempt: every existing column survives intact) OK\n";

// ---------------------------------------------------------------------------
// Test 2: a genuine successful translation must still overwrite the old value
// normally - the fix must not accidentally freeze columns forever.
$row2 = ['en' => 'Old stale value'];
$allSucceeded2 = true;
applyTranslationResult($row2, 'en', 'Fresh correct value', $allSucceeded2);
assert($row2['en'] === 'Fresh correct value', 'A successful translation must still replace the old value normally');
assert($allSucceeded2 === true, 'A fully successful attempt must be flagged as such');
echo "Test 2 (successful translation still overwrites normally, fix does not freeze data) OK\n";

// ---------------------------------------------------------------------------
// Test 3: mixed outcome within one row (e.g. Google down, DeepL/free chain
// segment recovered for one language but not another) - partial success must
// be preserved per-column, not all-or-nothing.
$row3 = ['en' => 'Old English', 'es' => 'Old Spanish', 'fr' => 'Old French'];
$allSucceeded3 = true;
applyTranslationResult($row3, 'en', 'New English', $allSucceeded3);   // succeeds
applyTranslationResult($row3, 'es', '', $allSucceeded3);              // fails
applyTranslationResult($row3, 'fr', 'New French', $allSucceeded3);    // succeeds
assert($row3['en'] === 'New English', 'A successful column in a mixed row must be updated');
assert($row3['es'] === 'Old Spanish', 'A failed column in a mixed row must keep its old value, not go blank');
assert($row3['fr'] === 'New French', 'A successful column after a failed one must still be updated normally');
assert($allSucceeded3 === false, 'A row is only "fully reconciled" if EVERY column succeeded - one failure marks the whole row as incomplete');
echo "Test 3 (mixed success/failure within one row: each column judged independently) OK\n";

// --- Replica of the bookkeeping-only-on-full-success fix -------------------

function reconcileBookkeeping(bool $allSucceeded, string $newSource, ?string $currentBookkeeping): ?string
{
    // THE FIX: only advance the "translated against" bookkeeping when EVERY
    // column actually got a fresh, successful translation - otherwise a
    // later pass must still be able to detect "this row still needs work".
    return $allSucceeded ? $newSource : $currentBookkeeping;
}

// Test 4: a fully successful reconcile advances the bookkeeping normally (so
// a later, unrelated ApplyChanges() call does not needlessly retry work that
// already succeeded).
$bookkeeping = reconcileBookkeeping(true, 'en', 'de');
assert($bookkeeping === 'en', 'Fully successful reconcile must advance the bookkeeping to the new source language');
echo "Test 4 (full success advances bookkeeping normally) OK\n";

// Test 5: THE core fix - a PARTIALLY or fully failed reconcile must leave the
// bookkeeping exactly as it was, so the mismatch against the row's own
// Quellsprache is still detected on the next pass and the row gets retried
// once a provider recovers - instead of being falsely marked "done" with
// stale/missing columns forever.
$bookkeeping2 = reconcileBookkeeping(false, 'en', 'de');
assert($bookkeeping2 === 'de', 'A failed/partial reconcile must NOT advance the bookkeeping - the row must remain eligible for a retry');
echo "Test 5 (failed/partial reconcile leaves bookkeeping unchanged, guaranteeing a future retry) OK\n";

echo "\nAll tests passed.\n";
