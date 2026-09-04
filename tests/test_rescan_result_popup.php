<?php
declare(strict_types=1);
// Standalone replica test for build 208 (2026-09-03, Nutzer-Frage): "Wenn der
// Rescan nichts zu tun hat - bekommt der Nutzer das ueberhaupt mit?"
//
// Nein. Der Rescan zeigte ausschliesslich Fortschrittstexte, die am Ende wieder
// geleert werden. Ein Lauf ohne Funde war damit von einem gar nicht
// ausgefuehrten nicht zu unterscheiden: es blitzt kurz etwas auf, danach steht
// das Formular da wie zuvor. "Aufraeumen" hatte seit jeher ein Ergebnis-Popup,
// der Rescan nicht.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$form = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);
$locale = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);

// Repliziert das Einmal-Muster aus GetConfigurationForm().
function abholen(int $gespeichert): array
{
    return [$gespeichert, $gespeichert >= 0 ? -1 : $gespeichert];
}

// Test 1: DER FALL - auch NULL Funde muessen gemeldet werden. Genau das ist die
// Frage, die der Nutzer hatte.
[$zeige, $rest] = abholen(0);
assert($zeige === 0, 'DER FALL: null Funde wird angezeigt, nicht verschwiegen');
assert($rest === -1, 'und danach verbraucht, damit es nicht bei jedem Oeffnen wiederkommt');
echo "Test 1 (auch null Funde werden gemeldet) OK\n";

// Test 2: ohne gelaufenen Rescan bleibt das Popup weg.
[$zeige, $rest] = abholen(-1);
assert($zeige === -1 && $rest === -1, 'ohne Lauf kein Popup');
echo "Test 2 (ohne Lauf kein Popup) OK\n";

// Test 3: die Zaehlung erfasst nur die GESCANNTEN Listen. Glossar und eigene
// Oberflaechentexte wachsen durch Nachbefuellung - sie als "neu gefunden" zu
// melden waere schlicht falsch.
$zaehler = substr($module, (int) strpos($module, 'private function CountScannedRows'), 900);
foreach (['propertyObjectNames', 'propertyObjectTexts', 'propertyEnumerationOptions',
          'propertyObjectAutomations', 'propertyObjectCharts', 'propertyObjectGreeting'] as $p) {
    assert(strpos($zaehler, $p) !== false, "$p muss mitgezaehlt werden");
}
assert(strpos($zaehler, 'propertyGlossary') === false, 'das Glossar gehoert NICHT in die Bilanz');
assert(strpos($zaehler, 'propertyOwnUiTexts') === false, 'die eigenen Oberflaechentexte ebenso wenig');
echo "Test 3 (gezählt wird nur, was aus dem Baum kommt) OK\n";

// Test 4: nur der MANUELLE Rescan hinterlaesst eine Bilanz. Ein Hintergrund-Lauf
// wuerde das Popup sonst irgendwann aufpoppen lassen, ohne dass jemand etwas
// angestossen hat - derselbe Fehler, den Build 155 beim Aufraeumen behoben hat.
$scan = substr($module, (int) strpos($module, 'private function ScanRootTree'), 30000);
$posSchreiben = strpos($scan, 'WriteAttributeInteger(');
$posBedingung = strpos($scan, 'if ($IsInteractive) {');
assert($posBedingung !== false && $posBedingung < $posSchreiben,
    'die Bilanz wird nur im interaktiven Lauf gemerkt');
echo "Test 4 (nur der manuelle Rescan meldet) OK\n";

// Test 5: das Popup existiert im Formular und ist anfangs unsichtbar.
$gefunden = null;
$suche = function (array $n) use (&$suche, &$gefunden): void {
    foreach ($n as $v) {
        if (is_array($v)) {
            if (($v['name'] ?? '') === 'RescanResultPopup') {
                $gefunden = $v;
            }
            $suche($v);
        }
    }
};
$suche($form);
assert($gefunden !== null, 'das Popup steht in form.json');
assert(($gefunden['type'] ?? '') === 'PopupAlert', 'als PopupAlert, wie beim Aufraeumen');
assert(($gefunden['visible'] ?? true) === false, 'und ist anfangs unsichtbar');
echo "Test 5 (das Popup ist angelegt und anfangs unsichtbar) OK\n";

// Test 6: seine Texte sind in allen vier Sprachen hinterlegt - sonst stuende ein
// deutscher Nutzer vor einer englischen Meldung.
foreach (['Result of "Rescan"', 'Newly found:'] as $text) {
    foreach (['de', 'es', 'it', 'fr'] as $sprache) {
        assert(isset($locale['translations'][$sprache][$text]),
            "\"$text\" fehlt in \"$sprache\"");
    }
}
echo "Test 6 (die Texte sind übersetzt) OK\n";

echo "\nAlle Tests OK (Build 208: der Rescan meldet sein Ergebnis).\n";
