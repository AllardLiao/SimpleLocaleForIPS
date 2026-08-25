<?php
declare(strict_types=1);
// Originally build 107 (2026-08-21): ObjectID 37322 ("Echo Bad" > "Info", a
// String variable) appeared in BOTH propertyObjectNames (every object with
// an Ident gets a row there, per WalkTree) AND propertyObjectTexts (also a
// tracked "Eigene Texte" String variable) - and propertyObjectTexts used to
// maintain its OWN, independent Name translation for its rows
// (fieldOriginalImportName/fieldNamePrefix), separate from
// propertyObjectNames' entry for the very same object. ApplyLanguage()
// called IPS_SetName() a second time from the ObjectTexts loop, silently
// overwriting the user's fresh correction from the ObjectNames loop.
// Build 107 fixed this by guarding the SECOND write ($writtenNameObjectIDs).
//
// Build 115 (Nutzer-Wunsch, 2026-08-22): since propertyObjectNames
// unconditionally covers EVERY object in the tree (including every String
// variable), a second, separately editable/translated Name field in
// "Eigene Texte" was ALWAYS structurally redundant - never an independent
// data source, always a duplicate of the same object's ObjectNames row,
// editable in two places at once with no guarantee they'd ever agree, and
// wastefully translated twice. Instead of guarding against the symptom
// (the second write), Build 115 removes the root cause entirely: "Eigene
// Texte" no longer tracks a Name at all, only the String variable's VALUE.
// The old $writtenNameObjectIDs dedup guard is gone - there is nothing left
// to guard against, since ApplyLanguage()'s ObjectTexts loop no longer calls
// IPS_SetName() at all, only WriteTrackedValueString().

function applyLanguageReplicaV2(array $objectNamesRows, array $objectTextsRows, string $language): array
{
    $liveNames = [];
    foreach ($objectNamesRows as $row) {
        $liveNames[$row['ObjectID']] = $row[$language] ?? $row['ORIGINAL_IMPORT'];
    }

    // The ObjectTexts loop below intentionally never touches $liveNames -
    // it has no Name field to resolve/write anymore (Build 115).
    $liveValues = [];
    foreach ($objectTextsRows as $row) {
        $valueObjectID = $row['ValueObjectID'] ?? $row['ObjectID'];
        $liveValues[$valueObjectID] = $row['Text_' . $language] ?? $row['ORIGINAL_IMPORT_Text'];
    }

    return ['names' => $liveNames, 'values' => $liveValues];
}

// Test 1: an object tracked in BOTH lists (a named "Eigene Texte" String
// variable) gets its Name EXCLUSIVELY from ObjectNames and its Value
// EXCLUSIVELY from ObjectTexts - no second, competing Name source exists
// anymore, so there is nothing left to silently overwrite.
$objectNames = [['ObjectID' => 37322, 'ORIGINAL_IMPORT' => 'Info', 'es' => 'Información-']];
$objectTexts = [['ObjectID' => 37322, 'ValueObjectID' => 37322, 'ORIGINAL_IMPORT_Text' => '<p>Text</p>', 'Text_es' => '<p>Texto</p>']];
$result1 = applyLanguageReplicaV2($objectNames, $objectTexts, 'es');
assert($result1['names'][37322] === 'Información-', 'The Name must come exclusively from ObjectNames, unaffected by ObjectTexts');
assert($result1['values'][37322] === '<p>Texto</p>', 'The Value must come exclusively from ObjectTexts, independent of the Name');
echo "Test 1 (an object tracked in both lists gets its Name only from ObjectNames and its Value only from ObjectTexts - no duplication) OK\n";

// Test 2: ObjectTexts rows no longer carry any Name-related field at all -
// editing/translating a Name in two places (the original complaint: "man
// kann an zwei Stellen den Titel anpassen - unlogisch") is now structurally
// impossible, not just guarded against.
$objectTextsRow = ['ObjectID' => 111, 'ValueObjectID' => 111, 'ORIGINAL_IMPORT_Text' => 'x', 'Text_es' => 'y'];
assert(!array_key_exists('ORIGINAL_IMPORT_Name', $objectTextsRow) && !array_key_exists('Name_es', $objectTextsRow), 'sanity: the replica row shape has no Name fields (matches the real, updated row shape)');
echo "Test 2 (ObjectTexts rows structurally carry no Name field - editing a title in two places is no longer possible) OK\n";

// Test 3: symmetry check - the real module.php must have actually removed
// the old dual-write guard and the Name-tracking fields, not just stopped
// using them.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

assert(strpos($moduleSource, '$writtenNameObjectIDs = [];') === false, 'The old dual-write guard variable must no longer be declared (Build 115) - there is nothing left to guard against (a historical mention in a comment is fine, actual code must be gone)');
assert(strpos($constantsSource, 'fieldOriginalImportName') === false, 'The fieldOriginalImportName constant must be removed entirely');
assert(strpos($constantsSource, 'fieldNamePrefix') === false, 'The fieldNamePrefix constant must be removed entirely');

// The ObjectTexts loop in ApplyLanguage() must contain exactly one
// IPS_SetName()-like write: none. It must call WriteTrackedValueString()
// but never @IPS_SetName() for its own row.
$applyLanguageStart = strpos($moduleSource, 'private function ApplyLanguage(');
$objectTextsLoopStart = strpos($moduleSource, 'foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {', $applyLanguageStart);
$objectTextsLoopEnd = strpos($moduleSource, "\n        }\n", $objectTextsLoopStart);
$objectTextsLoopBody = substr($moduleSource, $objectTextsLoopStart, $objectTextsLoopEnd - $objectTextsLoopStart);
assert(strpos($objectTextsLoopBody, 'IPS_SetName(') === false, 'The ObjectTexts loop in ApplyLanguage() must never call IPS_SetName() anymore (Build 115) - only WriteTrackedValueString()');
assert(strpos($objectTextsLoopBody, 'WriteTrackedValueString(') !== false, 'The ObjectTexts loop must still write the value via WriteTrackedValueString()');

echo "Test 3 (the real module.php has removed the dual-write guard, the Name constants, and the ObjectTexts loop no longer calls IPS_SetName()) OK\n";

// Test 4: BuildListColumns() for 'texts' must no longer expose a Name
// column - only Path/Wert-Objekt-ID/Original-Import (Text)/Text_<lang>.
assert(strpos($moduleSource, "'caption' => \$this->Translate('Original-Import (Name)')") === false, 'The "Original-Import (Name)" column must be removed from the Eigene-Texte list');
$formSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
assert(strpos($formSource, 'ORIGINAL_IMPORT_Name') === false, 'form.json must no longer declare the ORIGINAL_IMPORT_Name column');
assert(strpos($formSource, 'Eigene Texte (String-Variablen)') !== false, 'The list caption must be renamed to clarify it only ever contains String variables');
echo "Test 4 (the Name column is gone from both the dynamic column builder and the static form.json, and the list is renamed) OK\n";

echo "\nAll tests passed.\n";
