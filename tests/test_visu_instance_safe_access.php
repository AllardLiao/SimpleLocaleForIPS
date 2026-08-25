<?php
declare(strict_types=1);
// Standalone replica test for build 144/145 (2026-08-24).
//
// Entstanden aus dem Wunsch, die alte WebFront-Visualisierung zu unterstuetzen.
// Die Unterstuetzung selbst wurde nach Sichtung einer echten WebFront-
// Konfiguration bewusst verworfen (siehe README Change-Log) - der dabei
// gefundene ABSTURZ bleibt aber ein echter Fehler und ist hier abgesichert:
//
// IPS_GetProperty() wirft bei einem UNBEKANNTEN Property-Namen eine Exception -
// und "@" unterdrueckt in PHP nur Warnungen, NIEMALS Exceptions. Der Code las
// die fremde Visualisierungs-Instanz an zehn Stellen direkt per
// @IPS_GetProperty($visu, 'Automations'/'GreetingName'/'ShowGreeting'/...).
// Waehlt jemand dort eine Instanz, die diese Properties nicht kennt, riss der
// komplette Rescan mit einer unbehandelten Exception ab - statt die nicht
// unterstuetzte Instanz sauber als "Root fehlt" zu melden. Dasselbe galt fuer
// die beiden SCHREIBENDEN Zugriffe (IPS_SetProperty wirft genauso).
//
// Der Zugriff laeuft jetzt ueber IPS_GetConfiguration() (liefert das JSON ALLER
// vorhandenen Properties; ein fehlender Schluessel ist damit ein normaler
// Array-Miss).

// Repliziert GetVisuInstanceProperties(): tolerantes Lesen der Konfiguration.
function visuPropertiesReplica(?string $configurationJson): array
{
    $decoded = json_decode((string) $configurationJson, true);

    return is_array($decoded) ? $decoded : [];
}

// Repliziert ResolveVisuRootCategoryID(). $existingObjects simuliert
// IPS_ObjectExists(). Die Kandidatenliste ist bewusst kurz - siehe Test 8.
function resolveRootReplica(array $properties, array $existingObjects, array $candidates = ['BaseID']): int
{
    foreach ($candidates as $candidate) {
        $id = (int) ($properties[$candidate] ?? 0);
        if ($id !== 0 && in_array($id, $existingObjects, true)) {
            return $id;
        }
    }

    return 0;
}

// Test 1: die Kachel-Visualisierung ("BaseID") - der einzige unterstuetzte und
// tatsaechlich verifizierte Fall - funktioniert unveraendert.
$tile = visuPropertiesReplica('{"BaseID":12345,"Automations":"[]","ShowGreeting":1,"GreetingName":"Willkommen"}');
assert(resolveRootReplica($tile, [12345]) === 12345, 'die Kachel-Visualisierung muss ihre Startkategorie weiterhin ueber "BaseID" liefern');
echo "Test 1 (Kachel-Visualisierung über 'BaseID' funktioniert unverändert) OK\n";

// Test 2: eine Startkategorie, deren Objekt nicht mehr existiert (geloeschte
// Kategorie), darf nicht durchgereicht werden - sonst liefe der Rescan auf eine
// Leiche statt sauber "Root fehlt" zu melden.
$stale = visuPropertiesReplica('{"BaseID":999}');
assert(resolveRootReplica($stale, [12345]) === 0, 'zeigt "BaseID" auf ein nicht mehr existierendes Objekt, muss 0 herauskommen');
echo "Test 2 (eine gelöschte Startkategorie führt sauber zu 'Root fehlt') OK\n";

