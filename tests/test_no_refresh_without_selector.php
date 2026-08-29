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

// NACHTRAG Build 184: der Schutz bleibt, sein Zuschnitt wird praeziser. Frueher
// wurde die GANZE Nachricht unterdrueckt. Zerstoererisch war aber immer nur das
// Feld "html" - und mit <!--ACTIVE_LANGUAGE-->/<!--AVAILABLE_LANGUAGES--> gibt
// es jetzt Daten, die eine Vorlage mit eigener Auswahl sehr wohl braucht: ohne
// sie fror ihr Stand auf dem Ladezeitpunkt ein. Also wird jetzt genau das eine
// Feld weggelassen, statt die Nachricht zu verwerfen.

// Repliziert PushVisualizationUpdate() NACH Build 184.
function pushReplica(string $tileHtml): array
{
    $payload = ['activeLanguage' => 'de', 'languages' => [['code' => 'de', 'name' => 'Deutsch', 'current' => true]]];
    if (strpos($tileHtml, '<!--LANGUAGE_SELECT-->') !== false) {
        $payload['html'] = '<select>...</select>';
    }

    return $payload;
}

// Test 1: DER GESCHUETZTE FALL - module.html, bei dem der Platzhalter durch
// eigene Flaggen ersetzt wurde, der Handler aber noch drin ist. Kein html, also
// nichts, was ihr Layout loeschen koennte.
$angepasst = '<html><body><div id="w"><span>DE</span></div>'
    . '<script>function handleMessage(d){/* enthaelt REFRESH-Zweig */}</script></body></html>';
$payload = pushReplica($angepasst);
assert(!isset($payload['html']),
    'DER FIX: ohne Platzhalter darf kein html gesendet werden - auch nicht an einen eigenen Handler');
echo "Test 1 (kein html an eine Vorlage ohne Platzhalter) OK\n";

// Test 2: die Daten kommen trotzdem an - sonst waere <!--ACTIVE_LANGUAGE--> in
// genau den Vorlagen tot, die es brauchen.
assert($payload['activeLanguage'] === 'de' && $payload['languages'] !== [],
    'die Daten muessen auch ohne html ankommen');
echo "Test 2 (die Daten erreichen auch eine Vorlage ohne Platzhalter) OK\n";

// Test 3: die unveraenderte module.html bekommt das html weiterhin - dort ist
// das Neuzeichnen ja sinnvoll und zielt auf genau den richtigen Bereich.
$original = '<html><body><div id="w"><!--LANGUAGE_SELECT--></div></body></html>';
assert(isset(pushReplica($original)['html']), 'die unveraenderte Vorlage muss weiterhin neu gezeichnet werden');
echo "Test 3 (die unveränderte Vorlage wird weiterhin neu gezeichnet) OK\n";

// Test 4: Symmetrie-Check - die Pruefung muss aus DERSELBEN Quelle lesen wie die
// spaetere Ausgabe, sonst laufen Pruefung und Ergebnis auseinander.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function ActiveTileSupportsRefresh(): bool') !== false, 'die Pruefung muss existieren');

$pushStart = strpos($moduleSource, 'private function PushVisualizationUpdate');
$pushBody = substr($moduleSource, $pushStart, strpos($moduleSource, "\n    private function ", $pushStart + 10) - $pushStart);
assert(strpos($pushBody, "if (\$this->ActiveTileSupportsRefresh()) {") !== false,
    'und genau das html-Feld daran haengen');
assert(strpos($pushBody, "\$payload['html'] = ") !== false, 'nur dieses eine Feld steht unter der Bedingung');
// Die Nachricht selbst darf NICHT mehr davon abhaengen - sonst waeren die Daten
// wieder genau dort tot, wo sie gebraucht werden.
assert(strpos($pushBody, 'if (!$this->ActiveTileSupportsRefresh()) {') === false,
    'der Versand als Ganzes darf nicht mehr abgebrochen werden');
$bedingung = strpos($pushBody, 'ActiveTileSupportsRefresh');
$versand = strpos($pushBody, 'UpdateVisualizationValue');
assert($bedingung < $versand, 'die Bedingung muss VOR dem Versand ausgewertet werden');

$checkStart = strpos($moduleSource, 'private function ActiveTileSupportsRefresh');
$checkBody = substr($moduleSource, $checkStart, 1200);
foreach (['propertyUseCustomTile', 'propertyCustomTileHtml', 'GetSelectedTileTemplateHtml'] as $quelle) {
    assert(strpos($checkBody, $quelle) !== false, "die Pruefung muss dieselbe Quelle wie GetVisualizationTile beruecksichtigen: $quelle");
}
echo "Test 4 (die Prüfung liest aus derselben Quelle wie die Ausgabe) OK\n";

// Test 5: die Gast-Hinweise bleiben unberuehrt - sie laufen ueber ALERT und
// haben mit dem Neuzeichnen nichts zu tun. Genau das war die Beschwerde, mit der
// die ganze Runde anfing.
assert(strpos($moduleSource, 'private function PushTileAlert(string $Text): void') !== false, 'der Hinweis-Sender bleibt eigenstaendig');
$alertStart = strpos($moduleSource, 'private function PushTileAlert');
$alertBody = substr($moduleSource, $alertStart, 900);
assert(strpos($alertBody, 'ActiveTileSupportsRefresh') === false,
    'die Hinweise duerfen NICHT an der Neuzeichnen-Bedingung haengen - sie muessen jede Kachel erreichen');
echo "Test 5 (die Gast-Hinweise hängen nicht an dieser Bedingung) OK\n";

echo "\nAll tests passed.\n";
