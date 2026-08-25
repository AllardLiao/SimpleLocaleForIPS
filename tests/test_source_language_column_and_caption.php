<?php
declare(strict_types=1);
// Standalone replica test for build 81 (2026-08-20):
// Third and fourth bugs found while the user was live-testing builds 79/80. Debug
// logs confirmed "de" WAS correctly persisted in TargetLanguages (EnsureSourceLanguageIsTarget
// logged "'de' already present ... no-op" on the second run) - so the underlying data
// was already right. But two DISPLAY-layer functions still had leftover "skip the
// source language" logic from before build 79, when that was correct (the source
// language's content was always 100% identical to "Original import"). Both needed
// updating for the new reality that a ROW can have its own, different source language.

function buildLanguageColumnSetReplica(array $targetLanguages, string $sourceLanguage): array
{
    $columns = [];
    foreach ($targetLanguages as $language) {
        $columns[] = ['name' => $language]; // build 81: no longer skips $language === $sourceLanguage
    }

    return $columns;
}

function buildTargetLanguageOptionsReplica(array $allLanguageOptions, string $sourceLanguage, array $allowedLanguages, array $freeLanguageCodes, bool $restrictToTrial): array
{
    $options = [];
    foreach ($allLanguageOptions as $option) {
        if ($option['value'] === $sourceLanguage) {
            $options[] = $option; // build 81: always included, exempt from trial/allowed restrictions
            continue;
        }
        if ($restrictToTrial && !in_array($option['value'], $freeLanguageCodes, true)) {
            continue;
        }
        if ($allowedLanguages !== [] && !in_array($option['value'], $allowedLanguages, true)) {
            continue;
        }
        $options[] = $option;
    }

    return $options;
}

// Test 1: THE REPORTED BUG - "Object names"/"Object texts" must get a real column
// for the source language once it's a real target-language entry, so rows whose OWN
// source differs (e.g. an English-sourced row in a mostly-German tree) can show a
// genuine German translation there, distinct from "Original import".
$columns = buildLanguageColumnSetReplica(['en', 'es', 'de'], 'de');
assert(in_array('de', array_column($columns, 'name'), true), 'THE FIX: a column for the (now real target-language) source language must be generated, not silently skipped');
echo "Test 1 (Object names/texts now get a real column for the source language, matching what EnsureSourceLanguageIsTarget already persisted) OK\n";

// Test 2: THE OTHER REPORTED SYMPTOM - a blank row in "Target languages" with no
// visible caption. Root cause: BuildTargetLanguageOptions() (which supplies BOTH the
// Add-dropdown choices AND the caption used to render each already-saved row) still
// excluded the source language, so the List widget had nothing to display for it.
$allOptions = [['caption' => 'English', 'value' => 'en'], ['caption' => 'Español', 'value' => 'es'], ['caption' => 'Deutsch', 'value' => 'de']];
$targetOptions = buildTargetLanguageOptionsReplica($allOptions, 'de', [], [], false);
assert(in_array(['caption' => 'Deutsch', 'value' => 'de'], $targetOptions, true), 'THE FIX: the source languages own caption must be resolvable, so the auto-added row renders "Deutsch" instead of a blank row');
echo "Test 2 (Target languages list can now resolve a caption for the source-language row - no more blank row) OK\n";

// Test 3: the source language's caption must be available even under a restrictive
// trial or allowedLanguages state - otherwise build 80's allowedLanguages exemption
// (in EnforceLicensedLanguageLimit) would keep the row alive, but this SEPARATE
// caption-lookup function would still render it blank.
$restrictedOptions = buildTargetLanguageOptionsReplica($allOptions, 'de', ['en'], ['en'], true);
assert(in_array(['caption' => 'Deutsch', 'value' => 'de'], $restrictedOptions, true), 'The source languages caption must resolve even under trial restriction or a narrow allowedLanguages promo license - matches the build 80 exemption in EnforceLicensedLanguageLimit()');
echo "Test 3 (source-language caption resolves even under trial/allowedLanguages restrictions, consistent with the build 80 fix) OK\n";

// Test 4: other (non-source) languages are still correctly restricted when trial/
// allowedLanguages apply - the exemption is scoped only to the source language, not
// a blanket bypass.
assert(!in_array('es', array_column($restrictedOptions, 'value'), true), 'Non-source languages must still be filtered normally by trial/allowedLanguages restrictions - the exemption must not leak to other languages');
echo "Test 4 (non-source languages remain correctly restricted - the exemption does not leak) OK\n";

echo "\nAll tests passed.\n";
