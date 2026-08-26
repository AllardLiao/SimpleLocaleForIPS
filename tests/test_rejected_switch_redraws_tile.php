<?php
declare(strict_types=1);
// Standalone replica test for build 162 (2026-08-26, live gemeldet):
// "Das Feature 'einmal am Tag die Sprache ändern' setzt die Sprache im Tile
// nicht wieder zurück. (Alt de, dann auf en gewechselt, war nicht erlaubt,
// bleibt aber en drin stehen.)"
//
// RequestAction('Language') kennt DREI Ablehnungspfade. Zwei davon zeichneten
// die Kachel neu, damit die eingebaute Auswahl nicht auf der abgelehnten
// Sprache stehenbleibt - der Rate-Limit-Pfad war der einzige ohne diesen
// Aufruf. Der Gast sah dadurch "en", obwohl weiterhin "de" aktiv war.
//
// Reihenfolge ist wichtig: erst neu zeichnen, DANN die Meldung. Beides laeuft
// ueber UpdateVisualizationValue(); umgekehrt wuerde das Neuzeichnen die
// ALERT-Nutzlast wieder ueberschreiben.

// Repliziert die drei Ablehnungspfade und den Erfolgsfall.
// Rueckgabe: Liste der Aufrufe in ihrer Reihenfolge.
function requestActionReplica(string $fall): array
{
    $calls = [];
    if ($fall === 'unbekannt') {
        $calls[] = 'PushVisualizationUpdate';

        return $calls;
    }
    if ($fall === 'trial') {
        $calls[] = 'ResetToOriginalLanguageIfNeeded';
        $calls[] = 'PushVisualizationUpdate';
        $calls[] = 'PushTrialExpiredAlert';

        return $calls;
    }
    if ($fall === 'ratelimit') {
        $calls[] = 'PushVisualizationUpdate';
        $calls[] = 'PushLanguageSwitchLimitAlert';

        return $calls;
    }
    $calls[] = 'ApplyLanguage';

    return $calls;
}

// Test 1: DER GEMELDETE FALL - der Rate-Limit-Pfad muss die Kachel neu zeichnen.
$calls = requestActionReplica('ratelimit');
assert(in_array('PushVisualizationUpdate', $calls, true), 'DER GEMELDETE FALL: ohne Neuzeichnen bleibt die Auswahl auf der abgelehnten Sprache stehen');
echo "Test 1 (der Rate-Limit-Pfad zeichnet die Kachel neu) OK\n";

// Test 2: DIE REIHENFOLGE - erst zeichnen, dann melden. Andersherum wuerde das
// Neuzeichnen die ALERT-Nutzlast ueberschreiben und der Gast bekaeme gar keine
// Erklaerung, warum sein Wechsel nicht griff.
$zeichnen = array_search('PushVisualizationUpdate', $calls, true);
$melden = array_search('PushLanguageSwitchLimitAlert', $calls, true);
assert($zeichnen < $melden, 'DIE REIHENFOLGE: erst neu zeichnen, dann melden - sonst ueberschreibt das Neuzeichnen die Meldung');
echo "Test 2 (erst neu zeichnen, dann melden) OK\n";

// Test 3: KEIN Reset auf Original - das ist der bewusste Unterschied zum
// Testphasen-Fall. Die bisher aktive Sprache bleibt aktiv, nur der Wechsel
// wird verweigert.
assert(!in_array('ResetToOriginalLanguageIfNeeded', $calls, true), 'der Rate-Limit-Fall darf NICHT auf Original zuruecksetzen - nur der Wechsel wird verweigert');
echo "Test 3 (kein Reset auf Original - nur der Wechsel wird verweigert) OK\n";

// Test 4: alle drei Ablehnungspfade zeichnen neu - sonst faellt genau dieser
// Fehler beim naechsten Pfad wieder an.
foreach (['unbekannt', 'trial', 'ratelimit'] as $fall) {
    assert(in_array('PushVisualizationUpdate', requestActionReplica($fall), true), "Ablehnungspfad \"$fall\" muss die Kachel neu zeichnen");
}
echo "Test 4 (alle drei Ablehnungspfade zeichnen neu) OK\n";

// Test 5: der ERFOLGSFALL zeichnet NICHT selbst - ApplyLanguage() erledigt das.
// Ein zusaetzlicher Aufruf waere ein doppeltes Neuzeichnen.
assert(!in_array('PushVisualizationUpdate', requestActionReplica('erfolg'), true), 'im Erfolgsfall zeichnet ApplyLanguage() selbst - kein zweiter Aufruf');
echo "Test 5 (der Erfolgsfall zeichnet nicht zusätzlich) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'elseif ($this->IsLanguageSwitchRateLimited($language))');
assert($start !== false, 'der Rate-Limit-Zweig muss existieren');
$ende = strpos($moduleSource, '} else {', $start);
$body = substr($moduleSource, $start, $ende - $start);

assert(strpos($body, '$this->PushVisualizationUpdate();') !== false, 'DER FIX: der Rate-Limit-Zweig muss die Kachel neu zeichnen');
$zeichnenPos = strpos($body, '$this->PushVisualizationUpdate();');
$meldenPos = strpos($body, '$this->PushLanguageSwitchLimitAlert(');
assert($zeichnenPos < $meldenPos, 'das Neuzeichnen muss VOR der Meldung stehen');
assert(strpos($body, 'ResetToOriginalLanguageIfNeeded') === false, 'der bewusste Unterschied zum Trial-Fall muss erhalten bleiben: kein Reset auf Original');
echo "Test 6 (die reale Umsetzung zeichnet vor der Meldung und setzt nicht zurück) OK\n";

echo "\nAll tests passed.\n";
