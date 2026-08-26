<?php
declare(strict_types=1);
// Standalone replica test for build 155 (2026-08-26, live gemeldet):
// "Bei Klick auf remove geht am Ende ein Popup auf. Nach kurzer Zeit lädt die
// Instanz neu und es geht direkt wieder das Popup auf."
//
// Das Ergebnis-Popup von "Aufraeumen" ging sichtbar ZWEIMAL auf, weil es zwei
// unabhaengige Wege gab:
//   1. Build 98: sofortiges Live-Einblenden per UpdateFormField() auf dem noch
//      offenen Formular.
//   2. Das Attribut attributeLastCleanupRemovedCount, das PopulateFormElements()
//      beim (von Symcons Konsole automatisch ausgeloesten) Neuaufbau liest.
//
// Weg 2 ist der verlaessliche - er ueberlebt den Reload, Weg 1 nicht. Deshalb
// entfaellt das Live-Einblenden ersatzlos.
//
// Loest test_cleanup_deferred_reload.php ab: der beschrieb den Build-98-Stand
// samt verzoegertem CleanupReloadTimer, den Build 116 laengst entfernt hatte,
// und prueft nur noch seine eigene Replik - gegen die reale module.php sagte er
// nichts mehr aus.

// Repliziert den Abschluss von CleanupOrphanedRows() NACH dem Fix.
function cleanupAbschlussReplica(int $removedCount, array &$calls): int
{
    $calls[] = ['op' => 'WriteAttributeInteger', 'value' => $removedCount];
    $calls[] = ['op' => 'SetButtonProgress', 'field' => 'CleanupProgressBar'];

    return $removedCount;   // kein UpdateFormField mehr
}

// Repliziert PopulateFormElements(): liest den Zaehler und verbraucht ihn.
function populateReplica(int &$attribut): array
{
    $count = $attribut;
    if ($count >= 0) { $attribut = -1; }

    return [
        'popupSichtbar' => $count >= 0,
        'zahl'          => $count >= 0 ? (string) $count : '',
    ];
}

// Test 1: DER GEMELDETE FALL - der Abschluss darf das Popup nicht mehr selbst
// einblenden, sonst geht es zweimal auf.
$calls = [];
$attribut = cleanupAbschlussReplica(3, $calls);
$livePush = array_filter($calls, static fn (array $c): bool => ($c['op'] ?? '') === 'UpdateFormField');
assert($livePush === [], 'DER GEMELDETE FALL: kein Live-Einblenden mehr - sonst geht das Popup zweimal auf');
echo "Test 1 (der Abschluss blendet das Popup nicht mehr selbst ein) OK\n";

// Test 2: gezeigt wird es trotzdem - beim automatischen Neuaufbau, mit Zahl.
$anzeige = populateReplica($attribut);
assert($anzeige['popupSichtbar'] === true, 'das Popup MUSS beim Neuaufbau erscheinen - sonst faellt die Rueckmeldung ganz weg');
assert($anzeige['zahl'] === '3', 'und es muss die Anzahl tragen');
echo "Test 2 (beim automatischen Neuaufbau erscheint es genau einmal, mit Zahl) OK\n";

// Test 3: EINMALIG - ein spaeteres, unabhaengiges Oeffnen des Formulars darf es
// nicht erneut zeigen.
$spaeter = populateReplica($attribut);
assert($spaeter['popupSichtbar'] === false, 'der Zaehler muss verbraucht sein - sonst geht das Popup bei jedem Formular-Oeffnen auf');
echo "Test 3 (ein späteres Öffnen des Formulars zeigt es nicht erneut) OK\n";

// Test 4: "0 entfernt" ist ein gueltiges Ergebnis und muss gemeldet werden -
// sonst bliebe der Klick ohne jede Rueckmeldung.
$calls = [];
$attribut0 = cleanupAbschlussReplica(0, $calls);
$anzeige0 = populateReplica($attribut0);
assert($anzeige0['popupSichtbar'] === true, 'auch 0 entfernte Zeilen muessen gemeldet werden');
assert($anzeige0['zahl'] === '0', 'und zwar als "0", nicht als Leerfeld');
echo "Test 4 (auch \"0 entfernt\" wird gemeldet) OK\n";

// Test 5: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fnStart = strpos($moduleSource, 'private function CleanupOrphanedRows');
assert($fnStart !== false, 'CleanupOrphanedRows() muss existieren');
// Rumpf sauber abgrenzen: ein festes Zeichenfenster liefe in die naechste
// Funktion hinein und pruefte dann deren Aufrufe mit.
$fnEnde = strpos($moduleSource, "\n    private function ", $fnStart + 10);
$fnBody = substr($moduleSource, $fnStart, $fnEnde - $fnStart);

assert(strpos($fnBody, "UpdateFormField('CleanupResultPopup'") === false,
    'DER FIX: das Popup darf nicht mehr live eingeblendet werden - das war die zweite, ueberfluessige Anzeige');
assert(strpos($fnBody, "UpdateFormField('CleanupResultCountLabel'") === false,
    'ebenso wenig die Zahl - sie kommt jetzt aus PopulateFormElements');
assert(strpos($fnBody, 'WriteAttributeInteger(self::attributeLastCleanupRemovedCount, $removedCount)') !== false,
    'der Zaehler MUSS weiter geschrieben werden - er ist jetzt der einzige Weg zur Anzeige');
// Bewusst auf den AUFRUF geprueft, nicht auf das Wort: der Rumpf erklaert in
// mehreren Kommentarzeilen, warum es hier kein ReloadForm() gibt.
assert(strpos($fnBody, '$this->ReloadForm()') === false,
    'Build 116: kein eigener ReloadForm()-Aufruf - Symcons Konsole laedt nach jedem RequestAction ohnehin neu');

// Die Gegenseite muss den Zaehler weiterhin lesen UND verbrauchen.
assert(strpos($moduleSource, 'ReadAttributeInteger(self::attributeLastCleanupRemovedCount)') !== false,
    'GetConfigurationForm() muss den Zaehler lesen');
assert(strpos($moduleSource, 'WriteAttributeInteger(self::attributeLastCleanupRemovedCount, -1)') !== false,
    'und ihn einmalig verbrauchen - sonst ginge das Popup bei jedem Formular-Oeffnen auf');
echo "Test 5 (die reale Umsetzung zeigt das Popup nur noch über den Neuaufbau) OK\n";

echo "\nAll tests passed.\n";
