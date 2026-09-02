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

// Test 4: der Katalog ist deutschsprachig indiziert - fuer eine andere
// Quellsprache darf er NICHT greifen, sonst uebersetzte er munter Fremdtexte.
assert(findManual([], 'en', 'fr', '°C', false) === null, 'der Katalog gilt nur fuer deutsche Quelltexte');
echo "Test 4 (der Katalog greift nur bei deutscher Quellsprache) OK\n";

// Test 5: ein unbekannter Text faellt weiterhin an die API durch.
assert(findManual([], 'de', 'en', 'Hauswirtschaftsraum', false) === null, 'unbekannte Texte muessen weiterhin an die API gehen');
echo "Test 5 (unbekannte Texte gehen weiterhin an die API) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function FindManualTranslation');
assert($start !== false, 'FindManualTranslation() muss existieren');
$ende = strpos($moduleSource, "\n    private function FindBundledTranslation", $start);
assert($ende !== false, 'FindBundledTranslation() muss direkt darauf folgen');
$body = substr($moduleSource, $start, $ende - $start);
$body = implode("\n", array_filter(
    explode("\n", $body),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));

assert(strpos($body, "if (\$this->HasLicenseFeature('manual_translations')) {") !== false,
    'MIT dem Feature muss vor dem Fallback ausgestiegen werden');
$ausstieg = strpos($body, "HasLicenseFeature('manual_translations')");
$fallback = strpos($body, 'FindBundledTranslation(');
assert($ausstieg < $fallback, 'der Ausstieg muss VOR dem Fallback stehen - sonst greift er auch dort, wo die Tabelle massgeblich ist');

$bundledStart = strpos($moduleSource, 'private function FindBundledTranslation');
$bundledBody = substr($moduleSource, $bundledStart, 1600);
assert(strpos($bundledBody, "if (\$SourceLanguage !== 'de') {") !== false,
    'der Katalog darf nur fuer deutsche Quelltexte greifen');
assert(strpos($bundledBody, 'static $bundled = null;') !== false,
    'die Karte muss gepuffert werden - FindManualTranslation() laeuft je Text, ein Neuaufbau je Aufruf waere Verschwendung');
echo "Test 6 (die reale Umsetzung steigt mit Feature aus und puffert die Karte) OK\n";

echo "\nAll tests passed.\n";
