<?php
declare(strict_types=1);
// Standalone replica test for build 181 (2026-08-28, live gemeldet: "Die Instanz
// meldet translation failed. Wenn ich die Lizenz aktiviere meldet sie direkt
// active - kurz danach aber wieder translation failed").
//
// URSACHE (Log + dump24): Alle 47 Uebersetzungen liefen sauber durch. Einzelne
// Texte ueberschritten aber MyMemorys 500-Byte-Grenze und wurden uebersprungen.
// Da nur der kostenfreie Anbieter in der Kette steht, galt so ein Chunk als
// KOMPLETT gescheitert - und das setzte den Instanz-Fehlerstatus.
//
// Doppelt falsch: die Statusmeldung lautet "kein Anbieter war erreichbar",
// obwohl MyMemory sauber geantwortet hat. Und der Zustand war nicht abstellbar,
// weil jeder Lauf denselben Status neu setzte - an der Textlaenge aendert kein
// Wiederholungsversuch etwas.

// Repliziert TranslateChunkFree(): '' = an der Laengengrenze uebersprungen,
// null = Anbieter nicht erreichbar, sonst Erfolg.
function chunkFreeReplica(array $antworten, ?bool &$onlyTooLong): ?array
{
    $results = [];
    $anySucceeded = false;
    $tooLong = 0;
    $onlyTooLong = false;

    foreach ($antworten as $a) {
        if ($a === '') { $results[] = ''; $tooLong++; continue; }
        if ($a === null) { $results[] = ''; continue; }
        $results[] = $a;
        $anySucceeded = true;
    }
    if (!$anySucceeded) {
        $onlyTooLong = $tooLong === count($antworten);

        return null;
    }

    return $results;
}

// Repliziert den Abschluss von TranslateChunk().
function chunkReplica(array $antworten, array $kette): array
{
    $onlyTooLong = true;
    $frei = false;
    $result = chunkFreeReplica($antworten, $frei);
    $onlyTooLong = $onlyTooLong && $frei;
    if ($result !== null) {
        return ['status' => null, 'ergebnis' => $result];
    }

    return $onlyTooLong
        ? ['status' => null, 'ergebnis' => array_fill(0, count($antworten), '')]
        : ['status' => 'FEHLER', 'ergebnis' => array_fill(0, count($antworten), '')];
}

// Test 1: DER GEMELDETE FALL - lauter zu lange Texte setzen KEINEN Fehlerstatus.
$out = chunkReplica(['', '', ''], ['free']);
assert($out['status'] === null, 'DER FIX: eine reine Laengenueberschreitung darf keinen Fehlerstatus setzen');
echo "Test 1 (lauter zu lange Texte setzen keinen Fehlerstatus) OK\n";

// Test 2: DIE ABGRENZUNG - ein echter Anbieterausfall setzt ihn weiterhin.
// Sonst wuerde diese Aenderung eine echte Stoerung verschleiern.
$out = chunkReplica([null, null], ['free']);
assert($out['status'] === 'FEHLER', 'DIE ABGRENZUNG: ein echter Ausfall muss weiterhin gemeldet werden');
echo "Test 2 (ein echter Ausfall wird weiterhin gemeldet) OK\n";

// Test 3: gemischt - ein einziger echter Fehlschlag genuegt fuer den Status.
$out = chunkReplica(['', null, ''], ['free']);
assert($out['status'] === 'FEHLER', 'sobald EIN Text am Anbieter scheitert, ist es ein Ausfall');
echo "Test 3 (ein einziger echter Fehlschlag genügt für den Status) OK\n";

// Test 4: kommt irgendein Text durch, ist es ohnehin kein Ausfall - Teilerfolge
// bleiben erhalten (Build 151).
$out = chunkReplica(['', 'Hallo', null], ['free']);
assert($out['status'] === null && $out['ergebnis'][1] === 'Hallo', 'Teilerfolge bleiben erhalten und melden keinen Fehler');
echo "Test 4 (Teilerfolge bleiben erhalten) OK\n";

// Test 5: die uebersprungenen Texte bleiben LEER, nicht mit Originaltext
// gefuellt - sonst gaelten sie als fertig uebersetzt und wuerden nie wieder
// angefasst, etwa nachdem ein Google-Schluessel hinterlegt wurde.
$out = chunkReplica(['', ''], ['free']);
assert($out['ergebnis'] === ['', ''], 'uebersprungene Texte muessen offen bleiben');
echo "Test 5 (übersprungene Texte bleiben offen für einen späteren Anbieter) OK\n";

// Test 6: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'string $DebugContext = \'\', ?bool &$OnlyTooLong = null): ?array') !== false,
    'TranslateChunkFree muss zurueckmelden, ob nur die Laengengrenze griff');

$freeStart = strpos($moduleSource, 'private function TranslateChunkFree');
$freeBody = substr($moduleSource, $freeStart, strpos($moduleSource, "\n    private function ", $freeStart + 10) - $freeStart);
assert(strpos($freeBody, "if (\$translated === '') {") !== false,
    'der Laengen-Waechter liefert bewusst \'\' - das muss vom echten Fehlschlag getrennt werden');
assert(strpos($freeBody, '$OnlyTooLong = $tooLongCount === count($Texts);') !== false,
    'und nur dann gemeldet werden, wenn ALLE Texte daran scheiterten');

$chunkStart = strpos($moduleSource, 'private function TranslateChunk(');
$chunkBody = substr($moduleSource, $chunkStart, strpos($moduleSource, "\n    private function ", $chunkStart + 10) - $chunkStart);
assert(strpos($chunkBody, 'if ($onlyTooLong) {') !== false, 'der Aufrufer muss den Fall gesondert behandeln');
$vorher = strpos($chunkBody, 'if ($onlyTooLong) {');
$status = strpos($chunkBody, 'SetStatus($this->GetGlobalPauseUntil()');
assert($vorher < $status, 'und zwar VOR dem Setzen des Fehlerstatus');
assert(strpos($chunkBody, '$onlyTooLong = $onlyTooLong && $freeOnlyTooLong;') !== false,
    'ein bezahlter Anbieter kennt die Grenze nicht - schlaegt er fehl, ist es ein echter Ausfall');
echo "Test 6 (die reale Umsetzung trennt Längengrenze und Ausfall) OK\n";

echo "\nAll tests passed.\n";
