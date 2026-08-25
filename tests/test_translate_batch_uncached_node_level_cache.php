<?php
declare(strict_types=1);
// Build 119 (Nutzer-Wunsch, direkt im Anschluss an Build 118, 2026-08-22):
// Build 118 vermied nur DOPPELTE Anfragen INNERHALB eines einzelnen
// TranslateBatchUncached()-Aufrufs. Der Nutzer fragte zu Recht nach: sollte
// nicht auch der PERSISTENTE Cache (über mehrere Aktualisierungen hinweg) und
// die "Eigene Übersetzungstabelle" auf dieser Knotenebene greifen? Ein
// Wetter-Widget aendert bei JEDER Aktualisierung seinen GESAMTEN HTML-Inhalt
// (neue Messwerte) - der bisherige Zeilen-Cache (in TranslateBatch(), auf
// Ebene ganzer Zeilen-Rohtexte) trifft dadurch so gut wie NIE, obwohl viele
// einzelne Knoten darin (Wochentags-Kürzel, wiederkehrende Beschreibungen wie
// "Überwiegend bewölkt") bei JEDER Aktualisierung identisch bleiben und
// eigentlich eine hohe Trefferquote haben müssten.
//
// Fix: TranslateBatchUncached() prüft jetzt JEDEN eindeutigen Knoten
// zusätzlich gegen die manuelle Übersetzungstabelle und den persistenten
// Cache, BEVOR er an den Anbieter geschickt wird - nicht nur gegen die
// anderen Knoten desselben Aufrufs (Build 118).

function translateBatchUncachedNodeLevelReplica(
    array $translatableNodes,
    callable $findManualFn,
    callable $getCachedFn,
    callable $storeCachedFn,
    callable $chunkTranslateFn
): array {
    $uniqueNodes = array_values(array_unique($translatableNodes));

    $translatedByText = [];
    $freshNodes = [];
    foreach ($uniqueNodes as $node) {
        $manual = $findManualFn($node);
        if ($manual !== null) {
            $translatedByText[$node] = $manual;
            continue;
        }
        $cached = $getCachedFn($node);
        if ($cached !== null) {
            $translatedByText[$node] = $cached;
            continue;
        }
        $freshNodes[] = $node;
    }

    $freshTranslated = $chunkTranslateFn($freshNodes);
    foreach ($freshNodes as $i => $node) {
        $translated = $freshTranslated[$i] ?? '';
        $translatedByText[$node] = $translated;
        if ($translated !== '') {
            $storeCachedFn($node, $translated);
        }
    }

    return array_map(static fn (string $text): string => $translatedByText[$text] ?? '', $translatableNodes);
}

// Test 1: THE REPORTED GAP - ein Weekday-Kürzel, das bereits im persistenten
// Cache steht (von einer FRÜHEREN, separaten Aktualisierung), darf beim
// nächsten Wetter-Update NICHT erneut angefragt werden, obwohl der
// umgebende HTML-Rohtext (mit neuen Messwerten) komplett anders ist als
// beim letzten Mal.
$persistentCache = ['MO' => 'LUN', 'Überwiegend bewölkt' => 'Mayormente nublado'];
$providerCallLog = [];
$storedToCache = [];
$findManual = fn ($node) => null; // keine passende manuelle Übersetzung
$getCached = fn ($node) => $persistentCache[$node] ?? null;
$storeCached = function ($node, $translated) use (&$storedToCache) {
    $storedToCache[$node] = $translated;
};
$chunkTranslate = function (array $nodes) use (&$providerCallLog): array {
    foreach ($nodes as $node) {
        $providerCallLog[] = $node;
    }

    return array_map(fn ($t) => strtoupper($t), $nodes);
};

// Simuliert EINEN NEUEN Wetter-Update-Aufruf: "MO" und "Überwiegend bewölkt"
// sind bereits gecacht (aus einem FRÜHEREN Aufruf), "38 %" ist neu (heutiger
// Messwert, war noch nie zuvor gesehen).
$nodesThisUpdate = ['MO', 'Überwiegend bewölkt', '38 %'];
$result = translateBatchUncachedNodeLevelReplica($nodesThisUpdate, $findManual, $getCached, $storeCached, $chunkTranslate);

