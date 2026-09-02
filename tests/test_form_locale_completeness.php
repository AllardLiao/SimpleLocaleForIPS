<?php
declare(strict_types=1);
// Build 131 (Nutzer-Report, live gefunden): die Übersetzung für "Statistiken"
// (neu eingeführt in Build 130 für das zweispaltige Statistik-Layout) fehlte
// komplett in locale.json - in einer nicht-deutschen Konsolensprache blieb
// das Wort daher unübersetzt stehen. Bisher gab es keinen generellen Test,
// der ALLE statischen Formular-Beschriftungen gegen locale.json abgleicht -
// nur einzelne, punktuelle Tests fuer bestimmte Bereiche. Dieser Test deckt
// das systematisch ab: jede nicht-leere, buchstabenhaltige "caption" in
// form.json (auch innerhalb von Popups) muss in ALLEN vier Konsolensprachen
// eine exakte Übersetzung haben.

$formPath = dirname(__DIR__) . '/SimpleLocale/form.json';
$localePath = dirname(__DIR__) . '/SimpleLocale/locale.json';
$form = json_decode(file_get_contents($formPath), true);
$locale = json_decode(file_get_contents($localePath), true);

function collectCaptions(array $elements, array &$out): void
{
    foreach ($elements as $el) {
        if (isset($el['caption']) && is_string($el['caption']) && $el['caption'] !== '') {
            // Nur Beschriftungen mit mindestens einem Buchstaben - reine
            // Symbole/Emoji (z.B. "ℹ️") brauchen keine Übersetzung.
            if (preg_match('/\p{L}/u', $el['caption'])) {
                $out[$el['caption']] = true;
            }
        }
        foreach (['items', 'elements'] as $key) {
            if (isset($el[$key]) && is_array($el[$key])) {
                collectCaptions($el[$key], $out);
            }
        }
        if (isset($el['popup']) && is_array($el['popup'])) {
            if (isset($el['popup']['caption']) && is_string($el['popup']['caption']) && preg_match('/\p{L}/u', $el['popup']['caption'])) {
                $out[$el['popup']['caption']] = true;
            }
            if (isset($el['popup']['items']) && is_array($el['popup']['items'])) {
                collectCaptions($el['popup']['items'], $out);
            }
        }
        if (isset($el['columns']) && is_array($el['columns'])) {
            foreach ($el['columns'] as $col) {
                if (isset($col['caption']) && is_string($col['caption']) && preg_match('/\p{L}/u', $col['caption'])) {
                    $out[$col['caption']] = true;
                }
            }
        }
    }
}

$captions = [];
collectCaptions($form['elements'], $captions);
collectCaptions($form['actions'], $captions);

// Legitime Ausnahmen: reine Marken-/Produktnamen (werden nie übersetzt) und
// das wiederverwendete Info-Symbol (kein Text, keine Übersetzung nötig).
foreach (['Google Cloud Translate', 'DeepL', 'ℹ️', 'Simple Locale', 'https://www.synergetix.de/simplelocale/license.php'] as $exempt) {
    unset($captions[$exempt]);
}

assert(count($captions) > 50, 'Sanity-Check: es sollten deutlich mehr als 50 verschiedene Beschriftungen gefunden werden (Extraktion funktioniert sonst evtl. nicht richtig)');

// Test 1: DER GEMELDETE BUG - "Statistiken" muss jetzt in allen vier Sprachen
// eine Übersetzung haben.
foreach (['de', 'es', 'it', 'fr'] as $lang) {
    assert(isset($locale['translations'][$lang]['Statistics']), "DER BUG: 'Statistics' fehlt in der Sprache '$lang'");
}
echo "Test 1 ('Statistics' hat jetzt in allen vier Sprachen eine Übersetzung) OK\n";

// Test 2: DER ALLGEMEINE FALL - JEDE buchstabenhaltige Beschriftung im
// gesamten Formular (inkl. Popups, Spalten-Überschriften) muss in allen vier
// Sprachen eine exakte Übersetzung haben. Das haette den "Statistiken"-Bug
// von Anfang an gefangen.
$missing = [];
foreach (array_keys($captions) as $caption) {
    foreach (['de', 'es', 'it', 'fr'] as $lang) {
        if (!isset($locale['translations'][$lang][$caption])) {
            $missing[] = "$lang: " . mb_substr($caption, 0, 60, 'UTF-8');
        }
    }
}
assert($missing === [], "DER BUG: folgende Formular-Beschriftungen haben keine vollständige Übersetzung:\n" . implode("\n", array_slice($missing, 0, 20)));
echo "Test 2 (JEDE buchstabenhaltige Formular-Beschriftung hat in allen vier Sprachen eine exakte Übersetzung - genereller Schutz vor diesem Bug) OK\n";

// Test 3: die neue Erklärung beim "Bevorzugter Anbieter"-Dropdown zu den
// DeepL-Sprachcode-Implikationen muss existieren und auf Abschnitt 7 verweisen.
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

$providerPanel = findByName($form['elements'], 'TranslationProviderPanel');
assert($providerPanel !== null, 'TranslationProviderPanel muss weiterhin existieren');
$preferredIndex = null;
foreach ($providerPanel['items'] as $index => $item) {
    if (($item['name'] ?? null) === 'PreferredPaidProvider') {
        $preferredIndex = $index;
        break;
    }
}
assert($preferredIndex !== null, 'PreferredPaidProvider muss in TranslationProviderPanel liegen');
$nextItem = $providerPanel['items'][$preferredIndex + 1];
assert($nextItem['type'] === 'Label', 'DER BUG: direkt nach PreferredPaidProvider muss eine Erklärung zu den Sprachcode-Implikationen stehen');
assert(strpos($nextItem['caption'], 'EN-GB') !== false, 'die Erklärung muss die konkreten DeepL-Regionscodes (z.B. EN-GB) nennen');
assert(strpos($nextItem['caption'], 'section 7') !== false, 'die Erklärung muss auf Abschnitt 7 der Dokumentation verweisen');
echo "Test 3 (Erklärung zu den DeepL-Sprachcode-Implikationen direkt nach 'Bevorzugter Anbieter') OK\n";

// Test 4 (Build 139, Nutzer-Feedback: das Info-Icon "ⓘ" sah in der Konsole
// aus wie ein Aus-/Standby-Symbol, nicht wie Information): alle vier
// Info-Popup-Buttons zeigen jetzt sicherheitshalber Klartext "Information"
// statt eines missverständlichen Icon-Zeichens, entsprechend schmal statt
// Icon-Breite (40px).
foreach ($form['actions'] as $action) {
    if (($action['type'] ?? '') === 'RowLayout') {
        $infoButton = $action['items'][1] ?? null;
        if ($infoButton !== null && ($infoButton['type'] ?? '') === 'PopupButton') {
            assert(($infoButton['caption'] ?? null) === 'Information', 'DER BUG: Info-Popup-Buttons müssen Klartext "Information" zeigen, kein Icon-Zeichen (das "ⓘ"-Zeichen wurde in der Konsole fälschlich als Aus-/Standby-Symbol dargestellt)');
            assert(($infoButton['width'] ?? null) === '110px', 'DER BUG: Info-Popup-Buttons müssen breit genug fuer den Klartext "Information" sein, nicht mehr nur Icon-Breite (40px)');
        }
    }
}
echo "Test 4 (alle Info-Popup-Buttons zeigen sicheren Klartext 'Information' statt eines missverständlichen Icon-Zeichens) OK\n";

echo "\nAll tests passed.\n";
