<?php
declare(strict_types=1);
// Standalone replica test for build 154 (2026-08-26, live gemeldet, per dump23
// nachgewiesen): DATENVERLUST - die Spalte ORIGINAL_IMPORT_Text ("Eigene Texte")
// wurde bei praktisch allen Zeilen der Live-Instanz durch die UEBERSETZUNG
// ersetzt. Der naechste Lauf uebersetzte dann die Uebersetzung.
//
// Nachweis aus dump23: 67 der 88 Anfragen trugen als q= exakt das, was in dump22
// als ERGEBNIS zurueckkam - bei langpair=de|la. Die Mapping-Zeile zeigte
// ORIGINAL_IMPORT_Text von sechs Objekten in Latein, eines davon (57819) bereits
// mit arabischen Fragmenten: der Text war schon mehrfach im Kreis gelaufen.
//
// URSACHE 1 (die eigentliche): WriteTrackedValueString() setzte den
// Selbst-Schreib-Marker NACH @SetValueString(). Symcon stellt VM_UPDATE synchron
// zu - HandleTrackedVariableUpdate() lief also bereits, waehrend der Marker noch
// den alten Wert trug, und hielt den eigenen Schreibvorgang fuer eine externe
// Aenderung.
//
// URSACHE 2 (warum das Netz darunter riss): der Build-95-Schutz verglich den
// externen Wert nur mit der Zelle der AKTUELL aktiven Sprache. Genau die war nach
// dem Kontingent-Abbruch aus dump22 leer bzw. nur teilweise gefuellt - der
// Vergleich griff nicht.

// ---------------------------------------------------------------------------
// Repliziert WriteTrackedValueString() + synchrone VM_UPDATE-Zustellung.
// $marker wird per Referenz gefuehrt, $zugestellt sammelt, was
// HandleTrackedVariableUpdate() als "extern" eingestuft haette.
function schreibeMitMarkerNachher(int $id, string $wert, array &$marker, array &$alsExternGewertet): void
{
    // BUG-Reihenfolge (bis Build 153)
    $aktuellerMarker = $marker[$id] ?? null;      // Zustellung sieht DIESEN Stand
    if ($aktuellerMarker !== $wert) {
        $alsExternGewertet[] = $wert;
    }
    $marker[$id] = $wert;
}

function schreibeMitMarkerVorher(int $id, string $wert, array &$marker, array &$alsExternGewertet): void
{
    // FIX-Reihenfolge (ab Build 154)
    $marker[$id] = $wert;
    $aktuellerMarker = $marker[$id] ?? null;
    if ($aktuellerMarker !== $wert) {
        $alsExternGewertet[] = $wert;
    }
}

// Test 1: DIE URSACHE - bei der alten Reihenfolge gilt der eigene Schreibvorgang
// als externe Aenderung, bei der neuen nicht.
$marker = []; $extern = [];
schreibeMitMarkerNachher(56115, 'Mensa delineatio tabularium...', $marker, $extern);
assert($extern === ['Mensa delineatio tabularium...'], 'DER BUG: mit dem Marker NACH dem Schreiben gilt der eigene Schreibvorgang als extern');

$marker = []; $extern = [];
schreibeMitMarkerVorher(56115, 'Mensa delineatio tabularium...', $marker, $extern);
assert($extern === [], 'DER FIX: mit dem Marker VOR dem Schreiben wird der eigene Schreibvorgang korrekt erkannt');
echo "Test 1 (der Selbst-Schreib-Marker muss vor dem Schreibvorgang stehen) OK\n";

