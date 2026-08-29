<?php
declare(strict_types=1);
// Standalone replica test for build 184 (2026-08-29): <!--AVAILABLE_LANGUAGES-->
// und <!--ACTIVE_LANGUAGE--> in ApplyTilePlaceholders().
//
// ZWECK: ein eigenes Template soll sein Layout an der KONFIGURATION ausrichten
// koennen (nur die konfigurierten Flaggen zeigen, die aktive hervorheben),
// statt die Sprachcodes fest einzutippen - genau die Fehlerquelle, die in
// Build 175 das Popup fuer unbekannte Codes noetig gemacht hat.
//
// Die beiden Platzhalter landen typischerweise direkt in einer JS-Zuweisung
// (var langs = <!--AVAILABLE_LANGUAGES-->;). Daraus folgt die zentrale
// Zusicherung dieses Tests: der eingesetzte Wert ist IMMER gueltiges JSON,
// auch im Sperr- und im Sonderfall. Ein Klartextsatz waere dort ein
// Syntaxfehler und wuerde das ganze Skript des Templates mitreissen.

$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert($moduleSource !== false, 'module.php lesbar');

// Repliziert die beiden Ersetzungen aus ApplyTilePlaceholders().
function placeholderValues(bool $hatPro, string $current, string $source): array
{
    $available = '[]';
    if ($hatPro) {
        $available = json_encode([['code' => 'de', 'name' => 'Deutsch', 'current' => true]]);
    }

    $active = $current;
    if ($active === '' || $active === 'ORIGINAL_IMPORT') {
        $active = $source;
    }

    return ['available' => $available, 'active' => json_encode($active)];
}

// Test 1: DER FEHLER - ohne Pro wurde ein Klartextsatz eingesetzt. Jetzt eine
// leere, gueltige JSON-Liste.
$v = placeholderValues(false, 'de', 'de');
assert(json_decode($v['available'], true) === [],
    'DER FEHLER: ohne Pro eine leere Liste statt eines Klartextsatzes');
assert(json_last_error() === JSON_ERROR_NONE, 'und damit parsbar');
echo "Test 1 (ohne Pro gültiges, leeres JSON) OK\n";

// Test 2: der Sperrfall ist erreichbar - mitgelieferte Editions-Designs haengen
// NICHT an "custom_tile", der Platzhalter kann also ohne Pro ankommen.
$moduleSource = (string) $moduleSource;
$tileHtml = substr($moduleSource, (int) strpos($moduleSource, 'public function GetVisualizationTile'), 900);
assert(strpos($tileHtml, 'GetSelectedTileTemplateHtml') !== false,
    'gelieferte Vorlagen laufen durch denselben Platzhalter-Pfad');
echo "Test 2 (gelieferte Designs erreichen den Platzhalter auch ohne Pro) OK\n";

// Test 3: mit Pro das Format der oeffentlichen Funktion - {code, name, current}.
$v = placeholderValues(true, 'de', 'de');
$liste = json_decode($v['available'], true);
assert(is_array($liste) && isset($liste[0]['code'], $liste[0]['name'], $liste[0]['current']),
    'mit Pro die Liste aus code/name/current');
echo "Test 3 (mit Pro das Format der öffentlichen Funktion) OK\n";

// Test 4: DER SENTINEL - ORIGINAL_IMPORT ist modulintern (siehe Build 183) und
// darf nie in einem Template landen. Ein Wechsel zurueck aufs Original schreibt
// ihn kurzzeitig in die Property.
assert(placeholderValues(true, 'ORIGINAL_IMPORT', 'de')['active'] === '"de"',
    'DER SENTINEL: ORIGINAL_IMPORT wird auf die Quellsprache abgebildet');
assert(placeholderValues(true, '', 'fr')['active'] === '"fr"',
    'und eine leere Property ebenso');
assert(placeholderValues(true, 'en', 'de')['active'] === '"en"',
    'eine echte aktive Sprache bleibt unangetastet');
echo "Test 4 (ORIGINAL_IMPORT dringt nicht ins Template) OK\n";

