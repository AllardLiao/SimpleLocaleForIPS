<?php
declare(strict_types=1);
// Build 108 (Nutzer-Wunsch, 2026-08-21): Symcons eingebautes Chart-Element
// (WebFront visualization -> "Add Chart") zeigt pro Datenreihe einen eigenen
// Legenden-Titel ("Außentemperatur", "Wohnzimmer", ...) an - konfiguriert
// direkt im Medien-Inhalt des Charts (ObjectType 5 "Media", MediaType 4
// "MEDIATYPE_CHART"), NICHT als Objektname und NICHT als Variablen-
// Presentation-Caption. Diese Titel wurden bislang nie gescannt/übersetzt und
// blieben deshalb bei jedem Sprachwechsel unverändert Deutsch, während alles
// andere um sie herum (inkl. des Chart-Objektnamens selbst, siehe unten)
// bereits korrekt in die Zielsprache wechselte - live vom Nutzer per
// Screenshot gemeldet und mit einem echten IPS_GetMediaContent()-Dump
// bestätigt: {"datasets":[{"variableID":54040,"title":"Außentemperatur",...}, ...]}.
//
// Architektur (siehe SimpleLocaleConstants::propertyObjectCharts,
// SimpleLocale/module.php WalkTree()/MergeChartRows()/ApplyChartsLanguage()):
// ein Chart sitzt (anders als "Automations", die separat über die WebFront-
// Instanz gescannt werden) als normales Objekt im Root-Baum und wird daher
// direkt von WalkTree() mit erfasst - eindeutiger Zeilen-Schlüssel ist
// ChartID+VariableID, da ein Chart mehrere Datenreihen gleichzeitig zeigen
// kann. Schreiben passiert über IPS_SetMediaContent() (base64-kodiertes
// JSON), nicht IPS_SetName() - komplett unabhängig vom bereits bestehenden
// Objektnamen-Mechanismus, der den Chart-TITEL selbst (z.B. "Temperaturas")
// schon vorher automatisch übersetzt hat (jedes Objekt im Baum bekommt eine
// Objektnamen-Zeile, unabhängig vom Typ - nur die Legenden-Titel INNERHALB
// des Charts waren die Lücke).

function mergeChartRowsReplica(array $existingRows, array $scannedByKey): array
{
    $result = [];
    foreach ($existingRows as $row) {
        $key = ($row['ChartID'] ?? 0) . ':' . ($row['VariableID'] ?? 0);
        if (isset($scannedByKey[$key])) {
            $row['Path'] = $scannedByKey[$key]['Path'];
        }
        unset($scannedByKey[$key]);
        $result[] = $row;
    }
    foreach ($scannedByKey as $newRow) {
        $result[] = $newRow;
    }

    return $result;
}

function applyChartsLanguageReplica(array $rows, array $liveContent, string $language): array
{
    $rowsByVariableID = [];
    foreach ($rows as $row) {
        $rowsByVariableID[(int) $row['VariableID']] = $row;
    }

    foreach ($liveContent['datasets'] as &$dataset) {
        $row = $rowsByVariableID[(int) $dataset['variableID']] ?? null;
        if ($row === null) {
            continue;
        }
        $dataset['title'] = $row[$language] ?? $row['ORIGINAL_IMPORT'];
    }
    unset($dataset);

    return $liveContent;
}

