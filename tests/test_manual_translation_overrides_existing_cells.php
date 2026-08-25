<?php
declare(strict_types=1);
// Standalone replica test for build 93 (2026-08-20):
// User live-found: a manual glossary entry ("SSW" (de) -> "SSW" (en), correcting
// Google mistranslating the wind-direction abbreviation "SSW" [Süd-Südwest] as
// "week of pregnancy" [a German medical abbreviation coincidence]) had NO EFFECT on
// the live tile, even though the glossary table clearly had the matching entry.
// Root cause: the glossary lookup only ran inside TranslateBatch(), which is only
// reached for cells that are still EMPTY/pending - a cell that was already
// (wrongly) auto-translated BEFORE the glossary entry was added is never revisited,
// since the established rule everywhere else in the module is "never overwrite an
// already-filled cell" (protects genuine manual corrections). But the user's
// original request was explicit: manual entries "always take priority over online
// translations" - which can only be true if the glossary is also allowed to
// override an existing (even filled) cell. Fix: a new pre-pass
// (ApplyManualTranslationOverrides) now runs before the normal fill logic on every
// Rescan, checking EVERY cell (filled or not) against the glossary and overwriting
// it whenever a match exists with a different value.

function findManualTranslationReplica(array $rows, string $sourceLanguage, string $targetLanguage, string $text): ?string
{
    foreach ($rows as $row) {
        if (($row['Quellsprache'] ?? '') !== $sourceLanguage || ($row['ORIGINAL_IMPORT'] ?? '') !== $text) {
            continue;
        }
        $translation = (string) ($row[$targetLanguage] ?? '');
        if ($translation !== '') {
            return $translation;
        }
    }

    return null;
}

function applyManualTranslationOverridesReplica(array $rows, array $fieldGroups, string $sourceLanguage, array $targetLanguages, array $manualTranslations, bool $hasFeature): array
{
    if (!$hasFeature || $manualTranslations === []) {
        return $rows;
    }

    foreach ($rows as $index => $row) {
        $rowSourceLanguage = $row['Quellsprache'] ?? $sourceLanguage;
        foreach ($fieldGroups as $group) {
            $sourceText = (string) ($row[$group['raw']] ?? '');
            if ($sourceText === '') {
                continue;
            }
            foreach ($targetLanguages as $language) {
                $manual = findManualTranslationReplica($manualTranslations, $rowSourceLanguage, $language, $sourceText);
                if ($manual === null || ($row[$group['prefix'] . $language] ?? '') === $manual) {
                    continue;
                }
                $row[$group['prefix'] . $language] = $manual;
            }
        }
        $rows[$index] = $row;
    }

    return $rows;
}

$fieldGroups = [['raw' => 'ORIGINAL_IMPORT', 'prefix' => '']];
$manualTranslations = [['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'SSW', 'en' => 'SSW', 'es' => 'SSO']];

// Test 1: THE REPORTED BUG - a row already (wrongly) auto-translated BEFORE the
// glossary entry existed must now get overridden with the glossary value.
$rowsAlreadyWrong = [['ORIGINAL_IMPORT' => 'SSW', 'Quellsprache' => 'de', 'en' => 'week of pregnancy']];
$result1 = applyManualTranslationOverridesReplica($rowsAlreadyWrong, $fieldGroups, 'de', ['en'], $manualTranslations, true);
assert($result1[0]['en'] === 'SSW', 'THE FIX: an already-filled cell with a wrong automatic translation must be overwritten by a matching glossary entry - the whole point of "always takes priority"');
echo "Test 1 (an already-wrongly-translated cell is corrected by a matching glossary entry) OK\n";

// Test 2: a cell that already happens to match the glossary value is left alone
// (no pointless rewrite, though the observable outcome is identical either way).
$rowsAlreadyCorrect = [['ORIGINAL_IMPORT' => 'SSW', 'Quellsprache' => 'de', 'en' => 'SSW']];
$result2 = applyManualTranslationOverridesReplica($rowsAlreadyCorrect, $fieldGroups, 'de', ['en'], $manualTranslations, true);
assert($result2[0]['en'] === 'SSW', 'A cell already matching the glossary value stays correct (no-op, but still correct)');
echo "Test 2 (a cell already matching the glossary value remains correct) OK\n";

// Test 3: a row with NO matching glossary entry keeps its existing (right or
// wrong) content completely untouched - the override is targeted, not a blanket
// re-translation of everything.
$rowsUnrelated = [['ORIGINAL_IMPORT' => 'Cover', 'Quellsprache' => 'de', 'en' => 'Cover']];
$result3 = applyManualTranslationOverridesReplica($rowsUnrelated, $fieldGroups, 'de', ['en'], $manualTranslations, true);
assert($result3[0]['en'] === 'Cover', 'A row with no matching glossary entry must be completely untouched by this pass');
echo "Test 3 (an unrelated row is untouched - the override only targets matching rows) OK\n";

// Test 4: without the manual_translations license feature, no override happens at
// all, even with an already-wrong cell and a matching glossary entry present.
$result4 = applyManualTranslationOverridesReplica($rowsAlreadyWrong, $fieldGroups, 'de', ['en'], $manualTranslations, false);
assert($result4[0]['en'] === 'week of pregnancy', 'Without the manual_translations license feature, no override may happen, even with a matching glossary row present');
echo "Test 4 (no override happens without the manual_translations license feature) OK\n";

// Test 5: multiple field groups per row (e.g. ObjectTexts' Name + Text groups) are
// each checked independently against the glossary.
$fieldGroupsMulti = [['raw' => 'ORIGINAL_IMPORT', 'prefix' => 'Name_'], ['raw' => 'ORIGINAL_IMPORT_Text', 'prefix' => 'Text_']];
$manualMulti = [
    ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'SSW', 'en' => 'SSW'],
];
$rowsMulti = [['ORIGINAL_IMPORT' => 'SSW', 'ORIGINAL_IMPORT_Text' => 'Wetterlage', 'Quellsprache' => 'de', 'Name_en' => 'week of pregnancy', 'Text_en' => 'weather situation']];
$result5 = applyManualTranslationOverridesReplica($rowsMulti, $fieldGroupsMulti, 'de', ['en'], $manualMulti, true);
assert($result5[0]['Name_en'] === 'SSW', 'The Name field group must be corrected by its matching glossary entry');
assert($result5[0]['Text_en'] === 'weather situation', 'The Text field group has no matching glossary entry (different source text) and must remain untouched');
echo "Test 5 (multiple field groups per row are checked independently against the glossary) OK\n";

echo "\nAll tests passed.\n";
