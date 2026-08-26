<?php
declare(strict_types=1);
// Standalone replica test for build 150 (2026-08-25, live gemeldet und per
// Debug-Dump nachgewiesen):
//
// SYMPTOM: Nach einem Rescan blieben in "Eigene Texte" praktisch alle
// Zielsprachen-Zellen leer - auch triviale wie "Bernd" oder "Wohnbereich".
// Gefuellt waren nur Zahlen/Daten, die der lokale Filter ohne API bedient.
// Gleichzeitig war KEIN Kontingent erschoepft, KEINE Pause aktiv, und die
// Objektnamen-Tabelle uebersetzte einwandfrei.
//
// URSACHE (aus dump20): MyMemory antwortet fuer manche Eingaben mit HTTP 200
// UND "translatedText": null - live fuer "&nbsp;" aus einem HTML-Widget:
//
//   {"responseData":{"translatedText":null,"match":null},"responseStatus":200}
//
// Das ist kein Anbieter-Fehler, sondern "dafuer habe ich nichts". Der Code
// machte daraus aber ein null, und TranslateChunkFree() bricht bei einem null
// den KOMPLETTEN Chunk ab. TranslateChunk() wertet das als Anbieter-Fehlschlag,
// findet keinen weiteren Anbieter und fuellt ALLE Texte des Chunks mit
// Leerstrings. Ein einziges "&nbsp;" liess so bis zu 127 voellig unbeteiligte
// Texte unuebersetzt.
//
// Exakt dieselbe Fehlerklasse wie beim zu langen Text (siehe
// test_free_provider_oversized_text_no_longer_blocks_batch) - dieser Pfad wurde
// damals uebersehen.

const CHUNK_MAX = 128;

// Repliziert TranslateSingleFree() NACH dem Fix.
// $apiAntwort: string = uebersetzter Text, null = HTTP 200 ohne Uebersetzung,
//              false = echter Transportfehler/Kontingent
function translateSingleFreeReplica(string $text, $apiAntwort): ?string
{
    if ($apiAntwort === false) {
        return null;            // echter Fehlschlag -> Kette darf weiterreichen
    }
    if (!is_string($apiAntwort)) {
        return $text;           // keine Uebersetzung verfuegbar -> Original behalten
    }

    return $apiAntwort;
}

// Repliziert TranslateChunkFree().
function translateChunkFreeReplica(array $texts, array $antworten): ?array
{
    $results = [];
    foreach ($texts as $i => $text) {
        $translated = translateSingleFreeReplica($text, $antworten[$i]);
        if ($translated === null) {
            return null;
        }
        $results[] = $translated;
    }

    return $results;
}

// Repliziert die Folgewirkung in TranslateChunk(): ein null der Kette fuehrt zu
// lauter Leerstrings fuer den GESAMTEN Chunk.
function translateChunkReplica(array $texts, array $antworten): array
{
    $result = translateChunkFreeReplica($texts, $antworten);
    if ($result === null) {
        return array_fill(0, count($texts), '');
    }

    return $result;
}

// Test 1: DER GEMELDETE FALL - ein "&nbsp;" mitten im Batch darf die uebrigen
// Texte nicht mitreissen.
$texts     = ['Bernd', '&nbsp;', 'Wohnbereich'];
$antworten = ['Berndi', null, 'Habitatio'];
$result = translateChunkReplica($texts, $antworten);
assert($result[0] === 'Berndi', 'DER BUG: "Bernd" wurde sauber uebersetzt und muss sein Ergebnis behalten, auch wenn ein anderer Text im selben Durchlauf keine Uebersetzung hat');
assert($result[2] === 'Habitatio', 'dasselbe fuer jeden weiteren unbeteiligten Text');
assert($result !== ['', '', ''], 'DER BUG: ein einzelnes "keine Uebersetzung" darf NICHT den gesamten Chunk in Leerstrings verwandeln');
echo "Test 1 (ein einzelnes '&nbsp;' reißt die übrigen Texte des Durchlaufs nicht mehr mit) OK\n";

