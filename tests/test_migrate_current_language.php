<?php
declare(strict_types=1);
// Standalone replica test for build 185 (2026-09-01, Symcon-Review): die
// Normalisierung von propertyCurrentLanguage wandert aus ApplyChanges heraus.
//
// BEANSTANDET: ApplyChanges schrieb die eigene Konfiguration per IPS_SetProperty
// + IPS_ApplyChanges nach, wenn dort noch die interne Pseudo-Sprache
// "ORIGINAL_IMPORT" stand. Das ist ein Reentry in den eigenen
// Konfigurationslauf; vorgesehen ist dafuer Migrate().
//
// Die Stelle war aber nicht nur Migration: sie war auch fuer BRANDNEUE Instanzen
// tragend, weil der Registrierungs-Default ebenfalls der Sentinel war und der
// seit Build 79 keine Option des Selects mehr ist (Symcon verweigert dann JEDES
// Speichern, siehe Build 142). Migrate() laeuft bei einer neuen Instanz aber
// gerade NICHT. Deshalb drei Aenderungen, die nur zusammen tragen.

$moduleSource = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fenster = function (string $von, string $bis) use ($moduleSource): string {
    $a = (int) strpos($moduleSource, $von);
    $b = (int) strpos($moduleSource, $bis, $a + 1);

    return substr($moduleSource, $a, $b - $a);
};

// Repliziert Migrate().
function migrateReplica(array $konfiguration): string
{
    $data = (object) ['configuration' => (object) $konfiguration];
    if (($data->configuration->CurrentLanguage ?? '') !== 'ORIGINAL_IMPORT') {
        return '';
    }
    $data->configuration->CurrentLanguage = $data->configuration->SourceLanguage ?? 'de';

    return json_encode($data);
}

// Test 1: DER MIGRATIONSFALL - eine Alt-Instanz auf "Original".
$out = json_decode(migrateReplica(['CurrentLanguage' => 'ORIGINAL_IMPORT', 'SourceLanguage' => 'fr']), true);
assert($out['configuration']['CurrentLanguage'] === 'fr',
    'DER FALL: der Sentinel wird auf die Quellsprache umgeschrieben');
echo "Test 1 (Alt-Instanz auf Original wird migriert) OK\n";

// Test 2: alles andere bleibt unangetastet - leerer String heisst laut SDK
// "keine Aenderung noetig". Sonst schriebe das Modul bei JEDEM Start zurueck.
assert(migrateReplica(['CurrentLanguage' => 'en', 'SourceLanguage' => 'de']) === '',
    'eine gueltige Sprache darf nichts ausloesen');
echo "Test 2 (gültige Sprache löst keine Migration aus) OK\n";

// Test 3: fehlt die Quellsprache wider Erwarten, bleibt es beim Default.
$out = json_decode(migrateReplica(['CurrentLanguage' => 'ORIGINAL_IMPORT']), true);
assert($out['configuration']['CurrentLanguage'] === 'de', 'Rueckfall auf den Registrierungs-Default');
echo "Test 3 (Rückfall auf den Default ohne Quellsprache) OK\n";

// Test 4: DIE LUECKE, die Migrate ALLEIN nicht schliesst - es laeuft nicht beim
// ersten Anlegen. Der Registrierungs-Default darf deshalb nicht mehr der
// Sentinel sein, sonst stuende eine neue Instanz sofort auf einem Wert, den das
// Select nicht anbietet.
$create = $fenster('public function Create(): void', 'public function Migrate');
assert(strpos($create, "RegisterPropertyString(self::propertyCurrentLanguage, 'de')") !== false,
    'DIE LUECKE: der Startwert ist ein echter Sprachcode');
assert(strpos($create, 'RegisterPropertyString(self::propertyCurrentLanguage, self::langOriginalImport)') === false,
    'und nicht mehr der Sentinel');
assert(strpos($create, "RegisterPropertyString(self::propertySourceLanguage, 'de')") !== false,
    'derselbe Wert wie der Default der Quellsprache - die beiden gehoeren zusammen');
echo "Test 4 (der Registrierungs-Default ist selbst gültig) OK\n";

// Test 5: DER RUECKBAU - ApplyChanges schreibt die aktive Sprache nicht mehr
// wegen des Sentinels nach. Die Selbstheilung fuer einen Code, den die
// Zielsprachen nicht (mehr) enthalten, bleibt: die ist keine Migration, sondern
// greift, wenn der Admin gerade eine aktive Zielsprache entfernt hat.
// An der NAECHSTEN Methode begrenzen, nicht an einem Namen, der auch als Aufruf
// im Rumpf vorkommt - sonst laeuft das Fenster in die Folgefunktionen.
$apply = $fenster('public function ApplyChanges(): void', 'public function MessageSink');
assert(strpos($apply, 'langOriginalImport') === false,
    'DER RUECKBAU: ApplyChanges kennt den Sentinel nicht mehr');
assert(substr_count($apply, 'IPS_SetProperty($this->InstanceID, self::propertyCurrentLanguage') === 1,
    'genau eine Schreibstelle bleibt - die Selbstheilung');
assert(strpos($apply, 'IsSelectableGuestLanguage($currentLanguageForValidation)') !== false,
    'und die haengt an der Auswaehlbarkeit, nicht am Sentinel');
echo "Test 5 (nur die Selbstheilung bleibt in ApplyChanges) OK\n";

// Test 6: DIE ZWEITE LUECKE - eine eigene Kachel kann den Sentinel schicken (bis
// Build 183 tat das mitgelieferte Beispiel genau das). Ohne die Normalisierung
// in ApplyChanges muss er am EINGANG abgefangen werden, sonst landet er ueber
// ApplyLanguage doch wieder in der Property.
$request = $fenster('public function RequestAction(string $Ident, mixed $Value): void', 'case self::identRescan');
assert(strpos($request, '$requestedOriginalImport = $language === self::langOriginalImport;') !== false,
    'DIE LUECKE: der Sentinel wird am Eingang erkannt');
assert(strpos($request, 'propertySourceLanguage') !== false, 'und auf die Quellsprache abgebildet');
$posAbbildung = strpos($request, '$requestedOriginalImport = ');
$posAnwenden = strpos($request, '$this->ApplyLanguage($language);');
assert($posAbbildung < $posAnwenden, 'und zwar BEVOR die Sprache angewendet/geschrieben wird');
echo "Test 6 (der Sentinel wird am Eingang abgebildet) OK\n";

// Test 7: das Verhalten der Sperrfrist bleibt unveraendert - eine Rueckkehr auf
// das Original hat sie nie gestartet. Ohne das Merken waere aus der Abbildung
// versehentlich ein "echter" Wechsel geworden.
assert(strpos($request, '&& !$requestedOriginalImport;') !== false,
    'die Rueckkehr aufs Original zaehlt weiterhin nicht als Wechsel');
echo "Test 7 (die Sperrfrist verhält sich unverändert) OK\n";

echo "\nAlle Tests OK (Build 185: Migrate statt IPS_SetProperty in ApplyChanges).\n";
