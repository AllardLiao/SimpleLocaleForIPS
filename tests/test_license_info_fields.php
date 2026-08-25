<?php
// Standalone simulation of the new per-field license-info logic in
// PopulateFormElements() (module.php), replacing the old, never-console-
// translatable BuildLicenseInfoText() free-text blob. Mirrors the real
// switch-case bodies (copy-adapted) since there is no live Symcon instance
// available here. Verifies: every visible caption is either a literal from a
// small, fixed, pre-registered set (translatable) or a raw non-translatable
// value (date/number/code list) - never a runtime concatenation of both.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

function computeLicenseInfoFields(array $licenseInfo): array
{
    $licenseValid = $licenseInfo['valid'] ?? false;
    $fields = [];

    $edition = trim((string) ($licenseInfo['edition'] ?? ''));
    $fields['EditionLabel'] = ['visible' => $licenseValid && $edition !== '', 'caption' => $edition];

    $rowVisible = $licenseValid;
    $fields['TypeRow'] = ['visible' => $rowVisible];
    $fields['ExpiryRow'] = ['visible' => $rowVisible];
    $fields['LanguageLimitRow'] = ['visible' => $rowVisible];
    $fields['AllowedLanguagesRow'] = ['visible' => $rowVisible];

    $fields['TypeValueLabel'] = ['caption' => ($licenseInfo['type'] ?? '') === 'subscription' ? 'Abo' : 'Einmalkauf'];

    $expiresAt = (int) ($licenseInfo['expiresAt'] ?? 0);
    $fields['ExpiryConnectorLabel'] = ['caption' => $expiresAt === 0 ? 'läuft nie ab' : 'gültig bis'];
    $fields['ExpiryDateLabel'] = ['caption' => $expiresAt === 0 ? '' : date('d.m.Y', $expiresAt)];

    $languageLimit = (int) ($licenseInfo['languageLimit'] ?? 0);
    $fields['LanguageLimitConnectorLabel'] = ['caption' => $languageLimit === 0 ? 'unbegrenzt' : 'max.'];
    $fields['LanguageLimitNumberLabel'] = ['caption' => $languageLimit === 0 ? '' : (string) $languageLimit];

    $allowedLanguages = $licenseInfo['allowedLanguages'] ?? [];
    $fields['AllowedLanguagesValueLabel'] = ['caption' => $allowedLanguages === [] ? 'alle' : implode(', ', $allowedLanguages)];

    $featureMap = [
        'FeatureEditTranslations'        => 'edit_translations',
        'FeatureAutoRescan'              => 'auto_rescan',
        'FeaturePaidProviders'           => 'paid_providers',
        'FeatureUnlimitedLanguageSwitch' => 'unlimited_language_switch',
        'FeatureCustomTile'              => 'custom_tile',
    ];
    foreach ($featureMap as $fieldName => $featureKey) {
        $fields[$fieldName] = ['visible' => $licenseValid && in_array($featureKey, $licenseInfo['features'] ?? [], true)];
    }

    return $fields;
}

// A fixed, finite set of literal strings this design must ONLY ever produce
// for translatable captions (mirrors what's now in locale.json).
const TRANSLATABLE_LITERALS = [
    'Abo', 'Einmalkauf', 'läuft nie ab', 'gültig bis', 'unbegrenzt', 'max.', 'alle',
    '✓ Manuelles Editieren von Übersetzungen', '✓ Automatischer Rescan nach Zeitplan',
    '✓ Google/DeepL als Übersetzungsanbieter', '✓ Unbegrenzter Sprachwechsel',
    '✓ Eigene Sprachauswahl-Kachel',
];

// ---- Scenario 1: invalid/no license -> everything hidden ------------------
$fields = computeLicenseInfoFields(['valid' => false]);
assertTrue($fields['EditionLabel']['visible'] === false, 'invalid license: edition hidden');
assertTrue($fields['TypeRow']['visible'] === false, 'invalid license: type row hidden');
assertTrue($fields['ExpiryRow']['visible'] === false, 'invalid license: expiry row hidden');
assertTrue($fields['FeatureEditTranslations']['visible'] === false, 'invalid license: feature hidden');

