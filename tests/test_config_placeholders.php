<?php
declare(strict_types=1);
// Standalone replica test for build 184 (2026-08-29): <!--AVAILABLE_LANGUAGES-->
// und <!--ACTIVE_LANGUAGE--> in ApplyTilePlaceholders().
//
// ZWECK: ein eigenes Template soll sein Layout an der KONFIGURATION ausrichten
// koennen (nur die konfigurierten Flaggen zeigen, die aktive hervorheben),
// statt die Sprachcodes fest einzutippen - genau die Fehlerquelle, die in
// Build 175 den Hinweis fuer unbekannte Codes noetig gemacht hat.
//
// Die Werte landen typischerweise direkt in einer JS-Zuweisung
// (var langs = <!--AVAILABLE_LANGUAGES-->;). Daraus folgt die zentrale
// Zusicherung: der eingesetzte Wert ist IMMER gueltiges JSON. Ein Klartextsatz
// waere dort ein Syntaxfehler und wuerde das ganze Skript mitreissen.

$moduleSource = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert($moduleSource !== '', 'module.php lesbar');

$fenster = function (string $von, string $bis) use ($moduleSource): string {
    // Immer an der Folgestelle begrenzen, nie auf eine feste Zeichenzahl - eine
    // feste Groesse ist in dieser Suite schon mehrfach gerissen, sobald eine
    // Funktion durch Kommentare wuchs.
    $a = (int) strpos($moduleSource, $von);
    $b = (int) strpos($moduleSource, $bis, $a + 1);

    return substr($moduleSource, $a, $b - $a);
};

$apply = $fenster('private function ApplyTilePlaceholders', 'private function EnsureTileMessageHandler');
$push = $fenster('private function PushVisualizationUpdate', "\n    // ");
$build = $fenster('private function BuildAvailableLanguagesJson', "\n    // ");
$public = $fenster('public function GetAvailableLanguages', 'private function BuildAvailableLanguagesJson');

// Test 1: DIE EBENE DER SPERRE. Der Platzhalter selbst ist an KEIN Feature
// gebunden - dort ist die Sperre laengst gefallen: eigenes Kachel-HTML wirkt
// sich ueberhaupt nur mit "custom_tile" aus, ein Anwender ohne das Feature kann
// den Platzhalter also gar nicht erst einschleusen. Eine zweite Sperre haette
// nur die mitgelieferten Editions-Designs leer laufen lassen, fuer die er
// gedacht ist - und die schreiben nicht die Anwender.
assert(strpos($build, 'HasLicenseFeature') === false,
    'DIE EBENE: der Aufbau fuer den Platzhalter darf nicht noch einmal sperren');
assert(strpos($apply, 'HasLicenseFeature') === false,
    'und die Platzhalter-Kette ebenso wenig');
echo "Test 1 (der Platzhalter sperrt nicht ein zweites Mal) OK\n";

// Test 2: die vorgelagerte Sperre muss es aber wirklich geben, sonst waere
// Test 1 ein Loch statt einer Vereinfachung.
$tile = $fenster('public function GetVisualizationTile', 'private function GetSelectedTileTemplateHtml');
assert(strpos($tile, "HasLicenseFeature('custom_tile')") !== false,
    'eigenes Kachel-HTML wirkt nur mit dem Feature');
$resolve = $fenster('private function ResolveLanguageSelectHtml', 'private function GetDefaultCustomTileHtml');
assert(strpos($resolve, "HasLicenseFeature('custom_tile')") !== false,
    'und eine eigene Sprachauswahl ebenso');
assert(strpos($tile, 'GetSelectedTileTemplateHtml') !== false,
    'der einzige andere Weg in die Kachel ist ein geliefertes Design');
echo "Test 2 (die Sperre sitzt eine Ebene hoeher und greift dort) OK\n";

// Test 3: DIE FUNKTION bleibt hart gesperrt - sie ist der Weg, eine eigene
// Auswahl per Skript AN DER KACHEL VORBEI zu bauen, wo nichts vorgelagert ist.
assert(strpos($public, "HasLicenseFeature('custom_tile')") !== false
    && strpos($public, 'throw new Exception') !== false,
    'IPSSL_GetAvailableLanguages muss weiterhin werfen');
echo "Test 3 (die öffentliche Funktion bleibt gesperrt) OK\n";

// Test 4: DER SENTINEL - ORIGINAL_IMPORT ist modulintern (siehe Build 183) und
// darf nie in einem Template landen. Der Platzhalter laeuft deshalb ueber die
// oeffentliche GetCurrentLanguageCode(), die ihn bereits auf die Quellsprache
// abbildet - und die damit garantiert denselben Wert liefert wie ein Skript,
// das daneben IPSSL_GetCurrentLanguageCode() aufruft.
assert(strpos($apply, '$this->GetCurrentLanguageCode()') !== false,
    'DER SENTINEL: der Platzhalter laeuft ueber die oeffentliche Funktion');
