<?php
declare(strict_types=1);
// Standalone replica test for build 189 (2026-09-02, Nutzer-Wunsch): das GLOSSAR
// wird von den "Eigenen Uebersetzungen" getrennt.
//
// VORHER: MergeBundledManualTranslations() schrieb 89 mitgelieferte Zeilen
// (73 Einheiten + 16 Kompassrichtungen) in die editierbare Tabelle des Nutzers -
// alle fest auf Quellsprache Deutsch. Zwei Folgen:
//
//   1. Das eigene Glossar lag unter 89 Fremdzeilen begraben.
//   2. Fuer ein Objekt mit ANDERER Zeilen-Quellsprache griff keine davon. "km/h"
//      haette je Quellsprache eine eigene Zeile gebraucht - genau die Dopplung,
//      die der Nutzer vermeiden wollte.
//
// JETZT: eine eigene Tabelle ohne Quellsprachen-Spalte. Je Sprache eine Spalte,
// und JEDE kann die Quelle sein. Die Zuordnung von jeder Spalte in jede andere
// ist eindeutig - das ist die Bedeutung von "Glossar" hier.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$konstanten = (string) file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
$form = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);

$fenster = function (string $von) use ($module): string {
    $a = (int) strpos($module, $von);
    $b = (int) strpos($module, "\n    private function ", $a + 10);

    return substr($module, $a, $b - $a);
};

// Repliziert FindGlossaryTranslation().
function glossar(array $rows, string $source, string $target, string $text): ?string
{
    foreach ($rows as $row) {
        if ((string) ($row[$source] ?? '') !== $text) {
            continue;
        }
        $t = (string) ($row[$target] ?? '');
        if ($t !== '') {
            return $t;
        }
    }

    return null;
}

$zeile = [['de' => 'km/h', 'en' => 'km/h', 'es' => 'km/h', 'ru' => 'км/ч']];

// Test 1: DER KERN - EINE Zeile bedient jede Richtung.
assert(glossar($zeile, 'de', 'en', 'km/h') === 'km/h', 'DER KERN: deutsch -> englisch');
assert(glossar($zeile, 'en', 'ru', 'km/h') === 'км/ч', 'englisch -> russisch, dieselbe Zeile');
assert(glossar($zeile, 'ru', 'de', 'км/ч') === 'km/h', 'und rueckwaerts genauso');
echo "Test 1 (eine Zeile bedient jede Richtung) OK\n";

// Test 2: DIE ABGRENZUNG - getroffen wird nur ueber die Spalte der QUELLSPRACHE.
// Ein Text, der sich als franzoesisch ausgibt, trifft nicht, wenn die
// franzoesische Spalte leer ist. Genau so hat der Nutzer es definiert.
assert(glossar($zeile, 'fr', 'de', 'km/h') === null,
    'DIE ABGRENZUNG: ohne Wert in der Quellspalte kein Treffer');
assert(glossar($zeile, 'de', 'fr', 'km/h') === null, 'und ohne Wert in der Zielspalte ebenso wenig');
echo "Test 2 (nur über die Spalte der Quellsprache) OK\n";

// Test 3: die mitgelieferten Zeilen sind NICHT mehr in der eigenen Tabelle.
assert(strpos($module, 'MergeBundledManualTranslations') === false,
    'DIE TRENNUNG: die eigene Tabelle wird nicht mehr mit dem Katalog befuellt');
$scan = $fenster('private function ScanRootTree');
assert(strpos($scan, 'MergeBundledGlossaryRows($this->DecodeRows(self::propertyGlossary))') !== false,
    'der Katalog fuellt jetzt die Glossar-Tabelle');
assert(strpos($scan, 'propertyManualTranslations') === false,
    'die eigene Tabelle wird beim Rescan gar nicht mehr angefasst');
echo "Test 3 (der Katalog landet nicht mehr in der eigenen Tabelle) OK\n";

// Test 4: die Glossar-Tabelle hat KEINE Quellsprachen-Spalte - sonst waere sie
// nur eine zweite gerichtete Tabelle und die Dopplung bliebe.
$spalten = $fenster('private function BuildListColumns');
// Am naechsten Zweig begrenzen, nicht auf eine feste Zeichenzahl - ein festes
// Fenster ist in dieser Suite schon mehrfach gerissen, sobald Kommentare dazukamen.
$glossarStart = (int) strpos($spalten, "if (\$Kind === 'glossary')");
$glossarZweig = substr($spalten, $glossarStart, (int) strpos($spalten, "if (\$Kind === 'automations')") - $glossarStart);
assert(strpos($glossarZweig, 'BuildRowSourceLanguageColumn') === false,
    'DIE DEFINITION: keine Quellsprachen-Spalte im Glossar');
assert(strpos($glossarZweig, 'BuildLanguageColumnSet') !== false, 'nur die Sprachspalten');
echo "Test 4 (keine Quellsprachen-Spalte im Glossar) OK\n";

// Test 5: die eigene Tabelle behaelt ihre - dort legt die Zeile die Richtung fest.
$manualZweig = substr($spalten, (int) strpos($spalten, "if (\$Kind === 'manual')"), 900);
assert(strpos($manualZweig, 'BuildRowSourceLanguageColumn') !== false,
    'die "Eigenen Uebersetzungen" bleiben gerichtet - unveraendert');
