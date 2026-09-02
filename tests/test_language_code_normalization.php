<?php
declare(strict_types=1);
// Standalone replica test for build 186 (2026-09-02, live aufgefallen): Anbieter
// liefern denselben Sprachcode in unterschiedlicher Schreibweise.
//
// DER BEFUND: Google liefert klein und regionslos ("de", "en"), DeepL gross und
// fuer Englisch/Portugiesisch nur mit Region ("DE", "EN-GB", "PT-BR"). Roh
// uebernommen hatte das zwei Folgen:
//
//   1. GetKnownLanguages() mischt die eingebaute Liste mit der geholten. Bei
//      DeepL standen dadurch 20 der 30 eingebauten Sprachen DOPPELT in der
//      Auswahl - einmal "de", einmal "DE".
//   2. Wer erst nur DeepL eintraegt und spaeter einen Google-Key ergaenzt,
//      bekommt eine Liste in der anderen Schreibweise. Die bereits gewaehlten
//      Zielsprachen kommen darin nicht mehr vor und muessen neu gewaehlt werden -
//      ohne dass der Nutzer irgendetwas umgestellt haette.
//
// Intern gilt deshalb genau EINE Schreibweise: klein, Region mit Bindestrich.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$konstanten = (string) file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

const ALIASES = ['nb' => 'no', 'zh-hans' => 'zh', 'zh-hant' => 'zh-tw'];

function normalize(string $code): string
{
    $c = strtolower(str_replace('_', '-', trim($code)));

    return ALIASES[$c] ?? $c;
}

function forProvider(string $code, string $provider): string
{
    $c = normalize($code);
    if ($provider === 'google') {
        return explode('-', $c)[0];
    }
    if ($provider === 'deepl') {
        return strtoupper($c);
    }

    return str_contains($c, '-')
        ? explode('-', $c)[0] . '-' . strtoupper(explode('-', $c)[1])
        : $c;
}

// Test 1: DER FALL AUS DER PRAXIS - dieselbe Sprache von beiden Anbietern landet
// auf demselben internen Code. Das ist die ganze Pointe.
foreach ([['de', 'DE'], ['cs', 'CS'], ['uk', 'UK']] as [$google, $deepl]) {
    assert(normalize($google) === normalize($deepl),
        "DER FALL: \"$google\" (Google) und \"$deepl\" (DeepL) muessen denselben internen Code ergeben");
}
echo "Test 1 (beide Anbieter landen auf demselben internen Code) OK\n";

// Test 2: DIE DOPPELLISTE verschwindet dadurch VON SELBST - nicht durch
// Aufraeumen, sondern weil beide Quellen im selben Codesatz liegen.
$eingebaut = ['de','en','fr','es','it','nl','pt','pl','cs','da','sv','no','fi','el','ro','hu','tr','ru','uk','ar','zh','ja','ko','hi','id','is','cy','zu','mi','la'];
$deepl = ['BG','CS','DA','DE','EL','EN-GB','EN-US','ES','ET','FI','FR','HU','ID','IT','JA','KO','LT','LV','NB','NL','PL','PT-BR','PT-PT','RO','RU','SK','SL','SV','TR','UK','ZH'];
$gemischt = [];
foreach (array_merge($eingebaut, array_map('normalize', $deepl)) as $code) {
    $gemischt[$code] = true;
}
$doppelt = [];
foreach (array_keys($gemischt) as $code) {
    $sprache = explode('-', $code)[0];
    if ($code !== $sprache && isset($gemischt[$sprache])) {
        $doppelt[] = "$code neben $sprache";
    }
}
assert(strtolower(implode(',', array_keys($gemischt))) === implode(',', array_keys($gemischt)),
    'DIE DOPPELLISTE: in der gemischten Auswahl steht nichts mehr in Grossschreibung');
assert(!isset($gemischt['NB']) && isset($gemischt['no']), 'Norwegisch steht nur einmal drin');
echo "Test 2 (keine zwei Schreibweisen mehr in der Auswahl) OK\n";

// Test 3: die vier Regionalvarianten bleiben als eigene Sprachen erhalten -
// sie einfach auf "en"/"pt" zusammenzufalten waere Informationsverlust.
foreach (['EN-GB' => 'en-gb', 'EN-US' => 'en-us', 'PT-BR' => 'pt-br', 'PT-PT' => 'pt-pt'] as $roh => $intern) {
    assert(normalize($roh) === $intern, "Regionalvariante $roh bleibt erhalten");
    assert(normalize($roh) !== normalize(explode('-', $roh)[0]), "$roh faellt nicht mit der regionslosen Form zusammen");
}
echo "Test 3 (Regionalvarianten bleiben eigene Sprachen) OK\n";

