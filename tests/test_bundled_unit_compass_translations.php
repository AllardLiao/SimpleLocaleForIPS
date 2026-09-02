<?php
declare(strict_types=1);
// Standalone replica test for build 133 (2026-08-23):
// User request: avoid needless/risky translation-API calls for "number + unit"
// combinations common in home automation (e.g. "0.82 m/s") by pre-seeding the
// EXISTING, admin-editable "Eigene Übersetzungstabelle" (ManualTranslations) with
// (a) a universal pass-through list of SI/common units (identical in every
// language) and (b) a REAL per-language 16-point compass table, since compass
// abbreviations are the opposite of universal (German "O" = East, Spanish "O" =
// Oeste/WEST - same letter, opposite meaning). Rows are seeded ONCE and tracked so
// a deliberate admin deletion (e.g. "SSW" collides with a person's initials in
// their installation) never reappears on a later Rescan.

$unitBundle = ['°C', 'kg', 'km/h'];
$unitOverrides = [
    'km/h' => ['es' => 'kph', 'nl' => 'km/u', 'tr' => 'km/sa', 'ru' => 'км/ч'],
    'kg'   => ['ru' => 'кг'],
];
$compassBundle = [
    'O'   => ['en' => 'E',  'es' => 'E',  'nl' => 'O'],
    'W'   => ['en' => 'W',  'es' => 'O',  'nl' => 'W'],
    'SSW' => ['en' => 'SSW', 'es' => 'SSO', 'nl' => 'ZZW'],
];
$bundledLanguages = ['en', 'es', 'nl', 'tr', 'ru'];

function mergeBundledManualTranslationsReplica(
    array $existingRows,
    array $alreadySeeded,
    bool $hasManualTranslationsFeature,
    array $unitBundle,
    array $compassBundle,
    array $bundledLanguages,
    array $unitOverrides = []
): array {
    if (!$hasManualTranslationsFeature) {
        return ['rows' => $existingRows, 'seeded' => $alreadySeeded];
    }

    $existingKeys = [];
    foreach ($existingRows as $row) {
        if (($row['Source language'] ?? '') === 'de') {
            $existingKeys[(string) ($row['ORIGINAL_IMPORT'] ?? '')] = true;
        }
    }

    $result = $existingRows;

    foreach ($unitBundle as $unit) {
        if (isset($existingKeys[$unit]) || isset($alreadySeeded[$unit])) {
            continue;
        }
        $row = ['Source language' => 'de', 'ORIGINAL_IMPORT' => $unit];
        foreach ($bundledLanguages as $language) {
            $row[$language] = $unitOverrides[$unit][$language] ?? $unit;
        }
        $result[] = $row;
        $alreadySeeded[$unit] = true;
    }

    foreach ($compassBundle as $germanCompass => $translationsByLanguage) {
        if (isset($existingKeys[$germanCompass]) || isset($alreadySeeded[$germanCompass])) {
            continue;
        }
        $row = ['Source language' => 'de', 'ORIGINAL_IMPORT' => $germanCompass];
        foreach ($translationsByLanguage as $language => $translation) {
            $row[$language] = $translation;
        }
        $result[] = $row;
        $alreadySeeded[$germanCompass] = true;
    }

    return ['rows' => $result, 'seeded' => $alreadySeeded];
}

// Test 1: THE FEATURE - a fresh install (Standard+ license, no existing rows) gets
// every bundled unit AND compass row, with zero provider calls.
$fresh = mergeBundledManualTranslationsReplica([], [], true, $unitBundle, $compassBundle, $bundledLanguages, $unitOverrides);
$byKey = [];
foreach ($fresh['rows'] as $row) {
    $byKey[$row['ORIGINAL_IMPORT']] = $row;
}
assert($byKey['°C']['en'] === '°C' && $byKey['°C']['es'] === '°C', 'a fresh install must have universal units pre-filled identically across every bundled language, with zero provider calls');
echo "Test 1 (fresh install: universal units pre-filled identically for every bundled language) OK\n";

