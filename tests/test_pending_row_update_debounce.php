<?php
declare(strict_types=1);
// Standalone replica test for build 71 (2026-08-19):
// User-Beschwerde: eine häufig (mehrmals pro Minute) extern aktualisierte "Eigene
// Texte"-Variable hat bei JEDEM VM_UPDATE sofort IPS_SetProperty+IPS_ApplyChanges auf
// GENAU DIE Property ausgelöst, die im gerade offenen Konfigurationsformular als
// bearbeitbare Liste angezeigt wird - der Admin konnte dadurch praktisch nie eine
// eigene Änderung speichern, bevor die Liste unter ihm neu geschrieben wurde. Fix:
// die Live-Variable wird weiterhin sofort geschrieben (Gast sieht die Übersetzung
// sofort), aber die Property-PERSISTIERUNG (nur für einen späteren, seltenen
// Sprachwechsel gebraucht) wird gepuffert und erst nach einer Ruhephase (Debounce,
// 12 Minuten) committet. Speichert der Admin währenddessen im Formular, wird der
// Puffer VORHER eingespielt - keine verlorene Änderung.

const PENDING_DEBOUNCE_SECONDS = 720;

function bufferPendingTrackedRowUpdate(array &$pendingAttr, int &$flushAtAttr, string $property, string $valueObjectIDKey, array $fieldUpdates): void
{
    $pendingAttr[$property][$valueObjectIDKey] = $fieldUpdates;
    $flushAtAttr = time() + PENDING_DEBOUNCE_SECONDS;
}

function stagePendingTrackedRowUpdates(array &$pendingAttr, int &$flushAtAttr, array $rowsByProperty): array
{
    if ($pendingAttr === []) {
        return $rowsByProperty;
    }
    $pending = $pendingAttr;
    $pendingAttr = [];
    $flushAtAttr = 0;

    foreach ($pending as $property => $fieldUpdatesByValueObjectID) {
        foreach ($rowsByProperty[$property] ?? [] as $index => $row) {
            $key = (string) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0);
            if (!isset($fieldUpdatesByValueObjectID[$key])) {
                continue;
            }
            $rowsByProperty[$property][$index] = array_replace($row, $fieldUpdatesByValueObjectID[$key]);
        }
    }

    return $rowsByProperty;
}

// Test 1: mehrere aufeinanderfolgende Puffer-Aufrufe für DIESELBE ValueObjectID
// ERSETZEN den vorherigen Inhalt (nicht summieren) - korrekt für einen Debounce, nur
// der letzte Stand vor der Ruhephase muss geschrieben werden.
$pending = [];
$flushAt = 0;
bufferPendingTrackedRowUpdate($pending, $flushAt, 'ObjectTexts', '123', ['Text_de' => 'Regen', 'QuelleGeaendertAm' => 1000]);
bufferPendingTrackedRowUpdate($pending, $flushAt, 'ObjectTexts', '123', ['Text_de' => 'Sonnig', 'QuelleGeaendertAm' => 2000]);
assert($pending['ObjectTexts']['123']['Text_de'] === 'Sonnig', 'Ein neuerer Puffer-Aufruf für dieselbe ValueObjectID muss den älteren komplett ersetzen');
assert(count($pending['ObjectTexts']) === 1, 'Es darf nur EIN gepufferter Eintrag pro ValueObjectID existieren, kein Verlauf');
echo "Test 1 (aufeinanderfolgende Puffer-Aufrufe ersetzen sich, kein Aufsummieren) OK\n";

// Test 2: der Flush-Zeitpunkt wird bei jedem neuen Puffer-Aufruf neu berechnet (echtes
// Debounce-Verhalten - jede neue Aktivität schiebt den Zeitpunkt weiter nach hinten).
assert($flushAt >= time() + PENDING_DEBOUNCE_SECONDS - 2, 'Der Flush-Zeitpunkt muss nach jedem neuen Puffer-Aufruf neu (in die Zukunft) gesetzt sein');
echo "Test 2 (Flush-Zeitpunkt wird bei jeder neuen Aktivität neu berechnet) OK\n";

