<?php
declare(strict_types=1);
// Standalone replica test for build 149 (2026-08-25, Nutzer-Wunsch beim
// Testen):
//
// Ein Rescan bricht ab, solange irgendein Objekt im Baum keinen Namen hat - ein
// leerer Name laesst sich nicht uebersetzen. Beim Einrichten eines groesseren
// Baums sind das schnell Dutzende Objekte, und der Grossteil davon sind
// VERKNUEPFUNGEN: Symcon zeigt fuer eine namenlose Verknuepfung automatisch den
// Namen ihres Ziels an. In der Visualisierung sieht also alles richtig aus,
// waehrend IPS_GetName() leer bleibt - der Admin muesste von Hand exakt den
// Namen abtippen, den Symcon ohnehin schon anzeigt.
//
// Der neue Knopf uebernimmt genau das. Optisch aendert sich dadurch nichts, es
// gibt also auch nichts zu ueberschreiben - deshalb ohne Rueckfrage.

const OBJECTTYPE_CATEGORY_T = 0;
const OBJECTTYPE_VARIABLE_T = 2;
const OBJECTTYPE_LINK_T     = 6;

// Repliziert IsUnnamedObject().
function istUnbenannt(int $id, string $name): bool
{
    return $name === '' || preg_match('/\(ID:\s*' . $id . '\)\s*$/', $name) === 1;
}

// Repliziert NameUnnamedLinks() gegen einen simulierten Objektbaum.
// $baum: id => ['type'=>..., 'name'=>..., 'target'=>?int, 'locked'=>?bool]
function benenneLinks(array $unbenannteIds, array &$baum): array
{
    $renamed = 0;
    $skipped = 0;

    foreach ($unbenannteIds as $id) {
        if (!isset($baum[$id])) {
            continue;   // Objekt existiert nicht mehr - still uebergehen
        }
        if ($baum[$id]['type'] !== OBJECTTYPE_LINK_T) {
            $skipped++;
            continue;
        }
        $target = $baum[$id]['target'] ?? 0;
        if ($target === 0 || !isset($baum[$target])) {
            $skipped++;
            continue;
        }
        $zielName = $baum[$target]['name'];
        if (istUnbenannt($target, $zielName)) {
            $skipped++;
            continue;
        }
        if (!($baum[$id]['locked'] ?? false)) {
            $baum[$id]['name'] = $zielName;
        }
        // Nur zaehlen, was tatsaechlich angekommen ist.
        if ($baum[$id]['name'] === $zielName) {
            $renamed++;
        } else {
            $skipped++;
        }
    }

    return ['renamed' => $renamed, 'skipped' => $skipped];
}

// Test 1: DER HAUPTFALL - eine namenlose Verknuepfung uebernimmt den Namen
// ihres Ziels.
$baum = [
    10 => ['type' => OBJECTTYPE_LINK_T, 'name' => '', 'target' => 20],
    20 => ['type' => OBJECTTYPE_VARIABLE_T, 'name' => 'Wohnzimmer Temperatur'],
];
$r = benenneLinks([10], $baum);
assert($baum[10]['name'] === 'Wohnzimmer Temperatur', 'DER HAUPTFALL: die Verknuepfung muss den Namen ihres Ziels uebernehmen');
assert($r === ['renamed' => 1, 'skipped' => 0], 'genau eine Umbenennung, nichts uebersprungen');
echo "Test 1 (eine namenlose Verknüpfung übernimmt den Namen ihres Ziels) OK\n";

// Test 2: auch der Platzhalter-Name "... (ID: n)" gilt als unbenannt und wird
// ersetzt - genau diese Objekte meldet der Rescan ja an.
$baum = [
    11 => ['type' => OBJECTTYPE_LINK_T, 'name' => 'Link (ID: 11)', 'target' => 21],
    21 => ['type' => OBJECTTYPE_VARIABLE_T, 'name' => 'Küche Licht'],
];
assert(istUnbenannt(11, 'Link (ID: 11)'), 'der Platzhalter-Name muss als unbenannt gelten');
benenneLinks([11], $baum);
assert($baum[11]['name'] === 'Küche Licht', 'auch ein Platzhalter-Name muss ersetzt werden');
echo "Test 2 (auch der Platzhalter-Name '(ID: n)' wird ersetzt) OK\n";

// Test 3: NICHT-Verknuepfungen bleiben unangetastet. Eine unbenannte Kategorie
// hat kein Ziel, aus dem sich ein Name ableiten liesse - die muss der Admin
// selbst benennen, und sie muss in der Rueckmeldung auftauchen.
$baum = [12 => ['type' => OBJECTTYPE_CATEGORY_T, 'name' => '']];
$r = benenneLinks([12], $baum);
assert($baum[12]['name'] === '', 'eine Kategorie darf nicht automatisch benannt werden');
assert($r === ['renamed' => 0, 'skipped' => 1], 'sie muss als "nicht automatisch benennbar" gemeldet werden');
echo "Test 3 (Nicht-Verknüpfungen bleiben unangetastet und werden gemeldet) OK\n";

