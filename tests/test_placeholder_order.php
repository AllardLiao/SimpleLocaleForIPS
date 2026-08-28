<?php
declare(strict_types=1);
// Standalone replica test for build 177 (2026-08-28, an einem Kunden-Template
// aufgefallen): <!--WRAPPER_ID--> blieb in einer EIGENEN Sprachauswahl woertlich
// stehen.
//
// URSACHE: ApplyTilePlaceholders() ersetzte <!--WRAPPER_ID--> VOR
// <!--LANGUAGE_SELECT-->. Zum Zeitpunkt der Ersetzung war das eigene
// Sprachauswahl-HTML also noch gar nicht im Dokument - sein Platzhalter wurde
// nie gesehen und landete unveraendert in der Ausgabe.
//
// <!--TILE_ICON--> und die vier Zaehler standen schon immer NACH der
// Sprachauswahl und funktionierten deshalb in beiden Feldern. Genau diese
// Ungleichbehandlung war der Fehler.

// Repliziert ApplyTilePlaceholders() vorher/nachher.
function applyReplica(string $shell, string $select, string $icon, bool $mitFix): string
{
    if ($mitFix) {
        $html = str_replace('<!--LANGUAGE_SELECT-->', $select, $shell);
        $html = str_replace('<!--WRAPPER_ID-->', 'ipssl-select-wrapper-42', $html);
    } else {
        $html = str_replace('<!--WRAPPER_ID-->', 'ipssl-select-wrapper-42', $shell);
        $html = str_replace('<!--LANGUAGE_SELECT-->', $select, $html);
    }

    return str_replace('<!--TILE_ICON-->', $icon, $html);
}

$shell = '<div id="<!--WRAPPER_ID-->"><!--LANGUAGE_SELECT--></div>';
// Eine eigene Sprachauswahl, die selbst beide Platzhalter nutzt - genau der
// gemeldete Fall.
$select = '<div class="reihe" id="<!--WRAPPER_ID-->"><span><!--TILE_ICON--></span></div>';

// Test 1: DER GEMELDETE FALL - ohne den Fix bleibt der Platzhalter stehen.
$vorher = applyReplica($shell, $select, '<img>', false);
assert(strpos($vorher, '<!--WRAPPER_ID-->') !== false,
    'DER BUG: in der eigenen Sprachauswahl blieb <!--WRAPPER_ID--> woertlich stehen');
echo "Test 1 (der gemeldete Fall wird reproduziert) OK\n";

// Test 2: DER FIX - danach ist kein Platzhalter mehr uebrig.
$nachher = applyReplica($shell, $select, '<img>', true);
assert(strpos($nachher, '<!--WRAPPER_ID-->') === false, 'DER FIX: auch in der Sprachauswahl muss er ersetzt werden');
assert(strpos($nachher, '<!--LANGUAGE_SELECT-->') === false, 'die Sprachauswahl selbst ebenso');
assert(strpos($nachher, '<!--TILE_ICON-->') === false, 'und das Symbol');
echo "Test 2 (nach dem Fix bleibt kein Platzhalter stehen) OK\n";

// Test 3: die HUELLE verhaelt sich unveraendert - ihr Platzhalter wurde ja schon
// vorher ersetzt, nur eben in anderer Reihenfolge.
assert(substr_count($nachher, 'ipssl-select-wrapper-42') === 2,
    'beide Vorkommen - Huelle und Sprachauswahl - muessen dieselbe ID tragen');
echo "Test 3 (die Hülle verhält sich unverändert) OK\n";

// Test 4: DIE FALLE - die Sprachauswahl muss ZUERST eingesetzt werden, sonst
// sieht keine spaetere Ersetzung ihren Inhalt. Das galt fuer TILE_ICON schon
// immer und gilt jetzt fuer alle gleichermassen.
$nurIcon = applyReplica('<div><!--LANGUAGE_SELECT--></div>', '<span><!--TILE_ICON--></span>', 'ICON', true);
assert(strpos($nurIcon, 'ICON') !== false, 'ein Platzhalter INNERHALB der Sprachauswahl muss greifen');
echo "Test 4 (Platzhalter innerhalb der Sprachauswahl greifen) OK\n";

// Test 5: Symmetrie-Check gegen die reale Umsetzung - die Reihenfolge ist der
// ganze Fix, deshalb wird genau sie festgehalten.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function ApplyTilePlaceholders');
$body = substr($moduleSource, $start, strpos($moduleSource, "\n    private function ", $start + 10) - $start);
$posSelect = strpos($body, "'<!--LANGUAGE_SELECT-->'");
$posWrapper = strpos($body, "'<!--WRAPPER_ID-->'");
$posIcon = strpos($body, "'<!--TILE_ICON-->'");
assert($posSelect !== false && $posWrapper !== false && $posIcon !== false, 'alle drei Ersetzungen muessen vorkommen');
assert($posSelect < $posWrapper, 'DER FIX: die Sprachauswahl muss VOR der Wrapper-ID eingesetzt werden');
assert($posSelect < $posIcon, 'und vor dem Symbol - so war es schon');
assert(strpos($body, '$this->ApplyTranslationStatsPlaceholders($html)') !== false,
    'die Zaehler laufen unveraendert zuletzt, damit sie auch in der Sprachauswahl greifen');
echo "Test 5 (die reale Reihenfolge setzt die Sprachauswahl zuerst ein) OK\n";

echo "\nAll tests passed.\n";
