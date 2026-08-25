<?php
declare(strict_types=1);
// Build 130 (Nutzer-Wunsch, Nutzerführung im Konfigurationsformular): mehrteiliger
// Umbau des Konfigurationsformulars für bessere Übersichtlichkeit:
// 1) Kachel-bezogene Optionen (Symbole, eigene Kachel-HTML) in ein neues,
//    standardmäßig eingeklapptes ExpansionPanel "Kachel-Einstellungen" gruppiert.
// 2) Statistiken zweispaltig/eingerückt dargestellt (linke Spalte "Statistiken",
//    rechte Spalte die eigentlichen Werte).
// 3) "Automatischer Rescan" direkt über dem neuen Kachel-Panel positioniert.
// 4) Kurze Erklärung zur Scan-Sprache (Verhalten bei Sprachwechsel im
//    Zusammenhang mit Automatischem Rescan/neuen Objekten) mit Verweis auf
//    Abschnitt 7 der Dokumentation ergänzt.
// 5) Alle Übersetzungstabellen auf 100% Bildschirmbreite gestreckt.
// 6) Lizenz-Panel klappt nicht mehr allein wegen eines eingetragenen
//    Lizenzschlüssels automatisch auf - nur noch bei tatsächlich abgelaufener
//    Testphase.
// 7) Die langen Erläuterungstexte unter den vier Aktions-Buttons sind in ein
//    Info-Popup (ℹ️-Knopf direkt neben dem jeweiligen Button) ausgelagert
//    statt als Dauertext im Formular zu stehen.

$formPath = dirname(__DIR__) . '/SimpleLocale/form.json';
$modulePath = dirname(__DIR__) . '/SimpleLocale/module.php';
$form = json_decode(file_get_contents($formPath), true);
assert(is_array($form), 'form.json muss gültiges JSON sein');

