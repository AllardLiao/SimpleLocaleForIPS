<?php
declare(strict_types=1);
// Standalone replica test for build 182 (2026-08-28): Rueckbau der Ausweichlogik
// in LogTranslateMessage().
//
// VORGESCHICHTE: Bis Build 181 wich die Funktion im MessageSink-Kontext auf die
// globale IPS_LogMessage() aus - wegen einer einmal live beobachteten Warnung
// "InstanceInterface is not available". Nachgestellt wurde das anschliessend in
// einem eigenen Minimalmodul in vier Konstellationen (schlichtes Loggen;
// IPS_SetProperty + IPS_ApplyChanges auf die eigene Instanz aus dem Sink heraus;
// Zurueckschreiben in die ueberwachte Variable, also verschachtelte Zustellung;
// zehn Sekunden Laufzeit) - einzeln und kombiniert durchweg fehlerfrei.
//
// Der Umweg kostete den Schweregrad: IPS_LogMessage() kennt keinen, die Meldung
// erschien als graues "Custom" statt als rotes "FEHLER". Deshalb der Rueckbau -
// aber ueber eine Konstante, damit er sich mit einem Wort rueckgaengig machen
// laesst.

// Repliziert LogTranslateMessage().
function logReplica(bool $viaGlobal, bool $imMessageSink, bool $istFehler): array
{
    if (!$viaGlobal || !$imMessageSink) {
        return ['weg' => 'LogMessage', 'schweregrad' => $istFehler ? 'KL_ERROR' : 'KL_WARNING'];
    }

    return ['weg' => 'IPS_LogMessage', 'schweregrad' => null];
}

// Test 1: DER RUECKBAU - im MessageSink laeuft es jetzt ueber die typisierte
// Methode, mit echtem Schweregrad.
$r = logReplica(false, true, true);
assert($r['weg'] === 'LogMessage', 'DER RUECKBAU: auch im MessageSink ueber die typisierte Methode');
assert($r['schweregrad'] === 'KL_ERROR', 'und damit mit echtem Schweregrad - das war der Grund');
echo "Test 1 (im MessageSink jetzt mit echtem Schweregrad) OK\n";

// Test 2: ausserhalb des MessageSink war es immer schon so - unveraendert.
assert(logReplica(false, false, false)['schweregrad'] === 'KL_WARNING', 'ausserhalb unveraendert');
echo "Test 2 (außerhalb des MessageSink unverändert) OK\n";

// Test 3: DIE WIEDERHERSTELLUNG - Konstante auf true, und der alte Weg ist
// vollstaendig zurueck. Genau ein Wort.
$r = logReplica(true, true, true);
assert($r['weg'] === 'IPS_LogMessage', 'mit gesetzter Konstante muss der alte Weg wieder greifen');
echo "Test 3 (die Konstante stellt den alten Weg vollständig wieder her) OK\n";

// Test 4: der Schalter wirkt NUR im MessageSink - ausserhalb aendert er nichts.
assert(logReplica(true, false, true)['weg'] === 'LogMessage', 'ausserhalb des Sink darf der Schalter nichts aendern');
echo "Test 4 (der Schalter wirkt nur im MessageSink) OK\n";

// Test 5: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private const LOG_VIA_GLOBAL_IN_MESSAGE_SINK = false;') !== false,
    'die Konstante muss existieren und stillgelegt sein');
assert(strpos($moduleSource, 'if (!self::LOG_VIA_GLOBAL_IN_MESSAGE_SINK || !$this->isInMessageSinkDispatch) {') !== false,
    'und die Weiche steuern');

// Die Ausweichlogik selbst muss ERHALTEN bleiben - sonst waere der Rueckbau
// nicht mit einem Wort umkehrbar, und genau das war die Vorgabe.
assert(strpos($moduleSource, "IPS_LogMessage(\n            'Simple Locale #' . \$this->InstanceID,") !== false,
    'DIE VORGABE: der alte Weg muss vollstaendig im Code bleiben, nur stillgelegt');
assert(strpos($moduleSource, '$this->isInMessageSinkDispatch = true;') !== false,
    'die Kontext-Buchfuehrung muss ebenfalls bleiben - ohne sie waere die Konstante wirkungslos');
echo "Test 5 (die Ausweichlogik bleibt vollständig erhalten, nur stillgelegt) OK\n";

echo "\nAll tests passed.\n";