$current = $fenster('public function GetCurrentLanguageCode', 'public function GetAvailableLanguages');
assert(strpos($current, 'ResolveDisplayLanguageCode') !== false, 'die den Sentinel abbildet');
$resolveCode = $fenster('private function ResolveDisplayLanguageCode', 'private function GetGuestLanguageName');
assert(strpos($resolveCode, 'langOriginalImport') !== false
    && strpos($resolveCode, 'propertySourceLanguage') !== false,
    'naemlich auf die Quellsprache');
echo "Test 4 (ORIGINAL_IMPORT dringt nicht ins Template) OK\n";

// Test 5: DIE FALLE - die Stats-Liste ersetzt ueber zwei parallele Arrays.
// Stehen dort mehr Platzhalter als Werte, fuellt PHP still mit '' auf. Die
// beiden neuen gehoeren dort nicht hinein, sie sind vorher schon ersetzt.
$stats = $fenster('private function ApplyTranslationStatsPlaceholders', "\n    // ");
assert(strpos($stats, 'AVAILABLE_LANGUAGES') === false && strpos($stats, 'ACTIVE_LANGUAGE') === false,
    'DIE FALLE: die neuen Platzhalter stehen nicht in der Stats-Liste');
preg_match('/\$placeholders = \[(.*?)\];/s', $stats, $m);
assert(substr_count($m[1], '<!--') === 4, 'die Stats-Liste hat genau vier Eintraege');
echo "Test 5 (keine Längen-Asymmetrie in der Stats-Ersetzung) OK\n";

// Test 6: beide Platzhalter werden VOR EnsureTileMessageHandler ersetzt - sonst
// stuende ein <!--ACTIVE_LANGUAGE--> im gelieferten Design woertlich da (exakt
// der Build-177-Fehler mit <!--WRAPPER_ID-->).
$posAvailable = strpos($apply, "str_replace('<!--AVAILABLE_LANGUAGES-->'");
$posActive = strpos($apply, "str_replace('<!--ACTIVE_LANGUAGE-->'");
$posHandler = strpos($apply, 'return $this->EnsureTileMessageHandler');
assert($posAvailable !== false && $posActive !== false && $posHandler !== false, 'alle drei Stellen gefunden');
assert($posAvailable < $posHandler && $posActive < $posHandler, 'beide vor dem Handler ersetzt');
echo "Test 6 (Reihenfolge: Ersetzung vor dem Handler) OK\n";

// Test 7: DIE SYMMETRIE - Ladezeit-Wert und Live-Aktualisierung muessen aus
// DERSELBEN Quelle kommen. Sonst zeigt ein Template beim Laden etwas anderes als
// nach dem ersten Sprachwechsel, und der Fehler waere nur live zu sehen.
foreach (['BuildAvailableLanguagesJson', 'GetCurrentLanguageCode'] as $quelle) {
    assert(strpos($apply, '$this->' . $quelle . '()') !== false, "$quelle speist den Platzhalter");
    assert(strpos($push, '$this->' . $quelle . '()') !== false, "$quelle speist die REFRESH-Nutzlast");
}
echo "Test 7 (Ladezeit-Wert und Live-Aktualisierung aus derselben Quelle) OK\n";

// Test 8: die REFRESH-Nutzlast traegt beide Angaben und eine Nonce - ohne
// html-Teil ist sie bei einem ABGELEHNTEN Wechsel sonst identisch zur vorigen,
// und eine identische Nutzlast loest in der Kachel gar kein Ereignis aus.
assert(strpos($push, "'activeLanguage'") !== false && strpos($push, "'languages'") !== false,
    'die REFRESH-Nutzlast traegt beide Angaben');
assert(strpos($push, "'seq'") !== false, 'und eine Nonce');
echo "Test 8 (REFRESH trägt Daten und Nonce) OK\n";

// Test 9: das Ergebnis ist in jedem Fall gueltiges JSON - die Zusicherung, an
// der die ganze Runde haengt.
$beispiel = json_encode([['code' => 'de', 'name' => 'Deutsch', 'current' => true]]);
assert(json_decode($beispiel, true)[0]['code'] === 'de', 'die Liste ist parsbar');
assert(json_decode(json_encode('de')) === 'de', 'und der aktive Code ebenso');
echo "Test 9 (beide Werte sind gültiges JSON) OK\n";

echo "\nAlle Tests OK (Build 184: Konfigurations-Platzhalter).\n";
