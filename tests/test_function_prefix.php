<?php
declare(strict_types=1);
// Standalone replica test for build 185 (2026-09-01, Symcon-Review): der
// Store-Name traegt kein "for IP Symcon" mehr, und das Funktions-Praefix heisst
// SLOC statt IPSSL.
//
// Beanstandet war nur der Name; das Praefix fiel auf eigenen Wunsch mit. Zwei
// der Stellen sind PERSISTIERTE Bezeichner, keine Anzeigenamen - das Praefix der
// per RegisterTimer() angelegten Timer-Idents und der Name der privaten
// Variablenprofile aus Build 164, auf die vorhandene Variablen per
// IPS_SetVariableCustomProfile zeigen. Sie wurden bewusst mit umgestellt: zum
// Zeitpunkt der Umstellung existierten ausschliesslich eigene Testinstanzen, die
// neu angelegt wurden. Auf einer gelebten Installation waeren sonst Timer mit
// totem Callback und verwaiste Profile zurueckgeblieben.
//
// Der Test haelt fest, dass die Umstellung VOLLSTAENDIG ist - ein uebersehener
// Aufruf liefe auf eine Funktion, die es unter dem Namen nicht mehr gibt, und
// ein uebersehener Timer-Callback wuerde stumm ins Leere laufen.

$wurzel = dirname(__DIR__);
$module = (string) file_get_contents($wurzel . '/SimpleLocale/module.php');
$konstanten = (string) file_get_contents($wurzel . '/libs/SimpleLocaleConstants.php');
$moduleJson = json_decode((string) file_get_contents($wurzel . '/SimpleLocale/module.json'), true);
$libraryJson = json_decode((string) file_get_contents($wurzel . '/library.json'), true);

// Test 1: DIE BEANSTANDUNG - kein IPS/Symcon mehr im Namen.
assert($libraryJson['name'] === 'Simple Locale', 'DIE BEANSTANDUNG: der Store-Name ist bereinigt');
foreach (['ips', 'symcon'] as $verboten) {
    assert(stripos($libraryJson['name'], $verboten) === false, "kein \"$verboten\" im Store-Namen");
    assert(stripos((string) $moduleJson['name'], $verboten) === false, "und keins im Modulnamen");
}
echo "Test 1 (weder IPS noch Symcon im Namen) OK\n";

// Test 2: das Praefix.
assert($moduleJson['prefix'] === 'SLOC', 'das Funktions-Praefix ist SLOC');
echo "Test 2 (Präfix SLOC) OK\n";

// Test 3: DIE STILLE FALLE - die Timer-Callbacks muessen zum Praefix passen.
// Symcon speichert den Callback als Text; passt er nicht zum Praefix, ruft der
// Timer eine Funktion auf, die es nicht gibt - ohne dass beim Registrieren
// irgendetwas auffaellt.
preg_match_all("/RegisterTimer\([^,]+,[^,]+,\s*'(\w+)_/", $module, $treffer);
assert(count($treffer[1]) === 4, 'alle vier Timer-Registrierungen gefunden');
foreach ($treffer[1] as $praefix) {
    assert($praefix === $moduleJson['prefix'], "Timer-Callback nutzt das aktuelle Praefix, nicht '$praefix'");
}
echo "Test 3 (alle Timer-Callbacks nutzen das aktuelle Präfix) OK\n";

// Test 4: VOLLSTAENDIGKEIT - im ausgelieferten Code steht nirgends mehr IPSSL.
// Kommentare ausgenommen: dort steht die Historie der Umstellung.
foreach ([['module.php', $module], ['SimpleLocaleConstants.php', $konstanten]] as [$name, $quelle]) {
    $codeZeilen = array_values(array_filter(
        explode("\n", $quelle),
        function (string $zeile): bool {
            return strpos($zeile, 'IPSSL') !== false && strpos(ltrim($zeile), '//') !== 0;
        }
    ));
    assert($codeZeilen === [], "VOLLSTAENDIGKEIT: kein IPSSL mehr im Code von $name: " . implode(' | ', $codeZeilen));
}
echo "Test 4 (kein IPSSL mehr im ausgelieferten Code) OK\n";

// Test 5: auch die beiden persistierten Bezeichner sind umgestellt - sie waren
// die einzigen, bei denen eine Umstellung ueberhaupt Folgen haette.
assert(strpos($konstanten, "private const timerPrefix = 'SLOC_TIMER_';") !== false,
    'der Timer-Ident traegt das neue Praefix');
assert(strpos($module, "return 'SLOC.' . \$this->InstanceID . '.' . \$ValueObjectID;") !== false,
    'und der Profilname ebenso');
echo "Test 5 (auch die persistierten Bezeichner sind umgestellt) OK\n";

// Test 6: die nach aussen dokumentierten Befehle heissen entsprechend - sonst
// zeigt die Befehlsreferenz auf Namen, die es nicht gibt.
$readme = (string) file_get_contents($wurzel . '/SimpleLocale/README.md');
$referenz = substr($readme, (int) strpos($readme, '### 9. PHP-Befehlsreferenz'),
    (int) strpos($readme, '### 10. Integration') - (int) strpos($readme, '### 9. PHP-Befehlsreferenz'));
assert(strpos($referenz, 'IPSSL_') === false, 'die Befehlsreferenz nennt kein IPSSL_ mehr');
assert(substr_count($referenz, 'SLOC_') >= 6, 'sondern die SLOC_-Befehle');
echo "Test 6 (die Befehlsreferenz ist nachgezogen) OK\n";

echo "\nAlle Tests OK (Build 185: Name und Funktions-Präfix).\n";
