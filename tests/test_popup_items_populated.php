<?php
declare(strict_types=1);
// Standalone replica test for build 155 (2026-08-26, live gemeldet):
//
// SYMPTOM: Nach einem Klick auf "Aufraeumen" erscheint das Ergebnis-Popup mit
// der Anzahl entfernter Zeilen. Kurz darauf laedt die Konsole das Formular neu
// und das Popup geht erneut auf - diesmal OHNE Zahl.
//
// URSACHE: Die Inhalte eines PopupAlert stecken nicht in $element['items'],
// sondern eine Ebene tiefer in $element['popup']['items'].
// PopulateFormElements() stieg nur in 'items' ab und erreichte
// CleanupResultCountLabel deshalb nie. Das Popup selbst ist ein Element
// oberster Ebene und wurde korrekt sichtbar gesetzt - daher "Popup ja, Zahl
// nein".
//
// Warum das erste Popup die Zahl trotzdem zeigte: es wird per UpdateFormField()
// eingeblendet, und das adressiert ein Feld ueber seinen Namen, unabhaengig von
// der Verschachtelung. Erst der Neuaufbau des Formulars durch
// GetConfigurationForm() lief durch die lueckenhafte Rekursion.

// Repliziert PopulateFormElements() - nur die Rekursion und die beiden
// Aufraeumen-Faelle.
function populate(array &$elements, int $cleanupCount, bool $mitPopupZweig): void
{
    foreach ($elements as &$element) {
        if (isset($element['items']) && is_array($element['items'])) {
            populate($element['items'], $cleanupCount, $mitPopupZweig);
        }
        if ($mitPopupZweig && isset($element['popup']['items']) && is_array($element['popup']['items'])) {
            populate($element['popup']['items'], $cleanupCount, $mitPopupZweig);
        }

        switch ($element['name'] ?? '') {
            case 'CleanupResultPopup':
                $element['visible'] = $cleanupCount >= 0;
                break;
            case 'CleanupResultCountLabel':
                $element['caption'] = $cleanupCount >= 0 ? (string) $cleanupCount : '';
                break;
        }
    }
}

function finde(array $elements, string $name): ?array
{
    foreach ($elements as $e) {
        if (($e['name'] ?? '') === $name) { return $e; }
        foreach ([$e['items'] ?? [], $e['popup']['items'] ?? []] as $kinder) {
            if (is_array($kinder) && ($t = finde($kinder, $name)) !== null) { return $t; }
        }
    }

    return null;
}

$vorlage = [[
    'name'    => 'CleanupResultPopup',
    'type'    => 'PopupAlert',
    'visible' => false,
    'popup'   => ['items' => [[
        'type'  => 'RowLayout',
        'items' => [['type' => 'Label', 'name' => 'CleanupResultCountLabel', 'caption' => '']],
    ]]],
]];

// Test 1: DER GEMELDETE FALL - ohne den Popup-Zweig ist das Popup sichtbar,
// die Zahl darin aber leer.
$form = $vorlage;
populate($form, 7, false);
assert(finde($form, 'CleanupResultPopup')['visible'] === true, 'das Popup wurde schon vorher korrekt sichtbar gesetzt');
assert(finde($form, 'CleanupResultCountLabel')['caption'] === '', 'DER BUG: ohne den Popup-Zweig bleibt die Zahl leer - genau das wurde gemeldet');
echo "Test 1 (der gemeldete Fall wird durch die lückenhafte Rekursion reproduziert) OK\n";

// Test 2: DER FIX - mit dem Popup-Zweig steht die Zahl drin.
$form = $vorlage;
populate($form, 7, true);
assert(finde($form, 'CleanupResultCountLabel')['caption'] === '7', 'DER FIX: die Zahl muss auch im neu aufgebauten Formular stehen');
echo "Test 2 (mit dem Popup-Zweig trägt das Popup die Zahl) OK\n";

// Test 3: ohne vorangegangenes "Aufraeumen" bleibt das Popup zu und leer -
// sonst ginge es bei jedem Öffnen des Formulars auf.
$form = $vorlage;
populate($form, -1, true);
assert(finde($form, 'CleanupResultPopup')['visible'] === false, 'ohne vorangegangenes Aufraeumen darf das Popup nicht aufgehen');
assert(finde($form, 'CleanupResultCountLabel')['caption'] === '', 'und die Zahl bleibt leer');
echo "Test 3 (ohne vorangegangenes Aufräumen bleibt das Popup zu) OK\n";

// Test 4: "0 entfernt" ist ein gueltiges Ergebnis und muss als "0" erscheinen,
// nicht als leeres Feld - sonst sieht es aus wie der gemeldete Fehler.
$form = $vorlage;
populate($form, 0, true);
assert(finde($form, 'CleanupResultPopup')['visible'] === true, 'auch ein Ergebnis von 0 muss gemeldet werden');
assert(finde($form, 'CleanupResultCountLabel')['caption'] === '0', 'DIE FALLE: 0 muss als "0" erscheinen, nicht als leeres Feld');
echo "Test 4 (ein Ergebnis von 0 erscheint als \"0\", nicht als Leerfeld) OK\n";

// Test 5: die Rekursion muss beliebig tief greifen - die Zahl steckt real in
// einem RowLayout INNERHALB des Popups, nicht direkt darunter.
$tief = [[
    'name'  => 'CleanupResultPopup',
    'popup' => ['items' => [['items' => [['items' => [
        ['name' => 'CleanupResultCountLabel', 'caption' => ''],
    ]]]]]],
]];
populate($tief, 3, true);
assert(finde($tief, 'CleanupResultCountLabel')['caption'] === '3', 'die Rekursion muss auch mehrfach verschachtelte Popup-Inhalte erreichen');
echo "Test 5 (auch tief verschachtelte Popup-Inhalte werden erreicht) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php und form.json.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fnStart = strpos($moduleSource, 'private function PopulateFormElements(');
assert($fnStart !== false, 'PopulateFormElements() muss existieren');
$fnBody = substr($moduleSource, $fnStart, 2500);
assert(strpos($fnBody, "isset(\$element['popup']['items'])") !== false, 'DER FIX: die Rekursion muss auch in popup.items absteigen');
assert(strpos($fnBody, "isset(\$element['items'])") !== false, 'der bestehende items-Zweig muss erhalten bleiben');

// Und der Beweis, dass das Feld real dort liegt - sonst waere der Fix wirkungslos.
$form = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);
$gefunden = false;
$suche = function ($node, bool $inPopup) use (&$suche, &$gefunden): void {
    if (is_array($node)) {
        if ($inPopup && ($node['name'] ?? '') === 'CleanupResultCountLabel') { $gefunden = true; }
        foreach ($node as $key => $wert) { $suche($wert, $inPopup || $key === 'popup'); }
    }
};
$suche($form, false);
assert($gefunden, 'CleanupResultCountLabel muss real unterhalb eines popup-Knotens liegen - sonst zielt der Fix ins Leere');
echo "Test 6 (die reale Umsetzung steigt in popup.items ab und das Feld liegt real dort) OK\n";

echo "\nAll tests passed.\n";
