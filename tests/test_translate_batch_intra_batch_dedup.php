<?php
declare(strict_types=1);
// Build 117 (live gefunden, 2026-08-22): der Nutzer beobachtete im Debug-Log
// acht IDENTISCHE MyMemory-Anfragen für exakt denselben Text
// ("Überwiegend bewölkt") innerhalb weniger Sekunden, während eine
// Wetter-Kachel aktualisiert wurde. Ursache: eine Wettervorhersage-HTMLBox
// (z.B. "8-Tage Vorhersage") wird per SplitHtmlIntoTextNodes() in einzelne
// Text-Knoten zerlegt und ALLE zusammen in einem einzigen TranslateBatch()-
// Aufruf übersetzt - haben mehrere Tage zufällig dieselbe Beschreibung (z.B.
// "Überwiegend bewölkt" an mehreren Tagen), landete jedes weitere Vorkommen
// GENAUSO im "braucht frische Übersetzung"-Topf wie das erste, weil der
// persistente Cache (GetCachedTranslation) für diesen Text noch leer war -
// erst NACH Abschluss des GESAMTEN Batches wird der Cache befüllt (siehe
// TranslateBatch), zu spät für die anderen Vorkommen IM SELBEN Batch. Für
// MyMemory (kein echter Batch-Endpunkt, ein HTTP-Request pro Text, siehe
// TranslateChunkFree) bedeutete das: derselbe Text wird so oft angefragt,
// wie er im Batch vorkommt, statt nur einmal.

function translateBatchReplica(array $texts, callable $translateUncachedFn): array
{
    $cache = [];
    $results = [];
    $freshIndexes = [];
    $freshTexts = [];
    $textToFreshPosition = [];
    $duplicateFreshPositions = [];

    foreach ($texts as $i => $text) {
        if (isset($cache[$text])) {
            $results[$i] = $cache[$text];
            continue;
        }
        if (isset($textToFreshPosition[$text])) {
            $duplicateFreshPositions[$i] = $textToFreshPosition[$text];
            continue;
        }
        $textToFreshPosition[$text] = count($freshTexts);
        $freshIndexes[] = $i;
        $freshTexts[] = $text;
    }

    if ($freshTexts !== []) {
        $translated = $translateUncachedFn($freshTexts);
        foreach ($freshIndexes as $position => $originalIndex) {
            $results[$originalIndex] = $translated[$position];
            $cache[$freshTexts[$position]] = $translated[$position];
        }
        foreach ($duplicateFreshPositions as $originalIndex => $freshPosition) {
            $results[$originalIndex] = $results[$freshIndexes[$freshPosition]];
        }
    }

    ksort($results);

    return array_values($results);
}

// Test 1: THE REPORTED BUG - eine Wettervorhersage mit 8 Tagen, von denen
// mehrere dieselbe Beschreibung teilen, darf den (simulierten) Anbieter nur
// EINMAL je EINDEUTIGEM Text aufrufen, nicht einmal je Vorkommen.
$apiCallLog = [];
$fakeProvider = function (array $freshTexts) use (&$apiCallLog): array {
    foreach ($freshTexts as $text) {
        $apiCallLog[] = $text;
    }

    return array_map(fn ($t) => mb_strtoupper($t, 'UTF-8'), $freshTexts);
};

$weatherTexts = [
    'Überwiegend bewölkt', 'Leicht bewölkt', 'Überwiegend bewölkt', 'Sonnig',
    'Überwiegend bewölkt', 'Leicht bewölkt', 'Überwiegend bewölkt', 'Überwiegend bewölkt',
];
$result = translateBatchReplica($weatherTexts, $fakeProvider);

assert(count(array_unique($apiCallLog)) === count($apiCallLog), 'DER BUG: der (simulierte) Anbieter darf pro eindeutigem Text nur EINMAL aufgerufen werden, nicht einmal je Vorkommen');
assert(count($apiCallLog) === 3, 'Es gibt genau 3 eindeutige Texte ("Überwiegend bewölkt", "Leicht bewölkt", "Sonnig") - genau 3 Aufrufe erwartet, nicht 8');
assert($result === ['ÜBERWIEGEND BEWÖLKT', 'LEICHT BEWÖLKT', 'ÜBERWIEGEND BEWÖLKT', 'SONNIG', 'ÜBERWIEGEND BEWÖLKT', 'LEICHT BEWÖLKT', 'ÜBERWIEGEND BEWÖLKT', 'ÜBERWIEGEND BEWÖLKT'], 'Jedes Vorkommen muss trotzdem sein korrektes, an der richtigen Position stehendes Ergebnis bekommen, auch die Duplikate');
echo "Test 1 (8 Vorkommen mit 3 eindeutigen Texten -> nur 3 Anbieter-Aufrufe statt 8, alle 8 Ergebnisse trotzdem korrekt) OK\n";

// Test 2: keine Duplikate im Batch - unverändertes Verhalten, ein Aufruf pro
// eindeutigem Text (Normalfall, keine Regression).
$apiCallLog2 = [];
$fakeProvider2 = function (array $freshTexts) use (&$apiCallLog2): array {
    foreach ($freshTexts as $text) {
        $apiCallLog2[] = $text;
    }

    return array_map(fn ($t) => mb_strtoupper($t, 'UTF-8'), $freshTexts);
};
$noDuplicates = ['Sonnig', 'Regnerisch', 'Windig'];
$result2 = translateBatchReplica($noDuplicates, $fakeProvider2);
assert(count($apiCallLog2) === 3, 'Ohne Duplikate muss weiterhin jeder eindeutige Text genau einmal angefragt werden');
assert($result2 === ['SONNIG', 'REGNERISCH', 'WINDIG'], 'Ergebnisse müssen unverändert korrekt und in der richtigen Reihenfolge sein');
echo "Test 2 (kein Duplikat im Batch -> unverändertes Verhalten, keine Regression) OK\n";

// Test 3: Symmetrie-Check - die reale module.php muss die Dedup-Logik
// tatsächlich in TranslateBatch() verdrahtet haben.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, '$textToFreshPosition') !== false, 'TranslateBatch() muss eine Text->Position-Map fuer die Batch-interne Deduplizierung fuehren');
assert(strpos($moduleSource, '$duplicateFreshPositions') !== false, 'TranslateBatch() muss Duplikat-Positionen separat sammeln');
assert(strpos($moduleSource, 'if (isset($textToFreshPosition[$text])) {') !== false, 'TranslateBatch() muss vor dem Hinzufuegen zu $freshTexts pruefen, ob derselbe Text bereits im selben Batch vorgemerkt ist');
echo "Test 3 (die Batch-interne Deduplizierung ist tatsächlich in der realen TranslateBatch() verdrahtet) OK\n";

echo "\nAll tests passed.\n";
