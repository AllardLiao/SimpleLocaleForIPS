<?php
declare(strict_types=1);
// Standalone replica test for build 178 (2026-08-28, live gefunden): eine vom
// Server gelieferte Kachel-Vorlage bekam weder Popups noch das automatische
// Neuzeichnen.
//
// URSACHE: Das Modul schickt der Kachel REFRESH- und ALERT-Nachrichten,
// verarbeitet werden sie von handleMessage() - und die stand ausschliesslich in
// module.html. Eine gelieferte Vorlage (Build 172) ersetzt aber die KOMPLETTE
// Huelle. Wer ein Design anlegt, hatte damit unbemerkt saemtliche Gast-Hinweise
// und das Neuzeichnen abgeschaltet.
//
// Live genau so aufgetreten: der Sprachwechsel auf einen nicht konfigurierten
// Code wurde korrekt abgelehnt, die Ablehnung stand im Log - in der Kachel
// geschah nichts. Die Suche lief zuerst in die falsche Richtung (eigenes
// Kachel-HTML), bis klar wurde, dass es eine GELIEFERTE Vorlage war.

// Repliziert EnsureTileMessageHandler() inkl. der Umhuellung aus Build 184.
function ensureReplica(string $html, bool $supportsRefresh = true): string
{
    if (strpos($html, 'handleMessage') !== false) {
        // Build 184: der eigene Handler bleibt, bekommt aber den Haken
        // umgelegt - sonst erreichte window.slocOnLanguageChange genau die
        // Templates nie, die aus einer aelteren module.html stammen.
        if (strpos($html, 'slocOnLanguageChange') !== false) {
            return $html;
        }
        $wrap = '<script>/* wrapper: slocOnLanguageChange */</script>';
        $p = strripos($html, '</body>');

        return $p === false ? $html . $wrap : substr($html, 0, $p) . $wrap . substr($html, $p);
    }
    $script = '<script>function handleMessage(data){/* ... */}</script>';
    $pos = strripos($html, '</body>');

    return $pos === false ? $html . $script : substr($html, 0, $pos) . $script . substr($html, $pos);
}

// Test 1: DER GEMELDETE FALL - eine Vorlage ohne Handler bekommt einen.
$design = '<div class="sloc-select-row"><span>🇩🇪</span><span>🇨🇿</span></div>';
$out = ensureReplica($design);
assert(strpos($out, 'handleMessage') !== false, 'DER FIX: eine Vorlage ohne Handler muss einen bekommen');
assert(strpos($out, '🇨🇿') !== false, 'ihr eigener Inhalt bleibt unangetastet');
echo "Test 1 (eine Vorlage ohne Handler bekommt einen) OK\n";

// Test 2: DIE ABGRENZUNG - bringt eine Vorlage einen eigenen Handler mit, wird
// nichts angefasst. Sonst gaebe es zwei Funktionen gleichen Namens.
$mitEigenem = '<div></div><script>function handleMessage(d){ meineLogik(d); }</script>';
$out = ensureReplica($mitEigenem);
assert(strpos($out, 'function handleMessage(d){ meineLogik(d); }') !== false,
    'eine Vorlage mit eigenem Handler behaelt ihn unveraendert');
assert(substr_count($out, 'function handleMessage') === 1, 'kein zweiter Handler gleichen Namens');
echo "Test 2 (ein eigener Handler bleibt unangetastet) OK\n";

// Test 2b (Build 184): er bekommt aber den Haken umgelegt - sonst waere
// <!--ACTIVE_LANGUAGE--> in genau diesen Templates stumm eingefroren. Bringt das
// Template den Haken schon selbst mit (jede Kopie ab Build 184), passiert nichts.
assert(strpos($out, 'slocOnLanguageChange') !== false, 'der Haken muss ihn trotzdem erreichen');
$schonAktuell = '<div></div><script>function handleMessage(d){ window.slocOnLanguageChange && 0; }</script>';
assert(ensureReplica($schonAktuell) === $schonAktuell, 'ein Template mit eigenem Haken wird nicht doppelt bedient');
echo "Test 2b (der Haken erreicht auch einen eigenen Handler, aber nie doppelt) OK\n";

// Test 3: bei vollstaendigem HTML landet das Script INNERHALB des Body - danach
// wuerde es der Browser zwar auch ausfuehren, aber sauber ist sauber.
$vollstaendig = '<html><body><div>x</div></body></html>';
$out = ensureReplica($vollstaendig);
assert(strpos($out, '</script></body>') !== false, 'das Script muss vor </body> stehen');
assert(substr_count($out, '</body>') === 1, 'und der Body darf nicht doppelt geschlossen werden');
echo "Test 3 (bei vollständigem HTML landet es vor </body>) OK\n";

// Test 4: ein Fragment ohne <body> bekommt es angehaengt - genau der Fall einer
// gelieferten Vorlage, die nur ein <div> ist.
$fragment = '<div>x</div>';
assert(ensureReplica($fragment) === $fragment . '<script>function handleMessage(data){/* ... */}</script>',
    'ein Fragment bekommt das Script angehaengt');
