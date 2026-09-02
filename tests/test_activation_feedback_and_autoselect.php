<?php
declare(strict_types=1);
// Standalone replica test for build 175 (2026-08-27, vier Nutzer-Wuensche auf
// einmal - hier die drei, die Verhalten aendern):
//
// 1. Der Aktivierungsknopf war bei einem Serverproblem STUMM: es erschien
//    kommentarlos "Lizenz gueltig", obwohl die Bestaetigung beim Server
//    ausgeblieben war. Jetzt sagt ein eigenes Popup genau das - und zugleich,
//    dass die Lizenz lokal geprueft und gueltig ist.
// 2. Fehlschlaege der Serverkommunikation gehoeren als ECHTE Fehlermeldung ins
//    Symcon-Log, nicht nur ins Debug-Fenster. Ohne Instanz-Fehlerstatus: das
//    Modul arbeitet ja vollstaendig weiter.
// 3. Ein ERSTMALS eingetroffenes Editions-Design wird gleich aktiv gesetzt -
//    aber nur beim ersten Mal, sonst wuerde eine bewusste Abwahl des Kunden bei
//    jeder Aktivierung wieder ueberschrieben.

// --- 1./2. Rueckmeldung des Aktivierungsknopfs -----------------------------

// Repliziert die Popup-Entscheidung aus ActivateLicense().
function popupsReplica(bool $blocked, bool $serverReached): array
{
    return [
        'blocked'     => $blocked,
        'unreachable' => !$blocked && !$serverReached,
        'valid'       => !$blocked && $serverReached,
    ];
}

// Test 1: DER GEMELDETE FALL - Server nicht erreichbar.
$p = popupsReplica(false, false);
assert($p['unreachable'] === true, 'DER FIX: bei nicht erreichtem Server muss der Hinweis erscheinen');
assert($p['valid'] === false, 'und NICHT gleichzeitig "Lizenz gueltig" - das waere widerspruechlich');
echo "Test 1 (nicht erreichter Server meldet sich, statt \"gültig\" zu zeigen) OK\n";

// Test 2: Normalfall unveraendert.
$p = popupsReplica(false, true);
assert($p['valid'] === true && $p['unreachable'] === false, 'bei erreichtem Server bleibt es beim bisherigen Popup');
echo "Test 2 (der Normalfall bleibt unverändert) OK\n";

// Test 3: ein GEBLOCKTER Schluessel gewinnt ueber beides - die Aussage
// "geblockt" ist wichtiger als die Erreichbarkeit.
$p = popupsReplica(true, false);
assert($p['blocked'] === true && $p['unreachable'] === false && $p['valid'] === false,
    'bei geblocktem Schluessel darf nur dieses Popup erscheinen');
echo "Test 3 (ein geblockter Schlüssel gewinnt über beide anderen) OK\n";

// --- 3. Neues Editions-Design aktiv setzen ---------------------------------

// Repliziert den Auswahl-Teil aus StoreTileAssetBundle().
function autoSelectReplica(array $neu, array $vorherKeys, array $properties): array
{
    foreach ($neu as $asset) {
        if ($asset['scope'] !== 'edition') { continue; }
        if (isset($vorherKeys[$asset['kind'] . '|' . $asset['key']])) { continue; }
        $feld = $asset['kind'] === 'icon' ? 'TileIconId' : 'TileTemplateId';
        if (($properties[$feld] ?? '') === $asset['key']) { continue; }
        $properties[$feld] = $asset['key'];
    }

    return $properties;
}

$design = [
    ['key' => 'nachbarn2026', 'kind' => 'icon', 'scope' => 'edition'],
    ['key' => 'nachbarn2026', 'kind' => 'template', 'scope' => 'edition'],
];
$start = ['TileIconId' => 'auto', 'TileTemplateId' => 'default'];

// Test 4: DER GEMELDETE WUNSCH - beim ersten Eintreffen wird beides aktiv,
// unabhaengig davon, was vorher eingestellt war.
$nachher = autoSelectReplica($design, [], $start);
assert($nachher['TileIconId'] === 'nachbarn2026', 'das neue Symbol muss aktiv gesetzt werden');
assert($nachher['TileTemplateId'] === 'nachbarn2026', 'die neue Vorlage ebenso');
echo "Test 4 (ein erstmals eintreffendes Editions-Design wird aktiv) OK\n";

