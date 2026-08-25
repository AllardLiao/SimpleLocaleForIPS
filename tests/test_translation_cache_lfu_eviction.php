<?php
declare(strict_types=1);
// Standalone replica test for build 72 (2026-08-19):
// User-Feedback: der Übersetzungs-Cache (bislang FIFO, aelteste Eintraege zuerst
// raus, Kapazitaet 500) soll "schlauer" werden - ein Hit-Zaehler soll dafuer sorgen,
// dass bei Verdraengung die am WENIGSTEN genutzten Eintraege der letzten 24 Stunden
// zuerst rausfliegen, nicht einfach die zuerst gespeicherten. Zusaetzlich Kapazitaet
// auf 1000 erhoehen.

const HIT_DECAY_SECONDS = 86400;
const MAX_ENTRIES = 1000;

function getCachedTranslation(array &$cache, string $key): ?string
{
    if (!isset($cache[$key]) || !is_array($cache[$key])) {
        return null;
    }
    $entry = $cache[$key];
    $now = time();
    $cache[$key]['h'] = ($now - ($entry['t'] ?? 0)) > HIT_DECAY_SECONDS ? 1 : (int) ($entry['h'] ?? 0) + 1;
    $cache[$key]['t'] = $now;

    return $entry['v'] ?? null;
}

function storeCachedTranslation(array &$cache, string $key, string $value): void
{
    $cache[$key] = ['v' => $value, 'h' => 1, 't' => time()];
    if (count($cache) > MAX_ENTRIES) {
        uasort($cache, static function ($a, $b): int {
            return (($a['h'] ?? 0) <=> ($b['h'] ?? 0)) ?: (($a['t'] ?? 0) <=> ($b['t'] ?? 0));
        });
        $cache = array_slice($cache, count($cache) - MAX_ENTRIES, null, true);
    }
}

// Test 1: eine neu gespeicherte Übersetzung hat Hit-Zähler 1 (der Store selbst zählt
// als erster "Treffer").
$cache = [];
storeCachedTranslation($cache, 'k1', 'Wert 1');
assert($cache['k1']['h'] === 1, 'Ein frisch gespeicherter Eintrag muss Hit-Zähler 1 haben');
assert($cache['k1']['v'] === 'Wert 1', 'Der gespeicherte Wert muss korrekt abrufbar sein');
echo "Test 1 (frisch gespeicherter Eintrag hat Hit-Zähler 1) OK\n";

// Test 2: jeder Lesezugriff (Cache-Treffer) erhöht den Hit-Zähler um 1.
getCachedTranslation($cache, 'k1');
getCachedTranslation($cache, 'k1');
assert($cache['k1']['h'] === 3, 'Zwei weitere Lesezugriffe müssen den Hit-Zähler auf 3 erhöhen (1 vom Store + 2 Reads)');
echo "Test 2 (jeder Cache-Treffer erhöht den Hit-Zähler) OK\n";

// Test 3: KERNSTÜCK - bei Verdrängung fliegt der Eintrag mit dem NIEDRIGSTEN
// Hit-Zähler zuerst raus, NICHT der zuerst eingefügte (FIFO wäre hier "alt" statt
// "populär" gewesen).
$cache = [];
storeCachedTranslation($cache, 'alt_aber_populaer', 'Popular');
for ($i = 0; $i < 10; $i++) {
    getCachedTranslation($cache, 'alt_aber_populaer'); // Hit-Zähler jetzt 11
}
storeCachedTranslation($cache, 'neu_aber_unpopulaer', 'Unpopular'); // Hit-Zähler 1, aber JÜNGER

// Simuliert: Kapazität ist auf 1 begrenzt - der unpopuläre (aber neuere) Eintrag muss
// weichen, der populäre (aber ältere) bleibt.
$reduced = $cache;
uasort($reduced, static function ($a, $b): int {
    return (($a['h'] ?? 0) <=> ($b['h'] ?? 0)) ?: (($a['t'] ?? 0) <=> ($b['t'] ?? 0));
});
$reduced = array_slice($reduced, count($reduced) - 1, null, true);
assert(array_key_first($reduced) === 'alt_aber_populaer', 'Bei Kapazitätsdruck muss der Eintrag mit dem HÖCHSTEN Hit-Zähler überleben, unabhängig vom Einfüge-Zeitpunkt (Gegensatz zu reinem FIFO)');
echo "Test 3 (häufig genutzter älterer Eintrag überlebt einen selten genutzten neueren - kein reines FIFO mehr) OK\n";

