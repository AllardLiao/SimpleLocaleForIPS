<?php
declare(strict_types=1);
// Standalone replica test for build 135/136 (2026-08-23):
// User request (135): let the admin flag individual rows in "Objektnamen"/
// "Automations"/"Begrüßung" as "never translate" via a checkbox - for entries that
// should always show their raw source text regardless of the currently active guest
// language (e.g. proper nouns, brand names, technical abbreviations that shouldn't
// be machine-translated). Pre-filled with "Übersetzung aktiv" = true (translation
// active), so the admin opts specific rows OUT rather than opting the whole table in.
// Follow-up correction (136): the user actually meant EVERY row-based translation
// table, not just those three - explicitly including "Eigene Texte" (e.g. to
// exclude a single string variable holding JSON control data for another module,
// "as if its translation cells had been deleted"), plus "Aufzählungen" and
// "Charts". Deliberately excluded: "Eigene Übersetzungstabelle" (ManualTranslations)
// - structurally nothing is ever auto-translated there, so the flag would be
// meaningless.
// Follow-up (137): JSON raw content (see LooksLikeJson, Build 84) is UNCONDITIONALLY
// exempt from translation already, regardless of the checkbox - so for such a row
// the checkbox never actually does anything. The console should not misleadingly
// show "active" for a row that factually never gets translated - a rescan now
// automatically flips the checkbox to inactive for JSON rows, one-directionally
// only (never flips it back to active automatically, so a row the admin
// deactivated for an unrelated reason stays deactivated even if its content later
// happens to look like JSON and then stops).

function looksLikeJsonReplica(string $text): bool
{
    $trimmed = trim($text);
    if ($trimmed === '' || !in_array($trimmed[0], ['{', '['], true)) {
        return false;
    }
    json_decode($trimmed);

    return json_last_error() === JSON_ERROR_NONE;
}

function autoDeactivateTranslationForJsonContentReplica(array $row, string $rawField): array
{
    if (looksLikeJsonReplica((string) ($row[$rawField] ?? ''))) {
        $row['TranslationActive'] = false;
    }

    return $row;
}

function resolveRowValueReplica(array $row, string $selectedLanguage, string $languageField, string $sourceLanguage, string $rawField): string
{
    if ($selectedLanguage === 'ORIGINAL_IMPORT' || $selectedLanguage === $sourceLanguage) {
        return $row[$rawField] ?? '';
    }
    if (($row[$languageField] ?? '') !== '') {
        return $row[$languageField];
    }

    return $row[$rawField] ?? '';
}

function getEffectiveSelectedLanguageReplica(array $row, string $language): string
{
    return ($row['TranslationActive'] ?? true) ? $language : 'ORIGINAL_IMPORT';
}

function backfillTranslationActiveFlagReplica(array $row): array
{
    if (!array_key_exists('TranslationActive', $row)) {
        $row['TranslationActive'] = true;
    }

    return $row;
}

// Test 1: THE FEATURE - a row with TranslationActive=false must always resolve to
// its raw source text, no matter which guest language is currently active.
$deactivatedRow = ['Source language' => 'de', 'ORIGINAL_IMPORT' => 'Sebastian Simon Walter', 'en' => 'Sebastian Simon Walter (should never be shown)', 'TranslationActive' => false];
$effectiveEn = getEffectiveSelectedLanguageReplica($deactivatedRow, 'en');
$resolved = resolveRowValueReplica($deactivatedRow, $effectiveEn, 'en', 'de', 'ORIGINAL_IMPORT');
assert($resolved === 'Sebastian Simon Walter', 'THE BUG: a row with "Übersetzung aktiv" unchecked must always show its raw source text, even when a target-language cell happens to be filled');
echo "Test 1 (a deactivated row always resolves to its raw source text, regardless of the active guest language) OK\n";

// Test 2: an ACTIVE row (the default) resolves completely normally - the new
// mechanism must not interfere with ordinary translation.
$activeRow = ['Source language' => 'de', 'ORIGINAL_IMPORT' => 'Wohnzimmer', 'en' => 'Living room', 'TranslationActive' => true];
$effectiveEnActive = getEffectiveSelectedLanguageReplica($activeRow, 'en');
assert(resolveRowValueReplica($activeRow, $effectiveEnActive, 'en', 'de', 'ORIGINAL_IMPORT') === 'Living room', 'an active row must continue to resolve to its normal translated cell');
echo "Test 2 (an active row translates completely normally) OK\n";

