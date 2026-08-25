<?php
declare(strict_types=1);
// Build 114 (live per Debug-Log bestätigt, 2026-08-22): das "Aufräumen"-
// Ergebnis-Popup zeigte nach den vollen CLEANUP_RELOAD_DELAY_SECONDS (5s)
// zuverlässig einen LEEREN statt des tatsächlichen Zählers. Ursache: die
// Symcon-Konsole ruft GetConfigurationForm() nachweislich selbstständig ein
// weiteres Mal auf, kurz (~30ms) nach jedem RequestAction - das verbrauchte
// den (damals: "beim ersten Lesen sofort zurücksetzen") Zähler immer schon
// durch diesen automatischen Zwischenaufruf, bevor der eigentlich für die
// Anzeige vorgesehene, bewusst um 5 Sekunden verzögerte Reload
// (ProcessDeferredCleanupReload, Build 98) ihn zeigen konnte. Build 114
// verschob das Zurücksetzen deshalb dorthin, NACH dem verzögerten Reload.
//
// Build 116 (Nutzer-Wunsch, direkt im Anschluss, 2026-08-22): der ganze
// Build-98-Verzögerungsmechanismus wird ÜBERFLÜSSIG, sobald man denselben
// Fund (die Konsole lädt nach JEDEM RequestAction ohnehin automatisch neu)
// zu Ende denkt - der Grund für Build 98s Verzögerung (ein SOFORTIGES,
// vom Modul selbst ausgelöstes ReloadForm() ließe das gerade gezeigte Popup
// augenblicklich wieder verschwinden) betrifft nur einen ZUSÄTZLICHEN,
// eigenen Aufruf - die Konsole reißt das Popup mit IHREM EIGENEN
// automatischen Reload ohnehin genauso aus dem DOM, nur eben schon nach
// ~30ms statt nach 5s. Der einzige tatsächliche Effekt von Build 98s
// Verzögerung war ein SICHTBARES ZWEITES, überflüssiges Neuladen kurz nach
// dem ersten (live bestätigt, auch beim Rescan-Button trat dasselbe
// doppelte Neuladen auf). Fix: der komplette Verzögerungsmechanismus
// (ProcessDeferredCleanupReload/GetCleanupReloadTimerIdent/
// CLEANUP_RELOAD_DELAY_SECONDS, sowie ScanRootTree()s eigener, analoger
// ReloadForm()-Aufruf für den manuellen Rescan-Button) wurde komplett
// entfernt - GetConfigurationForm() liest den Zähler jetzt wieder (wie
// ursprünglich in Build 76) beim ERSTEN Lesen sofort zurück, denn jetzt
// gibt es wieder nur EINEN nachfolgenden Aufruf (den automatischen
// Konsolen-Reload), keinen konkurrierenden zweiten mehr.

function getConfigurationFormReadAndResetReplica(int &$attributeValue): int
{
    $value = $attributeValue;
    if ($value >= 0) {
        $attributeValue = -1;
    }

    return $value;
}

// Simuliert die jetzt (Build 116) einzige verbleibende Aufrufreihenfolge:
// CleanupOrphanedRows() schreibt den Zähler, danach genau EIN
// GetConfigurationForm()-Aufruf (Symcons automatischer Konsolen-Reload nach
// dem RequestAction - kein eigener, zusätzlicher Reload mehr).
$attribute = -1; // Ausgangszustand vor jedem Cleanup-Lauf
$attribute = 5;  // CleanupOrphanedRows() schreibt den frischen Zaehler

$shownCount = getConfigurationFormReadAndResetReplica($attribute);
assert($shownCount === 5, 'Der automatische Konsolen-Reload muss den korrekt geschriebenen Zaehler zeigen');
assert($attribute === -1, 'Nach diesem EINEN Aufruf muss der Zaehler sofort zurueckgesetzt sein - es gibt keinen zweiten, spaeteren Aufruf mehr, der ihn noch braeuchte');

// Ein spaeteres, unabhaengiges Oeffnen des Formulars (z.B. der Admin klickt
// irgendwann spaeter erneut auf "Konfigurieren") darf das Popup nicht wieder
// zeigen.
$shownCountLater = getConfigurationFormReadAndResetReplica($attribute);
assert($shownCountLater === -1, 'Ein spaeteres, unabhaengiges Oeffnen des Formulars darf den Zaehler nicht erneut zeigen');

echo "Test 1 (der Zaehler wird beim einzigen verbleibenden GetConfigurationForm()-Aufruf korrekt gezeigt und sofort zurueckgesetzt) OK\n";

// Test 2: Symmetrie-Check - die reale module.php muss den kompletten
// Verzoegerungsmechanismus aus Build 98/114 entfernt haben und
// GetConfigurationForm() muss wieder sofort zuruecksetzen.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

assert(strpos($moduleSource, 'public function ProcessDeferredCleanupReload()') === false, 'ProcessDeferredCleanupReload() muss komplett entfernt sein (Build 116)');
assert(strpos($moduleSource, 'private function GetCleanupReloadTimerIdent()') === false, 'GetCleanupReloadTimerIdent() muss komplett entfernt sein');
assert(strpos($constantsSource, 'CLEANUP_RELOAD_DELAY_SECONDS') === false, 'Die CLEANUP_RELOAD_DELAY_SECONDS-Konstante muss komplett entfernt sein');

$getConfigStart = strpos($moduleSource, 'public function GetConfigurationForm()');
$getConfigEnd = strpos($moduleSource, 'PopulateFormElements($form[\'elements\']', $getConfigStart);
$getConfigBody = substr($moduleSource, $getConfigStart, $getConfigEnd - $getConfigStart);
assert(strpos($getConfigBody, 'WriteAttributeInteger(self::attributeLastCleanupRemovedCount, -1);') !== false, 'GetConfigurationForm() muss den Cleanup-Zaehler wieder selbst (beim ersten Lesen) zuruecksetzen (Build 116 - es gibt keinen konkurrierenden zweiten Aufruf mehr, der das verhindern wuerde)');

echo "Test 2 (der komplette Verzoegerungsmechanismus ist entfernt, GetConfigurationForm() setzt den Zaehler wieder selbst zurueck) OK\n";

echo "\nAll tests passed.\n";
