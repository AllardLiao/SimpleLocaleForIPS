<?php
declare(strict_types=1);
// Standalone replica test for build 167 (2026-08-26, Nutzer-Entscheidung):
// "es gab ja noch keine anderen Instanzen außer bei mir. Niemand wird (außer
// bewußt) eine alte Version haben. Wir können meiner Meinung nach risikolos
// jeden Code, der gerade Upgradepfade behandeln würde, entfernen."
//
// Entfernt wurden die beiden Bereinigungen in Create(), die ausschliesslich auf
// bereits eingerichteten Installationen greifen konnten:
//   - Loeschen der frueheren HTMLBox-Dropdown-/"Sprache"-Variable
//   - Loeschen des toten Build-98-Verzoegerungstimers (CleanupReload)
//
// NICHT entfernt - und genau das haelt dieser Test fest, damit es bei einer
// spaeteren Aufraeumrunde nicht doch mitgenommen wird: Was wie eine Migration
// AUSSIEHT, aber ebenso frisch gescannte oder von Hand angelegte Zeilen traegt.
// Diese Unterscheidung ist der eigentliche Inhalt dieses Builds.

$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

// Test 1: die beiden Bereinigungen sind restlos weg - samt ihrer Konstanten,
// die sonst als toter Ballast zurueckblieben.
assert(strpos($moduleSource, 'identLanguageDropdown') === false, 'die Dropdown-Bereinigung muss entfernt sein');
assert(strpos($constantsSource, 'identLanguageDropdown') === false, 'und ihre Konstante ebenso');
assert(strpos($moduleSource, 'timerIdentCleanupReload') === false, 'die Timer-Bereinigung muss entfernt sein');
assert(strpos($constantsSource, 'timerIdentCleanupReload') === false, 'und ihre Konstante ebenso');
echo "Test 1 (beide Bereinigungen samt Konstanten sind entfernt) OK\n";

// Test 2: der Aktions-Ident "Language" bleibt - er hat mit der geloeschten
// VARIABLEN gleichen Namens nichts zu tun und traegt weiterhin den
// Sprachwechsel ueber RequestAction.
assert(strpos($constantsSource, "identLanguage = 'Language'") !== false, 'der Aktions-Ident muss bleiben');
assert(substr_count($moduleSource, 'self::identLanguage') >= 2, 'und weiterhin verwendet werden - er traegt den Sprachwechsel');
echo "Test 2 (der Aktions-Ident \"Language\" bleibt erhalten) OK\n";

// Test 3: DIE ABGRENZUNG - BackfillRowSourceLanguage() sieht nach Migration aus,
// traegt aber ebenso jede frisch gescannte Zeile. Ohne sie haette eine neue
// Zeile keine Quellsprache.
assert(strpos($moduleSource, 'private function BackfillRowSourceLanguage(array $Row, string $Fallback): array') !== false,
    'DIE ABGRENZUNG: BackfillRowSourceLanguage ist keine reine Migration und muss bleiben');
assert(substr_count($moduleSource, 'BackfillRowSourceLanguage') > 3,
    'sie muss weiterhin an allen Merge-Stellen haengen');
echo "Test 3 (BackfillRowSourceLanguage bleibt - trägt auch frische Zeilen) OK\n";

// Test 4: dasselbe fuer das "Uebersetzung aktiv"-Flag.
assert(strpos($moduleSource, 'private function BackfillTranslationActiveFlag(array $Row): array') !== false,
    'BackfillTranslationActiveFlag ist ebenfalls keine reine Migration');
echo "Test 4 (BackfillTranslationActiveFlag bleibt) OK\n";

// Test 5: der sourceChangedAt-Zweig deckt nicht nur Altzeilen ab, sondern jede
// Zeile, die seit ihrer Erfassung nie geaendert wurde. Entfernt man ihn, gilt
// der komplette Bestand als veraltet und wird neu uebersetzt - teuer und falsch.
$start = strpos($moduleSource, 'private function IsRowLanguageTranslationCurrent');
$body = substr($moduleSource, $start, 900);
assert(strpos($body, 'if ($sourceChangedAt === 0) {') !== false,
    'DIE FALLE: ohne diesen Zweig gaelte der komplette Bestand als veraltet und wuerde neu uebersetzt');
echo "Test 5 (der sourceChangedAt-Zweig bleibt) OK\n";

// Test 6: die Cache-Schemaversion ist keine Migration, sondern die Invalidierung
// fuer KUENFTIGE Versionssspruenge - sie muss bleiben.
assert(strpos($moduleSource, 'TRANSLATION_CACHE_SCHEMA_VERSION') !== false,
    'die Cache-Schemaversion ist vorwaertsgerichtet und darf nicht als Migration missverstanden werden');
echo "Test 6 (die Cache-Schemaversion bleibt) OK\n";

// Test 7: Create() darf ueberhaupt keine wertlesenden Zugriffe mehr enthalten -
// genau daran war die Build-132-Anomalie (Counter-Reset) gescheitert: dort
// werden Attribute nur DEKLARIERT, ReadAttribute* liefert nicht zuverlaessig den
// persistierten Wert.
$createStart = strpos($moduleSource, 'public function Create()');
$createEnde = strpos($moduleSource, 'public function ApplyChanges()');
$createBody = substr($moduleSource, $createStart, $createEnde - $createStart);
$createCode = implode("\n", array_filter(
    explode("\n", $createBody),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));
assert(strpos($createCode, '$this->ReadAttribute') === false,
    'DIE LEHRE AUS BUILD 132/149: in Create() darf kein Attribut GELESEN werden - dort werden sie nur deklariert');
echo "Test 7 (Create() liest kein Attribut - die Lehre aus Build 132) OK\n";

echo "\nAll tests passed.\n";