// Test 3: CRITICAL - a row from before Build 135 (field entirely absent) must
// default to "active" (true), both in the resolution logic AND in the backfill
// helper - a missing checkbox default must never silently disable translation for
// every pre-existing installation.
$legacyRow = ['Source language' => 'de', 'ORIGINAL_IMPORT' => 'Küche', 'en' => 'Kitchen'];
assert(getEffectiveSelectedLanguageReplica($legacyRow, 'en') === 'en', 'THE BUG: a pre-existing row without the TranslationActive field must default to active, not silently stop translating');
$backfilled = backfillTranslationActiveFlagReplica($legacyRow);
assert($backfilled['TranslationActive'] === true, 'the backfill helper must explicitly write true into a legacy row missing the field, so the console checkbox visually shows checked instead of misleadingly unchecked');
echo "Test 3 (a legacy row without the field defaults to active, and gets explicitly backfilled with true) OK\n";

// Test 4: the backfill helper must NEVER overwrite a row where the admin has
// deliberately unchecked the box (field present and false) - array_key_exists,
// not an emptiness/falsiness check.
$deliberatelyOff = ['Source language' => 'de', 'ORIGINAL_IMPORT' => 'SSW', 'TranslationActive' => false];
$afterBackfill = backfillTranslationActiveFlagReplica($deliberatelyOff);
assert($afterBackfill['TranslationActive'] === false, 'THE BUG: the backfill helper must never flip an admin-deactivated row back to active - it may only fill in a genuinely MISSING field');
echo "Test 4 (the backfill helper never overwrites a deliberately deactivated row) OK\n";

// Test 6 (Build 136): a deactivated "Eigene Texte" row must behave AS IF its
// translation cells had been deleted - exactly the semantics the user asked for -
// resolving to the raw variable content regardless of the active guest language.
$deactivatedTextRow = ['Source language' => 'de', 'ORIGINAL_IMPORT_Text' => '{"musicProvider":"CLOUDPLAYER"}', 'Text_en' => 'this must never be shown', 'TranslationActive' => false];
$effectiveForText = getEffectiveSelectedLanguageReplica($deactivatedTextRow, 'en');
assert(resolveRowValueReplica($deactivatedTextRow, $effectiveForText, 'Text_en', 'de', 'ORIGINAL_IMPORT_Text') === '{"musicProvider":"CLOUDPLAYER"}', 'THE FEATURE (136): a deactivated "Eigene Texte" row (e.g. one holding JSON control data for another module) must always resolve to its raw content, exactly as if the translation cells were deleted');
echo "Test 6 (a deactivated 'Eigene Texte' row resolves to its raw content, matching the user's 'as if the cells were deleted' requirement) OK\n";

// Test 7 (Build 136): CRITICAL for enumeration options/charts - a single shared
// variable presentation is built from MULTIPLE rows (one per field/dataset). A
// deactivated field must resolve to raw text while a DIFFERENT, still-active field
// on the SAME variable keeps translating normally - the flag is per-row/per-field,
// not an all-or-nothing switch for the whole variable.
$sharedRows = [
    'Caption' => ['Source language' => 'de', 'ORIGINAL_IMPORT' => 'Anwesend', 'en' => 'Present', 'TranslationActive' => true],
    'Suffix'  => ['Source language' => 'de', 'ORIGINAL_IMPORT' => 'ProduktCode-XY', 'en' => 'should-never-be-shown', 'TranslationActive' => false],
];
$replacements = [];
foreach ($sharedRows as $fieldPath => $row) {
    $effective = getEffectiveSelectedLanguageReplica($row, 'en');
    $resolved = resolveRowValueReplica($row, $effective, 'en', 'de', 'ORIGINAL_IMPORT');
    if ($resolved !== '') {
        $replacements[$fieldPath] = $resolved;
    }
}
assert($replacements['Caption'] === 'Present', 'an active field on a shared variable presentation must keep translating normally');
assert($replacements['Suffix'] === 'ProduktCode-XY', 'THE BUG: a deactivated field must resolve to its raw text even when a sibling field on the SAME variable/presentation is still actively translated - the flag must be per-row, not per-variable');
echo "Test 7 (within one shared variable presentation, a deactivated field stays raw while an active sibling field keeps translating - per-row granularity confirmed) OK\n";

// Test 8 (Build 136): CRITICAL for the live VM_UPDATE path - a deactivated row must
// never be sent to the translation API just because an external module wrote a
// fresh raw value onto the tracked variable. Replicates the guard condition from
// ApplyTrackedVariableUpdate() ($rowSourceLanguage !== $currentLanguage &&
// $currentLanguage !== ORIGINAL_IMPORT && translationActive).
function shouldTranslateOnLiveUpdateReplica(string $rowSourceLanguage, string $currentLanguage, bool $translationActive): bool
{
    return $rowSourceLanguage !== $currentLanguage && $currentLanguage !== 'ORIGINAL_IMPORT' && $translationActive;
}
assert(shouldTranslateOnLiveUpdateReplica('de', 'en', true) === true, 'an active row with a live external update must still translate immediately for the currently active guest language');
assert(shouldTranslateOnLiveUpdateReplica('de', 'en', false) === false, 'THE BUG: a deactivated row must NOT be translated on a live external update either - it would otherwise burn API quota for a row that will never actually show the translated result');
echo "Test 8 (a deactivated row is never sent to the translation API on a live VM_UPDATE, matching its already-established never-translate semantics) OK\n";

