<?php
// Build 210 (live: SymBox startete neun Tage lang nicht mehr). Der
// Quellsprachen-Abgleich merkte sich im Zeilenfeld "UebersetztGegen", gegen
// welche Quellsprache eine Zeile zuletzt uebersetzt wurde. Ein "Uebernehmen" im
// Formular speichert aber nur die Spalten der Liste - das Feld fehlte danach in
// JEDER Zeile. Der naechste Abgleich hielt deshalb alle 630 Zeilen fuer
// geaendert, markierte alle Uebersetzungen als veraltet und schickte alles
// erneut durch die Uebersetzung. Beim Start blockierte das Symcon (siehe
// test_startup_waits_for_kernel.php).
//
// Die Buchfuehrung liegt seither im Attribut ReconciledRowSourceLanguages.
// Echte Klasse, Stub-Umgebung wie test_license_features.php; die Anbieter sind
// pausiert, es geht also nichts ins Netz.
declare(strict_types=1);

require_once dirname(__DIR__) . '/.ips_stubs/autoload.php';
require_once dirname(__DIR__) . '/libs/SimpleLocaleConstants.php';
require_once dirname(__DIR__) . '/SimpleLocale/module.php';

class TimedModule extends IPSModulePublic
{
    protected function getTime()
    {
        return time();
    }
}

function callPrivate(object $obj, string $method, ...$args)
{
    $ref = new ReflectionMethod(SimpleLocale::class, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

$instance = new SimpleLocale(51515);
$moduleProp = new ReflectionProperty(IPSModuleStrict::class, 'module');
$moduleProp->setAccessible(true);
$moduleProp->setValue($instance, new TimedModule(51515));
// Die Instanz beim Stub-InstanceManager bekannt machen - IPS_SetProperty &
// Co. schlagen dort nach.
$manager = new ReflectionClass(IPS\InstanceManager::class);
foreach (['interfaces' => $instance, 'instances' => ['InstanceID' => 51515, 'ConnectionID' => 0, 'InstanceStatus' => 102, 'InstanceChanged' => time(), 'ModuleInfo' => ['ModuleID' => '', 'ModuleName' => 'Simple Locale', 'ModuleType' => 3]]] as $store => $entry) {
    $ref = $manager->getProperty($store);
    $ref->setAccessible(true);
    $list = $ref->getValue();
    $list[51515] = $entry;
    $ref->setValue(null, $list);
}
$instance->Create();
$module = $moduleProp->getValue($instance);
$propsRef = new ReflectionProperty(IPSModule::class, 'properties');
$propsRef->setAccessible(true);
$attrsRef = new ReflectionProperty(IPSModule::class, 'attributes');
$attrsRef->setAccessible(true);

function setProperty(string $name, $value): void
{
    global $module, $propsRef;
    $props = $propsRef->getValue($module);
    $props[$name]['Current'] = $value;
    $props[$name]['Pending'] = $value;
    $propsRef->setValue($module, $props);
}

function pendingRows(string $name): array
{
    global $module, $propsRef;

    return json_decode($propsRef->getValue($module)[$name]['Pending'], true);
}

setProperty('SourceLanguage', 'de');
setProperty('TargetLanguages', json_encode([['code' => 'de'], ['code' => 'en']]));
foreach (callPrivate($instance, 'GetProviderChain') as $provider) {
    $paused[$provider] = time() + 86400;
}
$attrs = $attrsRef->getValue($module);
$attrs['ProviderPausedUntil']['Current'] = json_encode($paused);
$attrsRef->setValue($module, $attrs);

// So sehen die Zeilen nach einem "Uebernehmen" aus: nur die Formularspalten.
$rows = [
    ['ObjectID' => 101, 'Path' => 'Wohnzimmer', 'ORIGINAL_IMPORT' => 'Wohnzimmer', 'Quellsprache' => 'de', 'TranslationActive' => true, 'de' => 'Wohnzimmer', 'en' => 'Living room'],
    ['ObjectID' => 102, 'Path' => 'Küche', 'ORIGINAL_IMPORT' => 'Küche', 'Quellsprache' => 'de', 'TranslationActive' => true, 'de' => 'Küche', 'en' => 'Kitchen'],
    ['ObjectID' => 103, 'Path' => 'Bad', 'ORIGINAL_IMPORT' => 'Bad', 'Quellsprache' => 'de', 'TranslationActive' => true, 'de' => 'Bad', 'en' => 'Bathroom'],
];
setProperty('ObjectNames', json_encode($rows));

function stampedRows(): array
{
    $stamped = [];
    foreach (pendingRows('ObjectNames') as $index => $row) {
        if (isset($row['QuelleGeaendertAm'])) {
            $stamped[] = $index;
        }
    }

    return $stamped;
}

// Test 1: bestehende Installation, noch keine Buchfuehrung - nichts gilt als geaendert.
$changed = callPrivate($instance, 'ReconcileRowSourceLanguageChanges');
assert($changed === false, 'DER BUG: Zeilen ohne Buchfuehrung duerfen nicht als geaendert gelten');
assert(stampedRows() === [], 'DER BUG: keine Zeile darf als "Quelle geaendert" markiert werden');
$known = json_decode(callPrivate($instance, 'ReadAttributeString', 'ReconciledRowSourceLanguages'), true);
assert(count($known['ObjectNames'] ?? []) === 3, 'die Buchfuehrung muss alle drei Zeilen kennen');
echo "Test 1 (fehlende Buchfuehrung loest keinen Abgleich aus) OK\n";

// Test 2: "Uebernehmen" mit einer echt geaenderten Quellsprache - genau diese Zeile.
$rows[1]['Quellsprache'] = 'en';
setProperty('ObjectNames', json_encode($rows));
$changed = callPrivate($instance, 'ReconcileRowSourceLanguageChanges');
assert($changed === true, 'eine echt geaenderte Quellsprache muss erkannt werden');
assert(stampedRows() === [1], 'nur die geaenderte Zeile darf markiert werden');
echo "Test 2 (echter Quellsprachen-Wechsel nach dem Formular-Speichern wird erkannt) OK\n";

// Test 3: erneutes "Uebernehmen" ohne Aenderung - nichts mehr zu tun.
$rowsAfterSave = $rows;
setProperty('ObjectNames', json_encode($rowsAfterSave));
$changed = callPrivate($instance, 'ReconcileRowSourceLanguageChanges');
assert($changed === false, 'ohne weitere Aenderung darf nichts mehr abgeglichen werden');
echo "Test 3 (wiederholtes Speichern ohne Aenderung bleibt still) OK\n";

// Test 4: frisch gescannte Zeile mit Zeilenfeld - das Feld gilt weiterhin.
$fresh = $rows;
$fresh[2]['UebersetztGegen'] = 'en';
setProperty('ObjectNames', json_encode($fresh));
$changed = callPrivate($instance, 'ReconcileRowSourceLanguageChanges');
assert($changed === true, 'ein vorhandenes Zeilenfeld mit abweichender Sprache muss weiterhin erkannt werden');
assert(in_array(2, stampedRows(), true), 'die Zeile mit abweichendem Zeilenfeld muss markiert werden');
echo "Test 4 (vorhandenes Zeilenfeld wird weiterhin ausgewertet) OK\n";

echo "\nAll tests passed.\n";
