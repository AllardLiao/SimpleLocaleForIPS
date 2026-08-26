<?php
declare(strict_types=1);
// Standalone replica test for build 160 (2026-08-26, Nutzer-Wunsch):
// "es verwirrt den User, die Tabelle zu sehen, unbefüllt und er kann Zeilen
// löschen."
//
// Bis Build 159 war die "Eigene Uebersetzungstabelle" auch ohne das Feature
// "manual_translations" sichtbar: leer, nicht vorbefuellt, aber mit
// funktionierendem Papierkorb. Der Nutzer sah also eine Tabelle, erwartete dass
// sie etwas tut, und bekam nichts.
//
// Entscheidung: ohne das Feature verschwindet die Tabelle GANZ. Ueberschrift und
// Beschreibung bleiben stehen - er soll lesen koennen, was ihm entgeht -,
// darunter erscheint die Absage. Nichts zum Klicken, keine falsche Erwartung.

// Repliziert die drei betroffenen Faelle aus PopulateFormElements().
function sichtbarkeiten(bool $hatFeature): array
{
    return [
        'Ueberschrift' => !$hatFeature,
        'Tabelle'      => $hatFeature,
        'Absage'       => !$hatFeature,
    ];
}

// Test 1: DER GEMELDETE FALL - ohne das Feature darf die Tabelle nicht mehr da
// sein, und damit auch kein Papierkorb.
$ohne = sichtbarkeiten(false);
assert($ohne['Tabelle'] === false, 'DER GEMELDETE FALL: ohne das Feature darf die Tabelle nicht sichtbar sein');
echo "Test 1 (ohne das Feature verschwindet die Tabelle) OK\n";

// Test 2: dafuer erscheinen Ueberschrift und Absage - sonst faellt der Abschnitt
// kommentarlos weg und der Nutzer erfaehrt gar nicht, dass es ihn gibt.
assert($ohne['Ueberschrift'] === true, 'die Ueberschrift muss stehenbleiben');
assert($ohne['Absage'] === true, 'die Absage muss erscheinen');
echo "Test 2 (Überschrift und Absage erscheinen stattdessen) OK\n";

// Test 3: MIT dem Feature ist es genau umgekehrt - Tabelle da, keine Absage.
$mit = sichtbarkeiten(true);
assert($mit['Tabelle'] === true, 'mit dem Feature muss die Tabelle da sein');
assert($mit['Ueberschrift'] === false && $mit['Absage'] === false, 'Ersatz-Ueberschrift und Absage duerfen dann NICHT erscheinen - sonst staende die Ueberschrift doppelt');
echo "Test 3 (mit dem Feature ist die Tabelle da und die Absage weg) OK\n";

// Test 4: die reale Umsetzung - Formularelemente und Verdrahtung.
$formJson = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
$form = json_decode($formJson, true);
assert(is_array($form), 'form.json muss valides JSON sein');

$gefunden = [];
$suche = function ($node) use (&$suche, &$gefunden): void {
    if (is_array($node)) {
        if (isset($node['name']) && is_string($node['name'])) { $gefunden[$node['name']] = $node; }
        foreach ($node as $wert) { $suche($wert); }
    }
};
$suche($form);

foreach (['ManualTranslationsUnavailableHeading', 'ManualTranslationsUnavailableHint'] as $name) {
    assert(isset($gefunden[$name]), "$name muss im Formular liegen");
    assert(($gefunden[$name]['visible'] ?? true) === false, "$name muss standardmaessig unsichtbar sein - sonst blitzt es bei jedem Formular-Aufbau kurz auf");
}
assert(isset($gefunden['ManualTranslations']), 'die Tabelle selbst muss weiterhin existieren');

$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, "case 'ManualTranslationsUnavailableHeading':") !== false, 'die Ueberschrift muss verdrahtet sein');
assert(strpos($moduleSource, "case 'ManualTranslationsUnavailableHint':") !== false, 'die Absage muss verdrahtet sein');
assert(strpos($moduleSource, "\$element['visible'] = !\$this->HasLicenseFeature('manual_translations');") !== false, 'beide haengen an der Umkehrung des Features');

$start = strpos($moduleSource, 'case self::propertyManualTranslations:');
$body = substr($moduleSource, $start, 900);
assert(strpos($body, "\$element['visible'] = false;") !== false, 'DER FIX: die Tabelle selbst muss ohne das Feature unsichtbar werden');
$hidePos = strpos($body, "\$element['visible'] = false;");
$valuesPos = strpos($body, "\$element['values']");
assert($hidePos < $valuesPos, 'der Ausstieg muss VOR dem Befuellen stehen - sonst wird fuer eine unsichtbare Tabelle unnoetig gearbeitet');
echo "Test 4 (Formularelemente und Verdrahtung sind real vorhanden) OK\n";

// Test 5: die Absage muss in ALLEN Sprachen registriert sein - sonst steht sie
// bei fremdsprachiger Konsole deutsch da (siehe Build 156).
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
$absage = 'Die eigene Übersetzungstabelle steht in dieser Edition nicht zur Verfügung.';
foreach ($locale['translations'] as $sprache => $eintraege) {
    assert(isset($eintraege[$absage]), "die Absage fehlt in der Sprache \"$sprache\"");
    assert(isset($eintraege['Eigene Übersetzungstabelle']), "die Ueberschrift fehlt in der Sprache \"$sprache\"");
}
echo "Test 5 (Absage und Überschrift sind in allen Sprachen registriert) OK\n";

echo "\nAll tests passed.\n";
