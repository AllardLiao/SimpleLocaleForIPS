<?php
declare(strict_types=1);
// Standalone replica of MergeGreetingRows() (post-fix), verifying the
// 2026-08-15 bug: raw text changing between scans must clear stale
// per-language translation columns, not just refresh ORIGINAL_IMPORT.

function mergeGreetingRows(array $ExistingRows, array $ScannedRows): array
{
    if ($ScannedRows === []) {
        return $ExistingRows;
    }
    if ($ExistingRows === []) {
        return $ScannedRows;
    }

    $row = $ExistingRows[0];
    $newRawText = $ScannedRows[0]['ORIGINAL_IMPORT'];

    if ($row['ORIGINAL_IMPORT'] !== $newRawText) {
        foreach (array_keys($row) as $field) {
            if (!in_array($field, ['ORIGINAL_IMPORT', 'ValueObjectID'], true)) {
                $row[$field] = '';
            }
        }
    }

    $row['ORIGINAL_IMPORT'] = $newRawText;

    if (isset($ScannedRows[0]['ValueObjectID'])) {
        $row['ValueObjectID'] = $ScannedRows[0]['ValueObjectID'];
    } else {
        unset($row['ValueObjectID']);
    }

    return [$row];
}

// Scenario: greeting cycled from "Guten Abend Connor!" (evening) to
// "Gute Nacht Connor!" (night) via an external VM_UPDATE write that already
// updated ORIGINAL_IMPORT+translations live (ApplyTrackedVariableUpdate).
// A Rescan then runs (manual or Pro auto-timer) and re-scans the SAME,
// now-updated live variable value.
$existing = [
    'ORIGINAL_IMPORT' => 'Gute Nacht Connor!',
    'ValueObjectID'   => 51042,
    'en'              => 'Good night, Connor!',
    'es'              => '¡Buenas noches, Connor!',
];
$scanned = [[
    'ORIGINAL_IMPORT' => 'Gute Nacht Connor!', // unchanged since last scan
    'ValueObjectID'   => 51042,
]];

$result = mergeGreetingRows([$existing], $scanned);
assert($result[0]['en'] === 'Good night, Connor!', 'Unchanged raw text: translations must NOT be cleared (avoid needless re-translation)');
assert($result[0]['es'] === '¡Buenas noches, Connor!', 'Unchanged raw text: es must survive');
echo "Test 1 (unchanged raw text preserves translations) OK\n";

// Now simulate: the live variable changed AGAIN (e.g. external script wrote a
// new value) but for whatever reason the VM_UPDATE-driven live retranslation
// didn't run yet (message lost/instance was offline) - Rescan is the only
// thing that picks it up. Old translations must be cleared, not left stale.
$existingStale = [
    'ORIGINAL_IMPORT' => 'Guten Abend Connor!', // stale raw text from before
    'ValueObjectID'   => 51042,
    'en'              => 'Good evening, Connor!', // stale translation
    'es'              => '¡Buenas tardes, Connor!', // stale translation
];
$scannedFresh = [[
    'ORIGINAL_IMPORT' => 'Gute Nacht Connor!', // live value has since moved on
    'ValueObjectID'   => 51042,
]];

$result2 = mergeGreetingRows([$existingStale], $scannedFresh);
assert($result2[0]['ORIGINAL_IMPORT'] === 'Gute Nacht Connor!', 'ORIGINAL_IMPORT must reflect the fresh scan');
assert($result2[0]['en'] === '', 'Changed raw text: stale en translation must be cleared so FillMissingTranslations() refills it');
assert($result2[0]['es'] === '', 'Changed raw text: stale es translation must be cleared so FillMissingTranslations() refills it');
assert($result2[0]['ValueObjectID'] === 51042, 'ValueObjectID must be preserved/updated from the scan');
echo "Test 2 (changed raw text clears stale translations - the actual bug fix) OK\n";

echo "All tests passed.\n";