// Test 1b (Build 134, user-confirmed correction): "hour" is NOT abbreviated with
// the Latin SI symbol "h" in every language - Spanish colloquially uses "kph" for
// km/h, Dutch writes "km/u" (uur = hour), Turkish "km/sa" (saat = hour), and
// Russian uses the fully Cyrillic "км/ч". These must be per-language OVERRIDES on
// top of the universal default, not a naive pass-through of the German "km/h".
assert($byKey['km/h']['es'] === 'kph', 'THE BUG: Spanish must abbreviate km/h as "kph", not pass through the German "km/h" unchanged');
assert($byKey['km/h']['nl'] === 'km/u', 'THE BUG: Dutch must abbreviate km/h as "km/u" (uur = hour), not "km/h"');
assert($byKey['km/h']['tr'] === 'km/sa', 'THE BUG: Turkish must abbreviate km/h as "km/sa" (saat = hour), not "km/h"');
assert($byKey['km/h']['ru'] === 'км/ч', 'THE BUG: Russian must use the fully Cyrillic "км/ч", not the Latin "km/h"');
assert($byKey['km/h']['en'] === 'km/h', 'English is NOT overridden - "km/h" remains the standard/official abbreviation there, unlike the Spanish colloquial "kph"');
echo "Test 1b (km/h gets genuine per-language overrides for es/nl/tr/ru instead of a naive pass-through of the German abbreviation) OK\n";

// Test 1c: a unit WITHOUT any override (e.g. "kg" for es/nl/tr, or any unit at all
// for a language with no override table) still falls back to the universal
// pass-through - overrides must be additive exceptions, not a replacement mechanism.
assert($byKey['kg']['es'] === 'kg' && $byKey['kg']['nl'] === 'kg' && $byKey['kg']['tr'] === 'kg', 'a unit without a language-specific override must still fall back to the universal pass-through value');
assert($byKey['kg']['ru'] === 'кг', 'THE BUG: Russian localizes almost every unit (not just km/h) - "kg" must become the Cyrillic "кг", a plain Latin pass-through would be wrong for a Russian-speaking user');
echo "Test 1c (units without a specific override still fall back to the universal pass-through; Russian correctly localizes far beyond just km/h) OK\n";

// Test 2: CRITICAL - compass directions must NOT be a naive 1:1 pass-through.
// German "O" (Ost/East) must become English "E", but Spanish "O" (Oeste/West) is a
// DIFFERENT letter with the OPPOSITE meaning - and German "W" (West) must become
// Spanish "O" (Oeste), the exact swap that makes naive pass-through wrong.
assert($byKey['O']['en'] === 'E', 'German "O" (Ost/East) must be translated to English "E", not passed through as "O"');
assert($byKey['W']['es'] === 'O', 'THE BUG naive pass-through would cause: German "W" (West) must become Spanish "O" (Oeste) - the same letter "O" that means EAST in German means WEST in Spanish');
echo "Test 2 (compass directions are genuinely per-language translated, not naively passed through - catches the German/Spanish 'O' meaning-reversal bug) OK\n";

// Test 3: THE OTHER CRITICAL FIX - a bundled row the admin has deliberately deleted
// (tracked via the seeded-keys attribute) must NOT reappear on the next Rescan, even
// though it is once again "missing" from the existing rows.
$afterDeletion = mergeBundledManualTranslationsReplica([], ['SSW' => true], true, $unitBundle, $compassBundle, $bundledLanguages, $unitOverrides);
$keysAfterDeletion = array_column($afterDeletion['rows'], 'ORIGINAL_IMPORT');
assert(!in_array('SSW', $keysAfterDeletion, true), 'THE BUG: a bundled suggestion the admin deliberately deleted (e.g. "SSW" collides with a person\'s initials in their installation) must stay deleted, not resurrect on the next Rescan');
assert(in_array('°C', $keysAfterDeletion, true), 'unrelated, never-seeded bundled entries must still be added normally');
echo "Test 3 (a deliberately deleted bundled row never reappears on a later Rescan, tracked via the seeded-keys attribute) OK\n";

// Test 4: an existing row already covering the same German source text (whether
// user-added or from a prior seed) is never duplicated.
$existingRow = [['Source language' => 'de', 'ORIGINAL_IMPORT' => 'kg', 'en' => 'kg-custom-value']];
$noDupe = mergeBundledManualTranslationsReplica($existingRow, [], true, $unitBundle, $compassBundle, $bundledLanguages, $unitOverrides);
$kgRows = array_filter($noDupe['rows'], fn ($r) => $r['ORIGINAL_IMPORT'] === 'kg');
assert(count($kgRows) === 1, 'an existing row for the same German source text must never be duplicated');
assert(array_values($kgRows)[0]['en'] === 'kg-custom-value', 'an existing row must be left completely untouched, even if it happens to match a bundled key');
echo "Test 4 (an existing row for the same source text is never duplicated or overwritten) OK\n";