// Test 3: StagePendingTrackedRowUpdates merged die gepufferten Feld-Updates korrekt
// über ValueObjectID (nicht Zeilenindex) in die AKTUELLEN Zeilen ein - andere Felder
// der Zeile (z.B. eine manuell vom Admin editierte andere Sprachspalte) bleiben dabei
// unangetastet.
$rows = [
    'ObjectTexts' => [
        0 => ['ObjectID' => 10, 'ValueObjectID' => 123, 'Text_de' => 'Regen', 'Text_fr' => 'Pluie (manuell korrigiert)'],
        1 => ['ObjectID' => 11, 'ValueObjectID' => 456, 'Text_de' => 'Kalt', 'Text_fr' => 'Froid'],
    ],
];
$result = stagePendingTrackedRowUpdates($pending, $flushAt, $rows);
assert($result['ObjectTexts'][0]['Text_de'] === 'Sonnig', 'Die gepufferte Änderung muss auf die korrekte Zeile (per ValueObjectID) angewendet werden');
assert($result['ObjectTexts'][0]['Text_fr'] === 'Pluie (manuell korrigiert)', 'Andere, nicht gepufferte Felder derselben Zeile (z.B. eine manuelle Korrektur) dürfen NICHT überschrieben werden');
assert($result['ObjectTexts'][1]['Text_de'] === 'Kalt', 'Eine Zeile ohne gepufferte Änderung bleibt komplett unangetastet');
echo "Test 3 (Merge trifft nur die betroffene Zeile über ValueObjectID, andere Felder/Zeilen bleiben unberührt) OK\n";

// Test 4: nach dem Staging ist der Puffer und der Flush-Zeitpunkt wieder leer/0 -
// verhindert ein doppeltes erneutes Schreiben und lässt die Infobox im Formular
// wieder verschwinden.
assert($pending === [], 'Der Puffer muss nach dem Staging leer sein');
assert($flushAt === 0, 'Der Flush-Zeitpunkt muss nach dem Staging zurückgesetzt sein');
echo "Test 4 (Puffer und Flush-Zeitpunkt werden nach dem Staging zurückgesetzt) OK\n";

// Test 5: ein leerer Puffer führt zu keiner Änderung (kein unnötiger Property-Write).
$rowsUnchanged = ['ObjectTexts' => [0 => ['ObjectID' => 10, 'ValueObjectID' => 123, 'Text_de' => 'Regen']]];
$stillEmpty = [];
$stillEmptyFlushAt = 0;
$resultUnchanged = stagePendingTrackedRowUpdates($stillEmpty, $stillEmptyFlushAt, $rowsUnchanged);
assert($resultUnchanged === $rowsUnchanged, 'Ohne gepufferte Änderungen darf sich an den Zeilen nichts ändern');
echo "Test 5 (ein leerer Puffer verändert nichts, kein unnötiger Persist) OK\n";

// --- Infobox-Sichtbarkeit im Konfigurationsformular ---

function isPendingRowUpdateNoticeVisible(int $flushAtAttr): bool
{
    return $flushAtAttr > time();
}

// Test 6: die Infobox ist nur sichtbar, solange tatsächlich etwas Gepuffertes
// aussteht (Flush-Zeitpunkt in der Zukunft) - nicht bei 0 (nichts gepuffert) und
// nicht bei einem (theoretisch) bereits verstrichenen Zeitpunkt.
assert(isPendingRowUpdateNoticeVisible(0) === false, 'Ohne Puffer (0) darf die Infobox nicht sichtbar sein');
assert(isPendingRowUpdateNoticeVisible(time() - 10) === false, 'Ein bereits verstrichener Zeitpunkt darf die Infobox nicht mehr zeigen');
assert(isPendingRowUpdateNoticeVisible(time() + 600) === true, 'Ein Zeitpunkt in der Zukunft muss die Infobox sichtbar machen');
echo "Test 6 (Infobox nur sichtbar, solange tatsächlich ein Flush in der Zukunft ansteht) OK\n";

echo "\nAll tests passed.\n";