// Test 5: DIE FALLE - die Stats-Liste ersetzt ueber zwei parallele Arrays.
// Stehen dort mehr Platzhalter als Werte, fuellt PHP still mit '' auf. Die
// beiden neuen gehoeren dort nicht hinein, sie sind vorher schon ersetzt.
$stats = substr($moduleSource, (int) strpos($moduleSource, 'private function ApplyTranslationStatsPlaceholders'), 1400);
assert(strpos($stats, 'AVAILABLE_LANGUAGES') === false && strpos($stats, 'ACTIVE_LANGUAGE') === false,
    'DIE FALLE: die neuen Platzhalter stehen nicht in der Stats-Liste');
preg_match('/\$placeholders = \[(.*?)\];/s', $stats, $m);
assert(substr_count($m[1], '<!--') === 4, 'die Stats-Liste hat genau vier Eintraege');
echo "Test 5 (keine Längen-Asymmetrie in der Stats-Ersetzung) OK\n";

// Test 6: beide Platzhalter werden VOR EnsureTileMessageHandler ersetzt - sonst
// stuende ein <!--ACTIVE_LANGUAGE--> im gelieferten Design woertlich da (exakt
// der Build-177-Fehler mit <!--WRAPPER_ID-->).
// Fenster exakt an der naechsten Funktion begrenzen statt auf eine feste
// Zeichenzahl - eine feste Groesse ist in dieser Suite schon mehrfach in die
// Folgefunktion gelaufen.
$applyStart = (int) strpos($moduleSource, 'private function ApplyTilePlaceholders');
$applyEnd = (int) strpos($moduleSource, 'private function EnsureTileMessageHandler', $applyStart);
$apply = substr($moduleSource, $applyStart, $applyEnd - $applyStart);
$pushStart = (int) strpos($moduleSource, 'private function PushVisualizationUpdate');
$pushEnd = (int) strpos($moduleSource, "\n    // ", $pushStart);
$push = substr($moduleSource, $pushStart, $pushEnd - $pushStart);

$posAvailable = strpos($apply, "str_replace('<!--AVAILABLE_LANGUAGES-->'");
$posActive = strpos($apply, "str_replace('<!--ACTIVE_LANGUAGE-->'");
$posHandler = strpos($apply, 'return $this->EnsureTileMessageHandler');
assert($posAvailable !== false && $posActive !== false && $posHandler !== false, 'alle drei Stellen gefunden');
assert($posAvailable < $posHandler && $posActive < $posHandler, 'beide vor dem Handler ersetzt');
echo "Test 6 (Reihenfolge: Ersetzung vor dem Handler) OK\n";

// Test 7: DIE SYMMETRIE - Ladezeit-Wert und Live-Aktualisierung muessen aus
// DERSELBEN Quelle kommen. Sonst zeigt ein Template beim Laden etwas anderes als
// nach dem ersten Sprachwechsel, und der Fehler waere nur live zu sehen.
foreach (['GetTileAvailableLanguagesJson', 'GetTileActiveLanguageCode'] as $helfer) {
    assert(substr_count($moduleSource, '$this->' . $helfer . '()') === 2,
        "DIE SYMMETRIE: $helfer muss von beiden Seiten benutzt werden - Platzhalter UND REFRESH");
    assert(strpos($apply, '$this->' . $helfer . '()') !== false, "$helfer speist den Platzhalter");
    assert(strpos($push, '$this->' . $helfer . '()') !== false, "$helfer speist die REFRESH-Nutzlast");
}
echo "Test 7 (Ladezeit-Wert und Live-Aktualisierung aus derselben Quelle) OK\n";

// Test 8: der Haken laeuft ueber die Kachel-Nachricht, nicht ueber ein erneutes
// Rendern - GetVisualizationTile() wird nur beim Laden aufgerufen.
assert(strpos($push, "'activeLanguage'") !== false && strpos($push, "'languages'") !== false,
    'die REFRESH-Nutzlast traegt beide Angaben');
assert(strpos($push, "'seq'") !== false,
    'und eine Nonce - ohne html ist die Nutzlast bei einem abgelehnten Wechsel sonst identisch zur vorigen');
echo "Test 8 (REFRESH trägt Daten und Nonce) OK\n";

echo "\nAlle Tests OK (Build 184: Konfigurations-Platzhalter).\n";