// Test 5: Light edition (no manual_translations feature) gets nothing added - it
// keeps calling the live API for these cases, as decided.
$lightEdition = mergeBundledManualTranslationsReplica([], [], false, $unitBundle, $compassBundle, $bundledLanguages, $unitOverrides);
assert($lightEdition['rows'] === [], 'Light edition (without the manual_translations license feature) must not receive any bundled rows - it continues to call the live translation API for these cases');
echo "Test 5 (Light edition without manual_translations gets no bundled rows, continues using the live API) OK\n";

// Test 6: Symmetry check - the real module.php must actually define and wire in
// this feature as designed (constants, seeded-tracking attribute, ScanRootTree wiring).
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
assert(strpos($moduleSource, 'private const UNIT_BUNDLED_TRANSLATIONS') !== false, 'the universal units list must exist as a class constant');
assert(strpos($moduleSource, 'private const COMPASS_BUNDLED_TRANSLATIONS') !== false, 'the per-language compass table must exist as a class constant');
assert(strpos($moduleSource, 'private function MergeBundledGlossaryRows(') !== false, 'the merge function must exist');
assert(strpos($moduleSource, '$this->MergeBundledGlossaryRows($this->DecodeRows(self::propertyGlossary))') !== false, 'MergeBundledManualTranslations() must actually be wired into ScanRootTree() against propertyManualTranslations');
assert(strpos($moduleSource, 'IPS_SetProperty($this->InstanceID, self::propertyGlossary, json_encode(array_values($glossary)));') !== false, 'the merged manual translations must actually be persisted back via IPS_SetProperty');
assert(strpos($constantsSource, "attributeSeededGlossaryKeys = 'SeededGlossaryKeys'") !== false, 'the seeded-keys tracking attribute must be declared');
assert(strpos($moduleSource, 'RegisterAttributeString(self::attributeSeededGlossaryKeys,') !== false, 'the seeded-keys tracking attribute must actually be registered in Create()');
assert(strpos($moduleSource, "!\$this->HasLicenseFeature('manual_translations')") !== false, 'the merge function must gate on the same manual_translations license feature as the rest of the glossary (Light edition keeps using the live API)');
echo "Test 6 (the real module.php actually defines and wires in the feature: constants, tracking attribute, ScanRootTree persistence, license gating) OK\n";

// Test 7 (Build 134): Symmetry check - the real module.php must actually define
// and apply the confirmed per-language unit overrides (km/h exceptions, Russian
// unit localization), not just the replica logic in this test.
assert(strpos($moduleSource, 'private const UNIT_BUNDLED_LANGUAGE_OVERRIDES') !== false, 'the per-language unit override table must exist as a class constant');
assert(strpos($moduleSource, "self::UNIT_BUNDLED_LANGUAGE_OVERRIDES[\$unit][\$language] ?? \$unit") !== false, 'the unit-seeding loop must actually consult the override table before falling back to the universal pass-through value');
assert(strpos($moduleSource, "'km/h' => ['es' => 'kph', 'nl' => 'km/u', 'tr' => 'km/sa', 'ru' => 'км/ч']") !== false, 'km/h must carry the confirmed es/nl/tr/ru overrides');
foreach (['V' => 'В', 'W' => 'Вт', 'kg' => 'кг', 'km' => 'км', 'Hz' => 'Гц', 'kWh' => 'кВт·ч'] as $unit => $expectedRussian) {
    $pattern = '/\'' . preg_quote($unit, '/') . '\'\s*=>\s*\[\'ru\'\s*=>\s*\'' . preg_quote($expectedRussian, '/') . '\'\]/u';
    assert(preg_match($pattern, $moduleSource) === 1, "the Russian override for \"$unit\" must actually be \"$expectedRussian\" in module.php, not just in this test's replica");
}
echo "Test 7 (the real module.php actually defines and applies the confirmed km/h and Russian unit overrides) OK\n";

echo "\nAll tests passed.\n";
