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

// Repliziert DropRedundantRegionVariants(): die Eigenregion faellt nur, wenn die
// Basissprache in DERSELBEN Anbieter-Liste steht.
function dropRedundant(array $codes): array
{
    $vorhanden = array_flip($codes);
    return array_values(array_filter($codes, static function (string $c) use ($vorhanden): bool {
        $t = explode('-', $c, 2);
        return !(count($t) === 2 && $t[0] === $t[1] && isset($vorhanden[$t[0]]));
    }));
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
// PT-PT bleibt eigenstaendig: DeepL kennt kein einfaches "PT", die Eigenregion
// ist dort also NICHT redundant. Build 187 hatte sie pauschal gestrichen und
// Portugiesisch damit anders behandelt als Englisch - siehe Test 9/10.
assert(normalize('PT-PT') === 'pt-pt', 'PT-PT bleibt eine eigene Variante');
assert(dropRedundant(['pt-pt', 'pt-br']) === ['pt-pt', 'pt-br'], 'ohne "pt" in der Liste faellt nichts weg');
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

// Test 9 (Build 188, live gemeldet): DeepL fuehrt die Basissprache UND ihre
// gleichnamige Eigenregion getrennt - "DE"/"DE-DE" heissen beide "German",
// "FR"/"FR-FR" beide "French". Steht die Basissprache in derselben Liste, ist
// die Eigenregion redundant und faellt weg.
assert(dropRedundant(['de', 'de-de', 'de-ch']) === ['de', 'de-ch'], 'DER FALL: "de-de" faellt neben "de" weg');
assert(dropRedundant(['fr', 'fr-fr', 'fr-ca']) === ['fr', 'fr-ca'], 'und "fr-fr" neben "fr"');
echo "Test 9 (redundante Eigenregion fällt weg) OK\n";

// Test 10: DIE ABGRENZUNG in beide Richtungen. Fremde Regionen sind eigene
// Zielsprachen und duerfen NIE fallen - der umgekehrte Fehler waere schlimmer,
// dabei verschwaende stillschweigend eine Sprache aus der Auswahl. Und ohne
// Basissprache in der Liste ist auch die Eigenregion nicht redundant.
foreach (['de-ch', 'fr-ca', 'pt-br', 'en-gb', 'en-us', 'es-419'] as $eigenstaendig) {
    assert(normalize($eigenstaendig) === $eigenstaendig, "\"$eigenstaendig\" muss eine eigene Sprache bleiben");
    assert(dropRedundant([$eigenstaendig]) === [$eigenstaendig], "\"$eigenstaendig\" darf nie wegfallen");
}
assert(dropRedundant(['pt-pt', 'pt-br']) === ['pt-pt', 'pt-br'],
    'DIE ABGRENZUNG: ohne "pt" in der Liste bleibt "pt-pt" - genau der Fall, den Build 187 falsch machte');
echo "Test 10 (fremde Regionen und basislose Eigenregionen bleiben) OK\n";

// Test 11: DIE PROBE AUFS EXEMPEL - die echte DeepL-Liste (110 Zielsprachen,
// live abgefragt).
$deeplEcht = explode(' ', 'AF AN AR AS AY AZ BA BE BG BN BR BS CA CS CY DA DE DE-CH DE-DE EL EN-GB EN-US EO ES ES-419 ET EU FA FI FR FR-CA FR-FR GA GL GN GU HA HE HI HR HT HU HY ID IG IS IT JA JV KA KK KO KY LA LB LN LT LV MG MI MK ML MN MR MS MT MY NB NE NL OC OM PA PL PS PT-BR PT-PT QU RO RU SA SK SL SQ SR ST SU SV SW TA TE TG TH TK TL TN TR TS TT UK UR UZ VI WO XH YI ZH ZH-HANS ZH-HANT ZU');
assert(count($deeplEcht) === 110, 'die reale Liste hat 110 Eintraege');
$intern = array_values(array_unique(array_map('normalize', $deeplEcht)));
$bereinigt = dropRedundant($intern);
$entfernt = array_values(array_diff($intern, $bereinigt));
sort($entfernt);
assert($entfernt === ['de-de', 'fr-fr'], 'genau diese Eigenregionen fallen weg: ' . implode(', ', $entfernt));
// ZH/ZH-HANS fallen bereits ueber die Alias-Tabelle zusammen, nicht ueber diese Regel.
assert(count($intern) === 109 && count($bereinigt) === 107,
    'aus 110 rohen werden 109 interne (ZH/ZH-HANS fallen ueber die Alias-Tabelle zusammen), '
        . 'nach der Bereinigung 107 - gefunden: ' . count($intern) . '/' . count($bereinigt));
foreach (['pt-pt', 'pt-br', 'en-gb', 'en-us', 'de-ch', 'fr-ca', 'es-419', 'zh-tw'] as $bleibt) {
    assert(in_array($bleibt, $bereinigt, true), "\"$bleibt\" muss erhalten bleiben");
}
echo "Test 11 (die reale DeepL-Liste verliert genau zwei redundante Einträge) OK\n";

echo "\nAlle Tests OK (Build 188: einheitliche Sprachcodes).\n";