// Test 1: WalkTree-Extraktion (simuliert) - vier Datenreihen aus dem echten,
// vom Nutzer gelieferten IPS_GetMediaContent()-Dump ergeben vier eigene
// Zeilen, je Schlüssel ChartID:VariableID.
$rawContent = json_decode('{"datasets":[
    {"variableID":54040,"title":"Außentemperatur","strokeColor":"#2c0aaa"},
    {"variableID":54083,"title":"Wohnzimmer","strokeColor":"#1ed078"},
    {"variableID":46335,"title":"Schlafzimmer","strokeColor":"#5cbb14"},
    {"variableID":24763,"title":"Bad","strokeColor":"#938067"}
]}', true);
$scanned = [];
foreach ($rawContent['datasets'] as $dataset) {
    $scanned['47446:' . $dataset['variableID']] = [
        'ChartID' => 47446, 'VariableID' => $dataset['variableID'],
        'Path' => 'WebFrontends > Kacheln', 'ORIGINAL_IMPORT' => $dataset['title'],
    ];
}
assert(count($scanned) === 4, 'Jede Datenreihe mit gesetztem variableID+title muss eine eigene Zeile ergeben');
assert($scanned['47446:54040']['ORIGINAL_IMPORT'] === 'Außentemperatur', 'Der Rohtext je Zeile muss exakt der Chart-eigene "title"-Wert sein');
echo "Test 1 (vier Datenreihen ergeben vier eigene ChartID:VariableID-Zeilen) OK\n";

// Test 2: MergeChartRows friert ORIGINAL_IMPORT/Übersetzungen für bekannte
// Zeilen ein (wie MergeAutomationRows/MergeRows), aktualisiert aber "Path" -
// Charts sitzen im Baum und können sich wie Objektnamen verschieben.
$existing = [[
    'ChartID' => 47446, 'VariableID' => 54040, 'Path' => 'Alter Pfad',
    'ORIGINAL_IMPORT' => 'Außentemperatur', 'es' => 'Temperatura exterior-korrigiert',
]];
$freshScan = ['47446:54040' => [
    'ChartID' => 47446, 'VariableID' => 54040, 'Path' => 'Neuer Pfad',
    'ORIGINAL_IMPORT' => 'Außentemperatur',
]];
$merged = mergeChartRowsReplica($existing, $freshScan);
assert($merged[0]['es'] === 'Temperatura exterior-korrigiert', 'Eine manuell korrigierte Übersetzung darf beim Merge nie verloren gehen');
assert($merged[0]['Path'] === 'Neuer Pfad', 'Path muss bei einer bekannten Zeile trotzdem aktualisiert werden (Chart kann verschoben worden sein)');
echo "Test 2 (bekannte Zeile: Übersetzung eingefroren, Path aktualisiert) OK\n";

// Test 3: eine neue Datenreihe (z.B. nachträglich zum Chart hinzugefügt) wird
// als komplett neue Zeile ergänzt, bestehende Zeilen bleiben unberührt.
$existing2 = [['ChartID' => 47446, 'VariableID' => 54040, 'ORIGINAL_IMPORT' => 'Außentemperatur', 'es' => 'Temperatura exterior']];
$freshScan2 = [
    '47446:54040' => ['ChartID' => 47446, 'VariableID' => 54040, 'Path' => 'p', 'ORIGINAL_IMPORT' => 'Außentemperatur'],
    '47446:99999' => ['ChartID' => 47446, 'VariableID' => 99999, 'Path' => 'p', 'ORIGINAL_IMPORT' => 'Neue Reihe'],
];
$merged2 = mergeChartRowsReplica($existing2, $freshScan2);
assert(count($merged2) === 2, 'Eine neu hinzugekommene Datenreihe muss als zusätzliche Zeile erscheinen, ohne die bestehende zu verdoppeln/verlieren');
assert($merged2[0]['es'] === 'Temperatura exterior', 'Die bestehende Zeile bleibt dabei unangetastet');
echo "Test 3 (neue Datenreihe wird ergänzt, bestehende bleibt unangetastet) OK\n";

