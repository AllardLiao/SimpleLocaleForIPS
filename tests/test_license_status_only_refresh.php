<?php
declare(strict_types=1);
// Standalone replica test for build 169 (2026-08-26, Nutzer-Frage: "Wenn
// Activate License nach erfolgreicher Aktivierung und gleichem Lizenzkey
// nochmal gedrückt wird - was passiert?" - Antwort damals: nichts, der Aufruf
// brach vorher ab).
//
// Das stand dem gerade dokumentierten Kulanz-Weg im Weg: setzt der Admin fuer
// eine Bestellung "Ablaufdatum ueberschreiben", holte das Modul den neuen Wert
// NICHT, wenn der Kunde auf den Knopf drueckt - erst die Tagespruefung brachte
// ihn, also bis zu 24 Stunden spaeter.
//
// Zweiter, dabei gefundener Befund: die Tagespruefung schickte dieselbe Nutzlast
// wie eine echte Erstaktivierung, und der Server legt pro Aufruf eine
// Aktivierungszeile an - pro Lizenz also JEDEN TAG eine. Die
// Weiterverkaufs-Erkennung (derselbe Hash mit abweichenden licensee-Werten)
// ersoff darin.
//
// Beides loest dasselbe Flag: "statusOnly" fragt den Stand ab, ohne eine
// Aktivierung zu melden.

// Repliziert TrackLicenseActivationIfNew() - liefert jetzt zurueck, ob gemeldet
// wurde.
function trackReplica(string $keyHash, string $zuletztGeprueft, bool $geblockt, bool $allowRecheck, array &$calls): bool
{
    $recheck = $allowRecheck && $geblockt;
    if (!$recheck && $zuletztGeprueft === $keyHash) {
        return false;
    }
    $calls[] = 'RecordLicenseActivation';

    return true;
}

// Repliziert den Zweig aus ActivateLicense().
function activateReplica(string $keyHash, string $zuletztGeprueft, bool $geblockt): array
{
    $calls = [];
    $gemeldet = trackReplica($keyHash, $zuletztGeprueft, $geblockt, true, $calls);
    if (!$gemeldet) {
        $calls[] = 'FetchLicenseStatus';
    }

    return $calls;
}

// Test 1: DER GEMELDETE FALL - zweiter Klick, unveraenderter gueltiger
// Schluessel. Frueher: gar nichts. Jetzt: Statusabfrage, KEINE zweite
// Aktivierung.
$calls = activateReplica('abc', 'abc', false);
assert(!in_array('RecordLicenseActivation', $calls, true), 'DER KERN: der zweite Klick darf KEINE weitere Aktivierung melden');
assert(in_array('FetchLicenseStatus', $calls, true), 'DER FIX: er muss aber den aktuellen Stand holen - sonst kommt ein Kulanz-Ablaufdatum erst am naechsten Tag an');
echo "Test 1 (zweiter Klick holt den Stand, ohne erneut zu aktivieren) OK\n";

// Test 2: der ERSTE Klick mit einem neuen Schluessel meldet weiterhin eine echte
// Aktivierung - und dann NICHT zusaetzlich noch eine Statusabfrage, das waere
// ein zweiter Request fuer dieselbe Antwort.
$calls = activateReplica('neu', 'abc', false);
assert(in_array('RecordLicenseActivation', $calls, true), 'ein neuer Schluessel muss weiterhin gemeldet werden');
assert(!in_array('FetchLicenseStatus', $calls, true), 'dann aber keine zusaetzliche Statusabfrage - die Antwort kam schon mit');
echo "Test 2 (ein neuer Schlüssel meldet wie bisher, ohne Doppelabfrage) OK\n";

// Test 3: ein als GEBLOCKT bekannter Schluessel wird beim ausdruecklichen Klick
// weiterhin echt neu gemeldet - dieses Verhalten bestand schon und bleibt.
$calls = activateReplica('abc', 'abc', true);
assert(in_array('RecordLicenseActivation', $calls, true), 'ein geblockter Schluessel wird beim ausdruecklichen Klick weiterhin neu gemeldet');
echo "Test 3 (ein geblockter Schlüssel wird weiterhin neu gemeldet) OK\n";

// Test 4: die reale Umsetzung im Modul.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function FetchLicenseStatus(string $KeyHash): void') !== false, 'die Statusabfrage muss existieren');
assert(strpos($moduleSource, "'statusOnly'     => true,") !== false, 'sie muss das Flag setzen');
assert(strpos($moduleSource, 'private function TrackLicenseActivationIfNew(bool $AllowRecheck = false): bool') !== false,
    'die Meldefunktion muss zurueckgeben, ob sie gemeldet hat');
assert(strpos($moduleSource, 'if (!$reported && self::LICENSE_ACTIVATION_REPORT_URL !== \'\') {') !== false,
    'ActivateLicense muss nur bei ausbleibender Meldung nachfragen');

// Die Tagespruefung darf keine eigene Nutzlast mehr bauen - sonst faellt sie
// wieder aus dem statusOnly-Pfad heraus.
// Rumpf sauber abgrenzen statt festes Zeichenfenster - sonst laeuft die Pruefung
// in die naechste Funktion hinein und schlaegt an deren Nutzlast an.
$dailyStart = strpos($moduleSource, 'private function PerformDailyLicenseCheck');
$dailyEnde = strpos($moduleSource, "\n    private function ", $dailyStart + 10);
$daily = substr($moduleSource, $dailyStart, $dailyEnde - $dailyStart);
assert(strpos($daily, '$this->FetchLicenseStatus(') !== false, 'die Tagespruefung muss ueber die Statusabfrage laufen');
assert(strpos($daily, "'licenseKeyHash' =>") === false, 'sie darf keine eigene Nutzlast mehr bauen - sonst meldet sie wieder taeglich eine Aktivierung');
echo "Test 4 (Modul: Statusabfrage verdrahtet, Tagesprüfung nutzt sie) OK\n";

// Test 5: der Knopf heisst jetzt "aktivieren/aktualisieren" - der zweite Klick
// TUT ja jetzt etwas, und das soll am Knopf stehen.
$form = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
$gefunden = false;
$suche = function ($n) use (&$suche, &$gefunden): void {
    if (is_array($n)) {
        if (($n['caption'] ?? '') === 'Lizenz aktivieren/aktualisieren') { $gefunden = true; }
        foreach ($n as $v) { $suche($v); }
    }
};
$suche($form);
assert($gefunden, 'der Knopf muss umbenannt sein');
foreach ($locale['translations'] as $sprache => $eintraege) {
    assert(isset($eintraege['Lizenz aktivieren/aktualisieren']), "die neue Beschriftung fehlt in \"$sprache\"");
    assert(!isset($eintraege['Lizenz aktivieren']), "die alte Beschriftung steht noch in \"$sprache\"");
}
echo "Test 5 (der Knopf ist umbenannt, in allen Sprachen) OK\n";

echo "\nAll tests passed.\n";
