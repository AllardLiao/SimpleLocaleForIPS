<?php
declare(strict_types=1);
// Standalone replica test for build 193 (2026-09-02, Nutzer-Wunsch): die
// Sperrfrist zwischen zwei Sprachwechseln kommt als ZEITWERT aus der Lizenz.
//
// VORHER: ein Ja/Nein-Feature ("unlimited_language_switch") und eine feste
// Konstante von 24 Stunden. In einer Spezialversion, die Features einzeln
// zusammenstellt und kein "Tier" kennt, liess sich die Dauer damit gar nicht
// festlegen - nur an oder aus.
//
// JETZT: switchIntervalMinutes in der Lizenz-Nutzlast - MINUTENGENAU, damit
// sich auch kurze Fristen abbilden lassen. 0 = unbegrenzt. Das hat
// NICHTS mit languageLimit zu tun (das ist die ANZAHL der Sprachen) und nichts
// mit "interval" (das ist der Abo-Zyklus jaehrlich/monatlich).

const STANDARD = 86400;

// Repliziert GetLanguageSwitchIntervalSeconds().
function intervall(array $info): int
{
    if (!($info['valid'] ?? false)) {
        return 0;
    }
    $minuten = (int) ($info['switchIntervalMinutes'] ?? -1);
    if ($minuten >= 0) {
        return $minuten * 60;
    }
    if (in_array('unlimited_language_switch', $info['features'] ?? [], true)) {
        return 0;
    }

    return STANDARD;
}

// Repliziert IsLanguageSwitchRateLimited() (ohne die Sonderfaelle Quellsprache/
// gleiche Sprache, die davor greifen).
function gesperrt(array $info, int $letzterWechselVorSekunden): bool
{
    $i = intervall($info);
    if ($i === 0) {
        return false;
    }

    return $letzterWechselVorSekunden < $i;
}

// Test 1: DER WUNSCH - die Dauer ist frei festlegbar.
assert(intervall(['valid' => true, 'switchIntervalMinutes' => 360]) === 21600, 'DER WUNSCH: 360 Minuten = 6 Stunden');
assert(intervall(['valid' => true, 'switchIntervalMinutes' => 30]) === 1800, 'und eine halbe Stunde - genau dafuer die Minuten');
assert(intervall(['valid' => true, 'switchIntervalMinutes' => 2880]) === 172800, 'auch laenger als ein Tag');
echo "Test 1 (die Dauer kommt frei aus der Lizenz) OK\n";

// Test 2: 0 = unbegrenzt - ausdruecklich so festgelegt.
assert(intervall(['valid' => true, 'switchIntervalMinutes' => 0]) === 0, 'DIE FESTLEGUNG: 0 = unbegrenzt');
assert(gesperrt(['valid' => true, 'switchIntervalMinutes' => 0], 1) === false, 'und dann wird nie gesperrt');
echo "Test 2 (0 bedeutet unbegrenzt) OK\n";

// Test 3: die Sperre wirkt genau bis zum Ablauf, nicht darueber hinaus.
$sechsStunden = ['valid' => true, 'switchIntervalMinutes' => 360];
assert(gesperrt($sechsStunden, 21599) === true, 'kurz vor Ablauf noch gesperrt');
assert(gesperrt($sechsStunden, 21600) === false, 'exakt bei Ablauf frei');
echo "Test 3 (die Sperre endet exakt mit der Frist) OK\n";

// Test 4: DIE ALTLAST - bereits ausgestellte Schluessel kennen das Feld nicht.
// Ohne Rueckfall wuerde sich so ein Schluessel stillschweigend anders verhalten.
assert(intervall(['valid' => true, 'features' => ['unlimited_language_switch']]) === 0,
    'DIE ALTLAST: das alte Ja/Nein-Feature gilt weiter als "unbegrenzt"');
assert(intervall(['valid' => true, 'features' => []]) === STANDARD,
    'und ohne beides bleibt es beim bisherigen Tag');
echo "Test 4 (bereits ausgestellte Schlüssel verhalten sich unverändert) OK\n";

// Test 5: der ausdrueckliche Zeitwert gewinnt gegen die Altlast - sonst liesse
// sich eine Edition mit dem alten Feature nicht mehr auf eine Frist umstellen.
assert(intervall(['valid' => true, 'switchIntervalMinutes' => 720, 'features' => ['unlimited_language_switch']]) === 43200,
    'der ausdrueckliche Wert gewinnt');
echo "Test 5 (der ausdrückliche Wert gewinnt gegen die Altlast) OK\n";

// Test 6: ohne gueltige Lizenz (Testphase) bleibt es unbegrenzt - die Sperre war
// nie als Testphasen-Beschraenkung gedacht, und das aendert sich hier nicht.
assert(intervall(['valid' => false]) === 0, 'Testphase unveraendert unbegrenzt');
echo "Test 6 (die Testphase bleibt unbegrenzt) OK\n";

// Test 7: Symmetrie-Check gegen die reale Umsetzung.
$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($module, "\$payload['switchIntervalMinutes'] = isset(\$payload['switchIntervalMinutes'])") !== false,
    'die Nutzlast muss das Feld normalisieren');
assert(strpos($module, "'switchIntervalMinutes' => \$payload['switchIntervalMinutes'],") !== false,
    'und GetLicenseInfo() muss es durchreichen');
assert(strpos($module, 'private function GetLanguageSwitchIntervalSeconds(): int') !== false, 'die Aufloesung muss existieren');
assert(strpos($module, 'languageSwitchMinIntervalSeconds') === false,
    'die feste 24-Stunden-Konstante darf nicht mehr benutzt werden');
// Der Abo-Zyklus heisst ebenfalls "interval" - die beiden duerfen nicht kollidieren.
assert(strpos($module, "'interval'         => \$payload['interval'],") !== false,
    'der Abo-Zyklus bleibt ein eigenes, unberuehrtes Feld');
echo "Test 7 (die reale Umsetzung ist verdrahtet, ohne Namenskollision) OK\n";

echo "\nAlle Tests OK (Build 193: frei festlegbare Sperrfrist).\n";
