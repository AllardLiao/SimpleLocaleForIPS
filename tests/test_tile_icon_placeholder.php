<?php
declare(strict_types=1);
// Standalone replica test for build 173 (2026-08-27, Nutzer-Frage: "wie bekomme
// ich das Icon in ein eigenes Template eingebunden?").
//
// Antwort war: gar nicht. Das Symbol wurde ausschliesslich INNERHALB der
// generierten Sprachauswahl gebaut (<span class="sloc-globe">…). Wer eine
// eigene Sprachauswahl hinterlegte, ersetzte damit den ganzen Block und verlor
// das Symbol ersatzlos - es gab keinen Weg, es zurueckzuholen.
//
// Das traf ausgerechnet Build 172: dort liefern wir editionsgebundene Symbole
// vom Server aus, und ein eigenes Template haette sie nie zeigen koennen - der
// Wiedererkennungswert waere genau dort verlorengegangen, wo am meisten
// gestaltet wird.
//
// Neu: <!--TILE_ICON--> setzt das gewaehlte Symbol an beliebiger Stelle ein.

// Repliziert ApplyTilePlaceholders() - nur die Reihenfolge und die Ersetzungen.
function applyReplica(string $html, string $selectHtml, string $iconHtml): string
{
    $html = str_replace('<!--WRAPPER_ID-->', 'sloc-select-wrapper-42', $html);
    $html = str_replace('<!--LANGUAGE_SELECT-->', $selectHtml, $html);

    return str_replace('<!--TILE_ICON-->', $iconHtml, $html);
}

// Repliziert ResolveTileIconHtml().
function iconReplica(bool $showIcon): string
{
    return $showIcon
        ? '<img alt="" class="sloc-tile-icon" style="max-width:100%;max-height:100%;display:block;" src="data:image/png;base64,AAA">'
        : '';
}

// Test 1: DER GEMELDETE FALL - ein eigenes Template mit eigener Sprachauswahl
// kann das Symbol jetzt setzen, wohin es will.
$template = '<div class="kopf"><!--TILE_ICON--></div><div class="fuss"><!--LANGUAGE_SELECT--></div>';
$out = applyReplica($template, '<select id="eigene"></select>', iconReplica(true));
assert(strpos($out, 'sloc-tile-icon') !== false, 'DER FIX: das Symbol muss an der Stelle des Platzhalters landen');
assert(strpos($out, 'eigene') !== false, 'die eigene Sprachauswahl bleibt unangetastet');
assert(strpos($out, '<!--TILE_ICON-->') === false, 'der Platzhalter selbst darf nicht stehenbleiben');
echo "Test 1 (das Symbol landet an der Stelle des Platzhalters) OK\n";

// Test 2: die Checkbox "Symbol in der Kachel anzeigen" gilt auch hier - sonst
// haette der Platzhalter sie stillschweigend uebergangen.
$aus = applyReplica($template, '', iconReplica(false));
assert(strpos($aus, 'sloc-tile-icon') === false, 'bei abgeschalteter Checkbox darf kein Symbol erscheinen');
assert(strpos($aus, '<!--TILE_ICON-->') === false, 'der Platzhalter muss trotzdem verschwinden, nicht sichtbar bleiben');
echo "Test 2 (die Checkbox wird respektiert, der Platzhalter verschwindet trotzdem) OK\n";

// Test 3: DIE FALLE - die Style-Angabe der eingebauten Kachel ("height:100%")
// setzt einen Elternrahmen mit fester Hoehe voraus. In einem eigenen Template
// gibt es den nicht; das Symbol waere unsichtbar. Die Platzhalter-Fassung darf
// deshalb NICHT auf height:100% stehen.
$icon = iconReplica(true);
// Auf die EIGENSTAENDIGE Angabe pruefen, nicht auf den Teilstring - "max-height:100%"
// enthaelt "height:100%" und wuerde sonst faelschlich anschlagen.
assert(strpos($icon, 'style="height:100%') === false && strpos($icon, ';height:100%') === false,
    'DIE FALLE: ein blankes height:100% kollabiert ohne Elternrahmen mit fester Hoehe');
assert(strpos($icon, 'max-height:100%') !== false, 'stattdessen natuerliche Groesse, die nur bei Bedarf schrumpft');
echo "Test 3 (die Platzhalter-Fassung kollabiert nicht ohne Elternrahmen) OK\n";

// Test 4: ein Template OHNE den Platzhalter bleibt unveraendert - Bestandskacheln
// duerfen sich durch den neuen Platzhalter nicht aendern.
$alt = '<div><!--LANGUAGE_SELECT--></div>';
assert(applyReplica($alt, '<select></select>', iconReplica(true)) === '<div><select></select></div>',
    'ein Template ohne den Platzhalter darf sich nicht aendern');
echo "Test 4 (bestehende Templates bleiben unverändert) OK\n";

// Test 5: mehrfaches Vorkommen wird ersetzt - str_replace, kein einmaliges.
$doppelt = applyReplica('<!--TILE_ICON--><!--TILE_ICON-->', '', iconReplica(true));
assert(substr_count($doppelt, 'sloc-tile-icon') === 2, 'jeder Platzhalter muss ersetzt werden, nicht nur der erste');
echo "Test 5 (mehrfaches Vorkommen wird ersetzt) OK\n";

// Test 6: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function ApplyTilePlaceholders');
$body = substr($moduleSource, $start, strpos($moduleSource, "\n    private function ", $start + 10) - $start);
assert(strpos($body, "str_replace('<!--TILE_ICON-->', \$this->ResolveTileIconHtml(), \$html)") !== false,
    'der Platzhalter muss real verdrahtet sein');

$resolveStart = strpos($moduleSource, 'private function ResolveTileIconHtml');
$resolve = substr($moduleSource, $resolveStart, 700);
assert(strpos($resolve, 'ReadPropertyBoolean(self::propertyShowGlobeIcon)') !== false,
    'die Checkbox muss beruecksichtigt werden');
assert(strpos($resolve, "'max-width:100%;max-height:100%;display:block;', 'sloc-tile-icon'") !== false,
    'die Platzhalter-Fassung braucht die nicht-kollabierende Angabe und ihre Klasse');

// Die eingebaute Kachel behaelt ihre bisherige Angabe - sonst veraendert sich
// die Optik jeder bestehenden Instanz.
assert(strpos($moduleSource, "\$ImgStyle = 'height:100%;width:auto;display:block;'") !== false,
    'die eingebaute Kachel muss ihre bisherige Style-Angabe als Vorgabe behalten');
echo "Test 6 (die reale Umsetzung ist verdrahtet, die eingebaute Kachel unverändert) OK\n";

echo "\nAll tests passed.\n";
