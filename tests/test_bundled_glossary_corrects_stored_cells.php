<?php
declare(strict_types=1);
// Standalone replica test for build 159 (2026-08-26, live gemeldet):
// "build 158 - keine Änderung. Übersetzung wieder von °C nach °F..."
//
// Build 158 hatte nur die halbe Strecke gebaut. Der mitgelieferte Katalog griff
// zwar bei NEUEN Uebersetzungen (FindManualTranslation), aber eine bereits
// falsch gespeicherte Zelle wurde nie wieder angefasst: befuellte Zellen werden
// nicht neu uebersetzt. Der einzige Durchlauf, der gespeicherte Zellen
// nachtraeglich korrigiert, ist ApplyManualTranslationOverrides() - und der
// stieg ohne das Feature "manual_translations" sofort aus.
//
// Ergebnis live: das Suffix "°C" blieb als "°F" gespeichert, obwohl das Modul
// die richtige Antwort inzwischen kannte.

const BUNDLED = ['°C' => ['en' => '°C'], 'SSW' => ['en' => 'SSW']];

// Repliziert FindManualTranslation() ab Build 158.
function findManual(array $rows, string $quelle, string $ziel, string $text, bool $hatFeature): ?string
{
    foreach ($rows as $row) {
        if (($row['Quellsprache'] ?? '') !== $quelle) { continue; }
        if ((string) ($row['ORIGINAL_IMPORT'] ?? '') !== $text) { continue; }
        $t = (string) ($row[$ziel] ?? '');
        if ($t !== '') { return $t; }
    }
    if ($hatFeature || $quelle !== 'de') { return null; }
    $t = (string) (BUNDLED[$text][$ziel] ?? '');

    return $t !== '' ? $t : null;
}

// Repliziert ApplyManualTranslationOverrides() NACH dem Fix.
function overridesReplica(array $rows, array $tabelle, bool $hatFeature, array $zielsprachen): array
{
    if ($hatFeature && $tabelle === []) {
        return $rows;
    }
    foreach ($rows as $i => $row) {
        $quelltext = (string) ($row['ORIGINAL_IMPORT'] ?? '');
        if ($quelltext === '') { continue; }
        foreach ($zielsprachen as $ziel) {
            $wert = findManual($tabelle, 'de', $ziel, $quelltext, $hatFeature);
            if ($wert === null || (string) ($row[$ziel] ?? '') === $wert) { continue; }
            $row[$ziel] = $wert;
        }
        $rows[$i] = $row;
    }

    return $rows;
}

// Ausgangslage wie live: das Suffix wurde einmal falsch uebersetzt gespeichert.
$gespeichert = [['ORIGINAL_IMPORT' => '°C', 'de' => '°C', 'en' => '°F']];

// Test 1: DER GEMELDETE FALL - vor Build 159 stieg der Durchlauf ohne Feature
// sofort aus, die falsche Zelle blieb stehen.
function overridesVorher(array $rows, array $tabelle, bool $hatFeature, array $ziele): array
{
    if (!$hatFeature) { return $rows; }

    return overridesReplica($rows, $tabelle, $hatFeature, $ziele);
}
$vorher = overridesVorher($gespeichert, [], false, ['de', 'en']);
assert($vorher[0]['en'] === '°F', 'DER BUG: ohne das Feature wurde die falsch gespeicherte Zelle nie korrigiert');
echo "Test 1 (der gemeldete Fall wird reproduziert: \"°F\" bleibt stehen) OK\n";

// Test 2: DER FIX - der Durchlauf laeuft jetzt auch ohne Feature und korrigiert
// die gespeicherte Zelle gegen den Katalog.
$nachher = overridesReplica($gespeichert, [], false, ['de', 'en']);
assert($nachher[0]['en'] === '°C', 'DER FIX: die bereits gespeicherte falsche Uebersetzung muss korrigiert werden');
echo "Test 2 (die gespeicherte Zelle wird gegen den Katalog korrigiert) OK\n";

// Test 3: die Abkuerzung "Feature vorhanden, Tabelle leer" bleibt erhalten -
// dort gibt es wirklich nichts zu pruefen.
$mitFeature = overridesReplica($gespeichert, [], true, ['de', 'en']);
assert($mitFeature[0]['en'] === '°F', 'MIT Feature und leerer Tabelle darf nichts passieren - dort ist die Tabelle massgeblich');
echo "Test 3 (mit Feature und leerer Tabelle bleibt alles unangetastet) OK\n";

// Test 4: DIE FALLE - ohne Feature darf NICHT ueber die leere Liste abgekuerzt
// werden. Genau diese Abkuerzung war der Fehler.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function ApplyManualTranslationOverrides');
assert($start !== false, 'ApplyManualTranslationOverrides() muss existieren');
$body = substr($moduleSource, $start, 2600);
$codeOnly = implode("\n", array_filter(
    explode("\n", $body),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));
assert(strpos($codeOnly, "if (!\$this->HasLicenseFeature('manual_translations')) {\n            return \$Rows;") === false,
    'DER FIX: der Durchlauf darf ohne das Feature nicht mehr sofort aussteigen');
assert(strpos($codeOnly, 'if ($hasManualTranslations && $manualTranslations === []) {') !== false,
    'die Abkuerzung darf NUR noch greifen, wenn das Feature vorhanden UND die Tabelle leer ist');
echo "Test 4 (die reale Umsetzung kürzt nur noch mit Feature ab) OK\n";

// Test 5: eine bereits korrekte Zelle wird nicht unnoetig angefasst.
$korrekt = [['ORIGINAL_IMPORT' => '°C', 'en' => '°C']];
assert(overridesReplica($korrekt, [], false, ['en']) === $korrekt, 'eine bereits korrekte Zelle darf unveraendert bleiben');
echo "Test 5 (eine bereits korrekte Zelle bleibt unverändert) OK\n";

// Test 6: Texte ausserhalb des Katalogs bleiben unangetastet - der Durchlauf
// darf keine normalen Uebersetzungen ueberschreiben.
$normal = [['ORIGINAL_IMPORT' => 'Wohnzimmer', 'en' => 'Living room']];
assert(overridesReplica($normal, [], false, ['en']) === $normal, 'normale Uebersetzungen duerfen nicht angefasst werden');
echo "Test 6 (normale Übersetzungen bleiben unangetastet) OK\n";

echo "\nAll tests passed.\n";
