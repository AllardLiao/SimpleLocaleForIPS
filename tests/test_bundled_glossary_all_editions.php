<?php
declare(strict_types=1);
// Standalone replica test for build 158 (2026-08-26, Nutzer-Entscheidung nach
// dem Befund aus Build 157):
//
// Bis Build 157 hingen ZWEI Dinge an einem einzigen Lizenz-Flag
// ("manual_translations"): die editierbare "Eigene Uebersetzungstabelle" UND
// die mitgelieferte Nachschlagetabelle fuer Einheiten/Kompassrichtungen. Eine
// Edition ohne das Feature (Light, oder eine Spezialversion, die es nicht
// fuehrt) schickte damit auch "°C" ganz normal an die API - und bekam auf
// Englisch "°F" zurueck. Eine Einheitenumrechnung als Uebersetzung ist in JEDER
// Edition falsch.
//
// Entscheidung: verkauft wird die EDITIERBARE Tabelle. Der interne Lookup
// greift ab jetzt in jeder Edition; ohne das Feature bleibt die Tabelle
// unsichtbar und unbearbeitbar.
//
// Die Gegenrichtung ist genauso wichtig: MIT dem Feature darf es KEINEN
// Fallback geben. Sonst waere eine bewusst geloeschte Zeile wirkungslos - und
// genau das ist ein dokumentiertes Recht des Admins ("SSW" kann ein
// Personen-Kuerzel sein statt einer Windrichtung).

const BUNDLED = [
    '°C'  => ['en' => '°C',  'fr' => '°C'],
    'SSW' => ['en' => 'SSW', 'fr' => 'SSO'],
];

// Repliziert FindGlossaryTranslation() (Build 189): spaltenbasiert, richtungsfrei.
function findGlossary(array $rows, string $source, string $target, string $text): ?string
{
    foreach ($rows as $row) {
        if ((string) ($row[$source] ?? '') !== $text) {
            continue;
        }
        $t = (string) ($row[$target] ?? '');
        if ($t !== '') {
            return $t;
        }
    }

    return null;
}

// Repliziert FindManualTranslation() NACH dem Fix.
function findManual(array $rows, string $quelle, string $ziel, string $text, bool $hatFeature): ?string
{
    foreach ($rows as $row) {
        if (($row['Source language'] ?? '') !== $quelle) { continue; }
        if ((string) ($row['ORIGINAL_IMPORT'] ?? '') !== $text) { continue; }
        $t = (string) ($row[$ziel] ?? '');
        if ($t !== '') { return $t; }
    }

    if ($hatFeature) {
        return null;
    }

    if ($quelle !== 'de') { return null; }
    $t = (string) (BUNDLED[$text][$ziel] ?? '');

    return $t !== '' ? $t : null;
}

// Test 1: DER GEMELDETE FALL - eine Edition OHNE das Feature hat eine leere
// Tabelle und bekam deshalb "°C" von der API zurueck (als "°F").
assert(findManual([], 'de', 'en', '°C', true) === null, 'Ausgangslage: ohne Tabelleneintrag gab es frueher keinen Treffer');
assert(findManual([], 'de', 'en', '°C', false) === '°C', 'DER FIX: ohne das Feature muss der mitgelieferte Katalog greifen - sonst wird aus "°C" ein "°F"');
echo "Test 1 (ohne das Feature greift der mitgelieferte Katalog) OK\n";

// Test 2: DIE GEGENRICHTUNG - MIT dem Feature ist die Tabelle massgeblich und
// eine bewusst geloeschte Zeile bleibt wirkungslos geloescht.
assert(findManual([], 'de', 'en', 'SSW', true) === null, 'MIT dem Feature darf es KEINEN Fallback geben - sonst waere eine bewusste Loeschung wirkungslos');
echo "Test 2 (mit dem Feature bleibt eine gelöschte Zeile gelöscht) OK\n";

// Test 3: ein eingetragener Wert gewinnt in beiden Faellen ueber den Katalog.
$eigen = [['Source language' => 'de', 'ORIGINAL_IMPORT' => '°C', 'en' => 'Grad Celsius']];
assert(findManual($eigen, 'de', 'en', '°C', true) === 'Grad Celsius', 'der eingetragene Wert gewinnt');
assert(findManual($eigen, 'de', 'en', '°C', false) === 'Grad Celsius', 'auch ohne Feature gewinnt ein vorhandener Eintrag vor dem Katalog');
echo "Test 3 (ein eingetragener Wert gewinnt immer vor dem Katalog) OK\n";

// Test 4 (Build 189 - GEAENDERT): der Katalog war deutschsprachig indiziert und
// griff fuer eine andere Quellsprache gar nicht. Genau das war die Luecke: ein
// Objekt mit englischer Zeilen-Quellsprache schickte "°C" an die API und bekam
// "°F" zurueck. Das Glossar sucht jetzt SPALTENBASIERT - jede Sprachspalte kann
// die Quelle sein, dieselbe Zeile traegt alle Richtungen.
$glossarZeilen = [['de' => '°C', 'en' => '°C', 'fr' => '°C']];
assert(findGlossary($glossarZeilen, 'en', 'fr', '°C') === '°C',
    'DIE LUECKE: der Treffer darf nicht mehr an deutscher Quellsprache haengen');
