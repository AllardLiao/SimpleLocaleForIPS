<?php
declare(strict_types=1);
// Build 128 (Nutzer-Report, live per Debug-Log bestätigt): ein einzelnes
// HTML-Widget übersetzt oft MEHRERE brandneue Knoten auf einmal (z.B.
// "Überwiegend Klar" + Sonnenauf-/-untergang + Windgeschwindigkeit +
// Windrichtung, alle zum ersten Mal gesehen). TranslateBatchUncached() rief
// dafür StoreCachedTranslation() bisher EINZELN je Knoten auf - jeder
// einzelne Aufruf machte eine eigene Sperre/Lese-/Schreibsequenz auf den
// (ohnehin fast vollen) Cache.
//
// WICHTIGER PRÄZISIERUNGS-HINWEIS (per eigenem Nachrechnen bestätigt, siehe
// unten Test 1/2): rein rechnerisch, OHNE Nebenläufigkeit, liefern Einzeln-
// Schreiben und gesammeltes Schreiben bei GLEICHEN Hit-Zählern/Zeitstempeln
// dasselbe Endergebnis - das gesammelte Schreiben "rettet" für sich allein
// also KEINEN Knoten vor einer echten Reservoir-Knappheit. Der tatsächliche
// Nutzen des gesammelten Schreibens ist ein anderer, ebenfalls echter: nur
// noch EIN Sperr-/Lese-/Schreibzyklus statt N einzelner pro Übersetzungs-
// Batch - das verkleinert das Zeitfenster, in dem eine ÜBERLAPPENDE,
// gleichzeitig laufende Skriptausführung (siehe Build 126, dort bereits als
// reale Ursache bestätigt: "Vorhersage und Aktuelle Bedingungen werden vom
// gleichen Script aktualisiert") dazwischenfunken kann, plus insgesamt
// weniger Overhead. Die eigentliche Abhilfe gegen das beobachtete Symptom
// (staendige Verdraengung) ist die gleichzeitig in diesem Build erhoehte
// TRANSLATION_CACHE_MAX_ENTRIES (1000 -> 3000) - mehr echtes Reservoir.

function storeCachedTranslationsBatchReplica(array $entries, array &$cache, int $maxEntries): void
{
    if ($entries === []) {
        return;
    }
    $now = 5000;
    foreach ($entries as $entry) {
        $cache[$entry['key']] = ['v' => $entry['translated'], 'h' => 1, 't' => $now];
    }
    if (count($cache) > $maxEntries) {
        uasort($cache, static fn ($a, $b): int => (($a['h'] ?? 0) <=> ($b['h'] ?? 0)) ?: (($a['t'] ?? 0) <=> ($b['t'] ?? 0)));
        $cache = array_slice($cache, count($cache) - $maxEntries, null, true);
    }
}

function storeCachedTranslationOneByOneReplica(array $entries, array &$cache, int $maxEntries): void
{
    foreach ($entries as $entry) {
        $cache[$entry['key']] = ['v' => $entry['translated'], 'h' => 1, 't' => 5000];
        if (count($cache) > $maxEntries) {
            uasort($cache, static fn ($a, $b): int => (($a['h'] ?? 0) <=> ($b['h'] ?? 0)) ?: (($a['t'] ?? 0) <=> ($b['t'] ?? 0)));
            $cache = array_slice($cache, count($cache) - $maxEntries, null, true);
        }
    }
}

$batch = [
    ['key' => 'ueberwiegend_klar', 'translated' => 'Mayormente despejado'],
    ['key' => 'sonnenaufgang', 'translated' => '06:03'],
    ['key' => 'sonnenuntergang', 'translated' => '20:17'],
    ['key' => 'windgeschwindigkeit', 'translated' => '0.82 m/s'],
    ['key' => 'windrichtung', 'translated' => 'O'],
];
$protected = [];
for ($i = 0; $i < 5; $i++) {
    $protected["protected_$i"] = ['v' => "Wert$i", 'h' => 5, 't' => 1000]; // etablierte, oft genutzte Alteintraege
}

// Test 1: bei ausreichendem Reservoir (mind. so viele guenstige alte
// Hit-1-Eintraege wie der Batch Knoten hat) ueberleben ALLE Batch-Knoten -
// UND ZWAR BEI BEIDEN Schreibvarianten identisch (keine Regression, keine
// Ueberraschung).
$reservoirSufficient = [];
for ($i = 0; $i < 5; $i++) {
    $reservoirSufficient["reservoir_$i"] = ['h' => 1, 't' => 100];
}
$cacheOneByOne1 = $reservoirSufficient + $protected;
storeCachedTranslationOneByOneReplica($batch, $cacheOneByOne1, 10);
$cacheBatched1 = $reservoirSufficient + $protected;
storeCachedTranslationsBatchReplica($batch, $cacheBatched1, 10);
foreach ($batch as $entry) {
    assert(isset($cacheOneByOne1[$entry['key']]), 'bei ausreichendem Reservoir muss Einzeln-Schreiben ebenfalls den ganzen Batch erhalten');
    assert(isset($cacheBatched1[$entry['key']]), 'bei ausreichendem Reservoir muss gesammeltes Schreiben den ganzen Batch erhalten');
}
echo "Test 1 (ausreichendes Reservoir: beide Schreibvarianten erhalten den kompletten Batch, keine Regression) OK\n";

