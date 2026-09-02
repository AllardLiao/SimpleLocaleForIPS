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
    $teile = explode('-', $c, 2);
    if (count($teile) === 2 && $teile[0] === $teile[1]) {
        $c = $teile[0];
    }

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
foreach (['EN-GB' => 'en-gb', 'EN-US' => 'en-us', 'PT-BR' => 'pt-br', 'FR-CA' => 'fr-ca', 'DE-CH' => 'de-ch'] as $roh => $intern) {
    assert(normalize($roh) === $intern, "Regionalvariante $roh bleibt erhalten");
    assert(normalize($roh) !== normalize(explode('-', $roh)[0]), "$roh faellt nicht mit der regionslosen Form zusammen");
}
// PT-PT ist bewusst KEINE eigene Variante: DeepL kennt gar kein einfaches "PT",
// europaeisches Portugiesisch IST dort die Basissprache. Bliebe es eigenstaendig,
// stuende Portugiesisch zweimal in der Auswahl - einmal als eingebautes "pt",
// einmal als "pt-pt". Siehe Test 9.
assert(normalize('PT-PT') === 'pt', 'PT-PT ist die Basissprache, keine eigene Variante');
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

// Test 9 (Build 187, live gemeldet): DeepL fuehrt Basissprache UND gleichnamige
// Eigenregion als getrennte Eintraege - "DE"/"DE-DE" heissen beide "German",
// "FR"/"FR-FR" beide "French". In der Auswahl standen sie zweimal untereinander
// und waren nicht unterscheidbar. Eine Region, die der Sprache entspricht,
// traegt keine Information.
foreach (['de-de' => 'de', 'fr-fr' => 'fr', 'pt-pt' => 'pt', 'it-it' => 'it'] as $roh => $erwartet) {
    assert(normalize($roh) === $erwartet, "DER FALL: \"$roh\" ist dieselbe Sprache wie \"$erwartet\"");
}
echo "Test 9 (Eigenregion fällt auf die Basissprache) OK\n";

// Test 10: DIE ABGRENZUNG - fremde Regionen sind echte, eigene Zielsprachen und
// duerfen NICHT zusammenfallen. Der umgekehrte Fehler waere schlimmer: dabei
// verschwaende stillschweigend eine Sprache aus der Auswahl.
foreach (['de-ch', 'fr-ca', 'pt-br', 'en-gb', 'en-us', 'es-419'] as $eigenstaendig) {
    assert(normalize($eigenstaendig) === $eigenstaendig, "\"$eigenstaendig\" muss eine eigene Sprache bleiben");
    assert(normalize($eigenstaendig) !== explode('-', $eigenstaendig)[0], "\"$eigenstaendig\" darf nicht auf die Basissprache fallen");
}
echo "Test 10 (fremde Regionen bleiben eigenständig) OK\n";

// Test 11: DIE PROBE AUFS EXEMPEL - die echte DeepL-Liste (110 Zielsprachen,
// live abgefragt). Genau drei Paare gehoeren zusammengefuehrt, kein viertes.
$deeplEcht = explode(' ', 'AF AN AR AS AY AZ BA BE BG BN BR BS CA CS CY DA DE DE-CH DE-DE EL EN-GB EN-US EO ES ES-419 ET EU FA FI FR FR-CA FR-FR GA GL GN GU HA HE HI HR HT HU HY ID IG IS IT JA JV KA KK KO KY LA LB LN LT LV MG MI MK ML MN MR MS MT MY NB NE NL OC OM PA PL PS PT-BR PT-PT QU RO RU SA SK SL SQ SR ST SU SV SW TA TE TG TH TK TL TN TR TS TT UK UR UZ VI WO XH YI ZH ZH-HANS ZH-HANT ZU');
assert(count($deeplEcht) === 110, 'die reale Liste hat 110 Eintraege');
$nachCode = [];
foreach ($deeplEcht as $code) {
    $nachCode[normalize($code)][] = $code;
}
$zusammengefuehrt = array_filter($nachCode, static fn (array $v): bool => count($v) > 1);
assert(count($zusammengefuehrt) === 3, 'genau drei Paare gehoeren zusammen, gefunden: ' . count($zusammengefuehrt));
foreach ([['DE', 'DE-DE'], ['FR', 'FR-FR'], ['ZH', 'ZH-HANS']] as $paar) {
    assert(in_array($paar, array_values($zusammengefuehrt), true), 'erwartetes Paar fehlt: ' . implode('/', $paar));
}
assert(count($nachCode) === 107, 'aus 110 rohen werden 107 interne Codes');
echo "Test 11 (die reale DeepL-Liste ergibt genau drei Zusammenführungen) OK\n";

echo "\nAlle Tests OK (Build 187: einheitliche Sprachcodes).\n";