// Test 4: WICHTIG - zeigt die Verknuepfung auf ein selbst unbenanntes Ziel,
// waere der uebernommene Name genauso wertlos. Dann lieber stehen lassen, damit
// der Admin die eigentliche Ursache sieht, statt eine Platzhalter-Kette zu
// bauen.
$baum = [
    13 => ['type' => OBJECTTYPE_LINK_T, 'name' => '', 'target' => 23],
    23 => ['type' => OBJECTTYPE_VARIABLE_T, 'name' => ''],
];
$r = benenneLinks([13], $baum);
assert($baum[13]['name'] === '', 'DIE FALLE: ein selbst unbenanntes Ziel darf keinen leeren Namen weiterreichen');
assert($r['skipped'] === 1, 'dieser Fall muss als uebersprungen gemeldet werden');
echo "Test 4 (ein selbst unbenanntes Ziel erzeugt keine wertlose Platzhalter-Kette) OK\n";

// Test 5: fehlendes oder geloeschtes Ziel - kein Absturz, sauber uebersprungen.
$baum = [14 => ['type' => OBJECTTYPE_LINK_T, 'name' => '', 'target' => 999]];
$r = benenneLinks([14], $baum);
assert($r === ['renamed' => 0, 'skipped' => 1], 'eine Verknuepfung ins Leere muss sauber uebersprungen werden');
// Ein inzwischen ganz geloeschtes Objekt aus der Liste zaehlt gar nicht mit.
$leer = [];
assert(benenneLinks([777], $leer) === ['renamed' => 0, 'skipped' => 0], 'ein nicht mehr existierendes Objekt darf weder zaehlen noch abbrechen');
echo "Test 5 (fehlendes Ziel oder gelöschtes Objekt brechen nicht ab) OK\n";

// Test 6: EHRLICHE ZAEHLUNG - ein gesperrtes Objekt lehnt das Umbenennen ab.
// Das darf NICHT als Erfolg gemeldet werden, sonst wundert sich der Admin,
// warum der Rescan danach immer noch meckert.
$baum = [
    15 => ['type' => OBJECTTYPE_LINK_T, 'name' => '', 'target' => 25, 'locked' => true],
    25 => ['type' => OBJECTTYPE_VARIABLE_T, 'name' => 'Flur Bewegung'],
];
$r = benenneLinks([15], $baum);
assert($baum[15]['name'] === '', 'ein gesperrtes Objekt bleibt unbenannt');
assert($r === ['renamed' => 0, 'skipped' => 1], 'DIE UNEHRLICHKEIT: eine fehlgeschlagene Umbenennung darf nicht als Erfolg gezaehlt werden');
echo "Test 6 (eine fehlgeschlagene Umbenennung wird ehrlich als übersprungen gezählt) OK\n";

// Test 7: gemischter Bestand - typischer Praxisfall.
$baum = [
    30 => ['type' => OBJECTTYPE_LINK_T, 'name' => '', 'target' => 40],
    31 => ['type' => OBJECTTYPE_LINK_T, 'name' => '', 'target' => 41],
    32 => ['type' => OBJECTTYPE_CATEGORY_T, 'name' => ''],
    40 => ['type' => OBJECTTYPE_VARIABLE_T, 'name' => 'Bad Heizung'],
    41 => ['type' => OBJECTTYPE_VARIABLE_T, 'name' => 'Bad Fenster'],
];
$r = benenneLinks([30, 31, 32], $baum);
assert($r === ['renamed' => 2, 'skipped' => 1], 'zwei Verknuepfungen benannt, die Kategorie gemeldet');
assert($baum[30]['name'] === 'Bad Heizung' && $baum[31]['name'] === 'Bad Fenster', 'beide Verknuepfungen muessen ihren jeweils eigenen Zielnamen bekommen');
echo "Test 7 (gemischter Bestand: Verknüpfungen benannt, Rest korrekt gemeldet) OK\n";

// Test 8: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
assert(strpos($constantsSource, "identNameUnnamedLinks = 'NameUnnamedLinks'") !== false, 'der Aktions-Ident muss deklariert sein');
assert(strpos($moduleSource, 'private function NameUnnamedLinks(): void') !== false, 'die Funktion muss existieren');
assert(strpos($moduleSource, 'case self::identNameUnnamedLinks:') !== false, 'die Aktion muss in RequestAction verdrahtet sein');
assert(strpos($moduleSource, "(\$object['ObjectType'] ?? -1) !== OBJECTTYPE_LINK") !== false, 'es duerfen ausschliesslich Verknuepfungen angefasst werden');
assert(strpos($moduleSource, '$this->IsUnnamedObject($targetID, $targetName)') !== false, 'ein selbst unbenanntes Ziel muss abgefangen werden');
// Die ehrliche Zaehlung: nach dem Schreiben wird der Name nochmal gelesen.
assert(strpos($moduleSource, "if ((string) @IPS_GetName(\$objectID) === \$targetName) {") !== false, 'gezaehlt werden darf nur, was nachweislich angekommen ist');
$formJson = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
assert(strpos($formJson, 'NameUnnamedLinksRow') !== false, 'der Knopf muss im Formular liegen');
assert(strpos($formJson, 'LinksNamedPopup') !== false, 'das Ergebnis-Popup muss im Formular liegen');
assert(strpos($moduleSource, "case 'NameUnnamedLinksRow':") !== false, 'der Knopf muss die Sichtbarkeit der Liste teilen (nur bei unbenannten Objekten)');
echo "Test 8 (die reale Umsetzung ist vollständig verdrahtet) OK\n";

echo "\nAll tests passed.\n";