// ---------------------------------------------------------------------------
// Repliziert die Guard-Kette aus ApplyTrackedVariableUpdate().
// Rueckgabe true = Rohtext wird uebernommen (gefaehrlich, wenn es die eigene
// Uebersetzung ist), false = abgewiesen.
function uebernimmtRohtext(array $zeile, string $neuerWert, string $aktiveSprache, array $zielsprachen, string $quellsprache, bool $mitBuild154Guard): bool
{
    // Build-95-Guard: nur die aktuell aktive Sprache
    if ($aktiveSprache !== 'ORIGINAL_IMPORT'
        && ($zeile['Text_' . $aktiveSprache] ?? null) === $neuerWert) {
        return false;
    }

    if ($mitBuild154Guard && $neuerWert !== '') {
        foreach ($zielsprachen as $code) {
            if ($code === $quellsprache || $code === 'ORIGINAL_IMPORT') {
                continue;
            }
            if (($zeile['Text_' . $code] ?? null) === $neuerWert) {
                return false;
            }
        }
    }

    return true;
}

// Test 2: DER GEMELDETE FALL - die aktive Sprache (la) hat eine LEERE Zelle,
// weil der Lauf am Tageslimit abbrach. Die Uebersetzung steckt aber in einer
// anderen Zelle. Vor Build 154 rutschte sie als neuer Rohtext durch.
$zeile = [
    'ORIGINAL_IMPORT_Text' => 'Der Wochenplan wird täglich neu erstellt.',
    'Text_la'              => '',                                        // 429 abgebrochen
    'Text_en'              => 'Mensa delineatio tabularium...',          // frueherer Lauf
];
$zielsprachen = ['de', 'la', 'en'];
assert(uebernimmtRohtext($zeile, 'Mensa delineatio tabularium...', 'la', $zielsprachen, 'de', false) === true,
    'DER BUG: mit leerer Zelle der aktiven Sprache griff der alte Guard nicht');
assert(uebernimmtRohtext($zeile, 'Mensa delineatio tabularium...', 'la', $zielsprachen, 'de', true) === false,
    'DER FIX: ein Wert, der EINER gespeicherten Uebersetzung dieser Zeile entspricht, darf nie Rohtext werden');
echo "Test 2 (eine Übersetzung wird auch bei leerer Zelle der aktiven Sprache nicht zum Rohtext) OK\n";

// Test 3: der bestehende Build-95-Schutz bleibt unveraendert wirksam.
$zeile2 = ['Text_la' => 'Salve Kai!'];
assert(uebernimmtRohtext($zeile2, 'Salve Kai!', 'la', ['de', 'la'], 'de', true) === false,
    'der Build-95-Schutz (aktive Sprache) muss weiterhin greifen');
echo "Test 3 (der bestehende Schutz für die aktive Sprache bleibt wirksam) OK\n";

// Test 4: DIE GRENZE - eine ECHTE externe Aenderung muss weiterhin durchkommen,
// sonst waere der Live-Nachuebersetzungs-Pfad tot.
$zeile3 = ['ORIGINAL_IMPORT_Text' => 'Alter Text', 'Text_la' => 'Textus vetus'];
assert(uebernimmtRohtext($zeile3, 'Ein völlig neuer deutscher Text', 'la', ['de', 'la'], 'de', true) === true,
    'DIE GRENZE: eine echte externe Aenderung muss weiterhin als neuer Rohtext uebernommen werden');
echo "Test 4 (echte externe Änderungen werden weiterhin übernommen) OK\n";

// Test 5: die QUELLSPRACHE ist ausgenommen. EnsureSourceLanguageIsTarget() traegt
// die Quellsprache als Zielsprache ein; dort ist Gleichheit mit dem Rohtext der
// Normalfall. Ohne die Ausnahme wuerde ein Fremdskript, das denselben deutschen
// Text erneut schreibt, faelschlich blockiert.
$zeile4 = ['ORIGINAL_IMPORT_Text' => 'Guten Tag', 'Text_de' => 'Guten Tag', 'Text_la' => 'Salve'];
assert(uebernimmtRohtext($zeile4, 'Guten Tag', 'la', ['de', 'la'], 'de', true) === true,
    'die Quellsprache muss von der Sperre ausgenommen sein - dort ist Gleichheit der Normalfall');
echo "Test 5 (die Quellsprache ist von der Sperre ausgenommen) OK\n";

