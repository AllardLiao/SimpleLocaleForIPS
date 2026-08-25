<?php
declare(strict_types=1);
// Standalone replica tests for the per-row editable "Quellsprache" feature
// (2026-08-16): each row in ObjectNames/ObjectTexts/EnumerationOptions/
// ObjectAutomations/ObjectGreeting now carries its own source language
// (fieldRowSourceLanguage = "Quellsprache") instead of relying solely on the
// instance-wide propertySourceLanguage ("Scan-Sprache" after the rename).
// Changing a row's Quellsprache and clicking "Uebernehmen" must clear + IMMEDIATELY
// retranslate that row's translation columns against the new source, and sync the
// internal bookkeeping field (fieldTranslatedAgainstSourceLanguage) so the same
// row isn't reconciled again on the next ApplyChanges() pass.

const FIELD_ROW_SOURCE = 'Quellsprache';
const FIELD_TRANSLATED_AGAINST = 'UebersetztGegen';
const LANG_ORIGINAL_IMPORT = 'ORIGINAL_IMPORT';

function getRowSourceLanguage(array $row, string $fallback): string
{
    $lang = (string) ($row[FIELD_ROW_SOURCE] ?? '');
    return $lang !== '' ? $lang : $fallback;
}

function backfillRowSourceLanguage(array $row, string $fallback): array
{
    if (($row[FIELD_ROW_SOURCE] ?? '') === '') {
        $row[FIELD_ROW_SOURCE] = $fallback;
    }
    if (($row[FIELD_TRANSLATED_AGAINST] ?? '') === '') {
        $row[FIELD_TRANSLATED_AGAINST] = $row[FIELD_ROW_SOURCE];
    }
    return $row;
}

// Stub translator: deterministic, records every call for assertions.
$translateLog = [];
function stubTranslateBatch(array &$log, array $texts, string $source, string $target): array
{
    if ($texts === [] || $source === $target) {
        return $texts;
    }
    $log[] = "$source->$target:" . implode('|', $texts);
    return array_map(fn ($t) => "[$target]$t", $texts);
}

function reconcileRowFields(array $row, array $fieldGroups, array $targetLanguages, bool &$changed, array &$log): array
{
    $newSource = getRowSourceLanguage($row, '');
    $translatedAgainst = (string) ($row[FIELD_TRANSLATED_AGAINST] ?? '');
    if ($newSource === '' || $newSource === $translatedAgainst) {
        return $row;
    }

    foreach ($fieldGroups as $group) {
        $rawText = (string) ($row[$group['raw']] ?? '');
        foreach ($targetLanguages as $language) {
            if ($language === $newSource || $rawText === '') {
                $row[$group['prefix'] . $language] = '';
                continue;
            }
            $translated = stubTranslateBatch($log, [$rawText], $newSource, $language);
            $row[$group['prefix'] . $language] = $translated[0] ?? '';
        }
    }

    $row[FIELD_TRANSLATED_AGAINST] = $newSource;
    $changed = true;

    return $row;
}

// ---------------------------------------------------------------------------
// Test 1: BackfillRowSourceLanguage - legacy row without the field gets the
// fallback, and bookkeeping starts in sync (no spurious reconcile).
$legacyRow = ['ObjectID' => 42, LANG_ORIGINAL_IMPORT => 'Haus'];
$backfilled = backfillRowSourceLanguage($legacyRow, 'de');
assert($backfilled[FIELD_ROW_SOURCE] === 'de', 'Legacy row must be backfilled with the fallback (instance Scan-Sprache)');
assert($backfilled[FIELD_TRANSLATED_AGAINST] === 'de', 'Bookkeeping must start in sync with the backfilled Quellsprache - no reconcile on the very next pass');
echo "Test 1 (BackfillRowSourceLanguage for legacy rows) OK\n";

// ---------------------------------------------------------------------------
// Test 2: no mismatch -> ReconcileRowFields is a complete no-op, no translation
// calls at all.
$log = [];
$changed = false;
$row = [
    LANG_ORIGINAL_IMPORT => 'Haus',
    FIELD_ROW_SOURCE => 'de',
    FIELD_TRANSLATED_AGAINST => 'de',
    'en' => 'House',
];
$result = reconcileRowFields($row, [['raw' => LANG_ORIGINAL_IMPORT, 'prefix' => '']], ['en', 'fr'], $changed, $log);
assert($changed === false, 'No Quellsprache mismatch -> nothing should be reconciled');
assert($result['en'] === 'House', 'Existing translation must survive untouched when nothing changed');
assert($log === [], 'No mismatch must not trigger any translation API call');
echo "Test 2 (no mismatch = no-op, no API calls) OK\n";

