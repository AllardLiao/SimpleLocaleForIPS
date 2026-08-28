<?php
declare(strict_types=1);
// Standalone replica test for build 170 (2026-08-26, Nutzer-Hinweis: "Wenn der
// User 'vergessen' hat zu aktivieren, können wir dies doch feststellen und
// einfach nachholen im Moment der Gültigkeitsprüfung. Es ist ja der gleiche
// Aufruf - mit vermutlich den gleichen Daten.").
//
// Der Hinweis traf eine echte Luecke, allerdings eine andere als vermutet:
// "Vergessen" kann man die Aktivierung gar nicht - TrackLicenseActivationIfNew()
// meldet auch beim blossen "Uebernehmen" des Formulars. Die Luecke lag im
// FEHLSCHLAG: attributeLastCheckedLicenseKeyHash wurde VOR dem Netzwerkaufruf
// gesetzt, und der Aufruf ist bewusst "fail open". War der Server in dem Moment
// nicht erreichbar - oder die Instanz offline -, galt die Meldung damit dauerhaft
// als erledigt und wurde NIE nachgeholt.
//
// Erschwerend: CallActivationReportAPI() lieferte null sowohl bei einem
// Netzwerkfehler als auch bei der voellig normalen leeren 204-Antwort ("nichts
// zu melden"). Erfolg und Fehlschlag waren nicht unterscheidbar - ohne diese
// Unterscheidung laesst sich ein Nachholen gar nicht bauen.

// Repliziert CallActivationReportAPI() NACH dem Fix.
// $transport: false = Server nicht erreicht, sonst der Antwort-Body.
function callReplica($transport): ?string
{
    return $transport === false ? null : (string) $transport;
}

// Repliziert RecordLicenseActivation() NACH dem Fix.
function recordReplica($transport, string $keyHash, string &$reportedHash): bool
{
    $response = callReplica($transport);
    if ($response === null) {
        return false;
    }
    $reportedHash = $keyHash;

    return true;
}

// Repliziert PerformDailyLicenseCheck() NACH dem Fix.
function dailyReplica(string $keyHash, string &$reportedHash, $transport, array &$calls): void
{
    if ($reportedHash !== $keyHash) {
        $calls[] = 'RecordLicenseActivation';
        recordReplica($transport, $keyHash, $reportedHash);

        return;
    }
    $calls[] = 'FetchLicenseStatus';
}

// Test 1: DIE UNTERSCHEIDUNG - eine leere 204-Antwort ist ERFOLG, kein Fehler.
assert(callReplica('') === '', 'eine leere 204-Antwort muss als Erfolg gelten - "angekommen, nichts zu melden"');
assert(callReplica(false) === null, 'nur ein Transportfehler darf null liefern');
echo "Test 1 (leere 204-Antwort gilt als Erfolg, nur Transportfehler als Fehlschlag) OK\n";

// Test 2: DIE LUECKE - schlaegt die Erstmeldung fehl, bleibt sie unerledigt.
$reported = '';
assert(recordReplica(false, 'abc', $reported) === false, 'ein Fehlschlag muss als solcher zurueckgemeldet werden');
assert($reported === '', 'und darf den Schluessel NICHT als gemeldet markieren');
echo "Test 2 (eine fehlgeschlagene Meldung markiert nichts als erledigt) OK\n";

// Test 3: DER FIX - die Tagespruefung holt sie nach, mit derselben Nutzlast.
$calls = [];
dailyReplica('abc', $reported, '', $calls);
assert($calls === ['RecordLicenseActivation'], 'DER FIX: die Tagespruefung muss die ausgebliebene Erstmeldung nachholen');
assert($reported === 'abc', 'und sie danach als erledigt markieren');
echo "Test 3 (die Tagesprüfung holt die ausgebliebene Meldung nach) OK\n";

// Test 4: ab dann wieder nur Statusabfrage - sonst waere Build 169 wieder
// zunichte und es entstuende taeglich eine Aktivierungszeile.
$calls = [];
dailyReplica('abc', $reported, '', $calls);
assert($calls === ['FetchLicenseStatus'], 'nach erfolgreicher Meldung darf nur noch der Status abgefragt werden');
echo "Test 4 (danach wieder nur Statusabfrage, keine tägliche Aktivierung) OK\n";

// Test 5: bleibt der Server dauerhaft unerreichbar, wird taeglich - und NUR
// taeglich - erneut versucht. Der passive Pfad darf nicht wiederholen, sonst
// loest jeder Formular-Klick einen weiteren Request aus.
$reported2 = '';
for ($tag = 0; $tag < 3; $tag++) {
    $calls = [];
    dailyReplica('xyz', $reported2, false, $calls);
    assert($calls === ['RecordLicenseActivation'], "Tag $tag: es muss weiter versucht werden");
}
assert($reported2 === '', 'solange der Server nicht erreichbar ist, bleibt der Schluessel unerledigt');
echo "Test 5 (ein dauerhaft unerreichbarer Server wird täglich erneut versucht) OK\n";

// Test 6: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

assert(strpos($constantsSource, "attributeReportedLicenseKeyHash = 'ReportedLicenseKeyHash'") !== false,
    'das Attribut fuer die tatsaechlich erfolgte Meldung muss deklariert sein');
// Seit Build 174/175 steht die Bedingung nicht mehr in einer Zeile, sondern als
// zwei fruehe Ausstiege (Transportfehler, HTTP-Fehlerstatus). Der KERN bleibt:
// null bedeutet ausschliesslich "keine verwertbare Antwort".
$callStart = strpos($moduleSource, 'private function CallActivationReportAPI');
$callBody = substr($moduleSource, $callStart, strpos($moduleSource, "\n    private function ", $callStart + 10) - $callStart);
assert(strpos($callBody, 'if ($response === false) {') !== false, 'ein Transportfehler muss null liefern');
assert(strpos($callBody, 'if ($httpStatus >= 400) {') !== false, 'ein HTTP-Fehlerstatus ebenso');
assert(substr_count($callBody, 'return null;') === 2, 'genau diese beiden Faelle - kein weiterer stiller Fehlschlag');
assert(strpos($callBody, 'return (string) $response;') !== false, 'alles andere gilt als verwertbare Antwort');
assert(strpos($moduleSource, 'private function RecordLicenseActivation(string $KeyHash, string $Licensee, array $Log): bool') !== false,
    'die Meldefunktion muss den Erfolg zurueckgeben');
assert(strpos($moduleSource, 'WriteAttributeString(self::attributeReportedLicenseKeyHash, $KeyHash);') !== false,
    'und den Schluessel erst NACH erfolgreicher Meldung markieren');

$start = strpos($moduleSource, 'private function PerformDailyLicenseCheck');
$ende = strpos($moduleSource, "\n    private function ", $start + 10);
$daily = substr($moduleSource, $start, $ende - $start);
assert(strpos($daily, 'ReadAttributeString(self::attributeReportedLicenseKeyHash) !== $keyHash') !== false,
    'die Tagespruefung muss auf eine ausgebliebene Meldung pruefen');
$nachholen = strpos($daily, '$this->RecordLicenseActivation(');
$status = strpos($daily, '$this->FetchLicenseStatus(');
assert($nachholen !== false && $status !== false, 'beide Wege muessen vorkommen');
assert($nachholen < $status, 'das Nachholen muss VOR der Statusabfrage stehen');
echo "Test 6 (die reale Umsetzung unterscheidet Erfolg und Fehlschlag und holt nach) OK\n";

echo "\nAll tests passed.\n";
