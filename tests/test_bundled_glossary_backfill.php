<?php
declare(strict_types=1);
// Standalone replica test for build 157 (2026-08-26, live gemeldet, per zwei
// Screenshots belegt):
//
// SYMPTOM 1: In der "Eigenen Uebersetzungstabelle" standen die mitgelieferten
// Einheiten-Zeilen (°C, °F, K, Pa, hPa, kVA, Ah, mAh ...) zwar da, ihre
// Sprachspalten waren aber LEER.
// SYMPTOM 2: Deshalb wurde das Suffix "°C" ganz normal an die API geschickt -
// und kam auf Englisch als "°F" zurueck. Eine Einheitenumrechnung, keine
// Uebersetzung.
//
// URSACHE: MergeBundledManualTranslations() uebersprang eine Zeile vollstaendig,
// sobald es sie schon gab ("isset($existingKeys[$unit]) -> continue"). Kam eine
// Zielsprache erst SPAETER dazu, wurde ihre Spalte nie mehr befuellt. Und eine
// leere Zelle gilt in FindManualTranslation() als "kein Treffer" - der Text
// laeuft dann in die API statt ins Glossar.

// Repliziert die mitgelieferte Zuordnung (Ausschnitt).
const BUNDLED = [
    '°C'  => ['en' => '°C',  'fr' => '°C',  'ru' => '°C'],
    'SSW' => ['en' => 'SSW', 'fr' => 'SSO', 'ru' => 'ЮЮЗ'],
];

// Repliziert MergeBundledManualTranslations() NACH dem Fix.
function mergeReplica(array $rows, array $alreadySeeded, bool $mitBackfill): array
{
    $existing = [];
    foreach ($rows as $i => $row) {
        if (($row['Quellsprache'] ?? '') !== 'de') { continue; }
        $text = (string) ($row['ORIGINAL_IMPORT'] ?? '');
        $existing[$text] = true;
        if (!$mitBackfill || !isset(BUNDLED[$text])) { continue; }
        foreach (BUNDLED[$text] as $lang => $wert) {
            if ((string) ($row[$lang] ?? '') !== '') { continue; }
            $row[$lang] = $wert;
        }
        $rows[$i] = $row;
    }

    foreach (BUNDLED as $text => $proSprache) {
        if (isset($existing[$text]) || isset($alreadySeeded[$text])) { continue; }
        $rows[] = ['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => $text] + $proSprache;
    }

    return $rows;
}

// Repliziert FindManualTranslation(): eine leere Zelle ist KEIN Treffer.
function findManual(array $rows, string $quelle, string $ziel, string $text): ?string
{
    foreach ($rows as $row) {
        if (($row['Quellsprache'] ?? '') !== $quelle) { continue; }
        if ((string) ($row['ORIGINAL_IMPORT'] ?? '') !== $text) { continue; }
        $t = (string) ($row[$ziel] ?? '');
        if ($t !== '') { return $t; }
    }

    return null;
}

// Ausgangslage wie live: die Zeile existiert, Englisch kam erst spaeter als
// Zielsprache dazu und ist deshalb leer.
$vorher = [['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => '°C', 'fr' => '°C']];
$seeded = ['°C' => true, 'SSW' => true];

// Test 1: DER GEMELDETE FALL - ohne Backfill bleibt die Zelle leer.
$ohne = mergeReplica($vorher, $seeded, false);
assert(($ohne[0]['en'] ?? '') === '', 'DER BUG: eine spaeter hinzugekommene Zielsprache blieb dauerhaft leer');
assert(findManual($ohne, 'de', 'en', '°C') === null, 'und damit fand das Glossar nichts - der Text ging an die API');
echo "Test 1 (der gemeldete Fall wird reproduziert: leere Spalte, kein Glossartreffer) OK\n";

// Test 2: DER FIX - die leere Zelle wird nachbefuellt und das Glossar greift.
$mit = mergeReplica($vorher, $seeded, true);
assert($mit[0]['en'] === '°C', 'DER FIX: die leere Sprachspalte muss nachbefuellt werden');
assert(findManual($mit, 'de', 'en', '°C') === '°C', 'DER KERN: "°C" muss aus dem Glossar kommen statt aus der API - sonst wird daraus "°F"');
echo "Test 2 (die Zelle wird nachbefüllt und \"°C\" bleibt \"°C\") OK\n";

// Test 3: EIN ADMIN-WERT GEWINNT IMMER - auch wenn er vom Vorschlag abweicht.
// Sonst wuerde eine bewusste Korrektur bei jedem Rescan wieder ueberschrieben.
$eigen = [['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'SSW', 'en' => 'Sued-Sued-West (Herr Schmidt)']];
$nachher = mergeReplica($eigen, ['SSW' => true], true);
assert($nachher[0]['en'] === 'Sued-Sued-West (Herr Schmidt)', 'ein bereits eingetragener Wert darf NIE ueberschrieben werden');
echo "Test 3 (ein eingetragener Wert wird nie überschrieben) OK\n";

// Test 4: eine bewusst GELOESCHTE Zeile bleibt geloescht - der Backfill darf
// den bestehenden Schutz nicht aushebeln.
$leer = mergeReplica([], ['°C' => true, 'SSW' => true], true);
assert($leer === [], 'DER BESTEHENDE SCHUTZ: einmal geloeschte Vorschlaege duerfen nicht zurueckkehren');
echo "Test 4 (bewusst gelöschte Vorschlagszeilen kehren nicht zurück) OK\n";

// Test 5: eine unbekannte Zeile des Admins wird nicht angefasst.
$fremd = [['Quellsprache' => 'de', 'ORIGINAL_IMPORT' => 'Hauswirtschaftsraum']];
$nachher = mergeReplica($fremd, ['°C' => true, 'SSW' => true], true);
assert($nachher[0] === $fremd[0], 'Zeilen ausserhalb des Katalogs duerfen unveraendert bleiben');
echo "Test 5 (eigene Zeilen des Admins bleiben unangetastet) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function MergeBundledManualTranslations');
assert($start !== false, 'MergeBundledManualTranslations() muss existieren');
$ende = strpos($moduleSource, "\n    private function BuildBundledManualTranslationMap", $start);
$body = substr($moduleSource, $start, $ende - $start);
$body = implode("\n", array_filter(
    explode("\n", $body),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));

assert(strpos($body, 'BuildBundledManualTranslationMap()') !== false,
    'Anlegen und Nachbefuellen muessen aus DERSELBEN Quelle speisen');
assert(strpos($body, "if ((string) (\$row[\$language] ?? '') !== '') {") !== false,
    'DER FIX: nur LEERE Zellen duerfen befuellt werden - ein Admin-Wert gewinnt');
assert(strpos($body, 'isset($alreadySeeded[$sourceText])') !== false,
    'der Schutz gegen zurueckkehrende geloeschte Zeilen muss erhalten bleiben');

// Und die Gegenseite: eine leere Zelle darf weiterhin als "kein Treffer" gelten -
// sonst lieferte das Glossar Leerstrings aus.
$findStart = strpos($moduleSource, 'private function FindManualTranslation');
$findBody = substr($moduleSource, $findStart, 900);
assert(strpos($findBody, "if (\$translation !== '') {") !== false,
    'FindManualTranslation() muss eine leere Zelle weiterhin ueberspringen');
echo "Test 6 (die reale Umsetzung befüllt nur Leerzellen aus einer gemeinsamen Quelle) OK\n";

echo "\nAll tests passed.\n";
