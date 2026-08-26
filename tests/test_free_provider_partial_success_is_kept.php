<?php
declare(strict_types=1);
// Standalone replica test for build 151 (2026-08-26, live gemeldet und per
// dump21 nachgewiesen):
//
// SYMPTOM: Ein Rescan lief minutenlang, uebersetzte nachweislich erfolgreich -
// und trug trotzdem nichts in die Tabelle ein. Der Nutzer sah im Log die
// letzte erfolgreiche Abfrage direkt vor einem Serverfehler und stellte fest:
// "erfolgreich abgefragt, aber nicht in der Liste eingetragen".
//
// URSACHE (dump21): MyMemory lieferte 21 Uebersetzungen sauber aus, dann kam
// ein HTTP 504 (Gateway Time-out, Server ueberlastet). TranslateChunkFree()
// brach daraufhin mit "return null" ab - und warf dabei ALLE 21 bereits
// fertigen Uebersetzungen weg. TranslateChunk() wertete das als
// Anbieter-Fehlschlag und fuellte den gesamten Chunk mit Leerstrings.
//
// Das Kontingent war fuer diese 21 Anfragen laengst verbraucht; beim naechsten
// Rescan begann alles von vorn, inklusive erneutem Verbrauch. Bei einem
// ueberlasteten Anbieter konnte ein Baum so NIE fertig uebersetzt werden.
//
// Besonders tueckisch: MyMemory hat keinen Batch-Endpunkt, ruft also pro Text
// einzeln auf. Ein Teilerfolg ist dort der NORMALFALL, nicht die Ausnahme -
// anders als bei Google/DeepL, wo ein Aufruf den ganzen Chunk abdeckt.

// Repliziert TranslateChunkFree() NACH dem Fix.
// $antworten: string = Erfolg, null = Fehlschlag dieses einen Textes
function chunkFreeReplica(array $texts, array $antworten): ?array
{
    $results = [];
    $anySucceeded = false;
    foreach ($texts as $i => $text) {
        if ($antworten[$i] === null) {
            $results[] = '';
            continue;
        }
        $results[] = $antworten[$i];
        $anySucceeded = true;
    }

    return $anySucceeded ? $results : null;
}

// Repliziert die Ketten-Logik aus TranslateChunk() NACH dem Fix.
// $kette: [providerName => antworten-array], null-Eintrag = Fehlschlag
function chainReplica(array $texts, array $kette): array
{
    $collected = array_fill(0, count($texts), '');

    foreach ($kette as $antworten) {
        $pending = array_keys(array_filter($collected, static fn (string $v): bool => $v === ''));
        if ($pending === []) {
            break;
        }
        $pendingTexts = array_values(array_map(static fn (int $i) => $texts[$i], $pending));
        $pendingAntw  = array_values(array_map(static fn (int $i) => $antworten[$i], $pending));

        $result = chunkFreeReplica($pendingTexts, $pendingAntw);
        if ($result === null) {
            continue;
        }
        foreach ($pending as $pos => $orig) {
            if (($result[$pos] ?? '') !== '') {
                $collected[$orig] = $result[$pos];
            }
        }
    }

    return $collected;
}

// Test 1: DER GEMELDETE FALL - erfolgreiche Uebersetzungen vor einem
// Serverfehler muessen erhalten bleiben.
$texts     = ['Bernd', 'Wohnbereich', 'f2880badc0d6', 'Zeitpunkt'];
$antworten = ['Berndi', 'Habitatio', 'f2880badc0d6', null];   // letzter: HTTP 504
$result = chunkFreeReplica($texts, $antworten);
assert($result !== null, 'DER BUG: ein Fehlschlag am Ende darf den Durchlauf nicht komplett verwerfen');
assert($result[0] === 'Berndi', 'die erste erfolgreiche Uebersetzung muss erhalten bleiben');
assert($result[1] === 'Habitatio', 'ebenso die zweite');
assert($result[2] === 'f2880badc0d6', 'ebenso die dritte - genau diese Zeile blieb im Report leer');
assert($result[3] === '', 'nur der tatsaechlich fehlgeschlagene Text bleibt offen');
echo "Test 1 (erfolgreiche Übersetzungen überleben einen Serverfehler im selben Durchlauf) OK\n";

// Test 2: DAS AUSMASS aus dem Report - 21 Erfolge, dann ein 504. Vorher waren
// alle 21 verloren.
$viele = [];
$antw  = [];
for ($i = 0; $i < 21; $i++) {
    $viele[] = "Text$i";
    $antw[]  = "Textus$i";
}
$viele[] = 'Ueberlastet';
$antw[]  = null;                      // HTTP 504
$result = chunkFreeReplica($viele, $antw);
$erhalten = count(array_filter($result, static fn (string $r): bool => $r !== ''));
assert($erhalten === 21, "DAS AUSMASS: alle 21 erfolgreichen Uebersetzungen muessen erhalten bleiben - erhalten: $erhalten");
echo "Test 2 (alle 21 Erfolge aus dem Report bleiben erhalten, nicht null davon) OK\n";

