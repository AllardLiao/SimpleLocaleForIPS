<?php
declare(strict_types=1);
// Standalone replica test for build 105 (2026-08-21): user-reported live
// regression, likely exposed (not caused) by build 104: a manually corrected
// translation cell showed the new content briefly, then reverted to the old
// value "einen Augenblick später" - the table/property still showed the
// user's edit, but the visu had the old value again.
//
// Root cause, confirmed by code reading: StagePendingTrackedRowUpdates()
// (the debounced flush of externally-triggered row updates, see
// BufferPendingTrackedRowUpdate/PENDING_ROW_UPDATE_DEBOUNCE_SECONDS)
// unconditionally overwrote row fields with whatever was buffered
// (`array_replace($row, $fieldUpdatesByValueObjectID[...])`), with zero
// regard for whether the row had since been manually edited via the exact
// "Übernehmen" that triggered this flush (FlushPendingTrackedRowUpdates()
// runs at the very start of every ApplyChanges() call, including the one
// caused by the admin's own save). A pending patch queued from an earlier
// external write (or a stale echo) would silently clobber a fresher manual
// correction to the same cell. Build 104 made this far more visible/frequent
// by making ApplyLanguage() (which pushes the row content to the live
// object) run on every content change instead of only on a real language
// switch - so a stale flush's damage, previously invisible until some later
// unrelated event, now surfaces on the very next ApplyChanges() reentry.
//
// Fix: BufferPendingTrackedRowUpdate() now also captures a baseline (the
// field's value BEFORE this externally-triggered change). At flush time,
// StagePendingTrackedRowUpdates() only applies a buffered field if the row's
// CURRENT value for that field still matches its baseline - i.e. nothing
// else (a manual edit) has touched it since. Other buffered fields (and
// other rows) still flush normally, preserving Build 71's original intent
// (an unrelated pending external update must not be lost by an unrelated
// "Übernehmen").

function stagePendingReplica(array $row, array $entry): array
{
    $fieldUpdates = $entry['fields'] ?? $entry;
    $baseline = $entry['baseline'] ?? [];

    foreach ($fieldUpdates as $field => $value) {
        if (array_key_exists($field, $baseline) && ($row[$field] ?? null) !== $baseline[$field]) {
            continue;
        }
        $row[$field] = $value;
    }

    return $row;
}

// Test 1: THE REPORTED BUG - a manual edit to the same field happened AFTER
// the patch was buffered (row's current value no longer matches the
// baseline) - the stale buffered value must NOT overwrite the fresh manual
// edit.
$rowAfterManualEdit = ['ORIGINAL_IMPORT_Text' => 'Guten Tag (manuell korrigiert)', 'QuelleGeaendertAm' => 1000];
$staleEntry = [
    'fields'   => ['ORIGINAL_IMPORT_Text' => 'Guten Tag (alter externer Wert)', 'QuelleGeaendertAm' => 500],
    'baseline' => ['ORIGINAL_IMPORT_Text' => 'Guten Tag (Ausgangswert)'],
];
$result1 = stagePendingReplica($rowAfterManualEdit, $staleEntry);
assert($result1['ORIGINAL_IMPORT_Text'] === 'Guten Tag (manuell korrigiert)', 'THE FIX: a stale buffered value must not overwrite a manual edit made to the same field since buffering');
echo "Test 1 (a manual edit since buffering is preserved, the stale buffered content field is skipped) OK\n";

// Test 2: bookkeeping fields WITHOUT a baseline (e.g. QuelleGeaendertAm) must
// still apply unconditionally - only content fields get the conflict check.
assert($result1['QuelleGeaendertAm'] === 500, 'Bookkeeping fields without a captured baseline must still apply unconditionally (no conflict risk for pure timestamps)');
echo "Test 2 (bookkeeping fields without a baseline still apply normally) OK\n";

// Test 3: THE ORIGINAL, LEGITIMATE USE CASE (Build 71) - nothing else touched
// the row since buffering (current value still matches baseline) - the
// buffered external update must apply normally, exactly as before.
$rowUnchangedSinceBuffering = ['ORIGINAL_IMPORT_Text' => 'Guten Tag (Ausgangswert)', 'QuelleGeaendertAt' => 500];
$legitimateEntry = [
    'fields'   => ['ORIGINAL_IMPORT_Text' => 'Guten Tag (neuer externer Wert)'],
    'baseline' => ['ORIGINAL_IMPORT_Text' => 'Guten Tag (Ausgangswert)'],
];
$result3 = stagePendingReplica($rowUnchangedSinceBuffering, $legitimateEntry);
assert($result3['ORIGINAL_IMPORT_Text'] === 'Guten Tag (neuer externer Wert)', 'A genuine, un-conflicted external update must still apply normally - no regression to the original Build 71 behavior');
echo "Test 3 (a genuine external update with no conflicting manual edit still applies normally) OK\n";

// Test 4: backward compatibility - an already-pending, pre-upgrade entry in
// the OLD flat shape (no 'fields'/'baseline' wrapper) must still be applied
// unconditionally (matching the pre-Build-105 behavior for anything already
// queued at upgrade time), not crash or silently vanish.
$rowOldShape = ['Text_es' => 'viejo'];
$oldShapeEntry = ['Text_es' => 'nuevo']; // flat, no 'fields' key at all
$result4 = stagePendingReplica($rowOldShape, $oldShapeEntry);
assert($result4['Text_es'] === 'nuevo', 'An already-pending, pre-upgrade flat-shape entry must still apply unconditionally (no baseline available for it)');
echo "Test 4 (a pre-upgrade, old-shape pending entry still applies, treated as having no baseline) OK\n";

// Test 5: symmetry check - the real module.php must actually capture and
// pass the baseline, and StagePendingTrackedRowUpdates() must check it.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, '$baselineRawValue = $Rows[$RowIndex][$RawField] ?? \'\';') !== false, 'ApplyTrackedVariableUpdate() must capture the raw-field baseline before mutating the row');
assert(strpos($moduleSource, "array_key_exists(\$field, \$baseline) && (\$row[\$field] ?? null) !== \$baseline[\$field]") !== false, 'StagePendingTrackedRowUpdates() must skip a field whose current value no longer matches its captured baseline');
echo "Test 5 (the fix is correctly wired into the real Buffer/Stage functions) OK\n";

echo "\nAll tests passed.\n";
