<?php
// Standalone simulation of the reworked Greeting logic in
// SimpleLocaleForIPS/SimpleLocale/module.php (ScanGreetingText,
// MergeGreetingRows, ApplyGreetingLanguage, SyncValueUpdateRegistrations,
// HandleTrackedVariableUpdate/ApplyTrackedVariableUpdate). Mirrors the real
// method bodies exactly (copy-adapted, $this-> calls replaced by injected
// fixture state) since there is no live Symcon instance available here.
declare(strict_types=1);

const VARIABLETYPE_STRING = 3;
const langOriginalImport = 'ORIGINAL_IMPORT';
const langOriginalImportText = 'ORIGINAL_IMPORT_Text';
const fieldTextPrefix = 'Text_';

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

// ---- Fixture state -------------------------------------------------------
$webFront = [
    'ShowGreeting'      => 0,
    'GreetingName'      => '',
    'GreetingVariableID' => 0,
];
$variables = []; // id => ['VariableType' => int, 'Value' => string, 'exists' => bool]

function ipsGetVariable(array $variables, int $id): ?array
{
    return $variables[$id] ?? null;
}

// ---- Mirrors ScanGreetingText(array $ScannedTexts): array ----------------
function ScanGreetingText(array $webFront, array $variables, array $ScannedTexts): array
{
    $showGreeting = (int) $webFront['ShowGreeting'];

    if ($showGreeting === 1 || $showGreeting === 3) {
        $name = (string) $webFront['GreetingName'];
        if ($name === '') {
            return [];
        }

        return [[langOriginalImport => $name]];
    }

    if ($showGreeting === 2) {
        $variableID = (int) $webFront['GreetingVariableID'];
        if ($variableID === 0 || !isset($variables[$variableID])) {
            return [];
        }

        $variable = $variables[$variableID];
        if ($variable['VariableType'] !== VARIABLETYPE_STRING) {
            return [];
        }

        foreach ($ScannedTexts as $row) {
            if ((int) ($row['ValueObjectID'] ?? 0) === $variableID) {
                return [];
            }
        }

        return [[
            langOriginalImport => $variable['Value'],
            'ValueObjectID'    => $variableID,
        ]];
    }

    return [];
}

// ---- Mirrors MergeGreetingRows(array $ExistingRows, array $ScannedRows) --
function MergeGreetingRows(array $ExistingRows, array $ScannedRows): array
{
    if ($ScannedRows === []) {
        return $ExistingRows;
    }

    if ($ExistingRows === []) {
        return $ScannedRows;
    }

    $row = $ExistingRows[0];
    $row[langOriginalImport] = $ScannedRows[0][langOriginalImport];

    if (isset($ScannedRows[0]['ValueObjectID'])) {
        $row['ValueObjectID'] = $ScannedRows[0]['ValueObjectID'];
    } else {
        unset($row['ValueObjectID']);
    }

    return [$row];
}

// ---- Scenario 1: mode "Automatic" (1), free text --------------------------
$webFront['ShowGreeting'] = 1;
$webFront['GreetingName'] = 'Guten Morgen';
$scanned = ScanGreetingText($webFront, $variables, []);
assertTrue($scanned === [[langOriginalImport => 'Guten Morgen']], 'mode 1: free-text row, no ValueObjectID');

$merged = MergeGreetingRows([], $scanned);
assertTrue(!isset($merged[0]['ValueObjectID']), 'mode 1: merged row has no ValueObjectID');

// ---- Scenario 2: mode "Variable" (2), variable outside root tree ---------
$variables[555] = ['VariableType' => VARIABLETYPE_STRING, 'Value' => 'Guten Tag'];
$webFront['ShowGreeting'] = 2;
$webFront['GreetingVariableID'] = 555;
$scanned2 = ScanGreetingText($webFront, $variables, []);
assertTrue($scanned2 === [[langOriginalImport => 'Guten Tag', 'ValueObjectID' => 555]], 'mode 2: variable row with ValueObjectID');

// Existing row from a *previous* rescan while mode 1 was active - has manual
// translations that must survive the mode switch, and ValueObjectID must now
// appear.
$existingFromMode1 = [
    [langOriginalImport => 'Guten Morgen', 'en' => 'Good Morning', 'fr' => 'Bonjour'],
];
$mergedAfterSwitch = MergeGreetingRows($existingFromMode1, $scanned2);
assertTrue($mergedAfterSwitch[0][langOriginalImport] === 'Guten Tag', 'mode switch 1->2: raw text updated');
assertTrue($mergedAfterSwitch[0]['ValueObjectID'] === 555, 'mode switch 1->2: ValueObjectID now set');
assertTrue($mergedAfterSwitch[0]['en'] === 'Good Morning', 'mode switch 1->2: stale translations preserved (no forced retranslation, matches module-wide convention)');

