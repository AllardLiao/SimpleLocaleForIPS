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

// Repliziert EnsureTileMessageHandler().
function ensureReplica(string $html, bool $supportsRefresh = true): string
{
    if (strpos($html, 'handleMessage') !== false) {
        return $html;
    }
    $script = '<script>function handleMessage(data){/* ... */}</script>';
    $pos = strripos($html, '</body>');

    return $pos === false ? $html . $script : substr($html, 0, $pos) . $script . substr($html, $pos);
}

// Test 1: DER GEMELDETE FALL - eine Vorlage ohne Handler bekommt einen.
$design = '<div class="ipssl-select-row"><span>🇩🇪</span><span>🇨🇿</span></div>';
$out = ensureReplica($design);
assert(strpos($out, 'handleMessage') !== false, 'DER FIX: eine Vorlage ohne Handler muss einen bekommen');
assert(strpos($out, '🇨🇿') !== false, 'ihr eigener Inhalt bleibt unangetastet');
echo "Test 1 (eine Vorlage ohne Handler bekommt einen) OK\n";

// Test 2: DIE ABGRENZUNG - bringt eine Vorlage einen eigenen Handler mit, wird
// nichts angefasst. Sonst gaebe es zwei Funktionen gleichen Namens.
$mitEigenem = '<div></div><script>function handleMessage(d){ meineLogik(d); }</script>';
assert(ensureReplica($mitEigenem) === $mitEigenem, 'eine Vorlage mit eigenem Handler darf NICHT ergaenzt werden');
echo "Test 2 (ein eigener Handler bleibt unangetastet) OK\n";

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
$start = strpos($moduleSource, 'private function EnsureTileMessageHandler');
$body = substr($moduleSource, $start, 2600);
assert(strpos($body, "strpos(\$Html, 'handleMessage') !== false") !== false, 'ein vorhandener Handler muss erkannt werden');
assert(strpos($body, 'm.action==="ALERT"') !== false, 'der eingesetzte Handler muss ALERT verarbeiten');
assert(strpos($body, 'm.action==="REFRESH"') !== false, 'und REFRESH - aber nur, wenn die Vorlage das vertraegt');
assert(strpos($body, '$refresh = $SupportsRefresh') !== false,
    'DER FIX AUS BUILD 179: das Neuzeichnen haengt daran, ob die Vorlage <!--LANGUAGE_SELECT--> benutzt');
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
echo "Test 6 (die eingebaute Kachel bleibt unverändert) OK\n";

echo "\nAll tests passed.\n";