// Test 2: der untranslatierbare Text behaelt sein ORIGINAL - kein Leerstring.
// Wichtig fuer HTML: ein Leerstring wuerde den Knoten beim Zusammensetzen
// loeschen und damit das Dokument beschaedigen (aus "&nbsp;" wuerde nichts).
assert($result[1] === '&nbsp;', 'DIE DOKUMENTBESCHAEDIGUNG: ein Knoten ohne Uebersetzung muss unveraendert erhalten bleiben, nicht geleert werden');
echo "Test 2 (der untranslatierbare Knoten bleibt unverändert erhalten, statt geleert zu werden) OK\n";

// Test 3: ein ECHTER Fehlschlag (Transport/Kontingent) muss weiterhin null
// liefern - nur so kann TranslateChunk() auf den naechsten Anbieter der Kette
// ausweichen. Diese Unterscheidung ist der Kern des Fixes.
$echterFehler = translateChunkFreeReplica(['Bernd', 'Wohnbereich'], ['Berndi', false]);
assert($echterFehler === null, 'DER KERN: ein echter Anbieter-Fehlschlag muss weiterhin null liefern, sonst faellt die Kette nie auf den naechsten Anbieter zurueck');
echo "Test 3 (ein echter Anbieter-Fehlschlag liefert weiterhin null, die Kette bleibt intakt) OK\n";

// Test 4: DAS AUSMASS - der Chunk fasst bis zu 128 Texte. Vorher machte ein
// einziges "keine Uebersetzung" alle 128 zunichte.
$viele   = [];
$antw    = [];
for ($i = 0; $i < CHUNK_MAX; $i++) {
    $viele[] = "Text$i";
    $antw[]  = "Textus$i";
}
$viele[64] = '&nbsp;';
$antw[64]  = null;
$result = translateChunkReplica($viele, $antw);
$leer = count(array_filter($result, static fn (string $r): bool => $r === ''));
assert($leer === 0, "DAS AUSMASS: kein einziger der 128 Texte darf wegen des einen untranslatierbaren leer bleiben - leer waren: $leer");
assert($result[0] === 'Textus0' && $result[127] === 'Textus127', 'die uebrigen Texte muessen ihre echten Uebersetzungen behalten');
echo "Test 4 (alle 128 Texte eines Durchlaufs überleben einen einzelnen untranslatierbaren) OK\n";

// Test 5: mehrere untranslatierbare Texte gleichzeitig - nichts anderes.
$mix = translateChunkReplica(
    ['&nbsp;', 'Bernd', '&shy;', 'Wohnbereich'],
    [null, 'Berndi', null, 'Habitatio']
);
assert($mix === ['&nbsp;', 'Berndi', '&shy;', 'Habitatio'], 'auch mehrere untranslatierbare Texte duerfen die uebrigen nicht beeintraechtigen');
echo "Test 5 (mehrere untranslatierbare Texte gleichzeitig sind ebenso unschädlich) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fnStart = strpos($moduleSource, 'private function TranslateSingleFree');
$fnBody = substr($moduleSource, $fnStart, strpos($moduleSource, 'private function TranslateChunkFree') - $fnStart);
assert(strpos($fnBody, 'return $Text;') !== false, 'DER FIX: bei gueltiger Antwort ohne Uebersetzung muss der Originaltext zurueckkommen');
assert(preg_match('/is_string\(\$translated\)\s*\?\s*\$translated\s*:\s*null/', $fnBody) !== 1, 'die alte Kurzform, die jede fehlende Uebersetzung zu null machte, darf nicht mehr existieren');
assert(strpos($fnBody, 'LogTranslateMessage') !== false, 'der Fall muss im Log auftauchen, damit er nicht wieder jahrelang unbemerkt bleibt');
// Der echte Fehlschlag muss weiterhin null liefern koennen.
assert(strpos($fnBody, 'return null;') !== false, 'echte Fehlschlaege muessen weiterhin null liefern, damit die Anbieter-Kette greift');
echo "Test 6 (die reale Umsetzung unterscheidet 'keine Übersetzung' von 'Fehlschlag') OK\n";

echo "\nAll tests passed.\n";
