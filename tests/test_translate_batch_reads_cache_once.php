<?php
// Build 210 (live: SymBox startete neun Tage lang nicht mehr). TranslateBatch()
// schlug fuer JEDEN Text einzeln im Cache nach - und dekodierte dabei jedes Mal
// den kompletten Cache (861 KB, 4.095 Eintraege), zaehlte den Treffer hoch und
// schrieb ihn komplett neu. Bei ein paar tausend Texten waren das Gigabytes an
// JSON: 27 Sekunden auf einem Mac, mehrere Minuten auf der SymBox.
//
// Prueft an der echten Klasse: der Cache wird pro Aufruf hoechstens einmal
// geschrieben, Treffer, Trefferzaehler und Statistik stimmen weiterhin, und das
// neue Nachschlagewerk fuer eigene Uebersetzungen/Glossar liefert fuer jeden
// Text dasselbe wie die Einzelsuche.
declare(strict_types=1);

require_once dirname(__DIR__) . '/.ips_stubs/autoload.php';
require_once dirname(__DIR__) . '/libs/SimpleLocaleConstants.php';
require_once dirname(__DIR__) . '/SimpleLocale/module.php';

class CountingModule extends IPSModulePublic
{
    public int $cacheWrites = 0;

    protected function getTime()
    {
        return time();
    }

    public function WriteAttributeString($Name, string $Value)
    {
        if ($Name === 'TranslationCache') {
            $this->cacheWrites++;
        }

        return parent::WriteAttributeString($Name, $Value);
    }
}

function callPrivate(object $obj, string $method, ...$args)
{
    $ref = new ReflectionMethod(SimpleLocale::class, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($obj, $args);
}

$instance = new SimpleLocale(52525);
$module = new CountingModule(52525);
$moduleProp = new ReflectionProperty(IPSModuleStrict::class, 'module');
$moduleProp->setAccessible(true);
$moduleProp->setValue($instance, $module);
$manager = new ReflectionClass(IPS\InstanceManager::class);
foreach (['interfaces' => $instance, 'instances' => ['InstanceID' => 52525, 'ConnectionID' => 0, 'InstanceStatus' => 102, 'InstanceChanged' => time(), 'ModuleInfo' => ['ModuleID' => '', 'ModuleName' => 'Simple Locale', 'ModuleType' => 3]]] as $store => $entry) {
    $ref = $manager->getProperty($store);
    $ref->setAccessible(true);
    $list = $ref->getValue();
    $list[52525] = $entry;
    $ref->setValue(null, $list);
}
$instance->Create();

$attrsRef = new ReflectionProperty(IPSModule::class, 'attributes');
$attrsRef->setAccessible(true);
function setAttribute(string $name, string $value): void
{
    global $module, $attrsRef;
    $attrs = $attrsRef->getValue($module);
    $attrs[$name]['Current'] = $value;
    $attrsRef->setValue($module, $attrs);
}

foreach (callPrivate($instance, 'GetProviderChain') as $provider) {
    $paused[$provider] = time() + 86400;
}
setAttribute('ProviderPausedUntil', json_encode($paused));

// 200 zwischengespeicherte Uebersetzungen.
$cache = [];
$texts = [];
for ($i = 0; $i < 200; $i++) {
    $texts[] = "Raum $i";
    $cache[callPrivate($instance, 'BuildTranslationCacheKey', 'de', 'en', "Raum $i")] = ['v' => "Room $i", 'h' => 1, 't' => time() - 10];
}
setAttribute('TranslationCache', json_encode($cache));

// Test 1: ein Aufruf, ein Schreibvorgang.
$batch = array_merge($texts, ['Raum 7', 'Raum 7']);
$module->cacheWrites = 0;
$result = callPrivate($instance, 'TranslateBatch', $batch, 'de', 'en');
assert($module->cacheWrites === 1, 'DER BUG: der Cache darf pro Aufruf nur einmal geschrieben werden, nicht ' . $module->cacheWrites . ' mal');
assert($result[0] === 'Room 0' && $result[199] === 'Room 199' && $result[200] === 'Room 7' && $result[201] === 'Room 7', 'die Treffer muessen an ihrer Position stehen');
echo "Test 1 (ein Aufruf mit 202 Texten schreibt den Cache genau einmal) OK\n";

// Test 2: Trefferzaehler laufen weiter wie bei Einzelabfragen.
$after = json_decode(callPrivate($instance, 'ReadAttributeString', 'TranslationCache'), true);
$key7 = callPrivate($instance, 'BuildTranslationCacheKey', 'de', 'en', 'Raum 7');
$key8 = callPrivate($instance, 'BuildTranslationCacheKey', 'de', 'en', 'Raum 8');
assert($after[$key7]['h'] === 4, 'dreimal abgefragt: Zaehler 1 + 3');
assert($after[$key8]['h'] === 2, 'einmal abgefragt: Zaehler 1 + 1');
echo "Test 2 (Trefferzaehler stimmen) OK\n";

// Test 3: die Statistik zaehlt jeden eingesparten Aufruf.
assert(callPrivate($instance, 'ReadAttributeString', 'StatsCacheSavedRequestCountV2') === '202', 'jeder der 202 Cache-Treffer zaehlt als eingespart');
echo "Test 3 (Statistik der eingesparten Anfragen stimmt) OK\n";

// Test 4: das Nachschlagewerk liefert fuer jeden Text dasselbe wie die Einzelsuche.
$manual = [
    ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'Tür', 'en' => ''],
    ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'Tür', 'en' => 'Door (manual)'],
    ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'Tür', 'en' => 'Door (second)'],
    ['Quellsprache' => 'fr', 'ORIGINAL_IMPORT' => 'Fenster', 'en' => 'Window (wrong source)'],
    ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => '12', 'en' => 'twelve'],
    ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => '', 'en' => 'empty source'],
];
$glossary = [
    ['de' => 'Tür', 'en' => 'Door (glossary)'],
    ['de' => 'Fenster', 'en' => ''],
    ['de' => 'Fenster', 'en' => 'Window'],
    ['de' => 'Fenster', 'en' => 'Window (second)'],
    ['de' => '012', 'en' => 'zero twelve'],
    ['de' => '', 'en' => 'nothing'],
];
$index = callPrivate($instance, 'BuildManualTranslationIndex', $manual, $glossary, 'de', 'en');
foreach (['Tür', 'Fenster', '12', '012', '', 'Unbekannt'] as $text) {
    $single = callPrivate($instance, 'FindManualTranslation', $manual, $glossary, 'de', 'en', $text);
    $fromIndex = array_key_exists($text, $index) ? $index[$text] : null;
    assert($single === $fromIndex, "Nachschlagewerk und Einzelsuche weichen ab fuer '$text'");
}
echo "Test 4 (Nachschlagewerk entspricht der Einzelsuche) OK\n";

echo "\nAll tests passed.\n";
