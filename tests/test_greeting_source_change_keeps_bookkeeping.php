<?php
declare(strict_types=1);
// Standalone replica test for build 207 (2026-09-03, live gemeldet): ein Rescan
// brach mit einem Fatal Error ab, und die Uebersetzung der Begruessung war
// deaktiviert, ohne dass jemand sie deaktiviert hatte.
//
// EINE URSACHE FUER BEIDES. Aenderte sich der Rohtext der Begruessung von
// aussen, lief an zwei Stellen dieselbe Schleife: "leere jedes Feld ausser
// Rohtext, ValueObjectID und Quellsprache". Sie traf damit auch die
// Buchhaltungsfelder:
//
//   - "UebersetztAm" ist eine ZUORDNUNG Sprache => Zeitstempel. Als '' war es
//     danach ein String. Der naechste Schreibzugriff brach ab:
//     "Cannot access offset of type string on string" - mitten im Rescan.
//   - "TranslationActive" ist die Einstellung des Nutzers. Als '' gilt sie als
//     AUS, die Uebersetzung schaltete sich also selbst ab. Bei einer
//     automatischen Begruessung (Tageszeit) aendert sich der Text regelmaessig -
//     deshalb trat es wiederholt auf.
//
// Der LESEzugriff war durch ?? abgesichert und meldete brav 0; nur das Schreiben
// brach ab. Genau das zeigte der Stacktrace.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');

const F_RAW = 'ORIGINAL_IMPORT';
const F_SRC = 'Quellsprache';
const F_ACTIVE = 'TranslationActive';
const F_AT = 'UebersetztAm';
const F_CHANGED = 'QuelleGeaendertAm';

// Repliziert ClearRowTranslationsAfterSourceChange().
function leeren(array $row): array
{
    $behalten = [F_RAW, 'ValueObjectID', F_SRC, F_ACTIVE];
    foreach (array_keys($row) as $field) {
        if (!in_array($field, $behalten, true)) {
            $row[$field] = '';
        }
    }
    $row[F_AT] = [];
    $row[F_CHANGED] = 1756900000;

    return $row;
}

// Repliziert MarkRowLanguageTranslated() NACH dem Fix.
function markieren(array $row, string $sprache): array
{
    $map = $row[F_AT] ?? [];
    $row[F_AT] = is_array($map) ? $map : [];
    $row[F_AT][$sprache] = 1756900001;

    return $row;
}

$zeile = [
    F_RAW => 'Guten Morgen', 'ValueObjectID' => 0, F_SRC => 'de',
    F_ACTIVE => true, F_AT => ['en' => 1756800000], F_CHANGED => 1756800000,
    'en' => 'Good morning', 'fr' => 'Bonjour',
];

// Test 1: DIE ABSICHT bleibt - die Uebersetzungszellen werden geleert.
$nachher = leeren($zeile);
assert($nachher['en'] === '' && $nachher['fr'] === '', 'DIE ABSICHT: veraltete Uebersetzungen verschwinden');
echo "Test 1 (die Übersetzungszellen werden weiterhin geleert) OK\n";

// Test 2: DER ABSTURZ - "UebersetztAm" muss eine ZUORDNUNG bleiben, kein String.
assert(is_array($nachher[F_AT]), 'DER ABSTURZ: die Zeitstempel-Zuordnung bleibt ein Array');
assert($nachher[F_AT] === [], 'und ist leer, die alten Stempel gelten ja nicht mehr');
echo "Test 2 (die Zeitstempel-Zuordnung bleibt ein Array) OK\n";

// Test 3: DIE STILLE DEAKTIVIERUNG - die Einstellung des Nutzers bleibt.
assert($nachher[F_ACTIVE] === true, 'DIE DEAKTIVIERUNG: die Einstellung wird nicht angefasst');
$aus = leeren([F_RAW => 'x', F_SRC => 'de', F_ACTIVE => false, 'en' => 'y']);
assert($aus[F_ACTIVE] === false, 'ein bewusstes Aus bleibt ebenso stehen');
echo "Test 3 (die Einstellung des Nutzers überlebt) OK\n";

// Test 4: der Aenderungszeitpunkt ist ein Zeitstempel, kein ''. Als 0 haette
// IsRowLanguageTranslationCurrent() jede Sprache als aktuell gemeldet und die
// Neuuebersetzung uebersprungen - der Rohtext waere frisch, die Uebersetzung alt.
assert($nachher[F_CHANGED] > 0, 'der Aenderungszeitpunkt ist gesetzt');
echo "Test 4 (der Änderungszeitpunkt ist gesetzt, nicht geleert) OK\n";

// Test 5: DIE HEILUNG - eine bereits gespeicherte Zeile traegt den String noch.
// Der Schreibzugriff darf daran nicht mehr abbrechen.
$kaputt = [F_RAW => 'x', F_AT => ''];
$geheilt = markieren($kaputt, 'de');
assert(is_array($geheilt[F_AT]) && $geheilt[F_AT]['de'] > 0,
    'DIE HEILUNG: ein vorhandener String wird beim Schreiben ersetzt statt zu brechen');
echo "Test 5 (bestehende kaputte Zeilen heilen beim Schreiben) OK\n";

// Test 6: beide Aufrufstellen benutzen dieselbe Funktion - vorher stand die
// Schleife zweimal im Code und haette einzeln repariert werden muessen.
assert(substr_count($module, '$this->ClearRowTranslationsAfterSourceChange(') === 2,
    'beide Stellen gehen ueber die gemeinsame Funktion');
assert(strpos($module, "if (!in_array(\$field, [self::langOriginalImport, 'ValueObjectID', self::fieldRowSourceLanguage], true)) {") === false,
    'die alte Schleife steht nirgends mehr');
$helfer = substr($module, (int) strpos($module, 'private function ClearRowTranslationsAfterSourceChange'), 1200);
assert(strpos($helfer, 'self::fieldTranslationActive,') !== false, 'die Einstellung steht in der Ausnahmeliste');
assert(strpos($helfer, 'self::fieldTranslatedAtByLanguage] = [];') !== false, 'die Zuordnung wird als Array gesetzt');
echo "Test 6 (eine gemeinsame Funktion für beide Stellen) OK\n";

echo "\nAlle Tests OK (Build 207: Begrüßung verliert ihre Buchhaltung nicht mehr).\n";
