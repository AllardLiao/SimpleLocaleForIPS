<?php
declare(strict_types=1);
// Build 118 (live gefunden, direkt im Anschluss an Build 117 - siehe README
// Change-Log): Build 117 deduplizierte identische Texte innerhalb eines
// TranslateBatch()-Aufrufs, aber NUR auf der Ebene ganzer Zeilen-Rohtexte
// ($Texts). Der Nutzer meldete per neuem Debug-Log (dump9.txt), dass exakt
// dasselbe Problem weiterhin auftrat: bis zu 12 identische Anfragen für
// denselben Text ("Überwiegend bewölkt") in wenigen Sekunden - Build 117s
// Fix hatte sichtbar NICHT geholfen.
//
// Ursache: für HTML-Inhalte (isHtml=true, z.B. ein Wetter-Widget als "Eigene
// Texte"-Zeile) zerlegt TranslateBatchUncached() JEDE Zeile intern NOCHMAL in
// einzelne Text-Knoten (SplitHtmlIntoTextNodes) und sammelt ALLE Knoten ÜBER
// ALLE Zeilen hinweg in einer flachen Liste ($translatable), die dann an den
// Anbieter geschickt wird. Diese Knoten-Ebene liegt UNTERHALB der Ebene, auf
// der Build 117 dedupliziert (ganze Zeilen-Rohtexte) - haben mehrere
// Vorhersage-Tage INNERHALB EINER EINZIGEN Wetter-Widget-Zeile zufällig
// dieselbe Beschreibung, blieben die daraus resultierenden identischen
// Text-Knoten von Build 117 komplett unberührt.
//
// Fix: TranslateBatchUncached() dedupliziert jetzt zusätzlich auf dieser
// tieferen Knoten-Ebene, direkt bevor die Liste an den Anbieter geschickt
// wird - die nachgelagerte Cursor-basierte Rekonstruktion bekommt weiterhin
// ein Ergebnis-Array in exakt derselben Länge/Reihenfolge wie die Eingabe,
// bleibt also unverändert kompatibel.

function translateBatchUncachedReplica(array $translatableNodes, callable $chunkTranslateFn): array
{
    $uniqueNodes = array_values(array_unique($translatableNodes));
    $uniqueTranslated = $chunkTranslateFn($uniqueNodes);
    $translatedByText = array_combine($uniqueNodes, $uniqueTranslated);

    return array_map(static fn (string $text): string => $translatedByText[$text], $translatableNodes);
}

// Test 1: THE REPORTED BUG - ein Wetter-Widget mit 5 Tagen, von denen 3
// dieselbe Beschreibung teilen, darf den (simulierten) Anbieter nur EINMAL
// je EINDEUTIGEM Knoten aufrufen.
$providerCallLog = [];
$fakeProvider = function (array $uniqueNodes) use (&$providerCallLog): array {
    foreach ($uniqueNodes as $node) {
        $providerCallLog[] = $node;
    }

    return array_map(fn ($t) => mb_strtoupper($t, 'UTF-8'), $uniqueNodes);
};

// Simuliert die flache Knotenliste EINES EINZIGEN Wetter-Widgets (eine
// einzige "Eigene Texte"-Zeile, intern in 5 Tages-Beschreibungen zerlegt).
$weatherWidgetNodes = [
    'Überwiegend bewölkt', 'Leicht bewölkt', 'Überwiegend bewölkt',
    'Sonnig', 'Überwiegend bewölkt',
];
$result = translateBatchUncachedReplica($weatherWidgetNodes, $fakeProvider);

assert(count(array_unique($providerCallLog)) === count($providerCallLog), 'DER BUG: der Anbieter darf pro eindeutigem Knoten nur EINMAL aufgerufen werden');
assert(count($providerCallLog) === 3, 'Es gibt 3 eindeutige Knoten ("Überwiegend bewölkt", "Leicht bewölkt", "Sonnig") innerhalb dieser EINEN Zeile - genau 3 Anbieter-Aufrufe erwartet, nicht 5');
assert($result === ['ÜBERWIEGEND BEWÖLKT', 'LEICHT BEWÖLKT', 'ÜBERWIEGEND BEWÖLKT', 'SONNIG', 'ÜBERWIEGEND BEWÖLKT'], 'Jeder Knoten muss trotzdem sein korrektes, an der richtigen Position stehendes Ergebnis bekommen - die Rekonstruktion (Cursor-basiert) braucht exakt dieselbe Länge/Reihenfolge wie die Eingabe');
echo "Test 1 (5 Text-Knoten EINER EINZIGEN HTML-Zeile mit 3 eindeutigen Werten -> nur 3 Anbieter-Aufrufe statt 5, alle 5 Ergebnisse trotzdem korrekt an ihrer Position) OK\n";

// Test 2: keine Duplikate - unverändertes Verhalten, keine Regression.
$providerCallLog2 = [];
$fakeProvider2 = function (array $uniqueNodes) use (&$providerCallLog2): array {
    foreach ($uniqueNodes as $node) {
        $providerCallLog2[] = $node;
    }

    return array_map(fn ($t) => mb_strtoupper($t, 'UTF-8'), $uniqueNodes);
};
$noDuplicateNodes = ['Sonnig', 'Regnerisch', 'Windig'];
$result2 = translateBatchUncachedReplica($noDuplicateNodes, $fakeProvider2);
assert(count($providerCallLog2) === 3, 'Ohne Duplikate muss weiterhin jeder eindeutige Knoten genau einmal angefragt werden');
assert($result2 === ['SONNIG', 'REGNERISCH', 'WINDIG'], 'Ergebnisse müssen unverändert korrekt und in der richtigen Reihenfolge sein');
echo "Test 2 (keine Duplikate unter den Knoten -> unverändertes Verhalten, keine Regression) OK\n";

// Test 3: Symmetrie-Check - die reale module.php muss die Dedup-Logik
// tatsächlich in TranslateBatchUncached() (nicht nur in TranslateBatch())
// verdrahtet haben. Build 119 erweiterte dieselbe Stelle zusätzlich um
// Cache-/Manuelle-Tabelle-Prüfung pro Knoten (siehe eigener Test dafür,
// test_translate_batch_uncached_node_level_cache.php) - die reine
// Dedup-Grundlage ($uniqueTranslatable) muss dabei erhalten geblieben sein.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$funcStart = strpos($moduleSource, 'private function TranslateBatchUncached(');
$funcEnd = strpos($moduleSource, "\n    }\n", strpos($moduleSource, 'return $result;', $funcStart));
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, '$uniqueTranslatable = array_values(array_unique($translatable));') !== false, 'TranslateBatchUncached() muss $translatable (die flache Knotenliste) vor dem Anbieter-Aufruf deduplizieren');
assert(strpos($funcBody, '$translatedByText[$node] = $manual;') !== false || strpos($funcBody, 'foreach ($uniqueTranslatable as $node)') !== false, 'Die eindeutige Knotenliste muss weiterhin die Grundlage für die nachgelagerte Verarbeitung (Cache/Manuell/Anbieter, siehe Build 119) sein');
echo "Test 3 (die Knoten-Ebenen-Deduplizierung ist tatsächlich in der realen TranslateBatchUncached() verdrahtet, eine Ebene unterhalb von Build 117s TranslateBatch()-Fix) OK\n";

echo "\nAll tests passed.\n";
