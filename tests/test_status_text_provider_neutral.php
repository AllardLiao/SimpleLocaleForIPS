<?php
declare(strict_types=1);
// Standalone replica test for build 163 (2026-08-26, live gemeldet, per
// Screenshot belegt): "Status wird mit 'Google Translate error' angezeigt. Die
// Google API ist aber gar nicht gepflegt."
//
// Status 203 (STATUS_TRANSLATE_ERROR) wird gesetzt, sobald ALLE Anbieter der
// Kette gescheitert sind - der Text stammte aber noch aus der Zeit, als Google
// der einzige Anbieter war. Eine Instanz, die ausschliesslich den kostenfreien
// Anbieter nutzt, bekam damit die Aufforderung, einen API-Key zu pruefen, den
// es gar nicht gibt.

$formJson = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
$form = json_decode($formJson, true);
assert(is_array($form['status'] ?? null), 'form.json muss einen status-Block haben');

$status203 = null;
foreach ($form['status'] as $eintrag) {
    if (($eintrag['code'] ?? 0) === 203) { $status203 = $eintrag; }
}
assert($status203 !== null, 'Status 203 muss definiert sein');

// Test 1: DER GEMELDETE FALL - der Text darf keinen einzelnen Anbieter mehr
// nennen. Er gilt fuer JEDEN Ausfall der gesamten Kette.
assert(stripos($status203['caption'], 'Google') === false, 'DER GEMELDETE FALL: der Status darf Google nicht mehr namentlich nennen - er gilt fuer die ganze Anbieterkette');
assert(stripos($status203['caption'], 'DeepL') === false, 'ebenso wenig DeepL');
assert(stripos($status203['caption'], 'API-Key') === false, 'und er darf nicht mehr zum Pruefen eines API-Keys auffordern - ohne bezahlten Anbieter gibt es keinen');
echo "Test 1 (der Statustext nennt keinen einzelnen Anbieter mehr) OK\n";

// Test 2: er muss trotzdem etwas AUSSAGEN - ein blosses "Fehler" waere
// wertlos. Der Nutzer soll wissen, dass es an der Erreichbarkeit lag und wo er
// nachsieht.
assert(mb_strlen($status203['caption']) > 30, 'der Text muss aussagekraeftig bleiben, nicht nur "Fehler"');
assert(mb_stripos($status203['caption'], 'Anbieter') !== false, 'er soll benennen, dass es an den Anbietern lag');
echo "Test 2 (der Text bleibt aussagekräftig) OK\n";

// Test 3: der Status wird erst gesetzt, wenn die GANZE Kette gescheitert ist -
// sonst waere der neue Text genauso falsch wie der alte.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'Fehlerstatus (STATUS_TRANSLATE_ERROR) wird nur gesetzt, wenn ALLE Anbieter') !== false,
    'die Bedingung "alle Anbieter gescheitert" muss dokumentiert bleiben - sie traegt den neuen Text');
echo "Test 3 (der Status gilt weiterhin nur für den Totalausfall der Kette) OK\n";

// Test 4: in ALLEN Sprachen registriert - ein Statustext laeuft ueber
// locale.json wie jede andere Caption (siehe Build 156).
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
foreach ($locale['translations'] as $sprache => $eintraege) {
    assert(isset($eintraege[$status203['caption']]), "der neue Statustext fehlt in der Sprache \"$sprache\"");
    assert(stripos($eintraege[$status203['caption']], 'Google') === false, "die Uebersetzung in \"$sprache\" nennt weiterhin Google");
}
echo "Test 4 (der neue Text ist in allen Sprachen registriert und nennt nirgends Google) OK\n";

// Test 5: der ALTE Text darf nirgends mehr stehen - sonst bliebe eine
// verwaiste Zeile in locale.json und der Verdacht, sie werde noch benutzt.
$altText = 'Google Translate Fehler - bitte API-Key prüfen';
assert(strpos($formJson, $altText) === false, 'der alte Text darf nicht mehr im Formular stehen');
foreach ($locale['translations'] as $sprache => $eintraege) {
    assert(!isset($eintraege[$altText]), "der alte Text steht noch in der Sprache \"$sprache\"");
}
echo "Test 5 (der alte Text ist restlos entfernt) OK\n";

echo "\nAll tests passed.\n";
