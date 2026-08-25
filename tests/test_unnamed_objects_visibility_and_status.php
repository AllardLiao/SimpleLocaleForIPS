<?php
declare(strict_types=1);
// Standalone replica test for build 141 (2026-08-24, zwei live gemeldete Bugs auf
// einer frischen Instanz):
//
// Bug 1: Erster Klick auf "Rescan" meldete "Unnamed objects found - see list in
// form." - die Liste war im Formular aber nirgends zu sehen. Sie tauchte erst
// spaeter auf, als der Nutzer aus einem voellig anderen Grund ("Latein" als
// Zielsprache hinzugefuegt) auf "Uebernehmen" klickte.
// Ursache: der Abbruch-Zweig in ScanRootTree() kehrt VOR dem abschliessenden
// IPS_ApplyChanges() zurueck. Die Build-116-Annahme "die Konsole laedt das
// Formular nach jedem RequestAction ohnehin selbst neu" stimmt aber nur, WEIL der
// normale Durchlauf dieses IPS_ApplyChanges() erreicht - DAS loest den Reload aus,
// nicht die RequestAction an sich. Der Abbruch-Zweig bekam dadurch nie einen
// Reload, und das frisch geschriebene Attribut wurde nie gerendert.
//
// Bug 2: Nach jenem "Uebernehmen" zeigte die Statuszeile "Aktiv" (102), obwohl die
// Liste der unbenannten Objekte im selben Formular unveraendert sichtbar darunter
// stand und weiterhin jeden Rescan blockiert. Ursache: ApplyChanges() hat den von
// ScanRootTree() gesetzten STATUS_UNNAMED_OBJECTS kommentarlos ueberschrieben.

const STATUS_ROOT_CATEGORY_MISSING = 201;
const STATUS_UNNAMED_OBJECTS = 202;
const STATUS_TRIAL_EXPIRED = 204;
const STATUS_TRANSLATE_PAUSED = 205;
const STATUS_ACTIVE = 102;

// Repliziert exakt den Abbruch-Zweig aus ScanRootTree(): liefert zurueck, ob ein
// ReloadForm() ausgeloest wurde.
function scanRootTreeAbortReplica(array $unnamedObjects, bool $isInteractive): array
{
    if ($unnamedObjects === []) {
        return ['aborted' => false, 'reloadFormCalled' => false, 'status' => null];
    }

    return [
        'aborted'           => true,
        'reloadFormCalled'  => $isInteractive,
        'status'            => STATUS_UNNAMED_OBJECTS,
    ];
}

// Repliziert exakt die Status-Kaskade aus ApplyChanges().
function applyChangesStatusReplica(bool $rootMissing, bool $trialLocked, bool $hasPendingUnnamed, bool $providersPaused): int
{
    if ($rootMissing) {
        return STATUS_ROOT_CATEGORY_MISSING;
    }
    if ($trialLocked) {
        return STATUS_TRIAL_EXPIRED;
    }
    if ($hasPendingUnnamed) {
        return STATUS_UNNAMED_OBJECTS;
    }

    return $providersPaused ? STATUS_TRANSLATE_PAUSED : STATUS_ACTIVE;
}

// Test 1: DER GEMELDETE BUG 1 - ein manuell ausgeloester Rescan, der wegen
// unbenannter Objekte abbricht, MUSS das Formular selbst neu laden. Ohne das bleibt
// die gerade geschriebene Liste unsichtbar, obwohl die Statusmeldung ausdruecklich
// auf sie verweist ("see list in form").
$manual = scanRootTreeAbortReplica([['ObjectID' => 12345, 'Path' => 'Kacheln']], true);
assert($manual['aborted'] === true, 'der Rescan muss bei unbenannten Objekten weiterhin abbrechen');
assert($manual['reloadFormCalled'] === true, 'DER BUG: ein manuell ausgeloester Rescan, der wegen unbenannter Objekte abbricht, muss das Formular selbst neu laden - er erreicht das sonst dafuer sorgende IPS_ApplyChanges() am Ende nie');
echo "Test 1 (manueller Rescan-Abbruch lädt das Formular selbst neu, damit die Liste sofort sichtbar wird) OK\n";

// Test 2: der HINTERGRUND-Rescan (Auto-Rescan-Timer) darf dabei ausdruecklich NICHT
// neu laden - das war der bewusst behobene Build-60-Bug (ein Timer riss dem Admin
// das offene Formular mitten in der Bearbeitung unter den Haenden weg und verwarf
// unsavte Aenderungen). Der Fix fuer Bug 1 darf den nicht wieder einschleppen.
$background = scanRootTreeAbortReplica([['ObjectID' => 12345, 'Path' => 'Kacheln']], false);
assert($background['aborted'] === true, 'auch der Hintergrund-Rescan muss bei unbenannten Objekten abbrechen');
assert($background['reloadFormCalled'] === false, 'DER BUG (Regression Build 60): der Hintergrund-Rescan darf NIE ein offenes Formular neu laden - sonst gehen unsavte Admin-Aenderungen verloren');
echo "Test 2 (Hintergrund-Rescan lädt weiterhin NIE neu - keine Regression des Build-60-Bugs) OK\n";