// Test 4: ApplyChartsLanguage schreibt den übersetzten Titel in die
// jeweilige Datenreihe, andere Felder (Farbe etc.) bleiben unverändert.
$rows = [
    ['ChartID' => 47446, 'VariableID' => 54040, 'ORIGINAL_IMPORT' => 'Außentemperatur', 'es' => 'Temperatura exterior'],
    ['ChartID' => 47446, 'VariableID' => 54083, 'ORIGINAL_IMPORT' => 'Wohnzimmer', 'es' => 'Salón'],
];
$liveContent = ['datasets' => [
    ['variableID' => 54040, 'title' => 'Außentemperatur', 'strokeColor' => '#2c0aaa'],
    ['variableID' => 54083, 'title' => 'Wohnzimmer', 'strokeColor' => '#1ed078'],
]];
$applied = applyChartsLanguageReplica($rows, $liveContent, 'es');
assert($applied['datasets'][0]['title'] === 'Temperatura exterior', 'Datenreihe 1 muss den spanischen Titel bekommen');
assert($applied['datasets'][1]['title'] === 'Salón', 'Datenreihe 2 muss den spanischen Titel bekommen');
assert($applied['datasets'][0]['strokeColor'] === '#2c0aaa', 'Farbe und alle anderen Felder je Datenreihe dürfen unangetastet bleiben');
echo "Test 4 (ApplyChartsLanguage ersetzt nur \"title\", alles andere bleibt unangetastet) OK\n";

// Test 5: eine Datenreihe ohne passende getrackte Zeile (z.B. Variable noch
// nicht gescannt) bleibt unverändert - kein Fehler, kein Datenverlust.
$rowsPartial = [['ChartID' => 47446, 'VariableID' => 54040, 'ORIGINAL_IMPORT' => 'Außentemperatur', 'es' => 'Temperatura exterior']];
$liveContentPartial = ['datasets' => [
    ['variableID' => 54040, 'title' => 'Außentemperatur'],
    ['variableID' => 999999, 'title' => 'Unbekannte Reihe'],
]];
$appliedPartial = applyChartsLanguageReplica($rowsPartial, $liveContentPartial, 'es');
assert($appliedPartial['datasets'][1]['title'] === 'Unbekannte Reihe', 'Eine (noch) nicht getrackte Datenreihe bleibt unverändert, statt geleert/kaputt geschrieben zu werden');
echo "Test 5 (nicht getrackte Datenreihe bleibt unverändert) OK\n";

// Test 6: Symmetrie-Check - die reale module.php muss das neue
// propertyObjectCharts tatsächlich in allen zentralen Mechanismen verdrahtet
// haben (WalkTree-Erkennung, Merge, Apply, Aufräumen, Formular-Spalten,
// GetTranslatableFieldGroupsByProperty fürs Quellsprachen-/Fingerprint-
// Handling, Fingerprint-Property-Liste).
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, "MEDIATYPE_CHART") !== false, 'WalkTree muss Chart-Media-Objekte über MEDIATYPE_CHART erkennen');
assert(strpos($moduleSource, 'private function MergeChartRows(') !== false, 'MergeChartRows() muss existieren');
assert(strpos($moduleSource, 'private function ApplyChartsLanguage(') !== false, 'ApplyChartsLanguage() muss existieren');
assert(strpos($moduleSource, '$this->ApplyChartsLanguage($Language, $sourceLanguage);') !== false, 'ApplyLanguage() muss ApplyChartsLanguage() tatsächlich aufrufen');
assert(strpos($moduleSource, "self::propertyObjectCharts => [") !== false, 'propertyObjectCharts muss in GetTranslatableFieldGroupsByProperty() eingetragen sein (Quellsprachen-Abgleich/Fingerprint/Staging)');
assert(substr_count($moduleSource, 'self::propertyObjectCharts,') >= 1, 'propertyObjectCharts muss in der Fingerprint-Property-Liste stehen');
assert(strpos($moduleSource, "\$liveCharts") !== false, 'CleanupOrphanedRows() muss Charts mit aufräumen');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
assert(strpos($constantsSource, "propertyObjectCharts = 'ObjectCharts'") !== false, 'Die Property-Konstante muss existieren');
echo "Test 6 (propertyObjectCharts ist in allen zentralen Mechanismen der realen module.php verdrahtet) OK\n";

