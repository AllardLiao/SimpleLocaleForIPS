<?php
declare(strict_types=1);
// Standalone replica test for build 203 (2026-09-02, Nutzer-Hinweis): Sprachen,
// die als QUELLSPRACHE einer Zeile in Gebrauch sind, duerfen nicht mehr aus den
// Zielsprachen verschwinden.
//
// DER FALL: das Modul bewirbt ausdruecklich, ein fremdsprachiges IPS-Modul in
// einem eigenen Scan-Gang mit abweichender Scan-Sprache zu erfassen. Danach
// tragen diese Zeilen z.B. "en" als Zeilen-Quellsprache, waehrend die Instanz
// weiter auf "de" scannt.
//
// Verschwindet "en" dann aus propertyTargetLanguages - durch Loeschen von Hand
// oder durch die Kuerzung am Sprachlimit -, hat das zwei Folgen:
//   1. Die Spalte "en" faellt aus allen Listen weg. Der ROHTEXT dieser Zeilen
//      ist damit im Formular nicht mehr erreichbar.
//   2. BuildRowSourceLanguageOptions() bietet den gespeicherten Wert nicht mehr
//      an - und Symcon verweigert dann das Speichern der Instanz ("Current value
//      ... is not available"), derselbe Fehler wie in Build 142.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fenster = function (string $von) use ($module): string {
    $a = (int) strpos($module, $von);
    $b = (int) strpos($module, "\n    private function ", $a + 10);

    return substr($module, $a, $b - $a);
};

// Repliziert GetUsedSourceLanguages().
function inGebrauch(string $scanSprache, array $zeilenQuellsprachen): array
{
    $codes = array_merge([$scanSprache], $zeilenQuellsprachen);

    return array_values(array_unique(array_filter($codes, static fn (string $c): bool => $c !== '')));
}

// Repliziert die Kuerzung NACH dem Fix.
function kuerzen(array $codes, string $quelle, int $limit, array $geschuetzt): array
{
    if ($limit <= 0) {
        return $codes;
    }
    $flip = array_flip($geschuetzt);
    $out = [];
    $ziele = 0;
    foreach ($codes as $code) {
        if ($code === $quelle || isset($flip[$code])) {
            $out[] = $code;
            continue;
        }
        if ($ziele >= $limit) {
            continue;
        }
        $ziele++;
        $out[] = $code;
    }

    return $out;
}

// Test 1: DER FALL - "en" ist Zeilen-Quellsprache und muss die Kuerzung
// ueberleben, auch bei Limit 1.
$geschuetzt = inGebrauch('de', ['de', 'en', 'en']);
assert($geschuetzt === ['de', 'en'], 'DER FALL: de (Scan) und en (Zeilen) sind in Gebrauch');
assert(kuerzen(['de', 'en', 'fr'], 'de', 1, $geschuetzt) === ['de', 'en', 'fr'],
    'die in Gebrauch befindliche Quellsprache darf nicht gekuerzt werden');
echo "Test 1 (eine benutzte Quellsprache überlebt die Kürzung) OK\n";

// Test 2: sie belegt auch keinen Platz - sonst blockierte die STRUKTUR die
// freie Wahl, und bei Limit 1 waere gar keine Zielsprache mehr moeglich.
assert(kuerzen(['de', 'en', 'fr', 'it'], 'de', 1, $geschuetzt) === ['de', 'en', 'fr'],
    'trotz "en" bleibt genau eine frei gewaehlte Zielsprache moeglich');
echo "Test 2 (sie belegt keinen Platz im Limit) OK\n";

// Test 3: ohne Gebrauch greift die Kuerzung ganz normal.
assert(kuerzen(['de', 'en', 'fr'], 'de', 1, inGebrauch('de', ['de'])) === ['de', 'en'],
    'eine nur gewaehlte Zielsprache wird sehr wohl gekuerzt');
echo "Test 3 (nicht benutzte Zielsprachen werden weiter gekürzt) OK\n";

// Test 4: die Ableitung kommt aus den DATEN, nicht aus einem mitgefuehrten
// Merker - sonst stimmte sie fuer Zeilen aus der Zeit davor nicht.
$used = $fenster('private function GetUsedSourceLanguages');
assert(strpos($used, 'ROW_SOURCE_LANGUAGE_PROPERTIES') !== false, 'ueber alle Zeilen-Listen');
assert(strpos($used, 'fieldRowSourceLanguage') !== false, 'ueber die Zeilen-Quellsprache');
assert(strpos($used, 'propertySourceLanguage') !== false, 'die aktuelle Scan-Sprache eingeschlossen');
assert(strpos($module, 'ROW_SOURCE_LANGUAGE_PROPERTIES = [') !== false, 'die Liste steht als Konstante');
assert(strpos($module, 'self::propertyManualTranslations,') !== false,
    'das eigene Glossar traegt ebenfalls Zeilen-Quellsprachen und gehoert dazu');
echo "Test 4 (aus den Daten abgeleitet, nicht mitgeschrieben) OK\n";

// Test 5: im Formular sind diese Zeilen weder aenderbar noch loeschbar -
// Symcon kennt dafuer "editable"/"deletable" je Zeile.
assert(strpos($module, "\$row['editable'] = false;") !== false, 'nicht aenderbar');
assert(strpos($module, "\$row['deletable'] = false;") !== false, 'nicht loeschbar');
assert(strpos($module, '$inGebrauch = array_flip($this->GetUsedSourceLanguages());') !== false,
    'gesperrt wird genau die Menge der benutzten Quellsprachen');
echo "Test 5 (die Zeilen sind im Formular gesperrt) OK\n";

// Test 6: DIE GEGENPROBE - eine frei gewaehlte Zielsprache bleibt bedienbar,
// sonst waere die Sperre eine Bevormundung.
$flip = array_flip(inGebrauch('de', ['de']));
assert(!isset($flip['fr']), 'eine reine Zielsprache ist nicht geschuetzt und bleibt aenderbar');
echo "Test 6 (freie Zielsprachen bleiben bedienbar) OK\n";

echo "\nAlle Tests OK (Build 203: benutzte Quellsprachen sind geschützt).\n";
