<?php
declare(strict_types=1);
// Standalone replica test for build 185 (2026-09-01, Symcon-Review): das
// Funktions-Praefix heisst SLOC statt IPSSL - aber NICHT ueberall.
//
// Symcon beanstandete "for IP Symcon" im Store-Namen; auf Wunsch faellt damit
// auch das Praefix IPSSL. Eine pauschale Ersetzung waere hier aber gefaehrlich:
// zwei Stellen bilden PERSISTIERTE Bezeichner, keine Anzeigenamen.
//
//   - timerPrefix bildet die per RegisterTimer() angelegten Timer-Idents. Neu
//     benannt legte jede bestehende Installation neue Timer an und liesse die
//     alten als verwaiste Objekte zurueck.
//   - GetForkedProfileName() bildet den Namen eines angelegten Variablenprofils,
//     auf das vorhandene Variablen per IPS_SetVariableCustomProfile zeigen. Neu
//     benannt verwaisen die Profile, und das Zurueckstellen auf das Original
//     (Build 164) liefe ins Leere.
//
// Dieser Test haelt beide Seiten fest: das Praefix IST umgestellt, die zwei
// persistierten Bezeichner sind es NICHT.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$konstanten = (string) file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
$moduleJson = json_decode((string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.json'), true);
$libraryJson = json_decode((string) file_get_contents(dirname(__DIR__) . '/library.json'), true);

// Test 1: DIE UMSTELLUNG - Praefix und Store-Name.
assert($moduleJson['prefix'] === 'SLOC', 'DIE UMSTELLUNG: das Funktions-Praefix ist SLOC');
assert($libraryJson['name'] === 'Simple Locale',
    'und der Store-Name traegt kein "for IP Symcon" mehr - genau die Beanstandung');
assert(stripos($libraryJson['name'], 'symcon') === false && stripos($libraryJson['name'], 'ips') === false,
    'weder Symcon noch IPS im Namen');
echo "Test 1 (Präfix SLOC, Store-Name ohne IPS/Symcon) OK\n";

// Test 2: die Timer-Callbacks muessen zum Praefix passen - sonst ruft Symcon eine
// Funktion auf, die es unter dem Namen gar nicht gibt, und der Timer laeuft
// stumm ins Leere.
preg_match_all("/RegisterTimer\([^,]+,[^,]+,\s*'(\w+)_/", $module, $treffer);
assert($treffer[1] !== [], 'Timer-Registrierungen gefunden');
foreach ($treffer[1] as $praefix) {
    assert($praefix === 'SLOC', "Timer-Callback nutzt das aktuelle Praefix, nicht '$praefix'");
}
echo "Test 2 (alle Timer-Callbacks nutzen das aktuelle Präfix) OK\n";

// Test 3: DIE AUSNAHME - der Timer-IDENT bleibt. Er ist persistiert.
assert(strpos($konstanten, "private const timerPrefix = 'IPSSL_TIMER_';") !== false,
    'DIE AUSNAHME: der persistierte Timer-Ident bleibt unveraendert');
echo "Test 3 (der persistierte Timer-Ident bleibt) OK\n";

// Test 4: DIE ZWEITE AUSNAHME - der Profilname bleibt.
assert(strpos($module, "return 'IPSSL.' . \$this->InstanceID . '.' . \$ValueObjectID;") !== false,
    'DIE AUSNAHME: der persistierte Profilname bleibt unveraendert');
echo "Test 4 (der persistierte Profilname bleibt) OK\n";

// Test 5: sonst darf nirgends mehr ein IPSSL-Funktionsaufruf stehen - ein
// uebersehener liefe auf eine Funktion, die es nicht mehr gibt.
assert(strpos($module, 'IPSSL_') === false, 'kein IPSSL_-Aufruf mehr im Modul');
// Nur noch der Profilname selbst und die Zeile, die seine Ausnahme begruendet -
// beide bewusst. Waechst die Zahl, wurde irgendwo ein Aufruf uebersehen.
$codeZeilen = array_values(array_filter(
    explode("\n", $module),
    function (string $zeile): bool {
        return strpos($zeile, 'IPSSL') !== false && strpos(ltrim($zeile), '//') !== 0;
    }
));
assert(count($codeZeilen) === 1, 'im CODE bleibt genau eine IPSSL-Stelle uebrig: ' . implode(' | ', $codeZeilen));
assert(strpos($codeZeilen[0], 'GetForkedProfileName') !== false || strpos($codeZeilen[0], "'IPSSL.'") !== false,
    'und das ist der Profilname');
echo "Test 5 (keine übersehenen IPSSL-Aufrufe) OK\n";

// Test 6: beide Ausnahmen sind im Code begruendet - sonst benennt sie beim
// naechsten Mal jemand doch um, ohne den Grund zu kennen.
foreach ([[$konstanten, 'timerPrefix'], [$module, 'Profil']] as [$quelle, $was]) {
    assert(strpos($quelle, 'Build 185: bewusst weiterhin') !== false,
        "die Ausnahme bei $was ist im Code begruendet");
}
echo "Test 6 (beide Ausnahmen sind im Code begründet) OK\n";

echo "\nAlle Tests OK (Build 185: Präfix SLOC, persistierte Bezeichner unangetastet).\n";
