<?php
declare(strict_types=1);
// Standalone replica test for build 70 (2026-08-19):
// "Nur aktive Sprache sofort, Rest lazy beim Sprachwechsel nachholen" - der User
// meldete, dass ApplyTrackedVariableUpdate (VM_UPDATE-Live-Nachuebersetzung) bisher
// bei JEDER externen Variablenaenderung ALLE konfigurierten Zielsprachen sofort neu
// uebersetzt hat, obwohl zu keinem Zeitpunkt mehr als eine Sprache gleichzeitig
// angezeigt wurde - bei einer haeufig aktualisierten Wetter-/Sensor-Variable (mehrmals
// pro Minute) hat das ein tägliches Uebersetzungs-Kontingent (77.000 Zeichen an einem
// Tag) in wenigen Stunden aufgebraucht. Neues Modell: nur die AKTUELL aktive
// Gast-Sprache wird sofort uebersetzt, alle anderen bleiben veraltet stehen (alter
// Fallback-Wert bleibt sichtbar) und werden erst beim naechsten tatsaechlichen
// Wechsel auf genau diese Sprache nachgeholt - erkannt ueber einen Zeitstempel-
// Abgleich (SourceChangedAt vs. TranslatedAt je Sprache), NICHT durch Loeschen der
// Zelle (die bliebe sonst bis zum Nachholen leer/unuebersetzt sichtbar).

const FIELD_SOURCE_CHANGED_AT = 'QuelleGeaendertAm';
const FIELD_TRANSLATED_AT_BY_LANGUAGE = 'UebersetztAm';

function isRowLanguageTranslationCurrent(array $row, string $toField, string $language): bool
{
    if (($row[$toField] ?? '') === '') {
        return false;
    }

    $sourceChangedAt = (int) ($row[FIELD_SOURCE_CHANGED_AT] ?? 0);
    if ($sourceChangedAt === 0) {
        return true;
    }

    $translatedAt = (int) ($row[FIELD_TRANSLATED_AT_BY_LANGUAGE][$language] ?? 0);

    return $translatedAt >= $sourceChangedAt;
}

function markRowLanguageTranslated(array &$row, string $language): void
{
    $row[FIELD_TRANSLATED_AT_BY_LANGUAGE][$language] = time();
}

function markRowSourceChanged(array &$row): void
{
    $row[FIELD_SOURCE_CHANGED_AT] = time();
}

// Test 1: eine komplett leere Zelle ist IMMER "nicht aktuell", unabhängig von jedem
// Zeitstempel - deckt brandneue Zeilen und neu hinzugekommene Zielsprachen ab.
$row = ['Name_en' => ''];
assert(isRowLanguageTranslationCurrent($row, 'Name_en', 'en') === false, 'Eine leere Zielsprachen-Zelle muss immer als "nicht aktuell" gelten');
echo "Test 1 (leere Zelle = nicht aktuell) OK\n";

// Test 2: MIGRATIONSSICHERHEIT - eine bereits gefüllte Zelle OHNE SourceChangedAt
// (Zeile aus einer Installation vor Build 70) gilt als aktuell, damit ein
// Modul-Update NICHT den kompletten Bestand einmalig neu übersetzt.
$legacyRow = ['Name_en' => 'Living room'];
assert(isRowLanguageTranslationCurrent($legacyRow, 'Name_en', 'en') === true, 'Eine gefüllte Zelle ohne SourceChangedAt (Alt-Zeile) muss als aktuell gelten - sonst Massen-Neuübersetzung nach Update');
echo "Test 2 (Alt-Zeile ohne Zeitstempel gilt als aktuell - Migrationssicherheit) OK\n";

// Test 3: eine frisch angelegte Zeile mit SourceChangedAt, aber noch nie für "en"
// übersetzt (kein TranslatedAt-Eintrag) - nicht aktuell.
$freshRow = ['Name_en' => '', FIELD_SOURCE_CHANGED_AT => time()];
assert(isRowLanguageTranslationCurrent($freshRow, 'Name_en', 'en') === false, 'Eine Zeile mit SourceChangedAt aber ohne TranslatedAt[en] darf nicht als aktuell gelten');
echo "Test 3 (SourceChangedAt gesetzt, aber nie übersetzt = nicht aktuell) OK\n";

// Test 4: TranslatedAt >= SourceChangedAt -> aktuell.
$currentRow = ['Name_en' => 'Living room', FIELD_SOURCE_CHANGED_AT => 1000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 2000]];
assert(isRowLanguageTranslationCurrent($currentRow, 'Name_en', 'en') === true, 'TranslatedAt nach SourceChangedAt muss als aktuell gelten');
echo "Test 4 (TranslatedAt nach SourceChangedAt = aktuell) OK\n";

// Test 5: TranslatedAt < SourceChangedAt (die Zeile wurde NACH der letzten
// Übersetzung inhaltlich geändert) -> veraltet, obwohl die Zelle noch einen
// (jetzt überholten) Wert trägt.
$staleRow = ['Name_en' => 'Old living room', FIELD_SOURCE_CHANGED_AT => 3000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 2000]];
assert(isRowLanguageTranslationCurrent($staleRow, 'Name_en', 'en') === false, 'TranslatedAt vor SourceChangedAt muss als veraltet erkannt werden, obwohl die Zelle noch einen alten Wert trägt');
echo "Test 5 (TranslatedAt vor SourceChangedAt = veraltet, alter Wert bleibt als Fallback erhalten) OK\n";

