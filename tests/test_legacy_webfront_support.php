<?php
declare(strict_types=1);
// Standalone replica test for build 144 (2026-08-24, Nutzer-Wunsch: auch die
// alte WebFront-Visualisierung unterstuetzen):
//
// Gemeldet war "er erkennt die RootKat bei der WebFront-Instanz nicht,
// vermutlich nutzt Symcon einen anderen Namen". Die Ursachensuche foerderte
// aber ein zweites, deutlich ernsteres Problem zutage:
//
// IPS_GetProperty() wirft bei einem UNBEKANNTEN Property-Namen eine Exception -
// und "@" unterdrueckt in PHP nur Warnungen, NIEMALS Exceptions. Der Code las
// die fremde Visualisierungs-Instanz an zehn Stellen direkt per
// @IPS_GetProperty($visu, 'Automations'/'GreetingName'/'ShowGreeting'/...) aus.
// Bei einer Instanz, die diese Properties nicht kennt - genau der Fall bei der
// alten WebFront-Visualisierung - riss also nicht nur die Root-Erkennung ab,
// sondern der komplette Rescan mit einer unbehandelten Exception.
//
// Beides ist hier abgesichert: der Zugriff laeuft jetzt ueber
// IPS_GetConfiguration() (liefert das JSON ALLER vorhandenen Properties, ein
// fehlender Schluessel ist ein normaler Array-Miss), und die Startkategorie
// wird ueber eine Kandidatenliste bekannter Property-Namen aufgeloest.

const ROOT_CANDIDATES = ['BaseID', 'BaseCategory', 'BaseCategoryID', 'RootID', 'RootCategoryID', 'CategoryID', 'StartCategoryID'];

// Repliziert GetVisuInstanceProperties(): tolerantes Lesen der Konfiguration.
function visuPropertiesReplica(?string $configurationJson): array
{
    $decoded = json_decode((string) $configurationJson, true);

    return is_array($decoded) ? $decoded : [];
}

// Repliziert ResolveVisuRootCategoryID(). $existingObjects simuliert
// IPS_ObjectExists().
function resolveRootReplica(array $properties, array $existingObjects): int
{
    foreach (ROOT_CANDIDATES as $candidate) {
        $id = (int) ($properties[$candidate] ?? 0);
        if ($id !== 0 && in_array($id, $existingObjects, true)) {
            return $id;
        }
    }

    return 0;
}

// Test 1: die Kachel-Visualisierung ("BaseID") funktioniert unveraendert - der
// bestehende, gut getestete Weg darf durch die Erweiterung nicht kippen.
$tile = visuPropertiesReplica('{"BaseID":12345,"Automations":"[]","ShowGreeting":1,"GreetingName":"Willkommen"}');
assert(resolveRootReplica($tile, [12345]) === 12345, 'die Kachel-Visualisierung muss ihre Startkategorie weiterhin ueber "BaseID" liefern');
echo "Test 1 (Kachel-Visualisierung über 'BaseID' funktioniert unverändert) OK\n";

// Test 2: DER GEMELDETE FALL - eine Visualisierung, die ihre Startkategorie
// unter einem ANDEREN Namen fuehrt, wird jetzt ebenfalls erkannt.
$legacy = visuPropertiesReplica('{"RootID":777}');
assert(resolveRootReplica($legacy, [777]) === 777, 'DER GEMELDETE FALL: eine Visualisierung mit abweichendem Property-Namen muss ueber die Kandidatenliste erkannt werden');
echo "Test 2 (abweichender Property-Name wird über die Kandidatenliste erkannt) OK\n";

// Test 3: die Reihenfolge zaehlt - "BaseID" gewinnt, wenn mehrere Kandidaten
// gleichzeitig belegt sind. Sonst koennte eine Instanz, die zufaellig noch eine
// alte Property mitschleppt, den falschen Baum liefern.
$both = visuPropertiesReplica('{"RootID":777,"BaseID":12345}');
assert(resolveRootReplica($both, [777, 12345]) === 12345, 'bei mehreren belegten Kandidaten muss die Reihenfolge der Liste entscheiden ("BaseID" zuerst)');
echo "Test 3 (bei mehreren Kandidaten entscheidet die Reihenfolge, BaseID zuerst) OK\n";

// Test 4: ein Kandidat, der auf ein NICHT existierendes Objekt zeigt (geloeschte
// Kategorie), wird uebersprungen - der naechste gueltige gewinnt. Sonst bliebe
// die Instanz wegen einer Leiche haengen, obwohl ein gueltiger Wert danebensteht.
$stale = visuPropertiesReplica('{"BaseID":999,"RootID":777}');
assert(resolveRootReplica($stale, [777]) === 777, 'ein Kandidat, dessen Objekt nicht mehr existiert, muss uebersprungen werden');
echo "Test 4 (ein Kandidat mit gelöschtem Objekt wird übersprungen) OK\n";

