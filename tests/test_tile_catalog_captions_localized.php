<?php
declare(strict_types=1);
// Standalone replica test for build 156 (2026-08-26, live gemeldet, per
// Screenshot belegt): Bei englischer Konsolensprache standen die
// Feldbeschriftungen des Kachel-Panels korrekt auf Englisch ("Tile template",
// "Icon in the tile"), die Auswahleintraege daneben aber weiter auf
// "Automatisch (Standard)" bzw. "Automatisch (Simple-Locale-Symbol)".
//
// URSACHE: BuildCatalogOptions() setzte die Beschriftung zur Laufzeit aus
// mehreren Translate()-Fragmenten zusammen. Eine so zusammengebaute
// Zeichenkette matcht NIE einen locale.json-Eintrag und bleibt dadurch an die
// Symcon-SYSTEMSPRACHE gebunden statt an die Konsolensprache des Betrachters.
// Derselbe Fehler war zuvor schon bei den Anbieter-Pausen-Zeilen und bei
// propertyAutoRescanInterval aufgetreten und dort genauso behoben worden.
//
// FIX: fester, vollstaendig vorregistrierter deutscher Gesamttext ohne
// Translate() - dann matcht die Konsole exakt und uebersetzt selbst.
//
// Preis dafuer: jeder neue Katalogeintrag braucht ZWEI locale.json-Zeilen (sein
// Label und die "Automatisch (Label)"-Kombination). Genau das prueft dieser
// Test - sonst faellt es erst einem Kunden mit fremdsprachiger Konsole auf.

$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
assert(is_array($locale['translations'] ?? null), 'locale.json muss einen translations-Block haben');

// Liest die 'label'-Werte eines Katalog-Konstantenblocks aus dem Quelltext.
function katalogLabels(string $source, string $konstante): array
{
    $start = strpos($source, 'private const ' . $konstante . ' = [');
    assert($start !== false, "Katalog $konstante muss existieren");
    $ende = strpos($source, '];', $start);
    $block = substr($source, $start, $ende - $start);

    preg_match_all("/'label'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/", $block, $treffer);

    return array_map(static fn (string $l): string => stripslashes($l), $treffer[1]);
}

$iconLabels     = katalogLabels($moduleSource, 'TILE_ICON_CATALOG');
$templateLabels = katalogLabels($moduleSource, 'TILE_TEMPLATE_CATALOG');
$alleLabels     = array_unique(array_merge($iconLabels, $templateLabels));

// Test 1: die Kataloge werden ueberhaupt gefunden - sonst prueft der Rest nichts.
assert($iconLabels !== [], 'der Symbol-Katalog muss Eintraege haben');
assert($templateLabels !== [], 'der Vorlagen-Katalog muss Eintraege haben');
echo "Test 1 (beide Kataloge werden aus dem Quelltext gelesen) OK\n";

// Test 2: DER GEMELDETE FALL - jede "Automatisch (Label)"-Kombination muss in
// JEDER Sprache vorregistriert sein. Fehlt eine, bleibt genau dieser Eintrag
// bei fremdsprachiger Konsole deutsch stehen.
$fehlend = [];
foreach ($locale['translations'] as $sprache => $eintraege) {
    foreach ($alleLabels as $label) {
        $kombination = 'Automatic (' . $label . ')';
        if (!isset($eintraege[$kombination])) {
            $fehlend[] = "$sprache: \"$kombination\"";
        }
    }
}
assert($fehlend === [], "DER GEMELDETE FALL: diese Auswahltexte bleiben bei fremdsprachiger Konsole deutsch:\n  " . implode("\n  ", $fehlend));
echo "Test 2 (jede \"Automatisch (…)\"-Kombination ist in allen Sprachen registriert) OK\n";

// Test 3: auch die blanken Labels selbst muessen registriert sein - sie werden
// als eigene Auswahleintraege ebenso roh ausgeliefert.
$fehlend = [];
foreach ($locale['translations'] as $sprache => $eintraege) {
    foreach ($alleLabels as $label) {
        if (!isset($eintraege[$label])) {
            $fehlend[] = "$sprache: \"$label\"";
        }
    }
}
assert($fehlend === [], "diese Katalog-Labels sind nicht uebersetzt:\n  " . implode("\n  ", $fehlend));
echo "Test 3 (jedes Katalog-Label ist in allen Sprachen registriert) OK\n";

// Test 4: DER FIX in der realen Umsetzung - die Beschriftungen duerfen NICHT
// mehr durch Translate() laufen und nicht mehr zusammengesetzt werden.
$start = strpos($moduleSource, 'private function BuildCatalogOptions');
assert($start !== false, 'BuildCatalogOptions() muss existieren');
$ende = strpos($moduleSource, "\n    private function ", $start + 10);
$body = substr($moduleSource, $start, $ende - $start);

// Kommentarzeilen abstreifen: der Rumpf ERKLAERT den alten Zustand und zitiert
// ihn dabei woertlich - ohne das Abstreifen wuerden die Pruefungen unten am
// Kommentar statt am Code anschlagen.
$body = implode("\n", array_filter(
    explode("\n", $body),
    static fn (string $zeile): bool => strpos(ltrim($zeile), '//') !== 0
));

assert(strpos($body, "\$this->Translate('Automatic')") === false,
    'DER BUG: "Automatisch" darf nicht mehr einzeln uebersetzt und danach zusammengesetzt werden');
assert(strpos($body, "'caption' => 'Automatic (' . \$Catalog[\$automaticId]['label'] . ')'") !== false,
    'DER FIX: die Beschriftung muss als fester, roher deutscher Gesamttext rausgehen');
assert(strpos($body, "'caption' => \$entry['label']") !== false,
    'auch die einzelnen Eintraege gehen roh raus, damit die Konsole sie matchen kann');
assert(strpos($body, "\$this->Translate(\$entry['label'])") === false,
    'DER BUG: das Label darf nicht mehr vorab uebersetzt werden - danach matcht locale.json nicht mehr');
echo "Test 4 (die reale Umsetzung liefert rohe, vorregistrierte Gesamttexte) OK\n";

// Test 5: DIE FALLE FUER SPAETER - ein neuer Katalogeintrag ohne die beiden
// locale-Zeilen muss auffallen. Hier simuliert, damit der Test nachweislich
// anschlaegt und nicht nur zufaellig gruen ist.
$erfundenesLabel = 'Weihnachten 2099';
$deckung = 0;
foreach ($locale['translations'] as $eintraege) {
    if (isset($eintraege['Automatic (' . $erfundenesLabel . ')'])) {
        $deckung++;
    }
}
assert($deckung === 0, 'Kontrollprobe: ein unbekanntes Label darf nicht zufaellig registriert sein');
echo "Test 5 (Kontrollprobe: ein neuer Eintrag ohne locale-Zeilen würde auffallen) OK\n";

echo "\nAll tests passed.\n";