// ---- Scenario 2: full Pro license, subscription, expiring, limited langs,
// all 5 features, real edition name. -----------------------------------------
$expiresAt = strtotime('2027-06-15 00:00:00 UTC');
$info = [
    'valid' => true,
    'type' => 'subscription',
    'expiresAt' => $expiresAt,
    'languageLimit' => 3,
    'allowedLanguages' => ['de', 'en', 'fr'],
    'features' => ['edit_translations', 'auto_rescan', 'paid_providers', 'unlimited_language_switch', 'custom_tile'],
    'edition' => 'Pro',
];
$fields = computeLicenseInfoFields($info);
assertTrue($fields['EditionLabel']['visible'] === true && $fields['EditionLabel']['caption'] === 'Pro', 'edition heading shown verbatim, untranslated (matches "Simple Locale for IPS" product-name precedent)');
assertTrue($fields['TypeValueLabel']['caption'] === 'Abo', 'subscription -> "Abo" literal');
assertTrue($fields['ExpiryConnectorLabel']['caption'] === 'gültig bis', 'has expiry -> "gültig bis" literal');
assertTrue($fields['ExpiryDateLabel']['caption'] === date('d.m.Y', $expiresAt), 'expiry date rendered as raw, non-translated value');
assertTrue($fields['LanguageLimitConnectorLabel']['caption'] === 'max.', 'limited -> "max." literal');
assertTrue($fields['LanguageLimitNumberLabel']['caption'] === '3', 'limit number rendered as raw, non-translated value');
assertTrue($fields['AllowedLanguagesValueLabel']['caption'] === 'de, en, fr', 'allowed languages rendered as raw code list, non-translated');
foreach (['FeatureEditTranslations', 'FeatureAutoRescan', 'FeaturePaidProviders', 'FeatureUnlimitedLanguageSwitch', 'FeatureCustomTile'] as $f) {
    assertTrue($fields[$f]['visible'] === true, "all 5 features visible for full Pro license ($f)");
}

// ---- Scenario 3: Light-style license - one_time, never expires, unlimited
// languages, no allowed-language restriction, zero extra features. ----------
$info2 = [
    'valid' => true,
    'type' => 'one_time',
    'expiresAt' => 0,
    'languageLimit' => 0,
    'allowedLanguages' => [],
    'features' => [],
    'edition' => '',
];
$fields2 = computeLicenseInfoFields($info2);
assertTrue($fields2['EditionLabel']['visible'] === false, 'empty edition (pre-edition-field key) -> heading hidden, no empty-string row');
assertTrue($fields2['TypeValueLabel']['caption'] === 'Einmalkauf', 'one_time -> "Einmalkauf" literal');
assertTrue($fields2['ExpiryConnectorLabel']['caption'] === 'läuft nie ab' && $fields2['ExpiryDateLabel']['caption'] === '', 'no expiry -> fixed literal, empty date value');
assertTrue($fields2['LanguageLimitConnectorLabel']['caption'] === 'unbegrenzt' && $fields2['LanguageLimitNumberLabel']['caption'] === '', 'no limit -> fixed literal, empty number value');
assertTrue($fields2['AllowedLanguagesValueLabel']['caption'] === 'alle', 'no restriction -> fixed literal "alle"');
foreach (['FeatureEditTranslations', 'FeatureAutoRescan', 'FeaturePaidProviders', 'FeatureUnlimitedLanguageSwitch', 'FeatureCustomTile'] as $f) {
    assertTrue($fields2[$f]['visible'] === false, "no extra features -> all feature lines hidden ($f)");
}

// ---- Scenario 4: verify every non-empty, translatable caption produced
// across both scenarios is drawn from the fixed literal set (i.e. nothing is
// ever a runtime concatenation of two translated fragments). --------------
$translatableCaptionKeys = ['TypeValueLabel', 'ExpiryConnectorLabel', 'LanguageLimitConnectorLabel', 'AllowedLanguagesValueLabel'];
foreach ([$fields, $fields2] as $set) {
    foreach ($translatableCaptionKeys as $key) {
        $caption = $set[$key]['caption'];
        // AllowedLanguagesValueLabel may legitimately be a raw, non-translated
        // code list (e.g. "de, en, fr") instead of a literal - only assert
        // membership when it isn't obviously a raw code list.
        if ($key === 'AllowedLanguagesValueLabel' && str_contains($caption, ', ')) {
            continue;
        }
        assertTrue(in_array($caption, TRANSLATABLE_LITERALS, true), "caption '$caption' ($key) is one of the fixed, pre-registered literals");
    }
}

echo "\nAll license-info field simulations passed.\n";
