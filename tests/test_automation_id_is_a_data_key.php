<?php
declare(strict_types=1);
// Standalone replica test for build 206 (2026-09-03, live gemeldet): die
// Automations der Kachel-Visualisierung wurden nicht mehr eingelesen.
//
// URSACHE - selbst verursacht in Build 185: bei der Umstellung auf Englisch als
// Quellsprache wurden alle deutschen Literale mechanisch durch ihre englische
// Uebersetzung ersetzt. "AutomationID" war zufaellig BEIDES: die deutsche
// Uebersetzung der Beschriftung "Automation ID" - UND der Name eines
// Datenfeldes. Die Ersetzung machte daraus ueberall "Automation ID", mit Leerzeichen.
//
// Damit las ScanAutomationsByID() die Automations-Property der Visu-Instanz
// unter einem Schluessel, den es dort nicht gibt: jeder Eintrag fiel durch, die
// Liste blieb leer. Zusaetzlich passten die gespeicherten Zeilen und die
// Formularspalte nicht mehr zusammen.
//
// Die Lehre: ein Datenschluessel darf nie zugleich Uebersetzungsschluessel sein.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$form = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);
$locale = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);

// Test 1: DER FEHLER - der Schluessel der Visu-Property heisst "AutomationID".
$scan = substr($module, (int) strpos($module, 'private function ScanAutomationsByID'), 1400);
assert(strpos($scan, "\$entry['AutomationID']") !== false,
    'DER FEHLER: die Automations-Property wird unter ihrem echten Schluessel gelesen');
assert(strpos($scan, "'Automation ID'") === false, 'kein Leerzeichen im Datenschluessel');
assert(strpos($scan, "GetVisuInstanceProperty(\$webFrontID, 'Automations'") !== false,
    'und zwar aus der Kachel-Visualisierungs-Instanz');
echo "Test 1 (die Visu-Property wird unter dem echten Schlüssel gelesen) OK\n";

// Test 2: derselbe Schluessel ueberall - Zeilen, Merge, Aufraeumen. Ein
// einzelner Ausreisser reichte, um die Zuordnung zu zerreissen.
// Die BESCHRIFTUNG darf "Automation ID" heissen - nur als Zugriff oder als
// Spaltenname waere das Leerzeichen der Fehler.
assert(strpos($module, "['Automation ID']") === false, 'kein Zugriff ueber den Anzeigetext');
assert(strpos($module, "'name' => 'Automation ID'") === false, 'und kein Spaltenname daraus');
assert(substr_count($module, "'Automation ID'") === 1, 'der Text kommt nur noch als Beschriftung vor');
assert(substr_count($module, "'AutomationID'") >= 6, 'der Schluessel kommt an allen Stellen vor');
echo "Test 2 (überall derselbe Schlüssel) OK\n";

// Test 3: die SPALTE traegt den Datenschluessel als "name" - die Beschriftung
// darf dagegen uebersetzt werden. Beides zu vermischen war der Ausloeser.
$gefunden = false;
$suche = function (array $n) use (&$suche, &$gefunden): void {
    foreach ($n as $v) {
        if (is_array($v)) {
            if (($v['name'] ?? '') === 'ObjectAutomations') {
                foreach ($v['columns'] ?? [] as $c) {
                    if (($c['name'] ?? '') === 'AutomationID') {
                        $gefunden = true;
                    }
                    assert(($c['name'] ?? '') !== 'Automation ID', 'die Spalte darf nicht auf den Anzeigetext hoeren');
                }
            }
            $suche($v);
        }
    }
};
$suche($form);
assert($gefunden, 'die Automations-Liste hat eine Spalte namens AutomationID');
assert(strpos($module, "['caption' => 'Automation ID', 'name' => 'AutomationID'") !== false,
    'auch die dynamisch gebaute Spalte trennt Beschriftung und Schluessel');
echo "Test 3 (Beschriftung übersetzt, Spaltenname nicht) OK\n";

// Test 4: DIE LEHRE - kein Datenschluessel darf zugleich Uebersetzungsschluessel
// sein, sonst trifft ihn die naechste mechanische Ersetzung wieder.
$schluessel = ['AutomationID', 'ORIGINAL_IMPORT', 'ObjectID', 'ChartID', 'VariableID', 'Quellsprache'];
foreach ($schluessel as $key) {
    foreach ($locale['translations'] as $sprache => $eintraege) {
        assert(!isset($eintraege[$key]),
            "DIE LEHRE: \"$key\" ist ein Datenschluessel und darf in \"$sprache\" kein Uebersetzungseintrag sein");
    }
}
echo "Test 4 (kein Datenschlüssel ist zugleich Übersetzungsschlüssel) OK\n";

echo "\nAlle Tests OK (Build 206: AutomationID ist wieder ein Datenschlüssel).\n";