// Test 7 (2026-08-21, live gemeldeter Nachtrag, Build 108): eine Variable,
// die zusätzlich als eigene Anzeige-Kachel im Root-Baum liegt, wird bereits
// über "Objektnamen" übersetzt - und Symcon übernimmt diesen neuen Namen
// nachweislich automatisch in die Chart-Legende, ganz ohne Zutun dieses
// Moduls. Für so eine Datenreihe darf KEINE eigene Charts-Zeile entstehen
// (keine doppelte/konkurrierende Übersetzung).
//
// Test 7b (2026-08-21, live gefundener KORRIGIERTER Bug, Build 109): die
// ursprüngliche Build-108-Heuristik verglich den Titel stattdessen gegen den
// AKTUELLEN Live-Namen der Variable - das ist FALSCH: Symcon füllt "title"
// beim Anlegen einer Datenreihe standardmäßig mit dem damaligen
// Variablennamen, auch wenn diese Variable NIRGENDS sonst im Baum
// eigenständig steht. Live beobachtet an einem zweiten Chart
// ("Humedad del aire" / Luftfeuchtigkeit): alle drei Datenreihen wurden
// fälschlich übersprungen (Titel == damaliger, nie geänderter Live-Name),
// obwohl NICHTS sie je übersetzt hätte - das Chart verschwand komplett aus
// der neuen "Charts"-Liste. Der Live-Namens-Vergleich wurde daher wieder
// entfernt; korrekt ist ausschließlich die Prüfung, ob die Variable
// TATSÄCHLICH als eigenständiges Objekt im selben Root-Baum-Scan gefunden
// wurde (ExcludeChartRowsForIndependentlyNamedVariables, angewendet NACH
// Abschluss des kompletten WalkTree()-Durchlaufs).
function excludeChartRowsForIndependentlyNamedVariablesReplica(array $scannedCharts, array $scannedNames): array
{
    foreach ($scannedCharts as $key => $row) {
        if (isset($scannedNames[(int) $row['VariableID']])) {
            unset($scannedCharts[$key]);
        }
    }

    return $scannedCharts;
}

// "Irradiación luminosa": Brillo (Este) steht zusätzlich eigenständig im Baum
// (ObjectID 54040) -> muss rausgefiltert werden, egal was ihr Titel gerade ist.
$scannedNamesIrradiacion = [54040 => ['ObjectID' => 54040, 'ORIGINAL_IMPORT' => 'Glanz (Osten)']];
$scannedChartsIrradiacion = [
    '99001:54040' => ['ChartID' => 99001, 'VariableID' => 54040, 'ORIGINAL_IMPORT' => 'Glanz (Osten)'],
];
$filtered1 = excludeChartRowsForIndependentlyNamedVariablesReplica($scannedChartsIrradiacion, $scannedNamesIrradiacion);
assert($filtered1 === [], 'Eine Variable, die zusätzlich eigenständig im Baum steht, darf keine Charts-Zeile bekommen - Symcon übersetzt die Legende bereits über "Objektnamen" mit');
echo "Test 7 (eigenständig im Baum stehende Variable wird korrekt herausgefiltert) OK\n";

// "Humedad del aire" (der korrigierte Bug): KEINE der drei Variablen steht
// eigenständig im Baum ($scannedNames leer) - alle drei Zeilen MÜSSEN
// erhalten bleiben, unabhängig davon, ob ihr Titel gerade zufällig noch dem
// (nie geänderten) Live-Namen entspricht.
$scannedNamesHumedad = []; // keine der drei Variablen ist eigenständig im Baum
$scannedChartsHumedad = [
    '28636:1' => ['ChartID' => 28636, 'VariableID' => 1, 'ORIGINAL_IMPORT' => 'Luftfeuchtigkeit'],
    '28636:2' => ['ChartID' => 28636, 'VariableID' => 2, 'ORIGINAL_IMPORT' => 'Luftfeuchtigkeit'],
    '28636:3' => ['ChartID' => 28636, 'VariableID' => 3, 'ORIGINAL_IMPORT' => 'Luftfeuchtigkeit'],
];
$filtered2 = excludeChartRowsForIndependentlyNamedVariablesReplica($scannedChartsHumedad, $scannedNamesHumedad);
assert(count($filtered2) === 3, 'DER BUG: alle drei Datenreihen müssen als Charts-Zeilen erhalten bleiben, wenn keine ihrer Variablen eigenständig im Baum steht - sonst übersetzt sie niemand');
echo "Test 7b (Regressionsfall 'Humedad del aire': keine unabhängig-im-Baum-stehende Variable -> alle Zeilen bleiben erhalten) OK\n";