// Test 9 (Build 137): THE FEATURE - a row whose raw text is valid JSON (and whose
// checkbox is still at its default "true") gets automatically flipped to
// "inactive" on the next rescan, since the checkbox never had any actual effect
// for such a row in the first place (LooksLikeJson already unconditionally
// exempts it in FillLanguageColumn).
$freshJsonRow = ['ORIGINAL_IMPORT_Text' => '{"musicProvider":"CLOUDPLAYER","searchPhrase":"jazz"}', 'TranslationActive' => true];
$autoDeactivated = autoDeactivateTranslationForJsonContentReplica($freshJsonRow, 'ORIGINAL_IMPORT_Text');
assert($autoDeactivated['TranslationActive'] === false, 'THE BUG: a row whose raw content is valid JSON must be automatically flipped to "Übersetzung aktiv" = inactive, since the checkbox is factually already ignored for JSON content');
echo "Test 9 (a JSON-content row is automatically flipped to inactive, since the checkbox already has no real effect there) OK\n";

// Test 10: a non-JSON row is left completely untouched by the auto-deactivation -
// it must not interfere with ordinary rows at all, regardless of the flag's
// current value.
$plainActiveRow = ['ORIGINAL_IMPORT_Text' => 'Guten Morgen!', 'TranslationActive' => true];
assert(autoDeactivateTranslationForJsonContentReplica($plainActiveRow, 'ORIGINAL_IMPORT_Text')['TranslationActive'] === true, 'a non-JSON row must never be touched by the JSON auto-deactivation, regardless of its current flag value');
echo "Test 10 (a non-JSON row is left completely untouched) OK\n";

// Test 11: CRITICAL - the auto-deactivation is ONE-DIRECTIONAL only. A row the
// admin deactivated for a completely unrelated reason (e.g. a proper noun) must
// stay deactivated even though its content is plain, non-JSON text - the function
// must never flip a false back to true.
$deliberatelyOffPlainRow = ['ORIGINAL_IMPORT' => 'Sebastian Simon Walter', 'TranslationActive' => false];
assert(autoDeactivateTranslationForJsonContentReplica($deliberatelyOffPlainRow, 'ORIGINAL_IMPORT')['TranslationActive'] === false, 'THE BUG: the auto-deactivation must never re-enable a row the admin deliberately turned off for an unrelated reason - it may only ever turn a checkbox OFF for JSON content, never back ON');
echo "Test 11 (the auto-deactivation never re-enables an already-deactivated row, confirming it is strictly one-directional) OK\n";

// Test 12 (Build 138): THE FEATURE - the "Übersetzung aktiv" checkbox column
// must be completely ABSENT (not just read-only, unlike BuildRowSourceLanguageColumn/
// BuildLanguageColumnSet) when the installation lacks the Pro feature
// "edit_translations" - the user explicitly wants it hidden entirely, not merely
// disabled, so a Standard/Light admin never even sees the option.
function buildTranslationActiveColumnReplica(bool $hasEditTranslationsFeature): ?array
{
    if (!$hasEditTranslationsFeature) {
        return null;
    }

    return ['caption' => 'Translation active', 'name' => 'TranslationActive', 'edit' => ['type' => 'CheckBox']];
}
function appendTranslationActiveColumnReplica(array $columns, bool $hasEditTranslationsFeature): array
{
    $column = buildTranslationActiveColumnReplica($hasEditTranslationsFeature);
    if ($column !== null) {
        $columns[] = $column;
    }

    return $columns;
}
assert(appendTranslationActiveColumnReplica(['ObjectID'], false) === ['ObjectID'], 'THE BUG: without the Pro feature "edit_translations", the checkbox column must be omitted entirely from the column list, not merely present-but-readonly');
assert(count(appendTranslationActiveColumnReplica(['ObjectID'], true)) === 2, 'with the Pro feature, the checkbox column must be appended normally');
echo "Test 12 (the checkbox column is completely hidden without the Pro feature 'edit_translations', not just read-only) OK\n";