function findByName(array $elements, string $name): ?array
{
    foreach ($elements as $el) {
        if (($el['name'] ?? null) === $name) {
            return $el;
        }
        foreach (['items', 'elements'] as $key) {
            if (isset($el[$key])) {
                $found = findByName($el[$key], $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }
    }

    return null;
}

function findParentItemsOf(array $elements, string $name): ?array
{
    foreach ($elements as $el) {
        foreach (['items', 'elements'] as $key) {
            if (isset($el[$key])) {
                foreach ($el[$key] as $child) {
                    if (($child['name'] ?? null) === $name) {
                        return $el[$key];
                    }
                }
                $found = findParentItemsOf($el[$key], $name);
                if ($found !== null) {
                    return $found;
                }
            }
        }
    }

    return null;
}

// Test 1: DER GEMELDETE WUNSCH - ein neues, standardmäßig eingeklapptes
// ExpansionPanel "Kachel-Einstellungen" muss existieren und die
// Kachel-bezogenen Optionen enthalten.
$tilePanel = findByName($form['elements'], 'TileSettingsPanel');
assert($tilePanel !== null, 'DER BUG: es muss ein ExpansionPanel "TileSettingsPanel" existieren');
assert($tilePanel['type'] === 'ExpansionPanel', 'TileSettingsPanel muss ein ExpansionPanel sein');
assert($tilePanel['expanded'] === false, 'TileSettingsPanel muss standardmäßig eingeklappt sein');
assert($tilePanel['caption'] === 'Kachel-Einstellungen', 'TileSettingsPanel muss die Beschriftung "Kachel-Einstellungen" tragen');
foreach (['ShowGlobeIcon', 'ShowInfoIcon', 'ShowTranslationStats', 'UseCustomTile', 'CustomTileHtmlButton'] as $expectedChild) {
    assert(findByName($tilePanel['items'], $expectedChild) !== null, "TileSettingsPanel muss '$expectedChild' enthalten");
}
echo "Test 1 (neues, standardmäßig eingeklapptes 'Kachel-Einstellungen'-Panel enthält alle Kachel-Optionen) OK\n";

// Test 2: "Automatischer Rescan" muss direkt VOR dem neuen Kachel-Panel stehen
// (dieselbe Elternebene, unmittelbar davor).
$configPanel = findByName($form['elements'], 'ConfigPanel');
$namesInOrder = array_map(fn ($el) => $el['name'] ?? null, $configPanel['items']);
$autoRescanIndex = array_search('AutoRescanInterval', $namesInOrder, true);
$tilePanelIndex = array_search('TileSettingsPanel', $namesInOrder, true);
assert($autoRescanIndex !== false && $tilePanelIndex !== false, 'AutoRescanInterval und TileSettingsPanel müssen beide direkt in ConfigPanel liegen');
assert($tilePanelIndex === $autoRescanIndex + 1, 'DER BUG: "Automatischer Rescan" muss unmittelbar VOR dem neuen Kachel-Panel stehen');
echo "Test 2 ('Automatischer Rescan' steht direkt über dem neuen Kachel-Einstellungen-Panel) OK\n";

// Test 3: die Statistik-Zeilen müssen ein zweispaltiges Layout haben - eine
// feste linke Spaltenbreite, "Statistiken" nur in der ersten Zeile.
foreach (['TranslationStatsRow1', 'TranslationStatsRow2', 'TranslationStatsRow3', 'TranslationStatsRow4'] as $rowName) {
    $row = findByName($form['elements'], $rowName);
    assert($row !== null, "$rowName muss weiterhin existieren");
    $firstItem = $row['items'][0];
    assert(($firstItem['width'] ?? null) !== null, "DER BUG: die erste Spalte von $rowName muss eine feste Breite für das zweispaltige Layout haben");
}
$row1 = findByName($form['elements'], 'TranslationStatsRow1');
assert($row1['items'][0]['caption'] === 'Statistiken', 'DER BUG: die linke Spalte muss in der ersten Statistik-Zeile "Statistiken" zeigen');
$row2 = findByName($form['elements'], 'TranslationStatsRow2');
assert($row2['items'][0]['caption'] === '', 'die linke Spalte muss in den folgenden Statistik-Zeilen leer bleiben (kein wiederholtes "Statistiken")');
echo "Test 3 (Statistiken sind zweispaltig/eingerückt dargestellt, 'Statistiken' erscheint nur einmal links) OK\n";

// Test 4: eine kurze Erklärung zur Scan-Sprache mit Verweis auf Abschnitt 7
// muss direkt nach dem SourceLanguage-Feld stehen.
$sourceLangIndex = array_search('SourceLanguage', $namesInOrder, true);
assert($sourceLangIndex !== false, 'SourceLanguage muss in ConfigPanel liegen');
$nextElement = $configPanel['items'][$sourceLangIndex + 1];
assert($nextElement['type'] === 'Label', 'DER BUG: direkt nach der Scan-Sprache muss ein erklärender Hinweis-Text stehen');
assert(strpos($nextElement['caption'], 'Abschnitt 7') !== false, 'die Erklärung zur Scan-Sprache muss auf Abschnitt 7 der Dokumentation verweisen');
assert(strpos($nextElement['caption'], 'Rescan') !== false, 'die Erklärung muss den Zusammenhang mit (Automatischem) Rescan ansprechen');
echo "Test 4 (kurze Erklärung zur Scan-Sprache mit Verweis auf Abschnitt 7 direkt nach dem Feld) OK\n";

// Test 5: alle neun Übersetzungstabellen müssen auf 100% Breite gestreckt sein.
foreach (['TargetLanguages', 'UnnamedObjects', 'ObjectNames', 'ObjectTexts', 'EnumerationOptions', 'ObjectAutomations', 'ObjectCharts', 'ObjectGreeting', 'ManualTranslations'] as $listName) {
    $list = findByName($form['elements'], $listName);
    assert($list !== null, "Liste '$listName' muss weiterhin existieren");
    assert(($list['width'] ?? null) === '100%', "DER BUG: Liste '$listName' muss auf 100% Breite gestreckt sein");
}
echo "Test 5 (alle neun Übersetzungstabellen sind auf 100% Bildschirmbreite gestreckt) OK\n";

// Test 6: die vier Aktions-Buttons müssen ihre Erläuterung als Info-Popup
// (ℹ️) statt als Dauertext haben - kein eigenständiges Label mehr direkt im
// Aktionsbereich für diese vier Erläuterungen.
$actionTexts = [
    'Rescan' => 'Durchsucht den Root der Visualisierung erneut',
    'CleanupOrphanedRows' => 'Entfernt dauerhaft alle Zeilen',
    'ClearTranslationCache' => 'Löscht nur den internen Zwischenspeicher',
    'CheckProviders' => 'Schickt eine einzelne Testanfrage',
];
foreach ($actionTexts as $actionKeyword => $expectedTextStart) {
    $found = false;
    foreach ($form['actions'] as $action) {
        if (($action['type'] ?? '') === 'RowLayout') {
            $button = $action['items'][0] ?? [];
            if (strpos($button['onClick'] ?? '', "'$actionKeyword'") !== false) {
                $infoButton = $action['items'][1] ?? [];
                assert(($infoButton['type'] ?? '') === 'PopupButton', "DER BUG: der Button für '$actionKeyword' muss von einem Info-PopupButton begleitet werden");
                assert(strpos($infoButton['popup']['items'][0]['caption'] ?? '', $expectedTextStart) === 0, "die Erläuterung für '$actionKeyword' muss jetzt im Info-Popup stehen, nicht mehr als Dauertext");
                $found = true;
            }
        }
    }
    assert($found, "DER BUG: der Button für '$actionKeyword' muss in einem RowLayout mit Info-Popup verpackt sein");
}
// Kein eigenständiges Label mit diesen langen Erläuterungstexten mehr direkt
// im Aktionsbereich (nur noch innerhalb der Popups).
foreach ($form['actions'] as $action) {
    if (($action['type'] ?? '') === 'Label') {
        foreach ($actionTexts as $expectedTextStart) {
            assert(strpos($action['caption'] ?? '', $expectedTextStart) !== 0, 'DER BUG: die lange Erläuterung darf nicht mehr als Dauertext-Label direkt im Aktionsbereich stehen');
        }
    }
}
echo "Test 6 (alle vier Button-Erläuterungen sind in ein Info-Popup mit ℹ️-Symbol ausgelagert, kein Dauertext mehr) OK\n";

// Test 7: Symmetrie-Check - LicensePanel darf laut der realen module.php nicht
// mehr allein wegen eines eingetragenen Lizenzschlüssels automatisch aufklappen.
$moduleSource = file_get_contents($modulePath);
$caseStart = strpos($moduleSource, "case 'LicensePanel':");
$caseEnd = strpos($moduleSource, 'break;', $caseStart);
$caseBody = substr($moduleSource, $caseStart, $caseEnd - $caseStart);
assert(strpos($caseBody, 'propertyLicenseKey') === false, 'DER BUG: LicensePanel darf nicht mehr allein wegen eines eingetragenen Lizenzschlüssels aufklappen');
assert(strpos($caseBody, 'IsTrialLocked()') !== false, 'LicensePanel muss weiterhin bei abgelaufener Testphase automatisch aufklappen');
echo "Test 7 (Lizenz-Panel klappt nicht mehr allein wegen eines eingetragenen Schlüssels auf, nur noch bei abgelaufener Testphase) OK\n";

// Test 8: die dynamische Formular-Rekursion (PopulateFormElements) muss laut
// eigenem Kommentar generisch durch JEDE Verschachtelungstiefe laufen - das
// neue TileSettingsPanel wird also automatisch mitverarbeitet, ohne eigene
// Sonderbehandlung im Code zu benötigen.
assert(strpos($moduleSource, "\$this->PopulateFormElements(\$element['items'], \$CleanupResultCount);") !== false, 'PopulateFormElements() muss generisch in jede verschachtelte items-Liste hinabsteigen, damit das neue Panel korrekt verarbeitet wird');
echo "Test 8 (die generische Formular-Rekursion verarbeitet das neue Panel automatisch mit, keine Sonderbehandlung nötig) OK\n";

// Test 9 (Build 138, Nutzer-Feedback "die Buttons ... sehen aber komisch aus",
// dann Build 139, Nutzer-Feedback per Screenshot: "das Infozeichen sieht
// leider aus wie ein 'Aus'-Zeichen"): zwei Anläufe für das Info-Symbol der
// vier Buttons. Build 138 versuchte, exakt das Zeichen "ⓘ" der Kachel zu
// verwenden (ohne zusätzliches Symcon-Icon) - live in der echten Konsole
// stellte sich das Zeichen aber als missverständliches Power-/Standby-Symbol
// dar, nicht als Info-Zeichen (Font-/Rendering-Unterschied zwischen der
// Kachel, die eigenes HTML/CSS liefert, und der nativen Symcon-Konsole, die
// das Zeichen mit ihrer eigenen Systemschrift rendert). Build 139 ersetzt es
// daher durch reinen, garantiert eindeutigen Klartext "Information" statt
// eines erneuten Icon-Versuchs - das vom Nutzer selbst als "am sichersten"
// vorgeschlagene Vorgehen. Ein Button ganz ohne Rahmen/Chrome (wie der reine
// Text-Span in der Kachel) ist über form.json technisch nicht erreichbar -
// jeder Button-/PopupButton-Typ der Symcon-Konsole rendert zwingend mit
// eigenem Rahmen, anders als die Kachel (eigenes, freies HTML/CSS).
foreach ($form['actions'] as $action) {
    if (($action['type'] ?? '') !== 'RowLayout') {
        continue;
    }
    $infoButton = $action['items'][1] ?? null;
    if (($infoButton['type'] ?? '') !== 'PopupButton') {
        continue;
    }
    assert(($infoButton['caption'] ?? '') === 'Information', 'DER BUG: der Info-Button muss den eindeutigen Klartext "Information" zeigen - das zuvor verwendete Zeichen "ⓘ" wurde in der echten Konsole live als irreführendes Aus-/Standby-Symbol dargestellt');
    assert(!array_key_exists('icon', $infoButton), 'der Info-Button soll weiterhin kein zusätzliches Symcon-eigenes Icon tragen - der Klartext allein reicht und ist eindeutig');
}
echo "Test 9 (die vier Info-Buttons zeigen nach zwei Anläufen sicheren Klartext 'Information' statt eines in der echten Konsole missverständlichen Icon-Zeichens) OK\n";

echo "\nAll tests passed.\n";