// Test 3: DIE ECHTE WEBFRONT-KONFIGURATION (live von einer laufenden Instanz
// uebernommen, Zugangsdaten entfernt). Sie hat KEINE Startkategorie-Property
// auf oberster Ebene - ihr Aufbau steckt in "Items" (JSON-String mit Widgets,
// deren Kategorie-Verweise erst in einem ZWEITEN verschachtelten JSON liegen).
// Erwartet wird hier ausdruecklich 0, also ein sauberes "Root fehlt".
//
// Der Punkt dieses Tests ist die Absicherung gegen ein VERSEHENTLICHES Treffen:
// die Konfiguration enthaelt mit "MobileID" eine Property, die zufaellig genau
// auf dieselbe Kategorie zeigt. Wuerde die Kandidatenliste je um geratene Namen
// erweitert und ein solcher unspezifischer Name geriete hinein, wuerde
// stillschweigend ein Baum uebersetzt, den niemand konfiguriert hat - schlimmer
// als eine klare Fehlermeldung.
// Bewusst programmatisch aufgebaut statt als handgeschriebener JSON-String:
// die WebFront verschachtelt JSON in JSON in JSON, das von Hand korrekt zu
// escapen ist fehleranfaellig (und ging beim ersten Versuch prompt schief).
$webFrontConfig = [
    'Access'       => 1,
    'EnableMobile' => true,
    'Items'        => json_encode([
        ['ClassName' => 'ClockWidget', 'Configuration' => '', 'ID' => 'clock', 'ParentID' => 'roottp', 'Position' => 0, 'Visible' => true],
        ['ClassName' => 'Category', 'Configuration' => json_encode(['baseID' => 45747]), 'ID' => 'root', 'ParentID' => 'roottp', 'Position' => 0, 'Visible' => true],
        ['ClassName' => 'TabPane', 'Configuration' => json_encode(['subTitle' => 'IP-Symcon', 'subIcon' => 'IPS']), 'ID' => 'roottp', 'ParentID' => '', 'Position' => 0, 'Visible' => true],
    ]),
    'MobileID'     => 45747,
    'Nested'       => true,
];
$webFront = visuPropertiesReplica(json_encode($webFrontConfig));
assert($webFront !== [], 'die WebFront-Konfiguration muss sich ueberhaupt einlesen lassen');
assert(!array_key_exists('BaseID', $webFront), 'Beleg fuer die Diagnose: die WebFront hat keine "BaseID"-Property auf oberster Ebene');
assert(resolveRootReplica($webFront, [45747]) === 0, 'die nicht unterstuetzte WebFront muss sauber 0 liefern (-> "Root fehlt"), nicht versehentlich ueber einen unspezifischen Namen wie "MobileID" einen Baum erwischen');
echo "Test 3 (die echte WebFront-Konfiguration ergibt sauber 'Root fehlt', kein versehentlicher Treffer) OK\n";

// Test 4: DER ABSTURZ-SCHUTZ - fehlende Properties duerfen nur leere Werte
// liefern, niemals abbrechen. Genau hieran starb zuvor der komplette Rescan.
assert(($webFront['Automations'] ?? '') === '', 'eine fehlende "Automations"-Property muss ein leerer Wert sein, kein Abbruch');
assert((int) ($webFront['ShowGreeting'] ?? 0) === 0, 'eine fehlende "ShowGreeting"-Property muss 0 ergeben - das fuehrt in den "Begruessung aus"-Zweig');
assert(json_decode((string) ($webFront['Automations'] ?? ''), true) === null, 'json_decode auf der fehlenden Property ergibt null -> die Automations-Erfassung liefert sauber []');
echo "Test 4 (fehlende Properties liefern leere Werte statt einer Exception) OK\n";

// Test 5: auch voellig unbrauchbare Konfigurationen (nicht erreichbare Instanz,
// kaputtes JSON) duerfen nur ein leeres Array ergeben.
foreach ([null, '', 'kein json', '[]', 'null'] as $broken) {
    $result = visuPropertiesReplica($broken === null ? null : (string) $broken);
    assert(is_array($result), 'auch bei unbrauchbarer Konfiguration muss ein Array herauskommen');
    assert(resolveRootReplica($result, [1, 2, 3]) === 0, 'aus einer unbrauchbaren Konfiguration darf nie eine Startkategorie abgeleitet werden');
}
echo "Test 5 (unerreichbare Instanz oder kaputtes JSON ergeben sauber ein leeres Ergebnis) OK\n";