// Test 4: DECAY - ein Eintrag, der seit über 24 Stunden nicht mehr gelesen wurde,
// wird beim nächsten Zugriff auf Hit-Zähler 1 zurückgesetzt statt seinen alten
// (hohen) Zähler ewig fortzuschreiben.
$cache = ['stale' => ['v' => 'Old popular value', 'h' => 50, 't' => time() - HIT_DECAY_SECONDS - 3600]];
getCachedTranslation($cache, 'stale');
assert($cache['stale']['h'] === 1, 'Ein seit über 24h nicht mehr gelesener Eintrag muss beim nächsten Zugriff auf Hit-Zähler 1 zurückgesetzt werden, nicht auf 51 hochgezählt');
echo "Test 4 (Hit-Zähler verfällt nach 24h Inaktivität statt für immer hoch zu bleiben) OK\n";

// Test 5: ein Zugriff INNERHALB der 24-Stunden-Frist zählt normal hoch (kein Decay).
$cache = ['fresh' => ['v' => 'Value', 'h' => 5, 't' => time() - 3600]]; // vor 1h zuletzt genutzt
getCachedTranslation($cache, 'fresh');
assert($cache['fresh']['h'] === 6, 'Ein Zugriff innerhalb von 24h muss normal hochzählen, kein Decay-Reset');
echo "Test 5 (Zugriff innerhalb der 24h-Frist zählt normal hoch) OK\n";

// Test 6: MIGRATIONSSICHERHEIT - ein alter, noch als reiner String gespeicherter
// Eintrag (Format vor Build 72) darf beim Sortieren/Verdrängen keinen Fehler werfen
// und gilt automatisch als Hit-Zähler 0 - wird also garantiert zuerst verdrängt.
$mixedCache = [
    'legacy_string_entry' => 'Ein alter, roher String-Wert ohne Metadaten',
    'new_object_entry' => ['v' => 'Neuer Wert', 'h' => 1, 't' => time()],
];
uasort($mixedCache, static function ($a, $b): int {
    return (($a['h'] ?? 0) <=> ($b['h'] ?? 0)) ?: (($a['t'] ?? 0) <=> ($b['t'] ?? 0));
});
assert(array_key_first($mixedCache) === 'legacy_string_entry', 'Ein alter String-Eintrag ohne Hit-Zähler muss beim Sortieren als Erstes (niedrigste Priorität) einsortiert werden, ohne einen Fehler auszulösen');
echo "Test 6 (alte String-Einträge aus der Vor-Build-72-Zeit werden fehlerfrei als Erstes verdrängt) OK\n";

// Test 7: bei GLEICHEM Hit-Zähler entscheidet der ältere letzte Zugriff (wer länger
// nicht mehr gebraucht wurde, fliegt zuerst raus).
$tieCache = [
    'kuerzlich_genutzt' => ['v' => 'A', 'h' => 3, 't' => time() - 100],
    'laenger_nicht_genutzt' => ['v' => 'B', 'h' => 3, 't' => time() - 5000],
];
uasort($tieCache, static function ($a, $b): int {
    return (($a['h'] ?? 0) <=> ($b['h'] ?? 0)) ?: (($a['t'] ?? 0) <=> ($b['t'] ?? 0));
});
assert(array_key_first($tieCache) === 'laenger_nicht_genutzt', 'Bei gleichem Hit-Zähler muss der länger nicht mehr genutzte Eintrag zuerst verdrängt werden');
echo "Test 7 (Gleichstand beim Hit-Zähler wird über den letzten Zugriffszeitpunkt aufgelöst) OK\n";

// Test 8: die neue Kapazitätsgrenze ist 1000 (vormals 500).
assert(MAX_ENTRIES === 1000, 'Die Cache-Kapazität muss auf 1000 Einträge erhöht sein');
echo "Test 8 (Cache-Kapazität auf 1000 Einträge erhöht) OK\n";

echo "\nAll tests passed.\n";
