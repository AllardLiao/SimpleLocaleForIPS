<?php
declare(strict_types=1);
// Standalone replica test for build 183 (2026-08-28): der mitgelieferte
// Beispielcode fuer die eigene Sprachauswahl-Kachel.
//
// VORGESCHICHTE: Das Beispiel in GetDefaultCustomLanguageSelectHtml() ist der
// Startwert von propertyCustomLanguageSelectHtml - jeder Pro-Kunde bekommt es
// vorbefuellt zu sehen und baut sein eigenes Design typischerweise darauf auf.
// Es schickte fuer Deutsch aber 'ORIGINAL_IMPORT', also genau den Sentinel, der
// seit Build 175 als modulintern aus der Doku fuer eigene Kacheln entfernt ist.
// Das Beispiel lehrte damit das Gegenteil der Anleitung.
//
// Korrekt ist der Sprachcode selbst: IsSelectableGuestLanguage() laesst die
// Scan-Sprache ausdruecklich durch (eigener Zweig neben langOriginalImport),
// und IsLanguageSwitchRateLimited() nimmt sie ebenso ausdruecklich vom
// Tagesschalter aus - ein Klick auf die Scan-Sprache verhaelt sich also
// identisch, nur ohne den internen Sentinel nach aussen zu tragen.

$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert($moduleSource !== false, 'module.php lesbar');

$start = strpos($moduleSource, 'private function GetDefaultCustomLanguageSelectHtml');
assert($start !== false, 'GetDefaultCustomLanguageSelectHtml existiert');
// Bis zum Ende des Heredoc begrenzen, sonst laufen die Zusicherungen in die
// naechste Funktion (in dieser Suite schon mehrfach passiert).
$ende = strpos($moduleSource, "\nHTML;", $start);
assert($ende !== false, 'Heredoc-Ende gefunden');
$beispiel = substr($moduleSource, $start, $ende - $start);

// Test 1: DER FUND - der interne Sentinel ist raus.
assert(strpos($beispiel, 'ORIGINAL_IMPORT') === false,
    'DER FUND: das mitgelieferte Beispiel traegt den internen Sentinel nicht mehr nach aussen');
echo "Test 1 (kein ORIGINAL_IMPORT im mitgelieferten Beispiel) OK\n";

// Test 2: stattdessen der echte Sprachcode - und weiterhin ueber den Ident,
// nicht ueber irgendeinen Instanznamen.
assert(strpos($beispiel, "requestAction('Language', 'de')") !== false,
    'Deutsch wird ueber seinen Sprachcode angefordert');
assert(strpos($beispiel, "requestAction('Language', 'en')") !== false,
    'Englisch unveraendert ueber seinen Sprachcode');
echo "Test 2 (Sprachcodes über den Ident 'Language') OK\n";

// Test 3: der Kommentar darf nicht mehr behaupten, ein unbekannter Code werde
// stillschweigend ignoriert - seit Build 175 sieht der Gast ein Popup.
assert(strpos($beispiel, 'wird ignoriert') === false,
    'die veraltete Zusage "wird ignoriert" ist raus');
assert(strpos($beispiel, 'abgelehnt') !== false && strpos($beispiel, 'Hinweis') !== false,
    'stattdessen: abgelehnt, mit Hinweis in der Kachel');
echo "Test 3 (Kommentar beschreibt das Popup statt stiller Ablehnung) OK\n";

// Test 4: die Scan-Sprache ist tatsaechlich auf beiden Wegen freigestellt -
// sonst waere der Wechsel von ORIGINAL_IMPORT auf 'de' eine Verschlechterung.
$selectable = substr($moduleSource, (int) strpos($moduleSource, 'private function IsSelectableGuestLanguage'), 700);
assert(strpos($selectable, 'propertySourceLanguage') !== false,
    'IsSelectableGuestLanguage laesst die Scan-Sprache durch');
$ratelimit = substr($moduleSource, (int) strpos($moduleSource, 'private function IsLanguageSwitchRateLimited'), 900);
assert(strpos($ratelimit, 'propertySourceLanguage') !== false,
    'IsLanguageSwitchRateLimited nimmt die Scan-Sprache vom Tagesschalter aus');
echo "Test 4 (Scan-Sprache auf beiden Wegen freigestellt) OK\n";

echo "\nAlle Tests OK (Build 183: mitgeliefertes Kachel-Beispiel).\n";