// Test 13 (Build 138): the runtime resolution/backfill/auto-deactivation logic
// must NOT be license-gated - only the form column itself. This matters for a
// downgrade scenario: a row a Pro admin deliberately deactivated must keep
// behaving as deactivated even after downgrading to Standard/Light, and the
// pre-existing (Build 84) automatic JSON exemption must keep working on every
// edition regardless of the Pro gate introduced here.
assert(getEffectiveSelectedLanguageReplica(['TranslationActive' => false], 'en') === 'ORIGINAL_IMPORT', 'a deactivated row must keep resolving to raw text even conceptually "after a downgrade" - GetEffectiveSelectedLanguage() itself carries no license check');
$moduleSourceForGate = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$getEffFnStart = strpos($moduleSourceForGate, 'private function GetEffectiveSelectedLanguage(');
$getEffFnBody = substr($moduleSourceForGate, $getEffFnStart, 300);
assert(strpos($getEffFnBody, 'HasLicenseFeature') === false, 'THE BUG: GetEffectiveSelectedLanguage() must NOT itself check any license feature - only the form column (BuildTranslationActiveColumn) is Pro-gated, the runtime behavior stays edition-independent');
echo "Test 13 (the runtime never-translate behavior stays edition-independent - only the checkbox's visibility in the form is Pro-gated) OK\n";

// Test 14: Symmetry check - the real module.php must actually wire this in across
// ALL SIX relevant row-based tables (Objektnamen, Eigene Texte, Aufzählungen,
// Charts, Automations, Begrüßung), running AFTER the existing backfill, and using
// the correct raw-text field per table (ORIGINAL_IMPORT_Text only for "Eigene
// Texte", ORIGINAL_IMPORT everywhere else).
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
assert(strpos($constantsSource, "fieldTranslationActive = 'TranslationActive'") !== false, 'the field constant must be declared');
assert(strpos($moduleSource, 'private function BuildTranslationActiveColumn(): ?array') !== false, 'the checkbox column builder must exist and be nullable (Build 138: hidden entirely without the Pro feature)');
assert(strpos($moduleSource, 'private function GetEffectiveSelectedLanguage(array $Row, string $Language): string') !== false, 'the effective-selected-language helper must exist');
assert(strpos($moduleSource, 'private function BackfillTranslationActiveFlag(array $Row): array') !== false, 'the backfill helper must exist');
assert(strpos($moduleSource, 'private function AutoDeactivateTranslationForJsonContent(array $Row, string $RawField): array') !== false, 'the new JSON auto-deactivation helper must exist');
assert(substr_count($moduleSource, '$this->AppendTranslationActiveColumn($columns)') === 6, 'the checkbox column must be wired into all 6 relevant tables (names/texts/options/charts/automations/greeting)');
assert(substr_count($moduleSource, '$this->GetEffectiveSelectedLanguage(') === 8, 'the effective-language helper must be used at all 7 write sites (ApplyLanguage x2 for names+texts, ApplyAutomationsLanguage, ApplyGreetingLanguage, ApplyChartsLanguage, ApplyEnumerationOptionsToVariable, ApplyForkedProfileToVariable) AND in ComputeActiveLanguageContentFingerprint - since build 166, because a fingerprint that ignores the flag decides wrongly whether ApplyLanguage needs to run');
assert(substr_count($moduleSource, "[\$this, 'BackfillTranslationActiveFlag']") === 6, 'the backfill must be wired into all 6 relevant ScanRootTree merges');
assert(substr_count($moduleSource, 'AutoDeactivateTranslationForJsonContent($row, self::langOriginalImport)') === 5, 'THE BUG: the JSON auto-deactivation must run for exactly the 5 tables using langOriginalImport as their raw field (names/options/automations/charts/greeting)');
assert(substr_count($moduleSource, 'AutoDeactivateTranslationForJsonContent($row, self::langOriginalImportText)') === 1, 'THE BUG: "Eigene Texte" must run the JSON auto-deactivation against its OWN raw field (langOriginalImportText), not langOriginalImport');
assert(strpos($moduleSource, "\$Rows[\$RowIndex][self::fieldTranslationActive] ?? true") !== false, 'the live VM_UPDATE path (ApplyTrackedVariableUpdate, shared by Eigene Texte and Begrüßung-in-Variable-mode) must also respect the flag before calling the translation API');
$buildColFnStart = strpos($moduleSource, 'private function BuildTranslationActiveColumn(): ?array');
$buildColFnBody = substr($moduleSource, $buildColFnStart, 300);
assert(strpos($buildColFnBody, "HasLicenseFeature('edit_translations')") !== false, 'THE BUG (138): BuildTranslationActiveColumn() must gate on the Pro feature "edit_translations", the same feature already used for manual translation editing');
echo "Test 14 (the real module.php wires the checkbox, its resolution logic, and the new JSON auto-deactivation into all 6 requested tables with the correct raw field per table, and gates only the column's visibility on the Pro feature) OK\n";

echo "\nAll tests passed.\n";