// Test 5: DIE ABGRENZUNG - kommt dasselbe Design erneut mit, bleibt eine
// zwischenzeitliche Abwahl des Kunden bestehen.
$abgewaehlt = ['TileIconId' => 'globe', 'TileTemplateId' => 'default'];
$vorher = ['icon|nachbarn2026' => true, 'template|nachbarn2026' => true];
assert(autoSelectReplica($design, $vorher, $abgewaehlt) === $abgewaehlt,
    'DIE ABGRENZUNG: ein bereits bekanntes Design darf die Auswahl NIE erneut ueberschreiben');
echo "Test 5 (ein bereits bekanntes Design überschreibt die Auswahl nicht) OK\n";

// Test 6: ein EDITIONSLOSES Design wird nie von selbst aktiv - es steht allen
// zu und verhaelt sich wie der Standard.
$fuerAlle = [['key' => 'schlicht', 'kind' => 'icon', 'scope' => 'all']];
assert(autoSelectReplica($fuerAlle, [], $start) === $start, 'ein editionsloses Design darf nichts umstellen');
echo "Test 6 (ein editionsloses Design stellt nichts um) OK\n";

// --- Symmetrie-Check gegen die reale Umsetzung -----------------------------
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
$formJson = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);

assert(strpos($formJson, 'LicenseServerUnreachablePopup') !== false, 'das Popup muss im Formular liegen');
assert(strpos($moduleSource, "UpdateFormField('LicenseServerUnreachablePopup', 'visible', !\$blocked && !\$serverReached)") !== false,
    'es muss an "nicht geblockt und nicht erreicht" haengen');
assert(strpos($moduleSource, "UpdateFormField('LicenseValidPopup', 'visible', !\$blocked && \$serverReached)") !== false,
    'und das Gueltig-Popup an der Gegenbedingung - nie beide gleichzeitig');

$callStart = strpos($moduleSource, 'private function CallActivationReportAPI');
$callBody = substr($moduleSource, $callStart, strpos($moduleSource, "\n    private function ", $callStart + 10) - $callStart);
// Kommentarzeilen abstreifen: der Rumpf ERKLAERT, warum ueber den Helfer
// geloggt wird, und nennt ihn dabei beim Namen.
$callBody = implode("\n", array_filter(
    explode("\n", $callBody),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));
assert(substr_count($callBody, 'LogTranslateMessage(') === 2,
    'beide Fehlschlagarten (Transport, HTTP-Status) muessen als Fehler geloggt werden');
assert(substr_count($callBody, 'true') >= 2, 'und zwar als FEHLER, nicht als Warnung');
// DIE FALLE: $this->LogMessage() scheitert innerhalb von MessageSink - und genau
// dorthin kann dieser Aufruf geraten (IM_CHANGESETTINGS -> IPS_ApplyChanges ->
// passiver Melde-Pfad). Der Helfer weicht dort auf IPS_LogMessage() aus.
assert(strpos($callBody, '$this->LogMessage(') === false,
    'nicht die geerbte Methode direkt verwenden - sie scheitert im MessageSink-Kontext');
assert(strpos($callBody, 'SetStatus') === false,
    'DIE ENTSCHEIDUNG: ein nicht erreichbarer Meldeserver setzt KEINEN Instanz-Fehlerstatus - das Modul arbeitet weiter');

assert(strpos($constantsSource, 'UNKNOWN_LANGUAGE_ALERT_TEXT') !== false, 'der Gast-Hinweis muss deklariert sein');
assert(strpos($moduleSource, 'private function PushUnknownLanguageAlert(): void') !== false, 'und gebaut werden');
$rejectPos = strpos($moduleSource, '$this->PushUnknownLanguageAlert();');
$redrawPos = strrpos(substr($moduleSource, 0, $rejectPos), '$this->PushVisualizationUpdate();');
assert($redrawPos !== false && $redrawPos < $rejectPos,
    'erst neu zeichnen, dann melden - sonst ueberschreibt das Neuzeichnen die Meldung');

$store = substr($moduleSource, strpos($moduleSource, 'private function StoreTileAssetBundle'), 6000);
assert(strpos($store, "isset(\$previousKeys[\$asset['kind'] . '|' . \$asset['key']])") !== false,
    'nur ein ERSTMALS eingetroffenes Design darf die Auswahl setzen');
assert(strpos($store, "\$asset['scope'] !== 'edition'") !== false, 'und nur ein editionsgebundenes');

foreach (['Activation not confirmed'] as $text) {
    foreach ($locale['translations'] as $sprache => $eintraege) {
        assert(isset($eintraege[$text]), "\"$text\" fehlt in der Sprache \"$sprache\"");
    }
}
echo "Test 7 (Popups, Logging, Gast-Hinweis und Auto-Auswahl sind real verdrahtet) OK\n";

echo "\nAll tests passed.\n";
