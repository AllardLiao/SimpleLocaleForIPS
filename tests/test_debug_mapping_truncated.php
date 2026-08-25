<?php
declare(strict_types=1);
// Build 126 (Nutzer-Report, live per Debug-Log gefunden): die "GoogleTranslate_Mapping"-
// Debug-Zeile loggte den vollen, ungekuerzten Rohtext JEDER anstehenden Zeile -
// bei einem umfangreichen HTML-Widget (Wetter-Skript mit <style>-Block ueber
// mehrere Vorhersage-Tage) ergab das eine einzelne Debug-Zeile von ueber 60.000
// Zeichen, obwohl die tatsaechlich an den Anbieter geschickten Anfragen (siehe
// GoogleTranslate_Request) dank Knoten-Aufteilung laengst klein sind - reine
// Log-Auflaehung ohne zusaetzlichen Diagnosewert. Fix: pro Zeile auf 200 Zeichen
// gekuerzt, mit Hinweis auf die tatsaechliche Gesamtlaenge.

function buildDebugMappingReplica(array $pending, array $rows): string
{
    $debugMapping = [];
    $batchPosition = 0;
    foreach ($pending as $rowIndex => $text) {
        $preview = mb_strlen($text, 'UTF-8') > 200
            ? mb_substr($text, 0, 200, 'UTF-8') . '... (gekürzt, ' . mb_strlen($text, 'UTF-8') . ' Zeichen gesamt)'
            : $text;
        $debugMapping[] = sprintf('[%d] ObjectID=%s: "%s"', $batchPosition, $rows[$rowIndex]['ObjectID'] ?? '?', $preview);
        $batchPosition++;
    }

    return implode("\n", $debugMapping);
}

// Test 1: DER GEMELDETE BUG - ein sehr langer Rohtext (z.B. ein Wetter-Widget
// mit <style>-Block) darf keine Mapping-Zeile von zehntausenden Zeichen mehr
// erzeugen.
$hugeText = str_repeat('Text-Inhalt eines grossen Widgets. ', 2000); // ~72.000 Zeichen
$pending = [0 => $hugeText];
$rows = [0 => ['ObjectID' => 46091]];
$result = buildDebugMappingReplica($pending, $rows);
assert(strlen($result) < 500, 'DER BUG: die Mapping-Zeile fuer eine einzelne Zeile darf nicht mehr zehntausende Zeichen lang sein (war ' . strlen($result) . ' Zeichen)');
assert(strpos($result, 'gekürzt') !== false, 'eine gekuerzte Zeile muss klar als gekuerzt erkennbar sein');
assert(strpos($result, (string) mb_strlen($hugeText, 'UTF-8')) !== false, 'die tatsaechliche Gesamtlaenge muss trotz Kuerzung sichtbar bleiben');
echo "Test 1 (ein sehr langer Rohtext erzeugt nur noch eine kurze, klar als gekürzt markierte Mapping-Zeile mit sichtbarer Gesamtlänge) OK\n";

// Test 2: die ObjectID-Zuordnung (der eigentliche Diagnosezweck dieser Debug-
// Zeile) bleibt auch bei Kuerzung vollstaendig erhalten.
assert(strpos($result, 'ObjectID=46091') !== false, 'die ObjectID-Zuordnung muss trotz Kuerzung des Textinhalts erhalten bleiben');
echo "Test 2 (die ObjectID-Zuordnung bleibt trotz Kürzung vollständig erhalten - der eigentliche Diagnosezweck ist nicht verloren) OK\n";

// Test 3: keine Regression - ein normal kurzer Text bleibt komplett unveraendert.
$shortPending = [0 => 'Leicht bewölkt', 1 => 'Echo Info'];
$shortRows = [0 => ['ObjectID' => 111], 1 => ['ObjectID' => 222]];
$shortResult = buildDebugMappingReplica($shortPending, $shortRows);
assert($shortResult === "[0] ObjectID=111: \"Leicht bewölkt\"\n[1] ObjectID=222: \"Echo Info\"", 'keine Regression: kurze Texte müssen weiterhin exakt unverändert und vollständig geloggt werden');
echo "Test 3 (kurze Texte bleiben exakt unverändert, keine Regression) OK\n";

// Test 4: Symmetrie-Check - die reale module.php muss die Kuerzung tatsaechlich
// in der GoogleTranslate_Mapping-Erzeugung verdrahtet haben.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$mappingCallPos = strpos($moduleSource, "SendDebug('GoogleTranslate_Mapping'");
$funcBodyStart = strrpos(substr($moduleSource, 0, $mappingCallPos), '$debugMapping = [];');
$funcBody = substr($moduleSource, $funcBodyStart, $mappingCallPos - $funcBodyStart);
assert(strpos($funcBody, 'gekürzt') !== false, 'die reale GoogleTranslate_Mapping-Erzeugung muss lange Zeilen tatsächlich kürzen');
assert(strpos($funcBody, '> 200') !== false, 'die 200-Zeichen-Grenze muss tatsächlich in der realen Funktion stehen');
echo "Test 4 (die Kürzung ist tatsächlich in der realen GoogleTranslate_Mapping-Erzeugung verdrahtet) OK\n";

echo "\nAll tests passed.\n";