// Test 5: kein Kandidat passt -> 0, was sauber zu STATUS_ROOT_CATEGORY_MISSING
// fuehrt. Bewusst KEINE blinde Suche nach "irgendeiner ID-Property": die koennte
// eine Verweis-Property auf eine ganz andere Kategorie erwischen und
// stillschweigend den falschen Baum uebersetzen - deutlich schlimmer als eine
// klare Fehlermeldung.
$unknown = visuPropertiesReplica('{"SomeOtherSetting":42,"Title":"WebFront"}');
assert(resolveRootReplica($unknown, [42]) === 0, 'kennt die Instanz keinen der Kandidaten, muss 0 zurueckkommen (-> STATUS_ROOT_CATEGORY_MISSING) statt blind irgendeine ID zu nehmen');
echo "Test 5 (unbekannte Visualisierung liefert sauber 0 statt blind irgendeine ID) OK\n";

// Test 6: DER ABSTURZ-SCHUTZ - eine Instanz ohne die erwarteten Properties darf
// beim Lesen nur leere Werte liefern, niemals abbrechen.
$noProps = visuPropertiesReplica('{"RootID":777}');
assert(($noProps['Automations'] ?? '') === '', 'eine fehlende "Automations"-Property muss ein leerer Wert sein, kein Abbruch');
assert((int) ($noProps['ShowGreeting'] ?? 0) === 0, 'eine fehlende "ShowGreeting"-Property muss 0 ergeben - das fuehrt in den "Begruessung aus"-Zweig');
assert(json_decode((string) ($noProps['Automations'] ?? ''), true) === null, 'json_decode auf der fehlenden Property ergibt null -> die Automations-Erfassung liefert sauber []');
echo "Test 6 (fehlende Properties liefern leere Werte statt einer Exception) OK\n";

// Test 7: auch voellig unbrauchbare Konfigurationen (nicht erreichbare Instanz,
// kaputtes JSON) duerfen nur ein leeres Array ergeben.
foreach ([null, '', 'kein json', '[]', 'null'] as $broken) {
    $result = visuPropertiesReplica($broken === null ? null : (string) $broken);
    assert(is_array($result), 'auch bei unbrauchbarer Konfiguration muss ein Array herauskommen');
    assert(resolveRootReplica($result, [1, 2, 3]) === 0, 'aus einer unbrauchbaren Konfiguration darf nie eine Startkategorie abgeleitet werden');
}
echo "Test 7 (unerreichbare Instanz oder kaputtes JSON ergeben sauber ein leeres Ergebnis) OK\n";

// Test 8: Symmetrie-Check - in der realen module.php darf KEIN direkter
// IPS_GetProperty()/IPS_SetProperty()-Zugriff auf die fremde
// Visualisierungs-Instanz mehr stehen (nur noch auf die EIGENE Instanz).
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$codeOnly = implode("\n", array_filter(
    explode("\n", $moduleSource),
    static fn (string $line): bool => !preg_match('/^\s*(\/\/|\*|\/\*)/', $line)
));
assert(strpos($codeOnly, 'IPS_GetProperty($webFrontID') === false, 'DER ABSTURZ: kein direkter IPS_GetProperty() auf die fremde Visualisierungs-Instanz mehr - das wirft bei unbekanntem Namen eine Exception, die "@" nicht abfaengt');
assert(strpos($moduleSource, 'private function GetVisuInstanceProperties(int $VisuInstanceID): array') !== false, 'der tolerante Sammel-Lesezugriff muss existieren');
assert(strpos($moduleSource, 'IPS_GetConfiguration($VisuInstanceID)') !== false, 'gelesen werden muss ueber IPS_GetConfiguration() - nur das vertraegt fehlende Properties');
assert(strpos($moduleSource, 'private function VisuInstanceHasProperty(int $VisuInstanceID, string $Name): bool') !== false, 'fuer die Schreibzugriffe muss es eine Existenzpruefung geben');
// Beide Schreibzugriffe auf die fremde Instanz muessen abgesichert sein.
assert(strpos($moduleSource, "if (!\$this->VisuInstanceHasProperty(\$webFrontID, 'GreetingName')) {") !== false, 'der Schreibzugriff auf "GreetingName" muss vorher pruefen, ob es die Property dort ueberhaupt gibt');
assert(strpos($moduleSource, "if (\$changed && \$this->VisuInstanceHasProperty(\$webFrontID, 'Automations')) {") !== false, 'der Schreibzugriff auf "Automations" muss ebenso abgesichert sein');
echo "Test 8 (die reale module.php greift nirgends mehr ungeschützt auf die fremde Instanz zu) OK\n";

// Test 9: Symmetrie-Check der Kandidatenliste - "BaseID" muss der erste Eintrag
// bleiben, damit die Kachel-Visualisierung immer gewinnt.
assert(preg_match('/VISU_ROOT_CATEGORY_PROPERTY_CANDIDATES\s*=\s*\[\s*\n\s*\'BaseID\'/', $moduleSource) === 1, 'die Kandidatenliste muss mit "BaseID" beginnen, damit die Kachel-Visualisierung immer Vorrang hat');
echo "Test 9 ('BaseID' steht an erster Stelle der Kandidatenliste) OK\n";

echo "\nAll tests passed.\n";