// Test 3: TOTALAUSFALL muss weiterhin null liefern - nur so erkennt
// TranslateChunk() den Anbieter als gescheitert und weicht auf den naechsten
// aus bzw. loest die Pausen-Eskalation aus.
assert(chunkFreeReplica(['A', 'B'], [null, null]) === null, 'DER KERN: kommt kein einziger Text durch, muss null zurueck - sonst gilt ein toter Anbieter faelschlich als erfolgreich');
echo "Test 3 (ein Totalausfall liefert weiterhin null, Kette und Pausen-Logik bleiben intakt) OK\n";

// Test 4: DIE KETTE - was der erste Anbieter offen laesst, holt der zweite
// nach. Und er bekommt NUR die offenen Texte, verbraucht also kein Kontingent
// fuer bereits Uebersetztes.
$texts = ['A', 'B', 'C'];
$result = chainReplica($texts, [
    ['Alpha', null, 'Gamma'],      // Anbieter 1: B scheitert
    ['x',     'Beta', 'y'],        // Anbieter 2: liefert B nach
]);
assert($result === ['Alpha', 'Beta', 'Gamma'], 'die Kette muss offene Texte beim naechsten Anbieter nachholen und bereits Uebersetztes unangetastet lassen');
echo "Test 4 (die Kette holt offene Texte beim nächsten Anbieter nach, ohne Übersetztes zu überschreiben) OK\n";

// Test 5: ein zweiter Anbieter darf ein bereits gutes Ergebnis NIE
// ueberschreiben - sonst haengt das Resultat von der Kettenreihenfolge ab und
// bereits bezahlte Arbeit wird doppelt gemacht.
$result = chainReplica(['A'], [
    ['Alpha'],
    ['SOLLTE-NICHT-GREIFEN'],
]);
assert($result === ['Alpha'], 'ein bereits uebersetzter Text darf vom naechsten Anbieter nicht erneut angefasst werden');
echo "Test 5 (bereits übersetzte Texte werden vom nächsten Anbieter nicht überschrieben) OK\n";

// Test 6: bleibt nach der ganzen Kette etwas offen, bleibt es LEER - nicht
// etwa mit dem Originaltext gefuellt. Eine leere Zelle gilt als "nicht
// aktuell" und wird beim naechsten Rescan erneut versucht; ein eingetragener
// Originaltext wuerde faelschlich als fertige Uebersetzung gelten und nie
// wieder angefasst.
$result = chainReplica(['A', 'B'], [
    ['Alpha', null],
    ['x',     null],
]);
assert($result === ['Alpha', ''], 'DIE FALLE: ein durchgehend fehlgeschlagener Text muss leer bleiben, damit ihn der naechste Rescan erneut versucht');
echo "Test 6 (durchgehend fehlgeschlagene Texte bleiben leer und werden später erneut versucht) OK\n";

// Test 7: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fnStart = strpos($moduleSource, 'private function TranslateChunkFree');
$fnBody = substr($moduleSource, $fnStart, 2600);
assert(strpos($fnBody, '$anySucceeded') !== false, 'TranslateChunkFree() muss Teilerfolge erkennen');
assert(preg_match('/if\s*\(\$translated === null\)\s*\{\s*\$results\[\] = \x27\x27;/', $fnBody) === 1, 'DER FIX: ein einzelner Fehlschlag darf nicht mehr den ganzen Durchlauf abbrechen, sondern nur diesen einen Text offen lassen');
assert(strpos($fnBody, 'if (!$anySucceeded) {') !== false, 'nur bei komplettem Ausfall darf null zurueckkommen');

$chunkStart = strpos($moduleSource, 'private function TranslateChunk(');
$chunkBody = substr($moduleSource, $chunkStart, 9000);
assert(strpos($chunkBody, '$collected') !== false, 'TranslateChunk() muss Teilergebnisse ueber die Kette hinweg sammeln');
assert(strpos($chunkBody, '$pendingIndexes') !== false, 'an den naechsten Anbieter duerfen nur die noch offenen Texte gehen');
assert(strpos($chunkBody, '$pendingTexts') !== false, 'die Restmenge muss tatsaechlich aus den offenen Texten gebildet werden');
// array_filter() waere hier falsch: es wertet auch eine Uebersetzung "0" als leer.
assert(strpos($chunkBody, 'count(array_filter($collected)) > 0') === false, 'array_filter() darf nicht ueber die Ergebnisse laufen - eine Uebersetzung, die woertlich "0" lautet, gaelte sonst als leer und wuerde verworfen');
echo "Test 7 (die reale Umsetzung sammelt Teilergebnisse und reicht nur Offenes weiter) OK\n";

echo "\nAll tests passed.\n";