// Test 6: Symmetrie-Check - in der realen module.php darf KEIN direkter
// IPS_GetProperty()-Zugriff auf die fremde Visualisierungs-Instanz mehr stehen
// (auf die EIGENE Instanz ist er unbedenklich, deren Properties sind bekannt).
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$codeOnly = implode("\n", array_filter(
    explode("\n", $moduleSource),
    static fn (string $line): bool => !preg_match('/^\s*(\/\/|\*|\/\*)/', $line)
));
assert(strpos($codeOnly, 'IPS_GetProperty($webFrontID') === false, 'DER ABSTURZ: kein direkter IPS_GetProperty() auf die fremde Visualisierungs-Instanz mehr - das wirft bei unbekanntem Namen eine Exception, die "@" nicht abfaengt');
assert(strpos($moduleSource, 'private function GetVisuInstanceProperties(int $VisuInstanceID): array') !== false, 'der tolerante Sammel-Lesezugriff muss existieren');
assert(strpos($moduleSource, 'IPS_GetConfiguration($VisuInstanceID)') !== false, 'gelesen werden muss ueber IPS_GetConfiguration() - nur das vertraegt fehlende Properties');
echo "Test 6 (kein ungeschützter Lesezugriff auf die fremde Instanz mehr) OK\n";

// Test 7: dasselbe fuer die beiden SCHREIBENDEN Zugriffe - IPS_SetProperty()
// wirft bei unbekanntem Namen genauso. Erreichbar z.B., wenn eine Instanz
// zuerst mit der Kachel-Visualisierung lief (dabei entstehen Begruessungs-/
// Automations-Zeilen) und danach auf eine andere Instanz umgestellt wird.
assert(strpos($moduleSource, 'private function VisuInstanceHasProperty(int $VisuInstanceID, string $Name): bool') !== false, 'fuer die Schreibzugriffe muss es eine Existenzpruefung geben');
assert(strpos($moduleSource, "if (!\$this->VisuInstanceHasProperty(\$webFrontID, 'GreetingName')) {") !== false, 'der Schreibzugriff auf "GreetingName" muss vorher pruefen, ob es die Property dort ueberhaupt gibt');
assert(strpos($moduleSource, "if (\$changed && \$this->VisuInstanceHasProperty(\$webFrontID, 'Automations')) {") !== false, 'der Schreibzugriff auf "Automations" muss ebenso abgesichert sein');
echo "Test 7 (beide Schreibzugriffe prüfen vorher die Existenz der Property) OK\n";

// Test 8 (Build 145): die Kandidatenliste darf NUR verifizierte Namen
// enthalten. Kurzzeitig standen dort geratene Namen, um die WebFront
// mitzunehmen - nach deren Verwerfen sind sie nutzlos UND riskant: traefe so
// ein Name zufaellig eine gleichnamige Property eines fremden Moduls, wuerde
// stillschweigend der falsche Baum uebersetzt.
assert(preg_match('/VISU_ROOT_CATEGORY_PROPERTY_CANDIDATES\s*=\s*\[(.*?)\];/s', $moduleSource, $m) === 1, 'die Kandidatenliste muss auffindbar sein');
preg_match_all("/'([^']+)'/", $m[1], $names);
assert($names[1] === ['BaseID'], 'DIE GEFAHR: die Kandidatenliste darf ausschliesslich verifizierte Property-Namen enthalten (aktuell nur "BaseID") - geratene Namen koennen still den falschen Baum treffen. Gefunden: ' . implode(', ', $names[1]));
echo "Test 8 (die Kandidatenliste enthält ausschließlich verifizierte Namen) OK\n";

echo "\nAll tests passed.\n";
