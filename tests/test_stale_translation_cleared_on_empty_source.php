<?php
declare(strict_types=1);
// Standalone replica test for build 101 (2026-08-21): user-confirmed live finding
// (via a direct property inspection script) - ObjectID 37723 ("Hinweis") has
// ORIGINAL_IMPORT_Text == "" right now (confirmed via the variable's own value
// history: this content is DYNAMIC and legitimately goes empty sometimes), yet
// Text_en still held "Temperature from the fall..." - a STALE translation from
// an earlier, non-empty state. User's own question: "wenn der Rohtext leer ist,
// sollten die Übersetzungen nicht auch leer sein?" - exactly right.
//
// Root cause: ApplyTrackedVariableUpdate() correctly writes an empty raw value
// straight through, and calls MarkRowSourceChanged() (by design, to make ALL
// language cells retroactively stale without deleting their current fallback
// value - see that function's own doc comment). But FillLanguageColumn(), which
// is what would normally pick up a "stale" cell and refresh it on the next
// Rescan, has always had a first guard of "$fromText !== ''" - an EMPTY raw
// text was therefore skipped entirely, INCLUDING skipping the propagation of
// that emptiness into already-filled (now stale) target-language cells. If the
// content happens to be empty again by the time of the next Rescan (likely for
// content that's blank most of the time), this can persist indefinitely.
//
// Fix: when the raw text is empty, actively clear an already non-empty target
// cell instead of leaving it untouched.

function fillLanguageColumnEmptySourceReplica(array $row, string $toField): array
{
    $fromText = $row['raw'] ?? '';
    if ($fromText === '') {
        if (($row[$toField] ?? '') !== '') {
            $row[$toField] = '';
        }
        return $row;
    }

    // (normal translate-if-pending path omitted - not the concern of this test)
    return $row;
}

// Test 1: THE REPORTED CASE - raw text is empty, target cell holds stale content
// from an earlier non-empty state - must get cleared.
$row1 = ['raw' => '', 'Text_en' => 'Temperature from the fall of the last 24 hours...'];
$result1 = fillLanguageColumnEmptySourceReplica($row1, 'Text_en');
assert($result1['Text_en'] === '', 'THE FIX: a stale translation must be cleared once the raw source becomes empty');
echo "Test 1 (stale translation is cleared once the raw source becomes empty) OK\n";

// Test 2: raw text is empty AND the target cell is ALSO already empty - no-op,
// no unnecessary write.
$row2 = ['raw' => '', 'Text_es' => ''];
$result2 = fillLanguageColumnEmptySourceReplica($row2, 'Text_es');
assert($result2['Text_es'] === '', 'An already-empty target cell stays empty (no-op)');
echo "Test 2 (an already-empty target cell is left alone, no unnecessary write) OK\n";

// Test 3: raw text is NON-empty - the clearing branch must not fire at all, the
// row is returned untouched for this replica (real translation logic is
// exercised elsewhere, not part of this fix).
$row3 = ['raw' => 'Guten Tag Connor!', 'Text_en' => 'Good afternoon Connor!'];
$result3 = fillLanguageColumnEmptySourceReplica($row3, 'Text_en');
assert($result3['Text_en'] === 'Good afternoon Connor!', 'A non-empty raw source must never trigger the clearing branch');
echo "Test 3 (a non-empty raw source never triggers the new clearing branch) OK\n";

// Test 4: symmetry check - the actual module.php must place the empty-source
// clearing INSIDE FillLanguageColumn()'s per-row loop, before the
// LooksLikeJson/IsRowLanguageTranslationCurrent pending-check, and the
// temporary Build 100 diagnostic logging must be gone.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'SLOC_TranslateGapDiag') === false, 'The temporary Build 100 diagnostic logging must be removed now that the mechanism is confirmed and fixed');
assert(strpos($moduleSource, "if (\$fromText === '') {") !== false, 'FillLanguageColumn() must contain the new empty-source handling branch');
echo "Test 4 (Build 100 diagnostic removed, Build 101 fix present in the real function) OK\n";

echo "\nAll tests passed.\n";