// Test 8: Symmetrie-Check - die reale module.php darf NICHT mehr gegen
// IPS_GetName() vergleichen (der korrigierte Bug), muss aber die neue
// Filterfunktion NACH dem WalkTree()-Aufruf in beiden Aufrufern verwenden.
assert(strpos($moduleSource, '$datasetTitle === @IPS_GetName($datasetVariableID)') === false, 'Der fehlerhafte Live-Namens-Vergleich darf nicht mehr in WalkTree() stehen (Build 109 korrigiert genau das)');
assert(strpos($moduleSource, 'private function ExcludeChartRowsForIndependentlyNamedVariables(') !== false, 'Die Filterfunktion muss existieren');
assert(strpos($moduleSource, '$this->ExcludeChartRowsForIndependentlyNamedVariables($scannedCharts, $scannedNames)') !== false, 'ScanRootTree() muss die Filterfunktion nach WalkTree() anwenden');
assert(strpos($moduleSource, '$liveCharts = $this->ExcludeChartRowsForIndependentlyNamedVariables($liveCharts, $liveNames);') !== false, 'CleanupOrphanedRows() muss dieselbe Filterfunktion anwenden');
echo "Test 8 (die korrigierte Filterfunktion ist in beiden Aufrufern der realen module.php verdrahtet, der fehlerhafte Vergleich ist entfernt) OK\n";

// Test 9 (2026-08-21, live gefundener KORRIGIERTER Bug, Build 110): Build
// 109 ging noch davon aus, ein leerer Titel sei die Ausnahme (Symcon fülle
// ihn beim Anlegen standardmäßig - diese Annahme war ebenfalls falsch). Der
// echte IPS_GetMediaContent()-Dump von "Humedad del aire" zeigte: ALLE DREI
// Datenreihen hatten `"title":""` (leer) - der bisherige Code
// (`if ($datasetVariableID === 0 || $datasetTitle === '') continue;`)
// übersprang sie deshalb schlicht ALLE, obwohl keine ihrer Variablen
// eigenständig im Baum steht (siehe Test 7b) und die Legende in der Kachel
// nachweislich unübersetzt "Luftfeuchtigkeit" zeigte. Symcon rendert bei
// leerem Titel live den AKTUELLEN Variablennamen - genau DAS muss also der
// Quelltext sein, den WalkTree für so eine Zeile verwendet.
function walkTreeChartSourceTextReplica(string $datasetTitle, int $variableID, callable $liveVariableName): string
{
    return $datasetTitle !== '' ? $datasetTitle : $liveVariableName($variableID);
}

$sourceExplicit = walkTreeChartSourceTextReplica('Außentemperatur', 54040, fn ($id) => 'sollte nie aufgerufen werden');
assert($sourceExplicit === 'Außentemperatur', 'Ein expliziter, nicht-leerer Titel muss unverändert als Quelltext übernommen werden');

$sourceFallback = walkTreeChartSourceTextReplica('', 1, fn ($id) => 'Luftfeuchtigkeit');
assert($sourceFallback === 'Luftfeuchtigkeit', 'DER BUG: bei leerem Titel muss der aktuelle Variablenname als Quelltext verwendet werden, sonst wird nie irgendetwas übersetzt');
echo "Test 9 (leerer Titel fällt auf den aktuellen Variablennamen als Quelltext zurück) OK\n";

