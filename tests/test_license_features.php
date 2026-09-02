<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/.ips_stubs/autoload.php';
require_once dirname(__DIR__) . '/libs/SimpleLocaleConstants.php';
require_once dirname(__DIR__) . '/SimpleLocale/module.php';

function callPrivate($obj, string $method, ...$args)
{
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

function invokePrivate($obj, string $method, ...$args)
{
    $ref = new ReflectionMethod($obj, $method);
    $ref->setAccessible(true);
    $ref->invokeArgs($obj, $args);
}

// SetProperty() only stages a 'Pending' value - it's only promoted to
// 'Current' (what ReadPropertyString sees) by the module's own
// ApplyChanges(), which we can't run here (needs the full Create()
// lifecycle - RegisterTimer/RootCategoryID/etc.). Write 'Current' directly
// on the stub's internal store instead, bypassing that staging step.
function setPropertyCurrent($instance, string $name, $value): void
{
    $moduleProp = new ReflectionProperty(IPSModuleStrict::class, 'module');
    $moduleProp->setAccessible(true);
    $module = $moduleProp->getValue($instance);

    $propsProp = new ReflectionProperty(IPSModule::class, 'properties');
    $propsProp->setAccessible(true);
    $props = $propsProp->getValue($module);
    $props[$name]['Current'] = $value;
    $propsProp->setValue($module, $props);
}

// SICHERHEIT: Dieser Test braucht den PRIVATEN Ed25519-Signierschluessel, um
// gueltige Lizenzschluessel zu erzeugen - also exakt den Schluessel, mit dem
// echte, verkaufbare Lizenzen ausgestellt werden. Der gehoert NIEMALS in dieses
// (oeffentliche) Repo, siehe den ausfuehrlichen Kommentar bei
// LICENSE_PUBLIC_KEY in libs/SimpleLocaleConstants.php.
//
// Er wird daher zur Laufzeit aus der Umgebung gelesen:
//     SLOC_LICENSE_SIGNING_KEY=<base64> php -d zend.assertions=1 ... tests/test_license_features.php
// Fehlt er, ueberspringt sich der Test sauber, statt fehlzuschlagen - die
// uebrige Suite bleibt dadurch auf jeder Maschine lauffaehig.
function getSigningKeyOrSkip(): string
{
    $b64 = getenv('SLOC_LICENSE_SIGNING_KEY') ?: '';
    if ($b64 === '') {
        echo "SKIP: Umgebungsvariable SLOC_LICENSE_SIGNING_KEY nicht gesetzt -\n";
        echo "      dieser Test braucht den privaten Signierschluessel, der bewusst\n";
        echo "      nicht im Repo liegt (siehe Kommentar oben). Uebrige Suite unberuehrt.\n";
        exit(0);
    }

    $key = base64_decode($b64, true);
    if ($key === false || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        fwrite(STDERR, "SLOC_LICENSE_SIGNING_KEY ist kein gueltiger Ed25519-Secret-Key (erwartet: base64 von "
            . SODIUM_CRYPTO_SIGN_SECRETKEYBYTES . " Bytes).\n");
        exit(1);
    }

    return $key;
}

function signPayload(array $payload): string
{
    $privateKey = getSigningKeyOrSkip();
    $json = json_encode($payload);
    $sig = sodium_crypto_sign_detached($json, $privateKey);
    $b64url = fn (string $d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');

    return $b64url($json) . '.' . $b64url($sig);
}

// Bypass Create() entirely (it registers an auto-rescan RegisterTimer call
// that needs a getTime() mock the bare stub harness doesn't provide, and
// needs a real IPS_CreateInstance()-registered object for other lifecycle
// bits we don't need here) - just register the two properties these tests
// actually touch, directly.
$instance = new SimpleLocale(424242);
invokePrivate($instance, 'RegisterPropertyString', 'LicenseKey', '');
invokePrivate($instance, 'RegisterPropertyString', 'TargetLanguages', '[]');
invokePrivate($instance, 'RegisterPropertyString', 'GoogleTranslateAPIKey', '');
// Seit Einfuehrung der Anbieter-Kette (GetProviderChain) zusaetzlich noetig.
invokePrivate($instance, 'RegisterPropertyString', 'PreferredPaidProvider', 'google');
invokePrivate($instance, 'RegisterPropertyString', 'DeepLAPIKey', '');
invokePrivate($instance, 'RegisterPropertyString', 'SourceLanguage', 'de');
invokePrivate($instance, 'RegisterAttributeString', 'AvailableLanguagesCache', '[]');
// Build 120 hat GetLicenseInfo() um den serverseitigen Widerruf und die
// Ablaufdatum-Ueberschreibung erweitert (siehe CheckLicenseStatus) - diese
// Attribute muessen hier daher ebenfalls registriert sein, sonst wirft der
// Stub schon beim ersten HasLicenseFeature()-Aufruf "Attribute ... not found".
invokePrivate($instance, 'RegisterAttributeString', 'LicenseExpiresAtOverrideKeyHash', '');
invokePrivate($instance, 'RegisterAttributeInteger', 'LicenseExpiresAtOverride', 0);
invokePrivate($instance, 'RegisterAttributeString', 'RevokedLicenseKeyHash', '');
invokePrivate($instance, 'RegisterAttributeString', 'BlockedLicenseKeyHash', '');

// --- Test 1: no license (trial) - editing allowed, no language restriction ---
$editableTrial = callPrivate($instance, 'HasLicenseFeature', 'edit_translations');
$allowedTrial = callPrivate($instance, 'GetLicensedAllowedLanguages');
echo 'Trial (no license): editable=' . ($editableTrial ? 'YES' : 'NO') . ', allowedLanguages=' . json_encode($allowedTrial) . "\n";

// --- Test 2: Pro key with allowedLanguages=[fi], features=[edit_translations], languageLimit=1 ---
$proKey = signPayload(['type' => 'one_time', 'expiresAt' => 0, 'languageLimit' => 1, 'allowedLanguages' => ['fi'], 'features' => ['edit_translations']]);
setPropertyCurrent($instance, 'LicenseKey', $proKey);

$editablePro = callPrivate($instance, 'HasLicenseFeature', 'edit_translations');
$allowedPro = callPrivate($instance, 'GetLicensedAllowedLanguages');
$limitPro = callPrivate($instance, 'GetLicensedLanguageLimit');
echo "Promo key (fi-only, edit allowed, limit 1): editable=" . ($editablePro ? 'YES' : 'NO') . ', allowedLanguages=' . json_encode($allowedPro) . ", limit=$limitPro\n";

// --- Test 3: standard key (no allowedLanguages, no features) - unrestricted, not editable ---
$standardKey = signPayload(['type' => 'one_time', 'expiresAt' => 0, 'languageLimit' => 0]);
setPropertyCurrent($instance, 'LicenseKey', $standardKey);
$editableStd = callPrivate($instance, 'HasLicenseFeature', 'edit_translations');
$allowedStd = callPrivate($instance, 'GetLicensedAllowedLanguages');
echo 'Standard key (no extras): editable=' . ($editableStd ? 'YES' : 'NO') . ', allowedLanguages=' . json_encode($allowedStd) . "\n";

// --- Test 4: BuildLanguageColumnSet respects the feature flag (the actual UI gate) ---
setPropertyCurrent($instance, 'LicenseKey', $proKey);
$columnsEditable = callPrivate($instance, 'BuildLanguageColumnSet', '', '', 'de', ['en']);
$hasEditKeyWhenPro = isset($columnsEditable[0]['edit']);

setPropertyCurrent($instance, 'LicenseKey', $standardKey);
$columnsReadonly = callPrivate($instance, 'BuildLanguageColumnSet', '', '', 'de', ['en']);
$hasEditKeyWhenStandard = isset($columnsReadonly[0]['edit']);

echo "BuildLanguageColumnSet 'edit' key present - Pro: " . ($hasEditKeyWhenPro ? 'YES' : 'NO') . ', Standard: ' . ($hasEditKeyWhenStandard ? 'YES' : 'NO') . "\n";

// --- Test 5: BuildTargetLanguageOptions - no API key set, generic message, no crash regardless of allowlist ---
$options = callPrivate($instance, 'BuildTargetLanguageOptions', 'de');
echo 'BuildTargetLanguageOptions (no API key set, expect placeholder): ' . json_encode($options) . "\n";