assert(findGlossary($glossarZeilen, 'fr', 'de', '°C') === '°C', 'und er gilt in jede Richtung');
// Die Abgrenzung: ein Text, der sich als spanisch ausgibt, trifft nur ueber die
// spanische Spalte - und die gibt es hier nicht.
assert(findGlossary($glossarZeilen, 'es', 'de', '°C') === null,
    'ohne Wert in der Quellspalte gibt es keinen Treffer');
echo "Test 4 (das Glossar trifft aus jeder Sprachspalte, in jede Richtung) OK\n";

// Test 5: ein unbekannter Text faellt weiterhin an die API durch.
assert(findManual([], 'de', 'en', 'Hauswirtschaftsraum', false) === null, 'unbekannte Texte muessen weiterhin an die API gehen');
echo "Test 5 (unbekannte Texte gehen weiterhin an die API) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function FindManualTranslation');
assert($start !== false, 'FindManualTranslation() muss existieren');
$ende = strpos($moduleSource, "\n    // Build 186", $start);
assert($ende !== false, 'das Ende von FindManualTranslation() muss auffindbar sein');
$body = substr($moduleSource, $start, $ende - $start);
$body = implode("\n", array_filter(
    explode("\n", $body),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));

// Build 189: die Entscheidung "gespeicherte Tabelle ODER mitgelieferter Katalog"
// liegt jetzt in GetGlossaryRowsForLookup(), nicht mehr in FindManualTranslation().
$lookupStart = (int) strpos($moduleSource, 'private function GetGlossaryRowsForLookup');
$lookupBody = substr($moduleSource, $lookupStart, 600);
assert(strpos($lookupBody, "HasLicenseFeature('glossary')") !== false,
    'MIT dem Feature ist die gespeicherte Tabelle massgeblich');
assert(strpos($lookupBody, 'BuildBundledGlossaryRows()') !== false,
    'OHNE das Feature greift der mitgelieferte Katalog direkt - in JEDER Edition');
// Build 189: die eigenen Uebersetzungen behalten Vorrang - sie sind die
// ausdrueckliche Festlegung des Admins. Erst danach das Glossar.
$eigene = strpos($body, '$ManualTranslationRows');
$glossar = strpos($body, 'FindGlossaryTranslation(');
assert($eigene !== false && $glossar !== false, 'beide Wege muessen vorkommen');
assert($eigene < $glossar, 'die eigenen Uebersetzungen muessen VOR dem Glossar geprueft werden');

// Build 189: die frueher hier gepruefte Bindung an 'de' ist bewusst entfallen -
// sie WAR die Luecke. Stattdessen: die Suche darf keine Sprache mehr auszeichnen.
$glossarStart = (int) strpos($moduleSource, 'private function FindGlossaryTranslation');
$glossarBody = substr($moduleSource, $glossarStart, 900);
assert(strpos($glossarBody, "'de'") === false,
    'die Glossar-Suche darf keine Sprache fest verdrahten');
assert(strpos($glossarBody, '$row[$SourceLanguage]') !== false && strpos($glossarBody, '$row[$TargetLanguage]') !== false,
    'sie muss Quell- UND Zielspalte dynamisch lesen');
// Build 189: gepuffert wird nicht mehr in der Suche, sondern durch den AUFRUFORT -
// die Zeilen werden einmal je Durchlauf beschafft, nicht je Text. Frueher stand
// dafuer ein "static" in der Suche; das ging nur, solange die Quelle rein aus
// Konstanten kam. Jetzt haengt sie an einer instanzabhaengigen Property, ein
// static waere dort schlicht falsch.
$suchen = ['FindGlossaryTranslation', 'FindManualTranslation'];
foreach ($suchen as $fn) {
    // An der naechsten Methode begrenzen, nicht auf eine feste Zeichenzahl - ein
    // festes Fenster ist in dieser Suite schon mehrfach in die Folgefunktion
    // gelaufen, und die naechste ist hier ausgerechnet GetGlossaryRowsForLookup().
    $a = (int) strpos($moduleSource, 'private function ' . $fn);
    $b = (int) strpos($moduleSource, "\n    private function ", $a + 10);
    $rumpf = substr($moduleSource, $a, $b - $a);
    assert(strpos($rumpf, 'GetGlossaryRowsForLookup()') === false,
        "$fn darf die Zeilen NICHT je Text neu beschaffen");
}
assert(substr_count($moduleSource, '$this->GetGlossaryRowsForLookup();') === 3,
    'die drei Uebersetzungswege beschaffen sie je einmal vorab');
echo "Test 6 (die Glossar-Zeilen werden je Durchlauf einmal beschafft) OK\n";

echo "\nAll tests passed.\n";
