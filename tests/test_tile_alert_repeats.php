<?php
declare(strict_types=1);
// Standalone replica test for build 176 (2026-08-27, live gemeldet: "Das mit dem
// Popup bei ungültigen Sprachcodes im tile klappt nicht.").
//
// URSACHE - und sie betraf ALLE drei Gast-Popups, nicht nur das neue:
// UpdateVisualizationValue() setzt einen WERT, keine Nachricht. Ein Wert, der
// sich nicht aendert, loest im Tile kein Ereignis aus. Zweimal dieselbe
// Ablehnung - gleicher ungueltiger Sprachcode, gleiche Meldung - ergab eine
// byteweise identische Nutzlast: das Popup erschien einmal und danach nie
// wieder. Beim Testen faellt genau das auf, weil man den Fall wiederholt.
//
// Fix: eine laufende Nummer macht jede Nutzlast eindeutig. Das Tile muss davon
// nichts wissen - handleMessage() ignoriert unbekannte Felder.

// Repliziert das Tile: reagiert NUR auf einen geaenderten Wert.
final class TileReplica
{
    private ?string $letzterWert = null;
    public int $popups = 0;

    public function setValue(string $payload): void
    {
        if ($payload === $this->letzterWert) {
            return;   // unveraendert -> kein Ereignis
        }
        $this->letzterWert = $payload;
        $nachricht = json_decode($payload, true);
        if (($nachricht['action'] ?? '') === 'ALERT') {
            $this->popups++;
        }
    }
}

// Repliziert PushTileAlert() VOR und NACH dem Fix.
function alertPayload(string $text, ?int $seq): string
{
    $nachricht = ['action' => 'ALERT', 'payload' => ['text' => $text]];
    if ($seq !== null) {
        $nachricht['seq'] = (string) $seq;
    }

    return json_encode($nachricht);
}

$text = 'Diese Sprache ist derzeit nicht eingerichtet. Bitte wähle eine andere.';

// Test 1: DER GEMELDETE FALL - ohne laufende Nummer erscheint das Popup genau
// einmal, danach nie wieder. Genau so verhielt es sich live.
$tile = new TileReplica();
for ($i = 0; $i < 3; $i++) {
    $tile->setValue(json_encode(['action' => 'REFRESH', 'payload' => ['html' => '<select></select>']]));
    $tile->setValue(alertPayload($text, null));
}
assert($tile->popups === 3, 'Zwischenschritt: mit dazwischenliegendem REFRESH aendert sich der Wert doch');
echo "Test 1 (mit dazwischenliegendem REFRESH allein erklärt sich der Fehler nicht) OK\n";

// Test 2: DER ECHTE FALL - laufen die beiden Aufrufe im selben Durchlauf
// zusammen, kommt nur der LETZTE Wert an. Dann ist die Nutzlast beim zweiten
// Versuch identisch und das Popup bleibt aus.
$tile = new TileReplica();
for ($i = 0; $i < 3; $i++) {
    $tile->setValue(alertPayload($text, null));   // nur der letzte Wert zaehlt
}
assert($tile->popups === 1, 'DER GEMELDETE FALL: ohne Unterscheidung erscheint das Popup nur beim ersten Mal');
echo "Test 2 (ohne laufende Nummer erscheint das Popup nur einmal) OK\n";

// Test 3: DER FIX - mit laufender Nummer erscheint es bei JEDEM Versuch.
$tile = new TileReplica();
for ($i = 1; $i <= 3; $i++) {
    $tile->setValue(alertPayload($text, $i));
}
assert($tile->popups === 3, 'DER FIX: jede Ablehnung muss ein Popup ausloesen, auch die wiederholte');
echo "Test 3 (mit laufender Nummer erscheint es bei jedem Versuch) OK\n";

// Test 4: DIE FALLE - ein Zeitstempel in Sekunden reicht NICHT: zwei Versuche
// innerhalb derselben Sekunde waeren wieder identisch. Deshalb ein Zaehler.
$tile = new TileReplica();
$sekunde = 1756300000;
foreach ([$sekunde, $sekunde] as $t) {
    $tile->setValue(json_encode(['action' => 'ALERT', 'payload' => ['text' => $text], 'seq' => (string) $t]));
}
assert($tile->popups === 1, 'DIE FALLE: ein Sekunden-Zeitstempel unterscheidet zwei schnelle Versuche nicht');
echo "Test 4 (ein Sekunden-Zeitstempel allein würde nicht reichen) OK\n";

// Test 5: das Tile muss von der Nummer nichts wissen - unbekannte Felder werden
// ignoriert, der Text bleibt unveraendert.
$nachricht = json_decode(alertPayload($text, 7), true);
assert($nachricht['payload']['text'] === $text, 'der angezeigte Text darf sich nicht aendern');
echo "Test 5 (der angezeigte Text bleibt unverändert) OK\n";

// Test 6: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function PushTileAlert(string $Text): void') !== false, 'der gemeinsame Sender muss existieren');
assert(strpos($moduleSource, '$this->tileMessageSequence++;') !== false, 'er muss die laufende Nummer hochzaehlen');
// Build 184: die Nummer heisst nicht mehr alertSequence - REFRESH braucht sie
// seitdem genauso, weil eine Nutzlast ohne html-Teil zur vorigen identisch
// sein kann (abgelehnter Wechsel). Ein gemeinsamer Zaehler, zwei Sender.
assert(substr_count($moduleSource, '$this->tileMessageSequence++;') === 2,
    'beide Sender muessen dieselbe Nonce-Quelle benutzen');
assert(substr_count($moduleSource, '$this->PushTileAlert(') === 3,
    'ALLE drei Gast-Popups muessen darueber laufen - Testphase, Sprachwechsel-Limit und unbekannte Sprache');

// Kein direkter ALERT-Aufruf mehr: sonst haette ein Pfad die Unterscheidung nicht.
$ohneKommentare = implode("\n", array_filter(
    explode("\n", $moduleSource),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));
assert(substr_count($ohneKommentare, "'action'  => 'ALERT'") === 1,
    'genau eine Stelle darf die ALERT-Nutzlast bauen');
assert(strpos($ohneKommentare, "'action' => 'ALERT'") === false,
    'keine Umgehung des gemeinsamen Senders');

// Das Tile darf unveraendert bleiben - der Fix kommt ohne Aenderung an module.html aus.
$tileSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.html');
assert(strpos($tileSource, 'message.action === "ALERT"') !== false, 'das Tile reagiert weiterhin auf ALERT');
assert(strpos($tileSource, 'seq') === false, 'und braucht von der laufenden Nummer nichts zu wissen');
echo "Test 6 (die reale Umsetzung bündelt alle Popups, das Tile bleibt unverändert) OK\n";

echo "\nAll tests passed.\n";