// Test 6: eine Zeile kann für EINE Sprache aktuell sein und für eine ANDERE
// gleichzeitig veraltet - TranslatedAt wird pro Sprache getrennt geführt.
$mixedRow = [
    'Name_en' => 'Living room',
    'Name_fr' => 'Ancien salon',
    FIELD_SOURCE_CHANGED_AT => 3000,
    FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 4000, 'fr' => 2000],
];
assert(isRowLanguageTranslationCurrent($mixedRow, 'Name_en', 'en') === true, 'en wurde NACH der Änderung übersetzt - muss aktuell sein');
assert(isRowLanguageTranslationCurrent($mixedRow, 'Name_fr', 'fr') === false, 'fr wurde VOR der Änderung übersetzt - muss veraltet sein, unabhängig vom Status von en');
echo "Test 6 (Aktualität wird pro Sprache unabhängig geführt) OK\n";

// Test 7: MarkRowSourceChanged macht eine zuvor aktuelle Übersetzung rückwirkend als
// veraltet erkennbar, OHNE den Zellenwert selbst zu löschen (Fallback bleibt sichtbar).
$row = ['Name_en' => 'Living room', FIELD_SOURCE_CHANGED_AT => 1000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 2000]];
assert(isRowLanguageTranslationCurrent($row, 'Name_en', 'en') === true);
markRowSourceChanged($row);
assert(isRowLanguageTranslationCurrent($row, 'Name_en', 'en') === false, 'Nach MarkRowSourceChanged muss die Zelle als veraltet gelten');
assert($row['Name_en'] === 'Living room', 'MarkRowSourceChanged darf den bisherigen Zellenwert NICHT löschen - er bleibt als Fallback sichtbar, bis eine frische Übersetzung vorliegt');
echo "Test 7 (MarkRowSourceChanged veraltet die Zelle, löscht aber nicht ihren Fallback-Wert) OK\n";

// Test 8: MarkRowLanguageTranslated macht eine veraltete Zelle wieder aktuell.
$row = ['Name_en' => 'Living room', FIELD_SOURCE_CHANGED_AT => 3000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 1000]];
assert(isRowLanguageTranslationCurrent($row, 'Name_en', 'en') === false);
markRowLanguageTranslated($row, 'en');
assert(isRowLanguageTranslationCurrent($row, 'Name_en', 'en') === true, 'Nach MarkRowLanguageTranslated muss die Zelle wieder als aktuell gelten');
echo "Test 8 (MarkRowLanguageTranslated macht eine veraltete Zelle wieder aktuell) OK\n";

// Test 9: FillLanguageColumn-Pending-Logik (Replik) - eine Zelle ist "pending"
// (muss übersetzt werden), wenn sie leer ODER veraltet ist, nicht mehr nur "leer".
function computePendingIndices(array $rows, string $fromField, string $toField, string $language): array
{
    $pending = [];
    foreach ($rows as $index => $row) {
        $fromText = $row[$fromField] ?? '';
        if ($fromText !== '' && !isRowLanguageTranslationCurrent($row, $toField, $language)) {
            $pending[] = $index;
        }
    }
    return $pending;
}

$rows = [
    0 => ['Name' => 'Küche', 'Name_en' => ''], // fehlend
    1 => ['Name' => 'Bad', 'Name_en' => 'Bathroom', FIELD_SOURCE_CHANGED_AT => 1000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 2000]], // aktuell
    2 => ['Name' => 'Flur', 'Name_en' => 'Old hallway', FIELD_SOURCE_CHANGED_AT => 3000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 1000]], // veraltet
];
$pending = computePendingIndices($rows, 'Name', 'Name_en', 'en');
assert($pending === [0, 2], 'Pending muss genau die fehlende (0) und die veraltete (2) Zeile umfassen, nicht die aktuelle (1)');
echo "Test 9 (Pending = leer ODER veraltet, aktuelle Zeilen werden übersprungen) OK\n";

// Test 10: eine fehlgeschlagene Neuübersetzung (leeres API-Ergebnis) darf eine
// bereits vorhandene, jetzt als veraltet markierte Zelle NICHT mit einem Leerstring
// überschreiben - der alte Fallback-Wert muss stehen bleiben.
function simulateFillLanguageColumnFailure(array $row, string $toField, string $language): array
{
    $translatedValue = ''; // simulierter API-Fehlschlag
    if ($translatedValue !== '') {
        $row[$toField] = $translatedValue;
        markRowLanguageTranslated($row, $language);
    }
    return $row;
}
$row = ['Name' => 'Flur', 'Name_en' => 'Old hallway', FIELD_SOURCE_CHANGED_AT => 3000, FIELD_TRANSLATED_AT_BY_LANGUAGE => ['en' => 1000]];
$result = simulateFillLanguageColumnFailure($row, 'Name_en', 'en');
assert($result['Name_en'] === 'Old hallway', 'Ein fehlgeschlagener Übersetzungsversuch darf die bestehende (veraltete, aber vorhandene) Zelle nicht mit einem Leerstring überschreiben');
echo "Test 10 (fehlgeschlagener Nachhol-Versuch löscht den bestehenden Fallback-Wert nicht) OK\n";

echo "\nAll tests passed.\n";