assert(!in_array('MO', $providerCallLog, true), 'DER BUG: ein bereits gecachtes Wochentags-Kürzel darf NICHT erneut beim Anbieter angefragt werden');
assert(!in_array('Überwiegend bewölkt', $providerCallLog, true), 'DER BUG: eine bereits gecachte, wiederkehrende Beschreibung darf NICHT erneut angefragt werden, nur weil der umgebende HTML-Rohtext sich geändert hat');
assert($providerCallLog === ['38 %'], 'Nur der tatsächlich neue, noch nie gesehene Knoten darf den Anbieter erreichen');
assert($result === ['LUN', 'Mayormente nublado', '38 %'], 'Die gecachten Knoten muessen ihr korrektes Ergebnis liefern, der neue Knoten wird (im Test) unveraendert "uebersetzt" zurueckgegeben');
assert($storedToCache === ['38 %' => '38 %'], 'Nur das frisch uebersetzte Ergebnis wird neu in den Cache geschrieben - bereits gecachte Knoten werden nicht redundant erneut gespeichert');
echo "Test 1 (bereits gecachte Wochentags-Kuerzel/wiederkehrende Beschreibungen werden beim naechsten Update NICHT erneut angefragt, nur der echte neue Wert) OK\n";

// Test 2: die "Eigene Übersetzungstabelle" muss ebenfalls auf Knotenebene
// greifen, unabhaengig vom Cache.
$findManualWithEntry = fn ($node) => $node === 'MO' ? 'Lunes (manuell)' : null;
$providerCallLog2 = [];
$chunkTranslate2 = function (array $nodes) use (&$providerCallLog2): array {
    foreach ($nodes as $node) {
        $providerCallLog2[] = $node;
    }

    return array_map(fn ($t) => strtoupper($t), $nodes);
};
$result2 = translateBatchUncachedNodeLevelReplica(['MO', 'DI'], $findManualWithEntry, fn ($n) => null, fn ($n, $t) => null, $chunkTranslate2);
assert(!in_array('MO', $providerCallLog2, true), 'Ein manueller Tabelleneintrag auf Knotenebene darf NIE den Anbieter erreichen');
assert($result2 === ['Lunes (manuell)', 'DI'], 'Der manuelle Eintrag muss verwendet werden, der andere Knoten normal uebersetzt');
echo "Test 2 (die Eigene Übersetzungstabelle greift ebenfalls auf Knotenebene, unabhaengig vom Cache) OK\n";

// Test 3: Symmetrie-Check - die reale module.php muss FindManualTranslation
// UND GetCachedTranslation/StoreCachedTranslationsBatch tatsaechlich in
// TranslateBatchUncached() verwenden, nicht nur in TranslateBatch(). Build 128
// (siehe test_batch_cache_write_no_self_eviction.php fuer den eigentlichen
// Fix): die frisch uebersetzten Knoten werden seither gesammelt in EINEM
// Rutsch geschrieben (StoreCachedTranslationsBatch), nicht mehr einzeln je
// Knoten (StoreCachedTranslation) - das verhindert Selbst-Verdraengung
// innerhalb desselben Batches.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$funcStart = strpos($moduleSource, 'private function TranslateBatchUncached(');
$funcEnd = strpos($moduleSource, "\n    }\n", strpos($moduleSource, 'return $result;', $funcStart));
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, '$this->FindManualTranslation($manualTranslationsForNodes, $Source, $Target, $node)') !== false, 'TranslateBatchUncached() muss FindManualTranslation() auf Knotenebene aufrufen');
assert(strpos($funcBody, '$this->GetCachedTranslation($Source, $Target, $node') !== false, 'TranslateBatchUncached() muss GetCachedTranslation() auf Knotenebene aufrufen');
assert(strpos($funcBody, '$this->StoreCachedTranslationsBatch($Source, $Target, $freshEntriesForCache') !== false, 'TranslateBatchUncached() muss frisch uebersetzte Knoten gesammelt ueber StoreCachedTranslationsBatch() in den Cache schreiben');
echo "Test 3 (Cache und Eigene Übersetzungstabelle sind tatsaechlich auf Knotenebene in der realen TranslateBatchUncached() verdrahtet) OK\n";

echo "\nAll tests passed.\n";
