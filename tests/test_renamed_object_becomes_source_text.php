<?php
// Build 212 (Nutzer-Wunsch, live gemeldet): eine im Visu-Baum umbenannte
// Kategorie bekam ihren alten Namen immer wieder zurueck. Objektnamen werden
// nicht beobachtet, und ApplyLanguage() schreibt stets den Wert aus der Tabelle.
//
// Seither gilt: war fuer die Zeile der Originaltext zu sehen (Quellsprache
// aktiv oder Uebersetzung der Zeile abgeschaltet), wird ein fremder Name als
// neuer Originaltext uebernommen. Stand eine Uebersetzung, bleibt offen, was
// gemeint war - dann wird wie bisher zurueckgesetzt.
//
// Echte Klasse, Stub-Umgebung mit echten Stub-Kategorien; die Anbieter sind
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

// Leerer Stub-Kernel, damit echte Kategorien angelegt werden koennen.
IPS\Kernel::reset();

$instance = new SimpleLocale(54545);
$moduleProp = new ReflectionProperty(IPSModuleStrict::class, 'module');
$moduleProp->setAccessible(true);
$moduleProp->setValue($instance, new TimedModule(54545));
$manager = new ReflectionClass(IPS\InstanceManager::class);
foreach (['interfaces' => $instance, 'instances' => ['InstanceID' => 54545, 'ConnectionID' => 0, 'InstanceStatus' => 102, 'InstanceChanged' => time(), 'ModuleInfo' => ['ModuleID' => '', 'ModuleName' => 'Simple Locale', 'ModuleType' => 3]]] as $store => $entry) {
    $ref = $manager->getProperty($store);
    $ref->setAccessible(true);
    $list = $ref->getValue();
    $list[54545] = $entry;
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

function nameRows(): array
{
    global $module, $propsRef;

    return json_decode($propsRef->getValue($module)['ObjectNames']['Current'], true);
}

setProperty('SourceLanguage', 'de');
setProperty('TargetLanguages', json_encode([['code' => 'de'], ['code' => 'en']]));
foreach (callPrivate($instance, 'GetProviderChain') as $provider) {
    $paused[$provider] = time() + 86400;
}
setAttribute('ProviderPausedUntil', json_encode($paused));

function setup(string $activeLanguage, array $overrides = []): array
{
    $living = IPS_CreateCategory();
    $kitchen = IPS_CreateCategory();
    $rows = [
        ['ObjectID' => $living, 'Path' => '', 'ORIGINAL_IMPORT' => 'Wohnzimmer', 'Quellsprache' => 'de', 'TranslationActive' => true, 'de' => 'Wohnzimmer', 'en' => 'Living room'],
        ['ObjectID' => $kitchen, 'Path' => '', 'ORIGINAL_IMPORT' => 'Küche', 'Quellsprache' => 'de', 'TranslationActive' => true, 'de' => 'Küche', 'en' => 'Kitchen'],
    ];
    foreach ($overrides as $index => $fields) {
        $rows[$index] = array_merge($rows[$index], $fields);
    }
    IPS_SetName($living, $activeLanguage === 'en' && ($rows[0]['TranslationActive'] ?? true) ? 'Living room' : 'Wohnzimmer');
    IPS_SetName($kitchen, $activeLanguage === 'en' ? 'Kitchen' : 'Küche');
    setProperty('ObjectNames', json_encode($rows));
    setProperty('CurrentLanguage', $activeLanguage);
    setAttribute('LastAppliedLanguage', $activeLanguage);

    return [$living, $kitchen];
}

// Test 1: Quellsprache aktiv, Kategorie umbenannt - der neue Name wird Originaltext.
[$living, $kitchen] = setup('de');
IPS_SetName($living, 'Wohnbereich');
callPrivate($instance, 'ApplyLanguage', 'de');
$rows = nameRows();
assert(IPS_GetName($living) === 'Wohnbereich', 'DER BUG: der neue Name darf nicht zurueckgesetzt werden');
assert($rows[0]['ORIGINAL_IMPORT'] === 'Wohnbereich', 'der neue Name muss als Originaltext gespeichert sein');
assert($rows[0]['en'] === '', 'die Uebersetzung des alten Texts muss geleert sein');
assert($rows[1]['ORIGINAL_IMPORT'] === 'Küche' && $rows[1]['en'] === 'Kitchen', 'andere Zeilen bleiben unberuehrt');
echo "Test 1 (Umbenennung bei aktiver Quellsprache wird Originaltext) OK\n";

// Test 2: Uebersetzung aktiv - mehrdeutig, es wird wie bisher zurueckgesetzt.
[$living, $kitchen] = setup('en');
IPS_SetName($kitchen, 'Cooking');
callPrivate($instance, 'ApplyLanguage', 'en');
assert(IPS_GetName($kitchen) === 'Kitchen', 'bei aktiver Uebersetzung muss der Name zurueckgesetzt werden');
assert(nameRows()[1]['ORIGINAL_IMPORT'] === 'Küche', 'der Originaltext darf sich dabei nicht aendern');
echo "Test 2 (Umbenennung bei aktiver Uebersetzung wird zurueckgesetzt) OK\n";

// Test 3: bei Quellsprache umbenannt, danach Wechsel auf Englisch - uebernommen
// wird trotzdem, weil zum Zeitpunkt der Umbenennung der Originaltext zu sehen war.
[$living, $kitchen] = setup('de');
IPS_SetName($living, 'Wohnbereich');
callPrivate($instance, 'ApplyLanguage', 'en');
assert(nameRows()[0]['ORIGINAL_IMPORT'] === 'Wohnbereich', 'die Umbenennung muss auch beim Wechsel in eine andere Sprache uebernommen werden');
assert(IPS_GetName($kitchen) === 'Kitchen', 'die uebrigen Objekte muessen normal uebersetzt werden');
echo "Test 3 (Umbenennung vor einem Sprachwechsel geht nicht verloren) OK\n";

// Test 4: Uebersetzung der Zeile abgeschaltet - der Originaltext war zu sehen.
[$living, $kitchen] = setup('en', [0 => ['TranslationActive' => false]]);
IPS_SetName($living, 'Wohnbereich');
callPrivate($instance, 'ApplyLanguage', 'en');
assert(nameRows()[0]['ORIGINAL_IMPORT'] === 'Wohnbereich', 'bei abgeschalteter Uebersetzung ist die Umbenennung eindeutig');
assert(IPS_GetName($living) === 'Wohnbereich', 'der Name muss stehen bleiben');
echo "Test 4 (Zeile ohne Uebersetzung: Umbenennung wird uebernommen) OK\n";

// Test 5: ein Name, den das Modul selbst geschrieben hat, ist keine Umbenennung.
[$living, $kitchen] = setup('de');
IPS_SetName($kitchen, 'Kitchen');
callPrivate($instance, 'ApplyLanguage', 'de');
assert(nameRows()[1]['ORIGINAL_IMPORT'] === 'Küche', 'eine vorhandene Uebersetzung darf nicht als neuer Originaltext gelten');
assert(IPS_GetName($kitchen) === 'Küche', 'der Name muss auf den Originaltext zurueckgesetzt werden');
echo "Test 5 (eigene Uebersetzung wird nicht als Umbenennung gewertet) OK\n";

echo "\nAll tests passed.\n";