// Test 10: Symmetrie-Check - die reale module.php darf Datenreihen nicht
// mehr allein wegen eines leeren Titels überspringen, sondern muss den
// Live-Namen als Fallback-Quelltext verwenden.
assert(strpos($moduleSource, "\$datasetVariableID === 0 || \$datasetTitle === ''") === false, 'Der alte, zu grobe "leerer Titel -> überspringen"-Check darf nicht mehr existieren (Build 110 korrigiert genau das)');
assert(strpos($moduleSource, '$sourceText = $datasetTitle !== \'\' ? $datasetTitle : (string) @IPS_GetName($datasetVariableID);') !== false, 'WalkTree() muss bei leerem Titel auf den aktuellen Live-Namen der Variable zurückfallen');
echo "Test 10 (der leerer-Titel-Fallback ist tatsächlich in der realen WalkTree() verdrahtet) OK\n";

// Test 11 (2026-08-21, live gefundener KORRIGIERTER Bug, Build 112): Build
// 109/110s ExcludeChartRowsForIndependentlyNamedVariables() unterschied
// bislang nicht zwischen einer Zeile, deren Quelltext aus dem Leer-Titel-
// Fallback stammt (Build 110), und einer Zeile mit einem ECHTEN, im Chart
// selbst gesetzten Titel - beide wurden gleich behandelt und bei
// eigenständig im Baum stehender Variable ausgeschlossen. Live beobachtet:
// "Außentemperatur" (Temperaturas) hatte einen expliziten, eigenen
// Chart-Titel, dessen Variable ZUFÄLLIG zusätzlich eigenständig im Baum
// stand - "Aufräumen" löschte die Zeile daraufhin fälschlich, obwohl
// Symcons Leer-Titel-Fallback (Build 110) hier gar nicht greift (ein
// gesetzter Titel wird immer unverändert angezeigt, unabhängig vom
// Variablennamen). Die Regel darf daher NUR für Zeilen mit
// '_EmptyTitleFallback' === true gelten.
function excludeChartRowsForIndependentlyNamedVariablesReplicaV2(array $scannedCharts, array $scannedNames): array
{
    foreach ($scannedCharts as $key => $row) {
        $isEmptyTitleFallback = $row['_EmptyTitleFallback'] ?? false;
        if ($isEmptyTitleFallback && isset($scannedNames[(int) $row['VariableID']])) {
            unset($scannedCharts[$key]);
        }
    }

    return $scannedCharts;
}

// "Außentemperatur": echter, eigener Chart-Titel (_EmptyTitleFallback=false),
// Variable steht ZUFÄLLIG zusätzlich eigenständig im Baum -> DARF NICHT
// ausgeschlossen werden, der eigene Titel ist unabhängig vom Variablennamen.
$scannedNamesTemperaturas = [54040 => ['ObjectID' => 54040, 'ORIGINAL_IMPORT' => 'Außentemperatur (Sensor)']];
$scannedChartsTemperaturas = [
    '47446:54040' => ['ChartID' => 47446, 'VariableID' => 54040, 'ORIGINAL_IMPORT' => 'Außentemperatur', '_EmptyTitleFallback' => false],
];
$filtered3 = excludeChartRowsForIndependentlyNamedVariablesReplicaV2($scannedChartsTemperaturas, $scannedNamesTemperaturas);
assert(count($filtered3) === 1, 'DER BUG: eine Zeile mit echtem, eigenem Chart-Titel darf NIE ausgeschlossen werden, auch wenn ihre Variable zusätzlich eigenständig im Baum steht');
echo "Test 11 (echter Chart-Titel bleibt erhalten, auch wenn die Variable zusätzlich eigenständig im Baum steht) OK\n";

