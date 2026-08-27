<?php
declare(strict_types=1);
// Standalone replica test for build 174 (2026-08-27, live gefunden): Symbol und
// Vorlage einer Edition kamen nie in den Auswahlfeldern an, obwohl der Server
// sie nachweislich auslieferte (HTTP 200 mit "assets" im JSON).
//
// URSACHENKETTE:
// 1. Beim ersten Aktivieren antwortete der Endpunkt mit HTTP 500 (eine fehlende
//    require-Zeile auf der Website).
// 2. CallActivationReportAPI() wertete ALLES ausser einem Transportfehler als
//    "angekommen" - eine 500 liefert aber eine nicht-leere Fehlerseite als Body.
//    Der Schluessel galt damit als erfolgreich gemeldet.
// 3. Seitdem lief jeder Klick ueber den statusOnly-Pfad, und dort laesst der
//    Server die Designs bewusst weg. Das konnte sich nie von selbst aufloesen:
//    Designs reisen nur mit einer ECHTEN Aktivierung mit, und die findet je
//    Schluessel genau einmal statt.
//
// Zwei Reparaturen: ein HTTP-Fehlerstatus zaehlt nicht mehr als angekommen, und
// der ausdrueckliche Klick darf die Designs zusaetzlich anfordern.

// Repliziert CallActivationReportAPI() NACH dem Fix.
function callReplica($transport, int $httpStatus): ?string
{
    if ($transport === false) { return null; }
    if ($httpStatus >= 400) { return null; }

    return (string) $transport;
}

// Test 1: DIE URSACHE - eine 500 mit Fehlerseite darf nicht mehr als Erfolg
// gelten, sonst wird der Schluessel faelschlich als gemeldet vermerkt.
assert(callReplica('<html>500 Internal Server Error</html>', 500) === null,
    'DIE URSACHE: eine HTTP 500 darf NICHT als angekommen gelten - sie hat einen nicht-leeren Body');
assert(callReplica('', 204) === '', 'eine leere 204 bleibt Erfolg - "angekommen, nichts zu melden"');
assert(callReplica('{"active":true}', 200) === '{"active":true}', 'eine echte Antwort bleibt Erfolg');
assert(callReplica(false, 0) === null, 'ein Transportfehler bleibt Fehlschlag');
echo "Test 1 (ein HTTP-Fehlerstatus zählt nicht mehr als angekommen) OK\n";

// Test 2: DIE FOLGE - mit dem Fix bleibt der Schluessel unerledigt, die
// Tagespruefung holt die Meldung nach (siehe Build 170) und bekommt dabei die
// Designs mit, weil eine echte Meldung kein statusOnly ist.
$reported = '';
$antwort = callReplica('<html>500</html>', 500);
if ($antwort !== null) { $reported = 'abc'; }
assert($reported === '', 'nach einer 500 darf der Schluessel nicht als gemeldet gelten - sonst gibt es nie ein Nachholen');
echo "Test 2 (nach einer 500 bleibt die Meldung offen und wird nachgeholt) OK\n";

// Test 3: DER ZWEITE WEG - der ausdrueckliche Klick fordert die Designs an,
// ohne eine weitere Aktivierung einzutragen. Ohne diesen Weg bliebe eine
// Instanz, deren Schluessel laengst gemeldet ist, dauerhaft ohne Designs.
function serverReplica(bool $statusOnly, bool $withAssets): array
{
    $antwort = ['active' => true];
    if (!$statusOnly || $withAssets) { $antwort['assets'] = 'signiertes-paket'; }
    // Eine Aktivierung wird AUSSCHLIESSLICH ohne statusOnly eingetragen.
    $antwort['_aktivierungEingetragen'] = !$statusOnly;

    return $antwort;
}
$klick = serverReplica(true, true);
assert(isset($klick['assets']), 'DER FIX: der ausdrueckliche Klick muss die Designs bekommen');
assert($klick['_aktivierungEingetragen'] === false, 'DER KERN: er darf dabei KEINE Aktivierung eintragen');
echo "Test 3 (der ausdrückliche Klick holt Designs ohne neue Aktivierung) OK\n";

// Test 4: die Tagespruefung fragt bewusst NICHT nach - sie liefe sonst jeden Tag
// fuer jede Installation unnoetig gross.
$taeglich = serverReplica(true, false);
assert(!isset($taeglich['assets']), 'die Tagespruefung darf die Designs nicht anfordern');
echo "Test 4 (die Tagesprüfung bleibt klein) OK\n";

// Test 5: eine echte Aktivierung bekommt sie weiterhin ungefragt.
$echt = serverReplica(false, false);
assert(isset($echt['assets']) && $echt['_aktivierungEingetragen'] === true,
    'eine echte Aktivierung bekommt die Designs wie bisher und wird eingetragen');
echo "Test 5 (eine echte Aktivierung bekommt sie wie bisher) OK\n";

// Test 6: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'if ($httpStatus >= 400) {') !== false, 'der HTTP-Status muss geprueft werden');
assert(strpos($moduleSource, 'CURLINFO_RESPONSE_CODE') !== false, 'und dafuer wirklich ausgelesen werden');

$fetchStart = strpos($moduleSource, 'private function FetchLicenseStatus');
$fetch = substr($moduleSource, $fetchStart, 1400);
assert(strpos($fetch, "\$entry['withAssets'] = true;") !== false, 'die Bitte um die Designs muss uebertragen werden');
assert(strpos($fetch, "'statusOnly'     => true,") !== false, 'sie darf statusOnly NICHT aufheben - sonst entstuende eine zweite Aktivierung');

$activateStart = strpos($moduleSource, 'private function ActivateLicense');
$activate = substr($moduleSource, $activateStart, 2500);
assert(strpos($activate, '$this->FetchLicenseStatus(hash(\'sha256\', $this->ReadPropertyString(self::propertyLicenseKey)), true);') !== false,
    'der ausdrueckliche Klick muss die Designs anfordern');
echo "Test 6 (die reale Umsetzung prüft den Status und fordert beim Klick an) OK\n";

echo "\nAll tests passed.\n";
