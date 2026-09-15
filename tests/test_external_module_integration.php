<?php
// Build 213 (Nutzer-Wunsch): Integration fuer fremde Module, die ihre eigene
// Kachel uebersetzen wollen (Anlass: da8ters Room-Kachel).
//
// 1. IsResponsibleFor(): Das bisherige README-Beispiel nahm immer die erste
//    Simple-Locale-Instanz. Bei zwei Visualisierungen (z.B. "Admin" und
//    "Wohnung") war das dauerhaft die falsche, weil die Reihenfolge stabil ist.
//    Jetzt fragt das fremde Modul jede Instanz, ob die Kachel in ihrem Baum
//    liegt - auch ueber eine Verknuepfung.
// 2. TranslateExternalTexts(): viele Texte in einem Aufruf, statt pro Text den
//    Cache neu einzulesen.
//
// Echte Klasse, Stub-Umgebung mit echten Stub-Objekten; die Anbieter sind
// pausiert, Uebersetzungen kommen aus dem Cache.
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

IPS\Kernel::reset();

function makeInstance(int $id): array
{
    $instance = new SimpleLocale($id);
    $moduleProp = new ReflectionProperty(IPSModuleStrict::class, 'module');
    $moduleProp->setAccessible(true);
    $moduleProp->setValue($instance, new TimedModule($id));
    $manager = new ReflectionClass(IPS\InstanceManager::class);
    foreach (['interfaces' => $instance, 'instances' => ['InstanceID' => $id, 'ConnectionID' => 0, 'InstanceStatus' => 102, 'InstanceChanged' => time(), 'ModuleInfo' => ['ModuleID' => '', 'ModuleName' => 'Simple Locale', 'ModuleType' => 3]]] as $store => $entry) {
        $ref = $manager->getProperty($store);
        $ref->setAccessible(true);
        $list = $ref->getValue();
        $list[$id] = $entry;
        $ref->setValue(null, $list);
    }
    $instance->Create();

    return [$instance, $moduleProp->getValue($instance)];
}

function setProperty(IPSModule $module, string $name, $value): void
{
    $ref = new ReflectionProperty(IPSModule::class, 'properties');
    $ref->setAccessible(true);
    $props = $ref->getValue($module);
    $props[$name]['Current'] = $value;
    $props[$name]['Pending'] = $value;
    $ref->setValue($module, $props);
}

function setAttribute(IPSModule $module, string $name, string $value): void
{
    $ref = new ReflectionProperty(IPSModule::class, 'attributes');
    $ref->setAccessible(true);
    $attrs = $ref->getValue($module);
    $attrs[$name]['Current'] = $value;
    $ref->setValue($module, $attrs);
}

// Zwei Visualisierungen, zwei Instanzen. Die Kachel haengt als Verknuepfung in "Wohnung".
$tileInstance = IPS_CreateCategory();
$apartmentRoom = IPS_CreateCategory();
$link = IPS_CreateLink();
IPS_SetLinkTargetID($link, $tileInstance);
$adminRoom = IPS_CreateCategory();

[$admin, $adminModule] = makeInstance(55001);
[$apartment, $apartmentModule] = makeInstance(55002);
setProperty($adminModule, 'ObjectNames', json_encode([['ObjectID' => $adminRoom, 'ORIGINAL_IMPORT' => 'Serverraum']]));
setProperty($apartmentModule, 'ObjectNames', json_encode([['ObjectID' => $apartmentRoom, 'ORIGINAL_IMPORT' => 'Wohnzimmer'], ['ObjectID' => $link, 'ORIGINAL_IMPORT' => 'Raumkachel']]));

// Test 1: genau eine Instanz ist zustaendig - auch ueber die Verknuepfung.
assert($apartment->IsResponsibleFor($tileInstance) === true, 'die Instanz mit der Verknuepfung muss fuer das Verknuepfungsziel zustaendig sein');
assert($admin->IsResponsibleFor($tileInstance) === false, 'DER BUG: eine andere Instanz darf sich nicht zustaendig fuehlen');
assert($apartment->IsResponsibleFor($apartmentRoom) === true && $apartment->IsResponsibleFor($link) === true, 'direkt enthaltene Objekte zaehlen ebenfalls');
assert($apartment->IsResponsibleFor(99999) === false, 'unbekannte Objekte gehoeren niemandem');
echo "Test 1 (die zustaendige Instanz wird eindeutig erkannt, auch ueber Verknuepfungen) OK\n";

// Test 2: nach einer Aenderung der Tabelle gilt der neue Stand, sobald ApplyChanges lief.
setProperty($adminModule, 'ObjectNames', json_encode([['ObjectID' => $adminRoom, 'ORIGINAL_IMPORT' => 'Serverraum'], ['ObjectID' => $tileInstance, 'ORIGINAL_IMPORT' => 'Raumkachel']]));
assert($admin->IsResponsibleFor($tileInstance) === false, 'der Puffer gilt, bis ApplyChanges ihn verwirft');
$admin->ApplyChanges();
assert($admin->IsResponsibleFor($tileInstance) === true, 'nach ApplyChanges muss der neue Stand gelten');
echo "Test 2 (der Puffer wird bei ApplyChanges neu aufgebaut) OK\n";

// Test 3: Sammeluebersetzung - Schluessel bleiben, leere und unbekannte Texte unveraendert.
setProperty($apartmentModule, 'SourceLanguage', 'de');
setProperty($apartmentModule, 'CurrentLanguage', 'en');
setProperty($apartmentModule, 'TargetLanguages', json_encode([['code' => 'de'], ['code' => 'en']]));
foreach (callPrivate($apartment, 'GetProviderChain') as $provider) {
    $paused[$provider] = time() + 86400;
}
setAttribute($apartmentModule, 'ProviderPausedUntil', json_encode($paused));
$cache = [];
foreach (['Licht' => 'Light', 'Heizung' => 'Heating'] as $german => $english) {
    $cache[callPrivate($apartment, 'BuildTranslationCacheKey', 'de', 'en', $german)] = ['v' => $english, 'h' => 1, 't' => time()];
}
setAttribute($apartmentModule, 'TranslationCache', json_encode($cache));

$result = $apartment->TranslateExternalTexts(['switch1' => 'Licht', 'switch2' => '', 'info' => 'Heizung', 'x' => 'Unbekannt'], '');
assert($result === ['switch1' => 'Light', 'switch2' => '', 'info' => 'Heating', 'x' => 'Unbekannt'], 'Schluessel, Uebersetzungen und Rueckfall muessen stimmen: ' . json_encode($result));
assert($apartment->TranslateExternalText('Licht', '') === 'Light', 'die Einzelvariante muss dasselbe liefern');
setProperty($apartmentModule, 'CurrentLanguage', 'de');
assert($apartment->TranslateExternalTexts(['a' => 'Licht'], '') === ['a' => 'Licht'], 'in der Quellsprache bleibt alles unveraendert');
echo "Test 3 (Sammeluebersetzung behaelt Schluessel und faellt sauber zurueck) OK\n";

echo "\nAll tests passed.\n";