// ---------------------------------------------------------------------------
// Test 3: THE core scenario - admin changes a row's Quellsprache from "de" to
// "en" in the form and clicks "Uebernehmen". All translation columns must be
// cleared+immediately retranslated against "en", and bookkeeping synced.
$log = [];
$changed = false;
$row = [
    LANG_ORIGINAL_IMPORT => 'Haus', // stale German raw text, now factually wrong since source changed to en
    FIELD_ROW_SOURCE => 'en', // admin just changed this in the form
    FIELD_TRANSLATED_AGAINST => 'de', // still reflects the OLD source -> mismatch
    'de' => '', // no column for the row's own (new) source
    'en' => 'House', // stale: this was the OLD target-language translation, now nonsensical (en->en)
    'fr' => 'Maison', // stale: computed against german "Haus", not against the new english raw text
];
$result = reconcileRowFields($row, [['raw' => LANG_ORIGINAL_IMPORT, 'prefix' => '']], ['de', 'en', 'fr'], $changed, $log);
assert($changed === true, 'A genuine Quellsprache mismatch must be reconciled');
assert($result['en'] === '', 'New source language itself never gets a translation column (ResolveRowValue returns raw for it)');
assert($result['de'] === '[de]Haus', 'Target "de" must be freshly retranslated against the NEW source (en), not left stale/empty');
assert($result['fr'] === '[fr]Haus', 'Target "fr" must be freshly retranslated against the NEW source (en) - old de->fr translation must be discarded');
assert($result[FIELD_TRANSLATED_AGAINST] === 'en', 'Bookkeeping must be synced to the new source language after reconciling');
assert(in_array('en->de:Haus', $log, true), 'Must have translated raw text from the NEW source (en) into de');
assert(in_array('en->fr:Haus', $log, true), 'Must have translated raw text from the NEW source (en) into fr');
assert(count($log) === 2, 'Exactly one translation call per non-source target language, no wasted/duplicate calls');
echo "Test 3 (Quellsprache change clears+immediately retranslates against the new source, bookkeeping synced) OK\n";

// ---------------------------------------------------------------------------
// Test 4: empty raw text must not trigger a translation API call, just clears
// the column.
$log = [];
$changed = false;
$row = [
    LANG_ORIGINAL_IMPORT => '',
    FIELD_ROW_SOURCE => 'en',
    FIELD_TRANSLATED_AGAINST => 'de',
    'fr' => 'Whatever',
];
$result = reconcileRowFields($row, [['raw' => LANG_ORIGINAL_IMPORT, 'prefix' => '']], ['de', 'en', 'fr'], $changed, $log);
assert($changed === true, 'Bookkeeping must still be synced even when raw text is empty');
assert($result['fr'] === '', 'Empty raw text must clear the stale translation instead of leaving garbage');
assert($log === [], 'Empty raw text must never trigger an API call');
echo "Test 4 (empty raw text: clear without wasting an API call) OK\n";

// ---------------------------------------------------------------------------
// Test 5: FillMissingTranslations-style grouping - rows with DIFFERENT own
// source languages within the SAME list must each translate against THEIR OWN
// source, not a single instance-wide source.
function fillMissingTranslationsGrouped(array $rows, string $rawField, string $prefix, string $instanceSource, array $targetLanguages, array &$log): array
{
    $indicesByRowSource = [];
    foreach ($rows as $index => $row) {
        $indicesByRowSource[getRowSourceLanguage($row, $instanceSource)][] = $index;
    }

    foreach ($indicesByRowSource as $rowSource => $indices) {
        foreach ($targetLanguages as $language) {
            if ($language === $rowSource) {
                continue;
            }
            foreach ($indices as $index) {
                $row = $rows[$index];
                $fromText = $row[$rawField] ?? '';
                if ($fromText === '' || ($row[$prefix . $language] ?? '') !== '') {
                    continue;
                }
                $translated = stubTranslateBatch($log, [$fromText], $rowSource, $language);
                $rows[$index][$prefix . $language] = $translated[0] ?? '';
            }
        }
    }

    return $rows;
}