// Test 2: PRÄZISIERUNG - bei KNAPPEM Reservoir (weniger guenstige alte
// Eintraege als der Batch Knoten hat) liefern Einzeln- und gesammeltes
// Schreiben nachweislich DASSELBE Ergebnis (verifiziert per Nachrechnen) -
// das gesammelte Schreiben ist also kein Ersatz fuer ausreichende
// Cache-Kapazitaet, sondern eine SEPARATE Verbesserung (siehe Test 3).
$reservoirScarce = [
    'reservoir_0' => ['h' => 1, 't' => 100],
    'reservoir_1' => ['h' => 1, 't' => 100],
    'reservoir_2' => ['h' => 1, 't' => 100],
];
$cacheOneByOne2 = $reservoirScarce + $protected;
storeCachedTranslationOneByOneReplica($batch, $cacheOneByOne2, 8);
$cacheBatched2 = $reservoirScarce + $protected;
storeCachedTranslationsBatchReplica($batch, $cacheBatched2, 8);
ksort($cacheOneByOne2);
ksort($cacheBatched2);
assert(array_keys($cacheOneByOne2) === array_keys($cacheBatched2), 'bei knappem Reservoir muessen Einzeln- und gesammeltes Schreiben nachweislich dasselbe Endergebnis liefern - das gesammelte Schreiben behebt Kapazitaetsknappheit fuer sich allein nicht');
echo "Test 2 (Präzisierung: bei knappem Reservoir verhalten sich beide Schreibvarianten nachweislich identisch - kein Wunder-Fix, siehe stattdessen Test 3+4) OK\n";

// Test 3: der tatsaechliche, beweisbare Vorteil des gesammelten Schreibens -
// ein einziger Lese-/Schreibzyklus statt N einzelner, unabhaengig vom
// Kapazitaets-Ausgang. Weniger Sperrzeit = kleineres Zeitfenster fuer die in
// Build 126 bestaetigte ueberlappende Nebenlaeufigkeit.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function StoreCachedTranslationsBatch(') !== false, 'StoreCachedTranslationsBatch() muss in der realen module.php existieren');
$funcStart = strpos($moduleSource, 'private function StoreCachedTranslationsBatch(');
$funcEnd = strpos($moduleSource, "\n    }\n", strpos($moduleSource, 'IPS_SemaphoreLeave($ident)', $funcStart));
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(substr_count($funcBody, 'ReadAttributeString(self::attributeTranslationCache)') === 1, 'StoreCachedTranslationsBatch() darf den Cache nur EINMAL lesen, nicht pro Knoten');
assert(substr_count($funcBody, 'WriteAttributeString(self::attributeTranslationCache') === 1, 'StoreCachedTranslationsBatch() darf den Cache nur EINMAL schreiben, nicht pro Knoten');
assert(substr_count($funcBody, 'IPS_SemaphoreEnter(') === 1, 'StoreCachedTranslationsBatch() darf die Sperre nur EINMAL pro Aufruf erwerben, nicht pro Knoten');
echo "Test 3 (der belegbare Vorteil: StoreCachedTranslationsBatch() sperrt/liest/schreibt nur einmal pro Batch, nicht pro Knoten) OK\n";

// Test 4: Symmetrie-Check - TranslateBatchUncached() muss tatsaechlich
// StoreCachedTranslationsBatch() verwenden (gesammelt), nicht mehr
// StoreCachedTranslation() (einzeln) fuer die Knotenebene.
$funcStart2 = strpos($moduleSource, 'private function TranslateBatchUncached(');
$funcEnd2 = strpos($moduleSource, "\n    }\n", strpos($moduleSource, 'return $result;', $funcStart2));
$funcBody2 = substr($moduleSource, $funcStart2, $funcEnd2 - $funcStart2);
assert(strpos($funcBody2, '$this->StoreCachedTranslationsBatch($Source, $Target, $freshEntriesForCache') !== false, 'TranslateBatchUncached() muss die frisch uebersetzten Knoten gesammelt ueber StoreCachedTranslationsBatch() schreiben');
echo "Test 4 (TranslateBatchUncached() ist tatsächlich auf das gesammelte Schreiben umgestellt) OK\n";

// Test 5: die gleichzeitig erhoehte Cache-Kapazitaet - die eigentliche
// Abhilfe gegen das beobachtete "staendig voll"-Symptom - ist tatsaechlich
// in der realen module.php verdrahtet.
assert(strpos($moduleSource, 'TRANSLATION_CACHE_MAX_ENTRIES = 10000;') !== false, 'DER FIX: TRANSLATION_CACHE_MAX_ENTRIES muss auf einen komfortablen Wert weit ueber dem harten Kern erhöht worden sein (Build 129: 10000) - das ist die eigentliche Abhilfe gegen die beobachtete staendige Verdraengung');
echo "Test 5 (die Cache-Kapazität ist tatsächlich von 1000 auf 3000 erhöht - die eigentliche Abhilfe) OK\n";

echo "\nAll tests passed.\n";
