<?php
declare(strict_types=1);
// Standalone replica test for build 153 (2026-08-26, live gemeldet, per dump22
// nachgewiesen): ZWEI Regressionen aus Build 151.
//
// Der Nutzer fuhr bewusst gegen MyMemorys Tageslimit, um das Verhalten zu
// sehen, und meldete: "wir fragen weiter tapfer ab, obwohl das Kontingent
// verbraucht ist" sowie "der Status verbleibt bei Aktiv".
//
// dump22, Lauf 3: 90 Texte erfolgreich, dann HTTP 429 mit dem Text
// "YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY. NEXT AVAILABLE IN
// 07 HOURS 15 MINUTES 47 SECONDS" - danach 35 WEITERE, voellig aussichtslose
// Aufrufe.
//
// URSACHE 1: Bis Build 151 stoppte der erste Fehlschlag den Durchlauf (mit dem
// Nebeneffekt, alle Teilerfolge zu verwerfen - deshalb der Umbau). Seitdem lief
// die Schleife stur weiter und fragte auch nach gesetzter Sperre weiter ab.
//
// URSACHE 2: Ein Teilerfolg galt als Anbieter-Erfolg und loeste
// ClearProviderPause() aus - die 7-Stunden-Sperre, die derselbe Durchlauf
// gerade gesetzt hatte, war sofort wieder weg. Deshalb blieb die Statuszeile
// auf "Aktiv" statt "pausiert", und der naechste Chunk rannte ungebremst in
// dieselbe Wand.

// Repliziert TranslateChunkFree() NACH dem Fix.
// $antworten: string = Erfolg, 'PAUSE' = Fehlschlag, der die Sperre setzt,
//             null = Fehlschlag ohne Sperre
function chunkFreeReplica(array $texts, array $antworten, bool &$paused, int &$apiCalls): ?array
{
    $results = [];
    $anySucceeded = false;

    foreach ($texts as $i => $text) {
        if ($paused) {
            $results[] = '';          // kein Aufruf mehr
            continue;
        }
        $apiCalls++;
        $a = $antworten[$i];
        if ($a === 'PAUSE') {
            $paused = true;
            $results[] = '';
            continue;
        }
        if ($a === null) {
            $results[] = '';
            continue;
        }
        $results[] = $a;
        if ($a !== '') {
            $anySucceeded = true;
        }
    }

    return $anySucceeded ? $results : null;
}

// Repliziert die ClearProviderPause-Entscheidung aus TranslateChunk().
function clearsPause(array $result): bool
{
    return !in_array('', $result, true);
}

// Test 1: DER GEMELDETE FALL - nach dem 429 darf KEIN weiterer Aufruf mehr
// erfolgen. Im Report waren es 35 aussichtslose.
$texts = [];
$antw  = [];
for ($i = 0; $i < 100; $i++) {
    $texts[] = "Text$i";
    $antw[]  = "Textus$i";
}
$antw[90] = 'PAUSE';          // hier schlaegt das Tageslimit zu
$paused = false;
$calls  = 0;
$result = chunkFreeReplica($texts, $antw, $paused, $calls);
assert($paused === true, 'die Sperre muss gesetzt worden sein');
assert($calls === 91, "DER GEMELDETE FALL: nach der Sperre darf kein weiterer Aufruf erfolgen - erwartet 91 (90 Erfolge + der eine Fehlschlag), tatsaechlich: $calls");
echo "Test 1 (nach dem Rate-Limit werden keine weiteren Aufrufe mehr abgesetzt) OK\n";

// Test 2: die 90 Erfolge VOR der Sperre bleiben trotzdem erhalten - der Gewinn
// aus Build 151 darf nicht verlorengehen.
$erhalten = count(array_filter($result, static fn (string $r): bool => $r !== ''));
assert($erhalten === 90, "die Erfolge vor der Sperre muessen erhalten bleiben - erhalten: $erhalten");
echo "Test 2 (die Erfolge vor der Sperre bleiben erhalten) OK\n";

// Test 3: DIE ZWEITE REGRESSION - ein unvollstaendiger Lauf darf die Sperre
// NICHT loeschen. Sonst verschwindet die gerade gesetzte 7-Stunden-Sperre
// sofort wieder, die Statuszeile bleibt auf "Aktiv", und der naechste Chunk
// rennt erneut in dieselbe Wand.
assert(clearsPause($result) === false, 'DIE ZWEITE REGRESSION: ein Lauf mit offenen Texten darf die Anbieter-Sperre nicht aufheben - ein Teilerfolg ist kein Gesundheitsnachweis');
echo "Test 3 (ein unvollständiger Lauf hebt die Anbieter-Sperre nicht auf) OK\n";

// Test 4: ein VOLLSTAENDIG gelieferter Lauf hebt sie sehr wohl auf - sonst
// bliebe ein laengst wieder gesunder Anbieter unnoetig gesperrt.
$paused2 = false;
$calls2  = 0;
$voll = chunkFreeReplica(['A', 'B'], ['Alpha', 'Beta'], $paused2, $calls2);
assert(clearsPause($voll) === true, 'ein vollstaendig gelieferter Lauf muss die Sperre aufheben - sonst bliebe ein gesunder Anbieter unnoetig gesperrt');
echo "Test 4 (ein vollständig gelieferter Lauf hebt die Sperre weiterhin auf) OK\n";

// Test 5: schlaegt der ALLERERSTE Text fehl und setzt die Sperre, kommt gar
// nichts durch -> null, damit die Kette auf den naechsten Anbieter ausweicht.
$paused3 = false;
$calls3  = 0;
$nichts = chunkFreeReplica(['A', 'B', 'C'], ['PAUSE', 'Beta', 'Gamma'], $paused3, $calls3);
assert($nichts === null, 'kommt kein einziger Text durch, muss null zurueck - nur so weicht die Kette auf den naechsten Anbieter aus');
assert($calls3 === 1, "nach der Sperre beim ersten Text darf nur EIN Aufruf erfolgt sein - tatsaechlich: $calls3");
echo "Test 5 (Sperre beim ersten Text: genau ein Aufruf, danach null für den Kettenwechsel) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$fnStart = strpos($moduleSource, 'private function TranslateChunkFree');
$fnBody = substr($moduleSource, $fnStart, 3500);
assert(strpos($fnBody, "if (\$this->IsProviderPaused('free')) {") !== false, 'DER FIX: die Schleife muss vor jedem Aufruf pruefen, ob der Anbieter inzwischen gesperrt wurde');
$pauseCheckPos = strpos($fnBody, "IsProviderPaused('free')");
$callPos = strpos($fnBody, '$this->TranslateSingleFree(');
assert($pauseCheckPos < $callPos, 'die Sperr-Pruefung muss VOR dem Aufruf stehen, sonst wird trotzdem angefragt');

$chunkStart = strpos($moduleSource, 'private function TranslateChunk(');
$chunkBody = substr($moduleSource, $chunkStart, 9000);
assert(strpos($chunkBody, "if (!in_array('', \$result, true)) {\n                \$this->ClearProviderPause(\$provider);") !== false, 'DER FIX: die Sperre darf nur bei VOLLSTAENDIGER Lieferung aufgehoben werden');
echo "Test 6 (die reale Umsetzung prüft die Sperre vor jedem Aufruf und hebt sie nur bei Vollerfolg auf) OK\n";

echo "\nAll tests passed.\n";
