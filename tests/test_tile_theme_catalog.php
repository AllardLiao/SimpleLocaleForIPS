<?php
declare(strict_types=1);
// Standalone replica test for build 146 (2026-08-25, Nutzer-Wunsch
// "Wiedererkennungswert fuer Spezial-Editionen"):
//
// Symbol UND mitgelieferte Kachel-Vorlage sollen auswaehlbar sein, damit z.B.
// ein Xmas-Special eine eigene Optik bekommt. Anforderungen des Nutzers:
//  - auf den Auslieferungszustand zurücksetzbar
//  - bei Modul-Updates nicht verloren
//  - alle jemals ausgelieferten Vorlagen bleiben mitgeliefert
//  - aber NICHT jede Edition darf alles auswaehlen (Xmas 2026 soll kein
//    Nikolaus-Design anbieten)
//
// ZWEI Entwurfsentscheidungen, die dieser Test festnagelt:
//
// 1. Gespeichert wird nur die ID, NIE der Inhalt. propertyCustomTileHtml
//    speichert den Inhalt und zeigt genau das Problem: eine Instanz friert die
//    Vorlage in dem Zustand ein, in dem sie sie erwischt hat, und bekommt
//    spaetere Korrekturen nie (real passiert - der Scrollbalken-Fix aus Build
//    143 erreichte keine Instanz, die die eigene Kachel schon aktiviert hatte).
//
// 2. Die Berechtigung laeuft NICHT ueber HasLicenseFeature(). Das gibt waehrend
//    der Testphase absichtlich ALLES frei - fuer Saison-Designs wuerde das den
//    Wiedererkennungswert aushebeln, um den es hier ueberhaupt geht.

// Testkatalog mit allen drei relevanten Faellen.
const KATALOG = [
    'ipssl'    => ['label' => 'Simple-Locale-Symbol', 'feature' => null],
    'globe'    => ['label' => 'Weltkugel',            'feature' => null],
    'xmas2026' => ['label' => 'Weihnachten 2026',     'feature' => 'theme_xmas2026'],
    'nik2026'  => ['label' => 'Nikolaus 2026',        'feature' => 'theme_nikolaus2026'],
];
const STANDARD_ID = 'ipssl';

// Repliziert HasThemeEntitlement().
function hatBerechtigung(?string $feature, array $lizenz): bool
{
    if ($feature === null) {
        return true;
    }
    if (!($lizenz['valid'] ?? false)) {
        return false;
    }

    return in_array($feature, $lizenz['features'] ?? [], true);
}

// Repliziert FilterCatalogByEntitlement().
function waehlbareIds(array $lizenz): array
{
    $ids = [];
    foreach (KATALOG as $id => $eintrag) {
        if (hatBerechtigung($eintrag['feature'], $lizenz)) {
            $ids[] = $id;
        }
    }

    return $ids;
}

// Repliziert ResolveCatalogId().
function aufloesen(string $gespeichert, array $lizenz): string
{
    if (isset(KATALOG[$gespeichert]) && hatBerechtigung(KATALOG[$gespeichert]['feature'], $lizenz)) {
        return $gespeichert;
    }

    return STANDARD_ID;
}

$testphase  = ['valid' => false];
$standard   = ['valid' => true, 'features' => ['paid_providers', 'manual_translations']];
$xmas       = ['valid' => true, 'features' => ['paid_providers', 'theme_xmas2026']];
$nikolaus   = ['valid' => true, 'features' => ['paid_providers', 'theme_nikolaus2026']];

// Test 1: DIE KERNANFORDERUNG - eine Xmas-Edition darf ihr eigenes Design
// waehlen, aber ausdruecklich NICHT das Nikolaus-Design.
$xmasAuswahl = waehlbareIds($xmas);
assert(in_array('xmas2026', $xmasAuswahl, true), 'die Xmas-Edition muss ihr eigenes Saison-Design waehlen koennen');
assert(!in_array('nik2026', $xmasAuswahl, true), 'DIE KERNANFORDERUNG: die Xmas-Edition darf das Nikolaus-Design NICHT angeboten bekommen');
assert(!in_array('nik2026', waehlbareIds($standard), true), 'eine Standard-Edition ohne Saison-Feature darf ebenfalls kein Saison-Design sehen');
assert(in_array('xmas2026', waehlbareIds($nikolaus), true) === false, 'umgekehrt genauso: die Nikolaus-Edition darf das Xmas-Design nicht sehen');
echo "Test 1 (jede Sonder-Edition sieht ausschließlich ihr eigenes Design) OK\n";

// Test 2: die Auslieferungszustaende (ohne Feature-Anforderung) sind IMMER
// waehlbar - in jeder Edition und auch in der Testphase.
foreach (['Testphase' => $testphase, 'Standard' => $standard, 'Xmas' => $xmas] as $name => $lizenz) {
    $auswahl = waehlbareIds($lizenz);
    assert(in_array('ipssl', $auswahl, true), "Auslieferungssymbol muss in '$name' waehlbar sein");
    assert(in_array('globe', $auswahl, true), "die Weltkugel muss als Auslieferungszustand in '$name' waehlbar sein");
}
echo "Test 2 (die Auslieferungszustände sind in jeder Edition wählbar, auch in der Testphase) OK\n";

