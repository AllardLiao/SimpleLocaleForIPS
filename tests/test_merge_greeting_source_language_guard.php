<?php
declare(strict_types=1);
// Standalone replica verifying the 2026-08-16 fix: MergeGreetingRows() must
// only refresh ORIGINAL_IMPORT (and clear translations) when the source
// language is reliably active during the Rescan - otherwise the currently
// displayed value is a TRANSLATION (written live by ApplyLanguage()), not
// genuine source-language text, and treating it as fresh raw text would
// silently corrupt the already-known row (a regression introduced by the
// earlier build-43 "stale translation" fix, caught by a user question about
// Rescan behavior while a non-source language is active).

function mergeGreetingRows(array $ExistingRows, array $ScannedRows, bool $IsSourceLanguageActive): array
{
    if ($ScannedRows === []) {
        return $ExistingRows;
    }
    if ($ExistingRows === []) {
        return $ScannedRows;
    }

    $row = $ExistingRows[0];
    $newRawText = $ScannedRows[0]['ORIGINAL_IMPORT'];

    if ($IsSourceLanguageActive && $row['ORIGINAL_IMPORT'] !== $newRawText) {
        foreach (array_keys($row) as $field) {
            if (!in_array($field, ['ORIGINAL_IMPORT', 'ValueObjectID'], true)) {
                $row[$field] = '';
            }
        }
        $row['ORIGINAL_IMPORT'] = $newRawText;
    }

    if (isset($ScannedRows[0]['ValueObjectID'])) {
        $row['ValueObjectID'] = $ScannedRows[0]['ValueObjectID'];
    } else {
        unset($row['ValueObjectID']);
    }

    return [$row];
}

// Test 1: THE regression scenario - source language NOT active during
// Rescan. The live variable currently shows "Good night, Connor!" (an
// English TRANSLATION, written by ApplyLanguage()), not genuine German
// source text. The existing row must survive completely untouched.
$existing = [
    'ORIGINAL_IMPORT' => 'Gute Nacht Connor!',
    'ValueObjectID'   => 51042,
    'en' => 'Good night, Connor!',
    'es' => '¡Buenas noches, Connor!',
];
$scannedWhileEnglishActive = [[
    'ORIGINAL_IMPORT' => 'Good night, Connor!', // the live value IS the English translation right now
    'ValueObjectID'   => 51042,
]];
$result = mergeGreetingRows([$existing], $scannedWhileEnglishActive, false);
assert($result[0]['ORIGINAL_IMPORT'] === 'Gute Nacht Connor!', 'Existing German raw text must survive - must NOT be replaced by the currently-displayed English translation');
assert($result[0]['en'] === 'Good night, Connor!', 'Existing translations must survive untouched');
assert($result[0]['es'] === '¡Buenas noches, Connor!', 'Existing translations must survive untouched');
echo "Test 1 (Rescan while non-source language active leaves the row untouched - the actual bug) OK\n";

// Test 2: source language genuinely active, raw text genuinely changed -
// must still refresh normally (this is the build-43 fix, must not regress).
$existingStale = [
    'ORIGINAL_IMPORT' => 'Guten Abend Connor!',
    'ValueObjectID'   => 51042,
    'en' => 'Good evening, Connor!',
];
$scannedWhileGermanActive = [[
    'ORIGINAL_IMPORT' => 'Gute Nacht Connor!', // genuine new German text, source language IS active
    'ValueObjectID'   => 51042,
]];
$result2 = mergeGreetingRows([$existingStale], $scannedWhileGermanActive, true);
assert($result2[0]['ORIGINAL_IMPORT'] === 'Gute Nacht Connor!', 'Raw text must refresh when source language is genuinely active');
assert($result2[0]['en'] === '', 'Stale translation must be cleared for retranslation when source language is genuinely active');
echo "Test 2 (build-43 fix still works when source language really is active) OK\n";

// Test 3: unchanged raw text, source language active - no-op, translations
// preserved (avoid needless retranslation).
$result3 = mergeGreetingRows([$existing], [['ORIGINAL_IMPORT' => 'Gute Nacht Connor!', 'ValueObjectID' => 51042]], true);
assert($result3[0]['en'] === 'Good night, Connor!', 'Unchanged raw text must never clear translations, regardless of active language');
echo "Test 3 (unchanged raw text is always a no-op) OK\n";

echo "\nAll tests passed.\n";
