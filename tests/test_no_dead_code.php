<?php
declare(strict_types=1);
// Standalone check for build 168 (2026-08-26, Nutzer-Auftrag: "Bitte checke mal,
// ob es weiteren toten Code noch gibt.").
//
// Entfernt wurden drei Attribute, die zwar registriert und beschrieben, aber
// NIE gelesen wurden - jeder Schreibvorgang darauf war reine Arbeit ohne Zweck:
//   attributeEffectiveRootCategoryID  (als "informativ gespiegelt" gedacht)
//   attributeLastDailyLicenseCheckAt  (als "informativ/Debug" gedacht)
//   attributeLicenseInfo              (als Anzeige-Cache gedacht, nie gelesen)
// Dazu vier verwaiste locale.json-Schluessel (x4 Sprachen).
//
// Dieser Test ist zugleich ein DAUERWAECHTER: er faellt aus, sobald ein neues
// Attribut nur geschrieben und nie gelesen wird, oder ein locale.json-Eintrag
// nirgends mehr vorkommt.

$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
$formSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);

// Test 1: die drei Attribute sind restlos weg.
foreach (['attributeEffectiveRootCategoryID', 'attributeLastDailyLicenseCheckAt', 'attributeLicenseInfo'] as $name) {
    assert(strpos($constantsSource, $name) === false, "$name muss aus den Konstanten entfernt sein");
    assert(strpos($moduleSource, $name) === false, "$name darf im Modul nicht mehr vorkommen");
}
echo "Test 1 (die drei nur-geschriebenen Attribute sind entfernt) OK\n";

// Test 2: DER DAUERWAECHTER - jedes deklarierte Attribut muss irgendwo GELESEN
// werden. Ein Attribut, das nur geschrieben wird, ist Arbeit ohne Wirkung.
$code = implode("\n", array_filter(
    explode("\n", $moduleSource . "\n" . $constantsSource),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));
preg_match_all('/private const (attribute\w+)\s*=/', $constantsSource, $treffer);
$nurGeschrieben = [];
foreach (array_unique($treffer[1]) as $name) {
    $gelesen = preg_match('/Read\w*\(\s*self::' . preg_quote($name, '/') . '\b/', $code);
    if ($gelesen === 0) {
        $nurGeschrieben[] = $name;
    }
}
assert($nurGeschrieben === [], "diese Attribute werden nur geschrieben, nie gelesen:\n  " . implode("\n  ", $nurGeschrieben));
echo "Test 2 (kein Attribut wird nur geschrieben) OK\n";

// Test 3: keine private Methode ohne Aufrufer.
preg_match_all('/private function (\w+)\s*\(/', $moduleSource, $treffer);
$ohneAufrufer = [];
foreach (array_unique($treffer[1]) as $name) {
    $aufrufe = preg_match_all('/(?:\$this->|self::)' . preg_quote($name, '/') . '\s*\(/', $code);
    $aufrufe += preg_match_all("/'" . preg_quote($name, '/') . "'/", $code);   // Callable-Schreibweise
    if ($aufrufe === 0) {
        $ohneAufrufer[] = $name;
    }
}
assert($ohneAufrufer === [], "diese privaten Methoden werden nie aufgerufen:\n  " . implode("\n  ", $ohneAufrufer));
echo "Test 3 (keine private Methode ohne Aufrufer) OK\n";

// Test 4: keine private Konstante ohne Verwendung.
preg_match_all('/private const (\w+)\s*=/', $moduleSource . "\n" . $constantsSource, $treffer);
$ungenutzt = [];
foreach (array_unique($treffer[1]) as $name) {
    if (preg_match('/self::' . preg_quote($name, '/') . '\b/', $code) === 0) {
        $ungenutzt[] = $name;
    }
}
assert($ungenutzt === [], "diese Konstanten werden nirgends verwendet:\n  " . implode("\n  ", $ungenutzt));
echo "Test 4 (keine ungenutzte Konstante) OK\n";

// Test 5: keine verwaisten locale.json-Eintraege. Beruecksichtigt beide
// Escaping-Formen - ein Text mit Anfuehrungszeichen steht in form.json escaped -
// und die zur Laufzeit zusammengesetzten "Automatisch (…)"-Kombinationen, deren
// Wortlaut per Konstruktion nirgends im Quelltext steht (siehe Build 156).
// Gegen die DEKODIERTEN Formularwerte pruefen statt gegen den Dateitext: sonst
// scheitert jeder Text mit Anfuehrungszeichen am JSON-Escaping und wird
// faelschlich als verwaist gemeldet.
$formValues = [];
$sammle = function ($node) use (&$sammle, &$formValues): void {
    if (is_string($node)) { $formValues[] = $node; }
    elseif (is_array($node)) { foreach ($node as $v) { $sammle($v); } }
};
$sammle(json_decode($formSource, true));
$heuhaufen = $moduleSource . "\n" . implode("\n", $formValues);
$laufzeit = [];
preg_match_all("/private const (?:TILE_ICON_CATALOG|TILE_TEMPLATE_CATALOG) = \[(.*?)\n    \];/s", $moduleSource, $kataloge);
foreach ($kataloge[1] as $block) {
    preg_match_all("/'label'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $block, $labels);
    foreach ($labels[1] as $label) {
        $laufzeit[] = 'Automatic (' . stripslashes($label) . ')';
    }
}

$verwaist = [];
foreach (array_keys($locale['translations']['de'] ?? []) as $schluessel) {
    if (in_array($schluessel, $laufzeit, true)) {
        continue;
    }
    // Vier Schreibweisen: wortwoertlich, PHP-escaped (einfaches
    // Anfuehrungszeichen), mit maskiertem Zeilenumbruch, und beides zusammen.
    $varianten = [
        $schluessel,
        str_replace("'", "\\'", $schluessel),
        str_replace("\n", '\\n', $schluessel),
        str_replace(["'", "\n"], ["\\'", '\\n'], $schluessel),
    ];
    $gefunden = false;
    foreach ($varianten as $variante) {
        if (strpos($heuhaufen, $variante) !== false) { $gefunden = true; break; }
    }
    if ($gefunden) {
        continue;
    }
    $verwaist[] = $schluessel;
}
assert($verwaist === [], "diese locale.json-Eintraege kommen nirgends mehr vor:\n  " . implode("\n  ", array_map(static fn ($s) => substr($s, 0, 70), $verwaist)));
echo "Test 5 (keine verwaisten Übersetzungen) OK\n";

// Test 6: alle Sprachen tragen denselben Schluesselsatz - eine Sprache, die
// einen Schluessel nicht kennt, faellt sonst still auf Deutsch zurueck.
$basis = array_keys($locale['translations']['de'] ?? []);
foreach ($locale['translations'] as $sprache => $eintraege) {
    $fehlt = array_diff($basis, array_keys($eintraege));
    $zuviel = array_diff(array_keys($eintraege), $basis);
    assert($fehlt === [], "in \"$sprache\" fehlen " . count($fehlt) . " Schluessel");
    assert($zuviel === [], "in \"$sprache\" stehen " . count($zuviel) . " ueberzaehlige Schluessel");
}
echo "Test 6 (alle Sprachen tragen denselben Schlüsselsatz) OK\n";

echo "\nAll tests passed.\n";