// Test 3: DIE TESTPHASEN-FALLE - waehrend der Testphase gibt HasLicenseFeature()
// absichtlich ALLES frei. Wuerde die Berechtigung darueber laufen, haette jede
// Testinstanz Zugriff auf jedes jemals ausgelieferte Sonder-Design und der
// ganze Wiedererkennungswert waere hinfaellig.
$testAuswahl = waehlbareIds($testphase);
assert($testAuswahl === ['ipssl', 'globe'], 'DIE FALLE: in der Testphase duerfen NUR die Auslieferungszustaende waehlbar sein, keine Saison-Designs - gefunden: ' . implode(', ', $testAuswahl));
echo "Test 3 (die Testphase bekommt trotz 'alle Features frei' KEIN Saison-Design) OK\n";

// Test 4: ZURUECKSETZBAR - der Standard ist immer erreichbar, und eine
// unbekannte ID (Vorlage aus einer neueren Modulversion) faellt sauber darauf
// zurueck statt ins Leere zu laufen.
assert(aufloesen('ipssl', $xmas) === 'ipssl', 'der Auslieferungszustand muss jederzeit waehlbar bleiben (Zuruecksetzen)');
assert(aufloesen('gibtesnicht', $xmas) === STANDARD_ID, 'eine unbekannte ID muss sauber auf den Standard zurueckfallen');
echo "Test 4 (auf Standard zurücksetzbar; unbekannte IDs fallen sauber zurück) OK\n";

// Test 5: DOWNGRADE - laeuft die Saison-Lizenz aus, greift die Auswahl nicht
// mehr, der gespeicherte Wert bleibt aber erhalten. Exakt das Muster von
// custom_tile/auto_rescan: kein Datenverlust, die Auswahl lebt nach erneuter
// Lizenzierung sofort wieder auf.
assert(aufloesen('xmas2026', $xmas) === 'xmas2026', 'mit gueltiger Saison-Lizenz muss das Design greifen');
assert(aufloesen('xmas2026', $testphase) === STANDARD_ID, 'ohne gueltige Lizenz darf das Saison-Design nicht mehr greifen');
assert(aufloesen('xmas2026', $standard) === STANDARD_ID, 'nach einem Downgrade ohne das Feature ebenfalls nicht');
// Der gespeicherte Wert selbst wird dabei nie angefasst - das Wiederaufleben
// nach erneuter Lizenzierung ist genau deshalb moeglich.
assert(aufloesen('xmas2026', $xmas) === 'xmas2026', 'nach erneuter Lizenzierung muss dieselbe gespeicherte ID wieder greifen');
echo "Test 5 (Downgrade deaktiviert die Auswahl, verwirft sie aber nicht - sie lebt wieder auf) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
assert(strpos($constantsSource, "propertyTileIconId = 'TileIconId'") !== false, 'die Symbol-Property muss deklariert sein');
assert(strpos($constantsSource, "propertyTileTemplateId = 'TileTemplateId'") !== false, 'die Vorlagen-Property muss deklariert sein');
assert(strpos($moduleSource, 'private const TILE_ICON_CATALOG') !== false, 'der Symbol-Katalog muss existieren');
assert(strpos($moduleSource, 'private const TILE_TEMPLATE_CATALOG') !== false, 'der Vorlagen-Katalog muss existieren');
assert(strpos($moduleSource, 'private function HasThemeEntitlement(?string $Feature): bool') !== false, 'die eigene Berechtigungspruefung muss existieren');
echo "Test 6 (Kataloge, Properties und Berechtigungsprüfung sind real vorhanden) OK\n";

// Test 7: DER ENTWURFSKERN - HasThemeEntitlement() darf NICHT ueber
// HasLicenseFeature() laufen, sonst kippt Test 3 in der Realitaet.
$fnStart = strpos($moduleSource, 'private function HasThemeEntitlement(');
$fnBody = substr($moduleSource, $fnStart, 600);
assert(strpos($fnBody, 'HasLicenseFeature') === false, 'DIE FALLE: HasThemeEntitlement() darf HasLicenseFeature() nicht verwenden - das gibt in der Testphase alles frei');
assert(strpos($fnBody, "\$info['valid']") !== false, 'HasThemeEntitlement() muss eine GUELTIGE Lizenz verlangen');
echo "Test 7 (die Theme-Berechtigung umgeht bewusst die 'Testphase gibt alles frei'-Regel) OK\n";

// Test 8: die Property darf nur die ID halten, nicht den Inhalt - sonst waere
// der ganze Zweck (Updates erreichen bestehende Instanzen) verfehlt. Beleg:
// die Vorlage wird beim Rendern aus der Datei gelesen, nicht aus der Property.
assert(strpos($moduleSource, 'private function GetSelectedTileTemplateHtml(): string') !== false, 'die Vorlage muss beim Rendern aufgeloest werden');
$tplFnStart = strpos($moduleSource, 'private function GetSelectedTileTemplateHtml(): string');
$tplFnBody = substr($moduleSource, $tplFnStart, 900);
assert(strpos($tplFnBody, 'file_get_contents') !== false, 'DER ENTWURFSKERN: der Vorlageninhalt muss beim Rendern frisch aus der Datei kommen, damit spaetere Korrekturen bestehende Instanzen erreichen');
assert(strpos($tplFnBody, 'ReadPropertyString(self::propertyTileTemplateId)') !== false, 'gelesen wird aus der Property nur die ID');
assert(strpos($tplFnBody, 'propertyCustomTileHtml') === false, 'die Vorlagen-Aufloesung darf nichts mit dem eigenen Kachel-Code des Nutzers zu tun haben - das sind bewusst getrennte Wege');
echo "Test 8 (gespeichert wird nur die ID, der Inhalt kommt beim Rendern frisch aus der Datei) OK\n";

echo "\nAll tests passed.\n";