// Test 6: ein LEERER neuer Wert darf die Sperre nicht ausloesen, nur weil
// zufaellig eine Sprachzelle ebenfalls leer ist.
$zeile5 = ['ORIGINAL_IMPORT_Text' => 'Text', 'Text_la' => ''];
assert(uebernimmtRohtext($zeile5, '', 'en', ['de', 'la'], 'de', true) === true,
    'ein leerer Wert darf nicht an einer zufaellig ebenfalls leeren Sprachzelle haengenbleiben');
echo "Test 6 (ein leerer Wert löst die Sperre nicht aus) OK\n";

// Test 7: DER ZYKLUS als Ganzes - zwei Runden Sprachwechsel duerfen den Rohtext
// nicht veraendern. Genau das ist live schiefgegangen.
$roh = 'Der Wochenplan wird täglich neu erstellt.';
$zeile6 = ['ORIGINAL_IMPORT_Text' => $roh, 'Text_la' => 'Mensa delineatio...'];
$marker = [];
for ($runde = 0; $runde < 2; $runde++) {
    $extern = [];
    schreibeMitMarkerVorher(56115, $zeile6['Text_la'], $marker, $extern);
    foreach ($extern as $wert) {
        if (uebernimmtRohtext($zeile6, $wert, 'la', ['de', 'la'], 'de', true)) {
            $zeile6['ORIGINAL_IMPORT_Text'] = $wert;
        }
    }
}
assert($zeile6['ORIGINAL_IMPORT_Text'] === $roh,
    'DER KERN: nach zwei Sprachwechsel-Runden muss der Rohtext unveraendert deutsch sein');
echo "Test 7 (zwei Sprachwechsel-Runden lassen den Rohtext unverändert) OK\n";

// Test 8: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');

$fnStart = strpos($moduleSource, 'private function WriteTrackedValueString');
assert($fnStart !== false, 'WriteTrackedValueString() muss existieren');
$fnBody = substr($moduleSource, $fnStart, 3000);
$markerPos = strpos($fnBody, 'WriteAttributeString(self::attributeLastSelfWrittenValues');
$writePos  = strpos($fnBody, '@SetValueString($ValueObjectID, $Value);');
assert($markerPos !== false && $writePos !== false, 'Marker und Schreibvorgang muessen beide vorkommen');
assert($markerPos < $writePos,
    'DER FIX: der Selbst-Schreib-Marker muss VOR @SetValueString() persistiert werden - sonst sieht die synchrone VM_UPDATE-Zustellung noch den alten Stand');

$guardStart = strpos($moduleSource, 'private function ApplyTrackedVariableUpdate');
$guardBody = substr($moduleSource, $guardStart, 6000);
assert(strpos($guardBody, 'TrackedValue_BackTranslationBlocked') !== false,
    'die Rueckuebersetzungs-Sperre muss vorhanden und im Debug sichtbar sein');
assert(strpos($guardBody, '$rowSourceLanguageForGuard') !== false,
    'die Sperre muss die Quellsprache der Zeile ausnehmen');
assert(strpos($guardBody, "if (\$NewValue !== '') {") !== false,
    'die Sperre darf bei leerem Wert nicht greifen');

// Die irrefuehrende Debug-Kategorie hat den Nutzer zweimal glauben lassen, es
// gehe ein Aufruf an Google - obwohl gar kein Google-Key konfiguriert war.
assert(strpos($moduleSource, "'GoogleTranslate_Mapping'") === false,
    'die irrefuehrende Debug-Kategorie GoogleTranslate_Mapping darf nicht mehr vorkommen - sie wird anbieterunabhaengig geschrieben');
assert(strpos($moduleSource, "'Translate_Mapping'") !== false,
    'die Mapping-Ausgabe muss anbieterneutral benannt sein');
echo "Test 8 (die reale Umsetzung hat beide Fixes und die neutrale Debug-Kategorie) OK\n";

echo "\nAll tests passed.\n";