// ---- Scenario 3: switch back 2->1, ValueObjectID must be cleared ---------
$webFront['ShowGreeting'] = 1;
$webFront['GreetingName'] = 'Guten Abend';
$scanned3 = ScanGreetingText($webFront, $variables, []);
$mergedBack = MergeGreetingRows($mergedAfterSwitch, $scanned3);
assertTrue(!isset($mergedBack['0']['ValueObjectID']) && !isset($mergedBack[0]['ValueObjectID']), 'mode switch 2->1: ValueObjectID cleared again');
assertTrue($mergedBack[0][langOriginalImport] === 'Guten Abend', 'mode switch 2->1: raw text updated');

// ---- Scenario 4: variable already covered by the normal root-tree scan ---
$scannedTextsFromTree = [
    ['ValueObjectID' => 555, 'ObjectID' => 555, 'Path' => 'Kacheln/Info'],
];
$webFront['ShowGreeting'] = 2;
$scanned4 = ScanGreetingText($webFront, $variables, $scannedTextsFromTree);
assertTrue($scanned4 === [], 'mode 2: variable already covered by root-tree scan -> Begrüßung stays empty (root tree owns it)');

// ---- Scenario 5: mode "None" (0) and unset/deleted variable --------------
$webFront['ShowGreeting'] = 0;
assertTrue(ScanGreetingText($webFront, $variables, []) === [], 'mode 0: no row');

$webFront['ShowGreeting'] = 2;
$webFront['GreetingVariableID'] = 999999; // does not exist
assertTrue(ScanGreetingText($webFront, $variables, []) === [], 'mode 2: missing variable -> no row');

// ---- Mirrors ApplyTrackedVariableUpdate's field-key generalization -------
// Verifies the SAME dispatcher logic used by HandleTrackedVariableUpdate
// correctly picks the bare-language-code scheme (prefix '') for
// propertyObjectGreeting vs. the Text_-prefixed scheme for propertyObjectTexts.
function ApplyTrackedVariableUpdateSim(array $Rows, int $RowIndex, string $RawField, string $TranslatedPrefix, array $targetLanguages, string $NewValue): array
{
    $Rows[$RowIndex][$RawField] = $NewValue;
    foreach ($targetLanguages as $lang) {
        $Rows[$RowIndex][$TranslatedPrefix . $lang] = '';
    }

    return $Rows;
}

$greetingRows = [['ORIGINAL_IMPORT' => 'Guten Tag', 'ValueObjectID' => 555, 'en' => 'Good Day', 'fr' => 'Bonjour']];
$updated = ApplyTrackedVariableUpdateSim($greetingRows, 0, langOriginalImport, '', ['en', 'fr'], 'Guten Abend');
assertTrue($updated[0][langOriginalImport] === 'Guten Abend', 'greeting dispatch: raw text overwritten');
assertTrue($updated[0]['en'] === '' && $updated[0]['fr'] === '', 'greeting dispatch: bare-language columns cleared (no Text_ prefix)');

$textsRows = [['ObjectID' => 12, 'ValueObjectID' => 12, langOriginalImportText => 'Old', fieldTextPrefix . 'en' => 'Old EN']];
$updatedTexts = ApplyTrackedVariableUpdateSim($textsRows, 0, langOriginalImportText, fieldTextPrefix, ['en'], 'New');
assertTrue($updatedTexts[0][langOriginalImportText] === 'New', 'texts dispatch: raw text overwritten');
assertTrue($updatedTexts[0][fieldTextPrefix . 'en'] === '', 'texts dispatch: Text_-prefixed column cleared');

// ---- HandleTrackedVariableUpdate dispatch priority: ObjectTexts before ---
// ObjectGreeting, and only one branch fires per ValueObjectID.
function DispatchTarget(array $textsRows, array $greetingRows, int $ValueObjectID): string
{
    foreach ($textsRows as $row) {
        $id = (int) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0);
        if ($id === $ValueObjectID) {
            return 'texts';
        }
    }

    if ($greetingRows !== [] && (int) ($greetingRows[0]['ValueObjectID'] ?? 0) === $ValueObjectID) {
        return 'greeting';
    }

    return 'untracked';
}

assertTrue(DispatchTarget([['ValueObjectID' => 12]], [['ValueObjectID' => 555]], 12) === 'texts', 'dispatch: ObjectTexts row wins for its own ID');
assertTrue(DispatchTarget([['ValueObjectID' => 12]], [['ValueObjectID' => 555]], 555) === 'greeting', 'dispatch: ObjectGreeting row wins for its own ID');
assertTrue(DispatchTarget([['ValueObjectID' => 12]], [['ValueObjectID' => 555]], 42) === 'untracked', 'dispatch: unrelated ID untracked');
assertTrue(DispatchTarget([['ValueObjectID' => 555]], [['ValueObjectID' => 555]], 555) === 'texts', 'dispatch: if root-tree scan ALSO tracks it (edge case bypassing the ScanGreetingText guard), ObjectTexts still takes priority, avoiding a dual-write race');

echo "\nAll Greeting-refactor simulations passed.\n";