echo "Test 4 (ein Fragment bekommt es angehängt) OK\n";

// Test 5: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function EnsureTileMessageHandler(string $Html, bool $SupportsRefresh): string') !== false,
    'die Absicherung muss existieren - seit Build 179 mit der Angabe, ob neu gezeichnet werden darf');
// Fenster an der naechsten Funktion begrenzen, nicht auf eine feste
// Zeichenzahl - eine feste Groesse ist in dieser Suite schon mehrfach gerissen,
// sobald die Funktion durch Kommentare wuchs.
$start = (int) strpos($moduleSource, 'private function EnsureTileMessageHandler');
$end = (int) strpos($moduleSource, 'private function EnsureLanguageChangeHook', $start);
$body = substr($moduleSource, $start, $end - $start);
assert(strpos($body, "strpos(\$Html, 'handleMessage') !== false") !== false, 'ein vorhandener Handler muss erkannt werden');
assert(strpos($body, 'm.action==="ALERT"') !== false, 'der eingesetzte Handler muss ALERT verarbeiten');
assert(strpos($body, 'm.action==="REFRESH"') !== false, 'und REFRESH - aber nur, wenn die Vorlage das vertraegt');
assert(strpos($body, '$redraw = $SupportsRefresh') !== false,
    'DER FIX AUS BUILD 179: das Neuzeichnen haengt daran, ob die Vorlage <!--LANGUAGE_SELECT--> benutzt');
// Build 184: der Haken fuer eigene Vorlagen muss AUSSERHALB dieser Bedingung
// stehen. Genau die Vorlagen, die kein html bekommen (weil sie ihre Auswahl
// selbst bauen), sind die, die ihn brauchen - haenge er mit am Neuzeichnen,
// erreichte er nie eine davon.
assert(strpos($body, 'window.slocOnLanguageChange') !== false,
    'der Haken fuer eigene Vorlagen muss eingesetzt werden');
assert(strpos($body, '$hook = ') !== false && strpos($body, '$hook') > strpos($body, '$redraw = $SupportsRefresh'),
    'er wird unabhaengig von $SupportsRefresh gebaut');
assert(strpos($body, '$redraw . $hook') !== false,
    'und unbedingt in den REFRESH-Zweig gesetzt, nicht in den html-Teil');
// Fehlt das Ziel-Element, darf das Neuzeichnen still scheitern - die Meldungen
// muessen trotzdem ankommen.
assert(strpos($body, 'if(w){w.innerHTML=m.payload.html;}') !== false,
    'ein fehlendes Ziel-Element darf den Handler nicht abbrechen lassen');

// Sie muss auch wirklich am Ende der Kette haengen, sonst greift sie nicht.
$applyStart = strpos($moduleSource, 'private function ApplyTilePlaceholders');
$applyBody = substr($moduleSource, $applyStart, strpos($moduleSource, "\n    private function ", $applyStart + 10) - $applyStart);
assert(strpos($applyBody, 'EnsureTileMessageHandler(') !== false, 'sie muss in der Platzhalter-Kette aufgerufen werden');
echo "Test 5 (die reale Umsetzung ist verdrahtet und bricht nicht ab) OK\n";

// Test 6: das eingebaute module.html bringt seinen Handler weiterhin selbst mit -
// dort darf nichts ergaenzt werden.
$tileSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.html');
assert(strpos($tileSource, 'handleMessage') !== false, 'die eingebaute Kachel behaelt ihren eigenen Handler');
assert(strpos($tileSource, 'slocOnLanguageChange') !== false,
    'Build 184: sie bedient den Haken selbst - Kopien davon brauchen keine Umhuellung');
echo "Test 6 (die eingebaute Kachel bleibt unverändert) OK\n";

// Test 7 (Build 184): die Umhuellung ersetzt den fremden Handler nicht, sondern
// ruft ihn weiter auf - sonst verloere ein Template sein eigenes Verhalten.
$hookStart = (int) strpos($moduleSource, 'private function EnsureLanguageChangeHook');
$hookBody = substr($moduleSource, $hookStart, (int) strpos($moduleSource, "\n    // Für eigene Kacheln", $hookStart) - $hookStart);
assert(strpos($hookBody, 'var inner=handleMessage;') !== false, 'der vorhandene Handler wird festgehalten');
assert(strpos($hookBody, 'return inner.apply(this,arguments);') !== false, 'und unveraendert weiter aufgerufen');
assert(strpos($hookBody, "strpos(\$Html, 'slocOnLanguageChange') !== false") !== false,
    'und uebersprungen, wenn das Template den Haken schon selbst bedient');
assert(strpos($hookBody, 'strripos($Html, \'</body>\')') !== false,
    'ans Ende des Body - der eigene Handler muss vorher definiert sein');
echo "Test 7 (die Umhüllung ruft den fremden Handler weiter auf) OK\n";

echo "\nAll tests passed.\n";
