<?php
declare(strict_types=1);
// Standalone replica test for build 164 (2026-08-26, live gemeldet und per
// Diagnose-Dump belegt):
//
// SYMPTOM: An einem Nuki-Schloss wurden die Aufzaehlungs-Captions nur teilweise
// uebersetzt. "Locking action" ja, "Blocking state"/"Batteries"/"Battery charge
// time"/"Keypad Battery" nein - obwohl alle fuenf gefuellte Uebersetzungen in
// der Tabelle hatten und alle fuenf identisch behandelt wurden.
//
// URSACHE: Symcon erlaubt die Enumeration-Praesentation nur fuer Variablen mit
// Variablen-Aktion ("This presentation is only available for variables with a
// variable action"). Bis Build 163 wurde jede Legacy-Variable darauf umgestellt.
// Der Fork kam durch - IPS_GetVariablePresentation() lieferte die uebersetzten
// Captions -, die Visu verwarf ihn aber und zeigte weiter das Profil.
//
// Der Dump belegte beides: alle fuenf bekamen PRESENTATION {52D9E126...}
// (Enumeration) statt {4153A8D4...} (Legacy), und nur "Locking action" hatte
// VariableAction = 16422 (die Nuki-Instanz selbst, gesetzt von EnableAction()).
// Im Dialog sieht das taeuschend leer aus: dort steht "Custom Action: (None)",
// waehrend die vom Modul gelieferte "Default Action" darunter aktiv ist.
//
// FIX: Variablen ohne Aktion bekommen statt der Praesentation ein geforktes
// PROFIL (private Kopie mit uebersetzten Namen, als VariableCustomProfile).

// Repliziert die Fork-Weiche aus ApplyEnumerationOptionsToVariable().
function forkWeg(string $presentation, int $action, int $customAction): string
{
    if ($presentation !== 'LEGACY') {
        return 'praesentation';
    }

    return ($action !== 0 || $customAction !== 0) ? 'praesentation' : 'profil';
}

// Test 1: DER GEMELDETE FALL - die vier Sensorvariablen ohne Aktion muessen den
// Profil-Weg nehmen.
foreach ([26788 => 'Blocking state', 28373 => 'Batteries', 48587 => 'Battery charge time', 24112 => 'Keypad Battery'] as $id => $name) {
    assert(forkWeg('LEGACY', 0, 0) === 'profil', "DER GEMELDETE FALL: \"$name\" ($id) hat keine Aktion und muss das Profil forken");
}
echo "Test 1 (Variablen ohne Aktion nehmen den Profil-Weg) OK\n";

// Test 2: "Locking action" hat eine Aktion (VariableAction = die Nuki-Instanz)
// und behaelt den bisherigen, funktionierenden Weg.
assert(forkWeg('LEGACY', 16422, 0) === 'praesentation', '"Locking action" hat eine Aktion und behaelt den Praesentations-Fork');
echo "Test 2 (mit Aktion bleibt es beim Präsentations-Fork) OK\n";

// Test 3: eine EIGENE Aktion zaehlt genauso - im Dialog steht sie als "Custom
// Action", waehrend VariableAction 0 bleibt.
assert(forkWeg('LEGACY', 0, 12345) === 'praesentation', 'eine eigene Aktion (Custom Action) zaehlt ebenso');
echo "Test 3 (eine eigene Aktion zählt ebenso) OK\n";

// Test 4: eine Variable, die ohnehin schon eine echte Enumeration hat, ist von
// der Weiche gar nicht betroffen - dort gab es nie ein Profil zum Forken.
assert(forkWeg('ENUMERATION', 0, 0) === 'praesentation', 'echte Enumerationen laufen weiter ueber den Praesentations-Weg');
echo "Test 4 (echte Enumerationen sind nicht betroffen) OK\n";

// Test 5: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

assert(strpos($constantsSource, "attributeEnumerationProfileBackup = 'EnumerationProfileBackup'") !== false,
    'das Backup-Attribut fuer den Profil-Fork muss deklariert sein');
assert(strpos($moduleSource, 'RegisterAttributeString(self::attributeEnumerationProfileBackup') !== false,
    'und registriert werden');
assert(strpos($moduleSource, 'private function ApplyForkedProfileToVariable(') !== false,
    'der Profil-Fork muss existieren');
assert(strpos($moduleSource, "\$hasAction = ((\$variable['VariableAction'] ?? 0) !== 0)") !== false,
    'DER FIX: die Weiche muss auf das Vorhandensein einer Aktion pruefen');
assert(strpos($moduleSource, "|| ((\$variable['VariableCustomAction'] ?? 0) !== 0)") !== false,
    'und dabei die eigene Aktion mit beruecksichtigen');
echo "Test 5 (die Weiche ist real verdrahtet) OK\n";

// Test 6: DER RUECKBAU - beim Zurueckstellen auf die Quellsprache muss die
// Variable ZUERST auf ihr altes Profil zurueckgesetzt und das eigene DANACH
// geloescht werden. Symcon verweigert das Loeschen eines Profils, das noch an
// einer Variable haengt.
$start = strpos($moduleSource, 'private function ApplyEnumerationOptionsToVariable');
$body = substr($moduleSource, $start, 4000);
$setzen = strpos($body, 'IPS_SetVariableCustomProfile($ValueObjectID, (string) $profileBackups[$backupKey])');
$loeschen = strpos($body, 'IPS_DeleteVariableProfile($ownProfile)');
assert($setzen !== false && $loeschen !== false, 'der Rueckbau muss beide Schritte enthalten');
assert($setzen < $loeschen, 'DIE FALLE: erst die Variable zuruecksetzen, DANN das Profil loeschen - sonst verweigert Symcon das Loeschen');
echo "Test 6 (der Rückbau setzt zurück, bevor er löscht) OK\n";

// Test 7: das Profil wird NICHT geloescht und neu angelegt, wenn es schon
// existiert - beim zweiten Sprachwechsel haengt es noch an der Variable.
$forkStart = strpos($moduleSource, 'private function ApplyForkedProfileToVariable');
$forkBody = substr($moduleSource, $forkStart, 4200);
assert(strpos($forkBody, 'if (!@IPS_VariableProfileExists($ownProfile)) {') !== false,
    'ein bereits vorhandenes Profil muss weiterverwendet statt neu angelegt werden');
assert(strpos($forkBody, 'IPS_DeleteVariableProfile') === false,
    'im Fork-Weg darf nicht geloescht werden - das Profil haengt beim zweiten Wechsel noch an der Variable');
assert(strpos($forkBody, 'if (!$anyTranslated) {') !== false,
    'weicht keine einzige Caption ab, darf gar kein Profil geforkt werden - sonst haengt ueberall ein Privatprofil, das nur das Original nachbaut');
echo "Test 7 (ein vorhandenes Profil wird weiterverwendet, ohne Abweichung gar keins) OK\n";

echo "\nAll tests passed.\n";
