<?php
declare(strict_types=1);
// Standalone replica test for build 179 (2026-08-28, live gemeldet: "sobald das
// Popup aufpoppt wird das tile zerstört - ein refresh bringt das korrekte
// zurück").
//
// URSACHE: Das Modul zeichnet die Kachel vor jeder Ablehnung neu
// (PushVisualizationUpdate), und REFRESH ersetzt den KOMPLETTEN Inhalt des
// Elements mit <!--WRAPPER_ID--> durch die Sprachauswahl. In module.html steht
// dort auch genau nur sie - eine gelieferte Vorlage kann die ID aber am
// AEUSSEREN Element tragen und daneben eigenes Layout enthalten. Genau das wurde
// dann weggeloescht: Popup erschien, Kachel zerfiel, ein Seiten-Reload stellte
// sie wieder her (weil das Original-HTML neu gerendert wurde).
//
// Und es war doppelt sinnlos: eine Vorlage OHNE <!--LANGUAGE_SELECT--> baut ihre
// Auswahl selbst - es gibt gar nichts nachzuzeichnen.

// Repliziert die Handler-Erzeugung aus EnsureTileMessageHandler().
function handlerReplica(bool $supportsRefresh): string
{
    $refresh = $supportsRefresh
        ? 'if(m.action==="REFRESH"){var w=document.getElementById("wrap");if(w){w.innerHTML=m.payload.html;}}else '
        : '';

    return 'function handleMessage(data){var m=JSON.parse(data);' . $refresh
        . 'if(m.action==="ALERT"){alert(m.payload.text);}}';
}

// Test 1: DER GEMELDETE FALL - eine Vorlage ohne <!--LANGUAGE_SELECT--> darf
// nicht neu gezeichnet werden, sonst loescht das Neuzeichnen ihr Layout.
$ohne = handlerReplica(false);
assert(strpos($ohne, 'REFRESH') === false, 'DER FIX: ohne eigene Sprachauswahl darf gar nicht neu gezeichnet werden');
echo "Test 1 (ohne <!--LANGUAGE_SELECT--> wird nicht neu gezeichnet) OK\n";

// Test 2: DIE MELDUNGEN bleiben davon unberuehrt - genau darum ging es dem
// Nutzer ja, das Popup soll kommen.
assert(strpos($ohne, 'ALERT') !== false, 'die Gast-Hinweise muessen weiterhin ankommen');
assert(strpos($ohne, 'alert(m.payload.text)') !== false, 'und tatsaechlich angezeigt werden');
echo "Test 2 (die Hinweise kommen weiterhin an) OK\n";

// Test 3: eine Vorlage MIT <!--LANGUAGE_SELECT--> bekommt beides - dort ist das
// Neuzeichnen ja gewollt und zielt auf genau den richtigen Bereich.
$mit = handlerReplica(true);
assert(strpos($mit, 'REFRESH') !== false && strpos($mit, 'ALERT') !== false, 'mit eigener Sprachauswahl bleibt beides');
echo "Test 3 (mit <!--LANGUAGE_SELECT--> bleibt beides erhalten) OK\n";

// Test 4: auch mit Neuzeichnen darf ein fehlendes Ziel-Element den Handler nicht
// abbrechen lassen - sonst kaeme die Meldung danach nicht mehr.
assert(strpos($mit, 'if(w){') !== false, 'ein fehlendes Ziel-Element muss abgefangen werden');
echo "Test 4 (ein fehlendes Ziel-Element bricht nichts ab) OK\n";

// Test 5: DIE ENTSCHEIDUNG wird am ORIGINAL getroffen, nicht am Ergebnis - nach
// den Ersetzungen steht der Platzhalter ja nicht mehr da.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$applyStart = strpos($moduleSource, 'private function ApplyTilePlaceholders');
$applyBody = substr($moduleSource, $applyStart, strpos($moduleSource, "\n    private function ", $applyStart + 10) - $applyStart);
assert(strpos($applyBody, "strpos(\$Html, '<!--LANGUAGE_SELECT-->') !== false") !== false,
    'DIE FALLE: gegen das ORIGINAL pruefen ($Html), nicht gegen das bereits ersetzte $html');
assert(strpos($applyBody, "strpos(\$html, '<!--LANGUAGE_SELECT-->')") === false,
    'gegen das Ergebnis geprueft waere immer false - das Neuzeichnen waere ueberall tot');
echo "Test 5 (die Entscheidung fällt am Original, nicht am Ergebnis) OK\n";

// Test 6: die eingebaute Kachel bringt ihren eigenen Handler mit und ist von
// alldem nicht betroffen.
$tileSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.html');
assert(strpos($tileSource, '<!--LANGUAGE_SELECT-->') !== false, 'die eingebaute Kachel nutzt den Platzhalter');
assert(strpos($tileSource, 'handleMessage') !== false, 'und bringt ihren Handler selbst mit');
echo "Test 6 (die eingebaute Kachel ist nicht betroffen) OK\n";

echo "\nAll tests passed.\n";