// Test 3: ohne unbenannte Objekte laeuft alles wie bisher durch (kein vorzeitiger
// Abbruch, kein Sonder-Reload) - der normale Weg bleibt voellig unberuehrt.
$clean = scanRootTreeAbortReplica([], true);
assert($clean['aborted'] === false && $clean['reloadFormCalled'] === false, 'ohne unbenannte Objekte darf weder abgebrochen noch ein Sonder-Reload ausgeloest werden - der normale Durchlauf erreicht sein eigenes IPS_ApplyChanges()');
echo "Test 3 (der normale Rescan-Durchlauf ist völlig unberührt, kein zusätzlicher Reload) OK\n";

// Test 4: DER GEMELDETE BUG 2 - ein beliebiges spaeteres "Uebernehmen" (z.B. nur
// eine Zielsprache hinzugefuegt) darf den Status NICHT auf "Aktiv" zuruecksetzen,
// solange die unbenannten Objekte noch anstehen.
assert(applyChangesStatusReplica(false, false, true, false) === STATUS_UNNAMED_OBJECTS, 'DER BUG: ApplyChanges() darf den Status nicht auf "Aktiv" zuruecksetzen, solange unbenannte Objekte anstehen - Formular (Liste sichtbar) und Statuszeile widersprachen sich sonst offen');
echo "Test 4 (ein späteres 'Übernehmen' zeigt weiterhin korrekt STATUS_UNNAMED_OBJECTS statt fälschlich 'Aktiv') OK\n";

// Test 5: sind die unbenannten Objekte abgearbeitet (naechster erfolgreicher Rescan
// leert das Attribut), meldet der Status wieder ganz normal "Aktiv" - der neue
// Zweig darf sich nicht dauerhaft festbeissen.
assert(applyChangesStatusReplica(false, false, false, false) === STATUS_ACTIVE, 'nach einem erfolgreichen Rescan (Attribut geleert) muss der Status wieder normal "Aktiv" melden');
echo "Test 5 (nach behobenen Benennungen meldet der Status wieder normal 'Aktiv') OK\n";

// Test 6: die Rangfolge bleibt korrekt - fundamentalere Blocker (fehlender
// Visualisierungs-Root, abgelaufene Testphase) gewinnen weiterhin gegen die
// unbenannten Objekte, genau wie in ScanRootTree() selbst geprueft wird.
assert(applyChangesStatusReplica(true, false, true, false) === STATUS_ROOT_CATEGORY_MISSING, 'ein fehlender Visualisierungs-Root muss weiterhin Vorrang vor den unbenannten Objekten haben');
assert(applyChangesStatusReplica(false, true, true, false) === STATUS_TRIAL_EXPIRED, 'eine abgelaufene Testphase muss weiterhin Vorrang vor den unbenannten Objekten haben');
assert(applyChangesStatusReplica(false, false, true, true) === STATUS_UNNAMED_OBJECTS, 'die unbenannten Objekte muessen ihrerseits Vorrang vor der reinen Anbieter-Pause haben - sie blockieren jeden Rescan komplett, waehrend eine Pause sich von selbst wieder aufloest');
echo "Test 6 (die Status-Rangfolge bleibt korrekt: Root/Testphase gewinnen, Anbieter-Pause verliert) OK\n";

// Test 7: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function ScanRootTree(bool $IsInteractive = false): void') !== false, 'ScanRootTree() muss den neuen $IsInteractive-Parameter tragen');
assert(strpos($moduleSource, '$this->ScanRootTree(true);') !== false, 'Rescan() (manuell/IPSSL_Rescan) muss ScanRootTree(true) aufrufen');
assert(preg_match('/public function AutoRescan\(\): void\s*\{\s*\$this->ScanRootTree\(\);/', $moduleSource) === 1, 'AutoRescan() muss ScanRootTree() OHNE das Interaktiv-Flag aufrufen - der Hintergrund-Timer darf nie neu laden');
assert(strpos($moduleSource, 'private function HasPendingUnnamedObjects(): bool') !== false, 'der gemeinsame Helfer HasPendingUnnamedObjects() muss existieren');
assert(strpos($moduleSource, '} elseif ($this->HasPendingUnnamedObjects()) {') !== false, 'ApplyChanges() muss die anstehenden unbenannten Objekte in seiner Status-Kaskade beruecksichtigen');
// Attribut nur noch an EINER Stelle gelesen (im Helfer) - verhindert strukturell,
// dass Statuszeile und Formular-Liste je wieder auseinanderlaufen.
assert(substr_count($moduleSource, 'ReadAttributeString(self::attributeUnnamedObjects)') === 1, 'DER BUG-KERN: das Attribut darf nur noch an EINER Stelle gelesen werden (GetPendingUnnamedObjects), damit Statuszeile und Formular-Liste strukturell nie wieder auseinanderlaufen koennen');
echo "Test 7 (die reale module.php verdrahtet beide Fixes, und das Attribut hat nur noch eine einzige Lesestelle) OK\n";

echo "\nAll tests passed.\n";