echo "Test 5 (die eigene Tabelle bleibt unverändert gerichtet) OK\n";

// Test 6: das Gate. Bearbeiten ab Standard ("glossary"), der NACHSCHLAG laeuft
// aber in jeder Edition - Einheiten muessen ueberall richtig behandelt werden,
// verkauft wird das Bearbeiten.
$lookup = $fenster('private function GetGlossaryRowsForLookup');
assert(strpos($lookup, "HasLicenseFeature('glossary')") !== false, 'mit Feature die gespeicherte Tabelle');
assert(strpos($lookup, 'BuildBundledGlossaryRows()') !== false, 'ohne Feature der Katalog direkt');
assert(strpos($module, "case self::propertyGlossary:") !== false, 'die Tabelle steht im Formular');
echo "Test 6 (Bearbeiten ab Standard, Nachschlag in jeder Edition) OK\n";

// Test 7: Property und Formularelement existieren und passen zusammen.
assert(strpos($konstanten, "propertyGlossary = 'Glossary'") !== false, 'die Property existiert');
$gefunden = false;
$suche = function (array $n) use (&$suche, &$gefunden): void {
    foreach ($n as $v) {
        if (is_array($v)) {
            if (($v['name'] ?? '') === 'Glossary' && ($v['type'] ?? '') === 'List') {
                $gefunden = true;
            }
            $suche($v);
        }
    }
};
$suche($form);
assert($gefunden, 'die Liste "Glossary" steht in form.json');
echo "Test 7 (Property und Formularliste passen zusammen) OK\n";

// Test 8 (Build 195): der Schluessel einer Katalogzeile ist KEINE Sprache.
// Vorher diente die deutsche Spalte dafuer - das widersprach der Idee der
// Tabelle und machte eigene Zeilen mit Katalogzeilen verwechselbar, sobald
// jemand auf Englisch arbeitet und seine deutsche Spalte zufaellig trifft.
$build = $fenster('private function BuildBundledGlossaryRows');
assert(strpos($build, 'self::fieldGlossaryCatalogKey => $germanText') !== false,
    'DIE UMSTELLUNG: Katalogzeilen tragen einen technischen Schluessel');
$merge = $fenster('private function MergeBundledGlossaryRows');
assert(strpos($merge, "\$row[self::fieldGlossaryCatalogKey]") !== false,
    'und die Nachbefuellung findet ihre Zeilen darueber');
assert(strpos($merge, "\$row['de']") === false,
    'die deutsche Spalte ist nicht mehr der Schluessel');
echo "Test 8 (der Katalog-Schlüssel ist keine Sprache) OK\n";

// Test 9 (Build 195): EINHEITEN werden fuer jede konfigurierte Sprache
// vorbelegt - sie sind Symbole und bleiben unveraendert. Genau das war die
// Luecke: eine Zielsprache ausserhalb der mitgelieferten Neun bekam eine leere
// Spalte, und "°C" ging dort wieder an den Anbieter.
$map = $fenster('private function BuildBundledManualTranslationMap');
assert(strpos($map, 'foreach ($Languages as $language)') !== false,
    'DIE LUECKE: Einheiten haengen nicht mehr an einer festen Sprachliste');
// KOMPASSRICHTUNGEN duerfen das ausdruecklich NICHT - sie haengen an den
// Woertern der Sprache (deutsch "O" wird tschechisch "V").
assert(strpos($map, 'self::COMPASS_BUNDLED_TRANSLATIONS as $germanCompass => $translationsByLanguage') !== false,
    'DIE ABGRENZUNG: der Kompass kommt weiter nur aus der gepflegten Tabelle');
echo "Test 9 (Einheiten für jede Sprache, Kompass nur wo gepflegt) OK\n";

// Test 10 (Build 196): nicht jede Einheit ist sprachunabhaengig. Genau 17 der
// 73 sind englisch abgeleitet oder sprachabhaengig ("rpm" ist deutsch "U/min",
// "kn" franzoesisch "nd"). Fuer eine NICHT gepruefte Sprache - Chinesisch,
// Japanisch, Griechisch - waere das Durchreichen des Symbols eine Vermutung.
// Der Beweis, dass Symbole nicht universell sind, steht im eigenen Code: fuer
// Russisch ueberschreiben wir 65 der 73.
$map = $fenster('private function BuildBundledManualTranslationMap');
assert(strpos($map, 'UNIT_LANGUAGE_DEPENDENT') !== false,
    'DIE ABGRENZUNG: die sprachabhaengigen Kuerzel sind benannt');
assert(strpos($map, 'if ($international || isset($geprueft[$language])) {') !== false,
    'SI-Symbole ueberall, sprachabhaengige Kuerzel nur in geprueften Sprachen');
// Und die Gegenrichtung: in den geprueften Sprachen aendert sich NICHTS.
assert(strpos($map, "array_merge(self::UNIT_COMPASS_BUNDLED_LANGUAGES, ['de'])") !== false,
    'die geprueften Sprachen umfassen Deutsch und die Kompass-Sprachen');
echo "Test 10 (sprachabhängige Kürzel nur in geprüften Sprachen) OK\n";

echo "\nAlle Tests OK (Build 196: Glossar getrennt, Vorbelegung nach Verlässlichkeit).\n";
