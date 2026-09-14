<?php
// Build 210 (live gemessen): ein Neuladen des Moduls wendete die aktive Sprache
// dreimal ineinander komplett an (3,5 s statt ~1,2 s auf der SymBox), und
// dasselbe Muster steckte in jedem Sprachwechsel eines Gastes.
//
// ApplyLanguage() stoesst an drei Stellen IPS_ApplyChanges() auf die eigene
// Instanz an. Der innere Durchlauf sah einen geaenderten Inhalt und wendete die
// Sprache selbst komplett an - danach tat der aeussere alles noch einmal.
// Zusaetzlich speicherte ApplyChanges() den Fingerabdruck vom Stand VOR dem
// Anwenden, sodass der naechste Durchlauf erneut anwendete.
//
// Echte Klasse, Stub-Umgebung; die fehlenden Uebersetzungen kommen aus dem
// Cache, es geht also nichts ins Netz.
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

class CountingLocale extends SimpleLocale
{
    public int $applyChangesCalls = 0;

    public function ApplyChanges(): void
    {
        $this->applyChangesCalls++;
        parent::ApplyChanges();
    }
}

function callPrivate(object $obj, string $method, ...$args)
{
    $ref = new ReflectionMethod(SimpleLocale::class, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

$instance = new CountingLocale(53535);
$moduleProp = new ReflectionProperty(IPSModuleStrict::class, 'module');
$moduleProp->setAccessible(true);
$moduleProp->setValue($instance, new TimedModule(53535));
$manager = new ReflectionClass(IPS\InstanceManager::class);
foreach (['interfaces' => $instance, 'instances' => ['InstanceID' => 53535, 'ConnectionID' => 0, 'InstanceStatus' => 102, 'InstanceChanged' => time(), 'ModuleInfo' => ['ModuleID' => '', 'ModuleName' => 'Simple Locale', 'ModuleType' => 3]]] as $store => $entry) {
    $ref = $manager->getProperty($store);
    $ref->setAccessible(true);
    $list = $ref->getValue();
    $list[53535] = $entry;
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

function setAttribute(string $name, string $value): void
{
    global $module, $attrsRef;
    $attrs = $attrsRef->getValue($module);
    $attrs[$name]['Current'] = $value;
    $attrsRef->setValue($module, $attrs);
}

function completedApplies(): int
{
    global $instance;

    return (int) explode('|', callPrivate($instance, 'GetBuffer', 'LanguageApplyRuns'), 2)[0];
}

setProperty('SourceLanguage', 'de');
setProperty('CurrentLanguage', 'de');
setProperty('TargetLanguages', json_encode([['code' => 'de'], ['code' => 'en']]));
setAttribute('LastAppliedLanguage', 'de');
foreach (callPrivate($instance, 'GetProviderChain') as $provider) {
    $paused[$provider] = time() + 86400;
}
setAttribute('ProviderPausedUntil', json_encode($paused));

// Englisch fehlt in allen Zeilen, liegt aber im Cache - der Sprachwechsel muss es nachtragen.
$rows = [];
$cache = [];
foreach (['Wohnzimmer' => 'Living room', 'Küche' => 'Kitchen', 'Bad' => 'Bathroom'] as $german => $english) {
    $rows[] = ['ObjectID' => 0, 'Path' => $german, 'ORIGINAL_IMPORT' => $german, 'Quellsprache' => 'de', 'TranslationActive' => true, 'de' => $german, 'en' => ''];
    $cache[callPrivate($instance, 'BuildTranslationCacheKey', 'de', 'en', $german)] = ['v' => $english, 'h' => 1, 't' => time()];
}
setProperty('ObjectNames', json_encode($rows));
setAttribute('TranslationCache', json_encode($cache));

// Test 1: ein Sprachwechsel wendet die Sprache genau einmal komplett an.
callPrivate($instance, 'ApplyLanguage', 'en');
assert(completedApplies() === 1, 'DER BUG: der Sprachwechsel wurde ' . completedApplies() . ' mal komplett angewendet statt einmal');
$props = $propsRef->getValue($module);
assert($props['CurrentLanguage']['Current'] === 'en', 'die neue Sprache muss gespeichert sein');
assert(json_decode($props['ObjectNames']['Current'], true)[1]['en'] === 'Kitchen', 'die fehlende Uebersetzung muss nachgetragen und gespeichert sein');
echo "Test 1 (ein Sprachwechsel mit nachgetragenen Uebersetzungen wendet einmal an) OK\n";

// Test 2: ein weiteres ApplyChanges ohne Aenderung wendet nicht erneut an.
$before = completedApplies();
$instance->ApplyChanges();
assert(completedApplies() === $before, 'DER BUG: ohne jede Aenderung darf nicht erneut angewendet werden');
echo "Test 2 (danach bleibt ApplyChanges still, der Fingerabdruck stimmt) OK\n";

echo "\nAll tests passed.\n";