$log = [];
$rows = [
    // Normal row: instance source (de) applies, needs en+fr columns.
    [LANG_ORIGINAL_IMPORT => 'Haus', FIELD_ROW_SOURCE => 'de'],
    // Foreign-module row: own source is "en", needs de+fr columns, NOT en.
    [LANG_ORIGINAL_IMPORT => 'House', FIELD_ROW_SOURCE => 'en'],
];
$filled = fillMissingTranslationsGrouped($rows, LANG_ORIGINAL_IMPORT, '', 'de', ['en', 'fr'], $log);
assert(($filled[0]['en'] ?? '') === '[en]Haus', 'Normal row (source=de) must be translated into en against de');
assert(($filled[0]['fr'] ?? '') === '[fr]Haus', 'Normal row (source=de) must be translated into fr against de');
assert(!isset($filled[1]['en']) || $filled[1]['en'] === '', 'Foreign row (source=en) must NEVER get an en column translated from itself');
assert(($filled[1]['fr'] ?? '') === '[fr]House', 'Foreign row (source=en) must be translated into fr against its OWN source (en), not de');
assert(in_array('de->en:Haus', $log, true), 'Normal row group uses de as source');
assert(in_array('de->fr:Haus', $log, true), 'Normal row group uses de as source');
assert(in_array('en->fr:House', $log, true), 'Foreign row group uses its own source (en), not the instance default (de)');
assert(!in_array('en->en:House', $log, true), 'Must never attempt to translate a row into its own source language');
echo "Test 5 (FillMissingTranslations groups rows by their OWN source language) OK\n";

// ---------------------------------------------------------------------------
// Test 6: MergeGreetingRows-style refresh preserves the row's own Quellsprache
// across a raw-text refresh (does NOT silently reset it back to the instance
// default), while still syncing bookkeeping so no spurious reconcile fires.
function mergeGreetingRowsWithSourceLanguage(array $existingRows, array $scannedRows, bool $isSourceLanguageActive): array
{
    if ($scannedRows === []) {
        return $existingRows;
    }
    if ($existingRows === []) {
        return $scannedRows;
    }

    $row = $existingRows[0];
    $newRawText = $scannedRows[0][LANG_ORIGINAL_IMPORT];

    if ($isSourceLanguageActive && $row[LANG_ORIGINAL_IMPORT] !== $newRawText) {
        foreach (array_keys($row) as $field) {
            if (!in_array($field, [LANG_ORIGINAL_IMPORT, 'ValueObjectID', FIELD_ROW_SOURCE], true)) {
                $row[$field] = '';
            }
        }
        $row[LANG_ORIGINAL_IMPORT] = $newRawText;
        $row[FIELD_TRANSLATED_AGAINST] = $row[FIELD_ROW_SOURCE];
    }

    return [$row];
}

// A greeting row whose Quellsprache was manually set to "en" (foreign-fed
// variable) - a routine raw-text refresh (source-active Rescan) must NOT reset
// it back to "de".
$existing = [[
    LANG_ORIGINAL_IMPORT => 'Good morning',
    FIELD_ROW_SOURCE => 'en',
    FIELD_TRANSLATED_AGAINST => 'en',
    'de' => 'Guten Morgen',
]];
$scanned = [[LANG_ORIGINAL_IMPORT => 'Good evening']]; // raw text changed
$merged = mergeGreetingRowsWithSourceLanguage($existing, $scanned, true);
assert($merged[0][FIELD_ROW_SOURCE] === 'en', 'A raw-text refresh must NOT reset a manually-set Quellsprache back to the instance default');
assert($merged[0]['de'] === '', 'Stale translation must still be cleared on raw-text refresh, regardless of Quellsprache');
assert($merged[0][FIELD_TRANSLATED_AGAINST] === 'en', 'Bookkeeping must stay synced to the (unchanged) Quellsprache after the refresh');
echo "Test 6 (MergeGreetingRows preserves a manually-set Quellsprache across raw-text refresh) OK\n";

echo "\nAll tests passed.\n";