// Test 4: DER RUECKWEG - jeder Anbieter bekommt seine eigene Schreibweise.
assert(forProvider('en-gb', 'google') === 'en', 'Google kennt keine Region - sonst schluege die Anfrage fehl');
assert(forProvider('en-gb', 'deepl') === 'EN-GB', 'DeepL bekommt Grossschreibung mit Region');
assert(forProvider('en-gb', 'free') === 'en-GB', 'MyMemory: Sprache klein, Region gross');
assert(forProvider('de', 'deepl') === 'DE' && forProvider('de', 'google') === 'de', 'der einfache Fall bleibt einfach');
echo "Test 4 (jeder Anbieter bekommt seine eigene Schreibweise) OK\n";

// Test 5: Stabilitaet - was einmal normalisiert ist, aendert sich nicht mehr.
foreach (['de', 'en-gb', 'no', 'zh-tw', 'pt-br'] as $intern) {
    assert(normalize($intern) === $intern, "\"$intern\" ist bereits normalisiert und bleibt unveraendert");
    foreach (['google', 'deepl', 'free'] as $p) {
        assert(normalize(forProvider($intern, $p)) === normalize(explode('-', $intern)[0]) || normalize(forProvider($intern, $p)) === $intern,
            "Rundlauf ueber $p verliert nichts ausser der Region, die $p nicht kennt");
    }
}
echo "Test 5 (die Normalisierung ist stabil) OK\n";

// Test 6: DIE STRUKTURELLE ZUSICHERUNG - kein Code darf mehr ROH an eine API
// gehen. Genau ein uebersehener Aufruf reichte, um den Fehler wieder
// einzuschleppen, und man saehe es erst live an einer fehlgeschlagenen Anfrage.
$ausgaenge = [
    "'source' => \$this->LanguageCodeForProvider(\$Source, 'google')",
    "'target' => \$this->LanguageCodeForProvider(\$Target, 'google')",
    "'source_lang' => \$this->LanguageCodeForProvider(\$Source, 'deepl')",
    "'target_lang' => \$this->LanguageCodeForProvider(\$Target, 'deepl')",
    "LanguageCodeForProvider(\$Source, 'free')",
    "LanguageCodeForProvider(\$Target, 'google')",
];
foreach ($ausgaenge as $stelle) {
    assert(strpos($module, $stelle) !== false, "DIE ZUSICHERUNG: dieser Ausgang muss umgerechnet werden: $stelle");
}
// Gegenprobe: die alten, rohen Formen duerfen nicht zurueckkehren.
foreach (["'source' => \$Source,", "'target' => \$Target,", "'source_lang' => \$Source,", "'target_lang' => \$Target,"] as $roh) {
    assert(strpos($module, $roh) === false, "roher Code an die API: $roh");
}
echo "Test 6 (kein Sprachcode geht mehr roh an eine API) OK\n";

// Test 7: die Eingaenge normalisieren ebenfalls - sonst haengt der GESPEICHERTE
// Code am gerade antwortenden Anbieter, und genau das war der Ausgangsfehler.
foreach (['FetchLanguageNamesGoogle', 'FetchLanguageNamesDeepL'] as $fn) {
    $a = (int) strpos($module, 'private function ' . $fn);
    $b = (int) strpos($module, "\n    // ", $a);
    $rumpf = substr($module, $a, $b - $a);
    assert(strpos($rumpf, 'NormalizeLanguageCode(') !== false, "$fn muss die gelieferten Codes normalisieren");
}
echo "Test 7 (beide Anbieter-Listen werden beim Einlesen normalisiert) OK\n";

// Test 8: die Alias-Tabelle steht im Code und enthaelt die Faelle, die eine
// echte Entscheidung brauchen - reine Grossschreibung gehoert NICHT hinein.
assert(strpos($konstanten, "'nb'      => 'no'") !== false, 'Bokmaal wird auf das eingebaute "no" gelegt');
assert(strpos($konstanten, 'LANGUAGE_CODE_ALIASES') !== false, 'die Tabelle existiert');
foreach (['de', 'cs', 'uk'] as $trivial) {
    assert(strpos($konstanten, "'$trivial' => '$trivial'") === false,
        "\"$trivial\" braucht keinen Alias - das erledigt strtolower()");
}
echo "Test 8 (die Alias-Tabelle enthält nur echte Entscheidungen) OK\n";

echo "\nAlle Tests OK (Build 186: einheitliche Sprachcodes).\n";
