<?php
declare(strict_types=1);
// Standalone replica test for build 180 (2026-08-28, Folgerung aus der
// Vorlagen-Diskussion: "also nehme ich zukünftig die module.html und passe diese
// an").
//
// Genau dieser - richtige - Weg fuehrte noch in eine Falle. Build 179 hat das
// zerstoererische Neuzeichnen fuer den vom Modul ERGAENZTEN Handler abgestellt.
// Wer aber module.html als Vorlage nimmt und nur <!--LANGUAGE_SELECT--> durch
// eigenes Markup ersetzt, bringt den Handler SELBST mit - er wird also nicht
// ergaenzt, und seine REFRESH-Behandlung haette weiterhin den Inhalt des
// Wrappers geloescht.
//
// Deshalb jetzt an der Quelle: benutzt die aktive Vorlage den Platzhalter nicht,
// wird gar kein REFRESH mehr gesendet. Was nicht gesendet wird, kann nichts
// zerstoeren - unabhaengig davon, welcher Handler in der Kachel sitzt.

// Repliziert PushVisualizationUpdate() NACH dem Fix.
function pushReplica(string $tileHtml, array &$gesendet): void
{
    if (strpos($tileHtml, '<!--LANGUAGE_SELECT-->') === false) {
        return;
    }
    $gesendet[] = 'REFRESH';
}

// Test 1: DER GESCHUETZTE FALL - module.html, bei dem der Platzhalter durch
// eigene Flaggen ersetzt wurde, der Handler aber noch drin ist.
$angepasst = '<html><body><div id="w"><span>🇩🇪</span></div>'
    . '<script>function handleMessage(d){/* enthaelt REFRESH-Zweig */}</script></body></html>';
$gesendet = [];
pushReplica($angepasst, $gesendet);
assert($gesendet === [], 'DER FIX: ohne Platzhalter darf kein REFRESH gesendet werden - auch nicht an einen eigenen Handler');
echo "Test 1 (kein REFRESH an eine Vorlage ohne Platzhalter) OK\n";

// Test 2: die unveraenderte module.html bekommt es weiterhin - dort ist das
// Neuzeichnen ja sinnvoll und zielt auf genau den richtigen Bereich.
$original = '<html><body><div id="w"><!--LANGUAGE_SELECT--></div></body></html>';
$gesendet = [];
pushReplica($original, $gesendet);
assert($gesendet === ['REFRESH'], 'die unveraenderte Vorlage muss weiterhin neu gezeichnet werden');
echo "Test 2 (die unveränderte Vorlage wird weiterhin neu gezeichnet) OK\n";

// Test 3: Symmetrie-Check - die Pruefung muss aus DERSELBEN Quelle lesen wie die
// spaetere Ausgabe, sonst laufen Pruefung und Ergebnis auseinander.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function ActiveTileSupportsRefresh(): bool') !== false, 'die Pruefung muss existieren');

$pushStart = strpos($moduleSource, 'private function PushVisualizationUpdate');
$pushBody = substr($moduleSource, $pushStart, strpos($moduleSource, "\n    private function ", $pushStart + 10) - $pushStart);
assert(strpos($pushBody, 'if (!$this->ActiveTileSupportsRefresh()) {') !== false, 'und den Versand abbrechen');
$abbruch = strpos($pushBody, 'ActiveTileSupportsRefresh');
$versand = strpos($pushBody, 'UpdateVisualizationValue');
assert($abbruch < $versand, 'die Pruefung muss VOR dem Versand stehen');

$checkStart = strpos($moduleSource, 'private function ActiveTileSupportsRefresh');
$checkBody = substr($moduleSource, $checkStart, 1200);
foreach (['propertyUseCustomTile', 'propertyCustomTileHtml', 'GetSelectedTileTemplateHtml'] as $quelle) {
    assert(strpos($checkBody, $quelle) !== false, "die Pruefung muss dieselbe Quelle wie GetVisualizationTile beruecksichtigen: $quelle");
}
echo "Test 3 (die Prüfung liest aus derselben Quelle wie die Ausgabe) OK\n";

// Test 4: die Gast-Hinweise bleiben unberuehrt - sie laufen ueber ALERT und
// haben mit dem Neuzeichnen nichts zu tun. Genau das war die Beschwerde, mit der
// die ganze Runde anfing.
assert(strpos($moduleSource, 'private function PushTileAlert(string $Text): void') !== false, 'der Hinweis-Sender bleibt eigenstaendig');
$alertStart = strpos($moduleSource, 'private function PushTileAlert');
$alertBody = substr($moduleSource, $alertStart, 900);
assert(strpos($alertBody, 'ActiveTileSupportsRefresh') === false,
    'die Hinweise duerfen NICHT an der Neuzeichnen-Bedingung haengen - sie muessen jede Kachel erreichen');
echo "Test 4 (die Gast-Hinweise hängen nicht an dieser Bedingung) OK\n";

echo "\nAll tests passed.\n";
