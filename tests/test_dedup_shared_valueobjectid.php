<?php
declare(strict_types=1);
// Standalone replica verifying the 2026-08-15 "shared ValueObjectID" bug fix:
// two tree links pointing at the same underlying string variable (e.g. one
// via a root-level link, another nested deeper) used to create two
// independently-drifting rows, racing to overwrite the same live variable on
// every language switch.

function deduplicateTextRowsByValueObjectID(array $Rows): array
{
    $result = [];
    $seen = [];
    foreach ($Rows as $row) {
        $valueObjectID = (int) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0);
        if ($valueObjectID !== 0) {
            if (isset($seen[$valueObjectID])) {
                continue;
            }
            $seen[$valueObjectID] = true;
        }
        $result[] = $row;
    }
    return $result;
}

// Test 1: dedup keeps the first row, drops the later duplicate.
$rows = [
    ['ObjectID' => 17034, 'ValueObjectID' => 40412, 'ORIGINAL_IMPORT_TEXT' => 'Müllabfuhr', 'TEXT_en' => 'Garbage collection'],
    ['ObjectID' => 99999, 'ValueObjectID' => 12345, 'ORIGINAL_IMPORT_TEXT' => 'Mondphase', 'TEXT_en' => 'Moon phase'],
    ['ObjectID' => 15464, 'ValueObjectID' => 40412, 'ORIGINAL_IMPORT_TEXT' => '<meta name="viewport"...', 'TEXT_en' => '<style> body {margin: 0px...'],
];
$result = deduplicateTextRowsByValueObjectID($rows);
assert(count($result) === 2, 'Exactly one row per unique ValueObjectID must survive');
assert($result[0]['ObjectID'] === 17034, 'First-seen row (17034) must be kept');
assert($result[1]['ObjectID'] === 99999, 'Unrelated row (moon phase, unique ValueObjectID) must survive untouched');
foreach ($result as $r) {
    assert($r['ObjectID'] !== 15464, 'Duplicate row (15464, same ValueObjectID as 17034) must be dropped');
}
echo "Test 1 (dedup keeps first row per shared ValueObjectID) OK\n";

// Test 2: ApplyLanguage's write-loop guard - even WITHOUT a rescan (so
// duplicates are still present live), the second row must not overwrite
// what the first row already wrote for the same target variable.
function simulateApplyLanguageWriteLoop(array $Rows): array
{
    $writes = []; // valueObjectID => value actually written (last call wins per ID, simulating SetValueString)
    $writtenValueObjectIDs = [];
    foreach ($Rows as $row) {
        $valueObjectID = (int) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0);
        if ($valueObjectID === 0 || isset($writtenValueObjectIDs[$valueObjectID])) {
            continue;
        }
        $writtenValueObjectIDs[$valueObjectID] = true;
        $writes[$valueObjectID] = $row['TEXT_en']; // the value this row would have written
    }
    return $writes;
}

$liveDuplicateRows = [
    ['ObjectID' => 17034, 'ValueObjectID' => 40412, 'TEXT_en' => 'Garbage collection'], // freshly maintained
    ['ObjectID' => 15464, 'ValueObjectID' => 40412, 'TEXT_en' => '<style> body {margin: 0px...'], // stale/frozen
];
$writes = simulateApplyLanguageWriteLoop($liveDuplicateRows);
assert($writes[40412] === 'Garbage collection', 'First row (actively maintained) must win the write, not be clobbered by the stale second row');
echo "Test 2 (write-loop guard prevents the stale duplicate from clobbering the live one) OK\n";

echo "All tests passed.\n";