// Gegenprobe: "Irradiación luminosa"-Fall (Test 7) muss mit der V2-Funktion
// weiterhin exakt gleich funktionieren, jetzt mit explizitem Flag.
$scannedNamesIrradiacionV2 = [54040 => ['ObjectID' => 54040, 'ORIGINAL_IMPORT' => 'Glanz (Osten)']];
$scannedChartsIrradiacionV2 = [
    '99001:54040' => ['ChartID' => 99001, 'VariableID' => 54040, 'ORIGINAL_IMPORT' => 'Glanz (Osten)', '_EmptyTitleFallback' => true],
];
$filtered4 = excludeChartRowsForIndependentlyNamedVariablesReplicaV2($scannedChartsIrradiacionV2, $scannedNamesIrradiacionV2);
assert($filtered4 === [], 'Der Leer-Titel-Fallback-Fall (Test 7) muss weiterhin korrekt ausgeschlossen werden');
echo "Test 12 (Leer-Titel-Fallback-Fall weiterhin korrekt ausgeschlossen) OK\n";

// Test 13: Symmetrie-Check - die reale module.php muss das transiente Flag
// setzen, prüfen, UND vor dem Persistieren wieder entfernen (kein Ballast in
// der gespeicherten Property/Formular-Tabelle).
assert(strpos($moduleSource, "'_EmptyTitleFallback'                       => \$datasetTitle === ''") !== false, 'WalkTree() muss das transiente Flag anhand des ORIGINALEN Titels setzen');
assert(strpos($moduleSource, '$isEmptyTitleFallback = $row[\'_EmptyTitleFallback\'] ?? false;') !== false, 'ExcludeChartRowsForIndependentlyNamedVariables() muss das Flag auswerten, nicht mehr pauschal ausschließen');
assert(strpos($moduleSource, "unset(\$newRow['_EmptyTitleFallback']);") !== false, 'MergeChartRows() muss das transiente Flag vor dem Persistieren neuer Zeilen entfernen');
echo "Test 13 (das transiente Flag ist korrekt gesetzt, ausgewertet und vor dem Persistieren entfernt) OK\n";

// Test 14 (2026-08-21, live gemeldeter SCHWERER Bug, Build 113): live
// gemeldet, dass nach "Aufräumen" plötzlich viele manuell korrigierte
// "Objektnamen"-Zeilen fehlten, obwohl ihre Objekte in der Visualisierung
// nachweislich noch existierten - starker Verdacht, dass eine PHP-Exception
// (z.B. aus IPS_GetMedia()/IPS_GetMediaContent() bei einem ungewöhnlich
// konfigurierten Medienobjekt - @ unterdrückt NUR Warnungen, KEINE
// geworfenen Exceptions) den kompletten WalkTree()-Durchlauf mitten im Baum
// abbricht. "Aufräumen" hält dann jedes ab diesem Punkt noch nicht
// besuchte, in Wahrheit weiterhin existierende Objekt für verwaist und
// löscht seine Zeile - ein nachfolgender Rescan übersetzt sie als "neu"
// frisch, jede manuelle Korrektur ist verloren. Der gesamte Chart-Scan-Block
// in WalkTree() muss daher in einem eigenen try/catch laufen, damit ein
// Fehler bei einem einzelnen Objekt nie mehr den Rest des Baum-Scans
// gefährdet.
assert(strpos($moduleSource, 'if ($object[\'ObjectType\'] === OBJECTTYPE_MEDIA) {' . "\n" . '                try {') !== false, 'Der Chart-Scan-Block muss in einem try{} laufen (Absicherung gegen eine Exception, die sonst den kompletten WalkTree()-Durchlauf abbricht)');
assert(strpos($moduleSource, '} catch (\Throwable $e) {') !== false, 'Der Chart-Scan-Block muss eine Exception fangen (nicht nur Warnungen per @ unterdrücken) und geloggt weiterlaufen, statt den ganzen Scan abzubrechen');
echo "Test 14 (der Chart-Scan-Block ist gegen eine Exception abgesichert, die sonst den gesamten Baum-Scan abbrechen und dadurch andere Zeilen fälschlich als verwaist erscheinen lassen könnte) OK\n";

echo "\nAll tests passed.\n";
