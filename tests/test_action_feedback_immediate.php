<?php
declare(strict_types=1);
// Standalone replica test for build 209 (2026-09-03, Nutzer-Wunsch): der
// Fortschrittsbalken erschien erst nach 2-3 Sekunden, und in dieser Zeit liess
// sich munter weiterklicken.
//
// URSACHE: der Balken wurde erst NACH den Vorpruefungen eingeblendet - Root-Abruf
// ueber die Visu-Instanz, Lizenzpruefung, EnsureSourceLanguageIsTarget() und (seit
// Build 208) der Fingerabdruck ueber die gespeicherten Zeilen. Je nach Groesse der
// Installation vergehen dabei mehrere Sekunden, in denen sichtbar nichts geschieht.
//
// Zusaetzlich blieben alle Knoepfe waehrend eines womoeglich minutenlangen Laufs
// bedienbar - man klickt dann den naechsten an, weil scheinbar nichts passiert.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$form = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);

function funktion(string $module, string $name): string
{
    $a = (int) strpos($module, 'function ' . $name . '(');
    $b = (int) preg_match('/\n    (?:public|private|protected) function /', $module, $m, PREG_OFFSET_CAPTURE, $a + 10)
        ? $m[0][1] : strlen($module);

    return substr($module, $a, $b - $a);
}

// Test 1: DER FALL - in ScanRootTree() steht die Rueckmeldung VOR der ersten
// Pruefung. Alles andere kostet Zeit, bevor irgendetwas sichtbar wird.
$scan = funktion($module, 'ScanRootTree');
$posBalken = strpos($scan, "SetRescanProgress('Reading the tree…')");
$posErstePruefung = strpos($scan, 'ReadPropertyBoolean(self::propertyActive)');
assert($posBalken !== false && $posErstePruefung !== false, 'beide Stellen gefunden');
assert($posBalken < $posErstePruefung,
    'DER FALL: der Balken muss vor der ersten Pruefung erscheinen');
assert(strpos($scan, 'SetActionButtonsEnabled(false)') < $posErstePruefung,
    'und die Knopfsperre ebenso');
echo "Test 1 (Rescan meldet sich vor der ersten Prüfung) OK\n";

// Test 2: dasselbe beim Aufraeumen und bei der Anbieterpruefung.
foreach (['CleanupOrphanedRows' => 'CleanupProgressBar',
          'CheckProviders' => 'ProviderCheckProgressBar'] as $fn => $balken) {
    $body = funktion($module, $fn);
    $pos = strpos($body, "SetButtonProgress('" . $balken . "', '");
    $sperre = strpos($body, 'SetActionButtonsEnabled(false)');
    assert($pos !== false && $sperre !== false, "$fn: Balken und Sperre vorhanden");
    // Beides muss in den ersten Zeilen stehen, nicht erst nach Vorarbeit.
    assert(substr_count(substr($body, 0, $pos), "\n") < 8, "$fn: der Balken steht ganz vorn");
}
echo "Test 2 (Aufräumen und Anbieterprüfung ebenso) OK\n";

// Test 3: DIE BILANZ - jeder Ausstieg gibt die Knoepfe wieder frei. Bliebe einer
// uebrig, waere das Formular bis zum naechsten Neuaufbau blockiert.
foreach (['ScanRootTree', 'CleanupOrphanedRows', 'CheckProviders', 'ActivateLicense'] as $fn) {
    $body = funktion($module, $fn);
    $sperren = substr_count($body, 'SetActionButtonsEnabled(false)');
    $frei = substr_count($body, 'SetActionButtonsEnabled(true)');
    assert($sperren === 1, "$fn sperrt genau einmal");
    assert($frei >= 1, "$fn gibt wieder frei");
    // Je vorzeitigem Ausstieg ein Freigeben, plus einmal am regulaeren Ende.
    $ausstiege = substr_count($body, "\n            return;");
    assert($frei >= $ausstiege, "$fn: jeder vorzeitige Ausstieg gibt frei ($frei fuer $ausstiege)");
}
echo "Test 3 (jeder Ausstieg gibt die Knöpfe wieder frei) OK\n";

// Test 4: die Knoepfe sind ueberhaupt ansprechbar - ohne "name" laesst sich per
// UpdateFormField nichts an ihnen aendern. Genau daran scheiterte es bisher.
$namen = [];
$suche = function (array $n) use (&$suche, &$namen): void {
    foreach ($n as $v) {
        if (is_array($v)) {
            if (($v['type'] ?? '') === 'Button') {
                assert(($v['name'] ?? '') !== '', 'DIE VORAUSSETZUNG: jeder Button braucht einen Namen: ' . ($v['caption'] ?? '?'));
                $namen[] = $v['name'];
            }
            $suche($v);
        }
    }
};
$suche($form);
assert(count($namen) === 6, 'alle sechs Aktionsknoepfe sind benannt, gefunden: ' . count($namen));
foreach ($namen as $name) {
    assert(strpos($module, "'" . $name . "'") !== false, "\"$name\" muss in der Sperrliste stehen");
}
echo "Test 4 (alle Buttons sind benannt und in der Sperrliste) OK\n";

// Test 5: die Balken sind doppelt so breit wie zuvor - vorher hatten sie gar
// keine Breitenangabe und liefen auf der Voreinstellung.
$balken = 0;
$suche2 = function (array $n) use (&$suche2, &$balken): void {
    foreach ($n as $v) {
        if (is_array($v)) {
            if (($v['type'] ?? '') === 'ProgressBar') {
                assert(($v['width'] ?? '') === '600px', 'der Balken traegt eine ausdrueckliche Breite');
                $balken++;
            }
            $suche2($v);
        }
    }
};
$suche2($form);
assert($balken === 3, 'alle drei Balken, gefunden: ' . $balken);
echo "Test 5 (alle drei Balken sind breiter) OK\n";

echo "\nAlle Tests OK (Build 209: sofortige Rückmeldung, gesperrte Knöpfe).\n";
