<?php
declare(strict_types=1);
// Standalone replica test for build 103 (2026-08-21): user-confirmed live finding.
// A Meldungen-log entry read "alle Anbieter der Kette (deepl [pausiert], google
// [pausiert], free) haben 'de' -> 'es' abgelehnt (... 77 Text(e), erster Text:
// 'Echo Info')" - looked like even a trivial 9-character word was rejected. The
// user then pasted the actual FreeTranslate_Response debug line for "Echo Info":
// HTTP 200, quotaFinished:false, translatedText:"Echo Info" - a genuine SUCCESS.
//
// Root cause: TranslateSingleFree() returned null (the same signal as a real
// failure) whenever a text exceeded MyMemory's 500-byte limit. Its caller,
// TranslateChunkFree() - MyMemory has no real batch endpoint, one HTTP request
// per text - aborts the ENTIRE chunk and discards every already-successfully-
// translated text the instant it sees a single null. With both paid providers
// (deepl, google) also paused at the time, one oversized text anywhere in a
// 77-text batch (not necessarily "Echo Info", just coincidentally the first
// text listed in the summary log line) dragged all 76 other, perfectly
// translatable texts down with it.
//
// Fix: TranslateSingleFree() now returns '' (the same "nothing to do, move on"
// signal already used for a blank input) instead of null for an oversized text
// - TranslateChunkFree() keeps going instead of aborting; only the oversized
// text's own cell stays empty (retried on the next Rescan, ideally via a paid
// provider without this limit by then).

function translateSingleFreeReplica(string $text, bool $isOversized): ?string
{
    if (trim($text) === '') {
        return '';
    }
    if ($isOversized) {
        return ''; // THE FIX (was: return null;)
    }

    return $text; // stand-in for a real successful MyMemory response
}

function translateChunkFreeReplica(array $texts, array $oversizedFlags): ?array
{
    $results = [];
    foreach ($texts as $i => $text) {
        $translated = translateSingleFreeReplica($text, $oversizedFlags[$i] ?? false);
        if ($translated === null) {
            return null;
        }
        $results[] = $translated;
    }

    return $results;
}

// Test 1: THE REPORTED CASE - a 77-text batch where exactly one text (not
// necessarily the first) is oversized must still translate all 76 others
// successfully, instead of the whole batch coming back null.
$texts = array_fill(0, 76, 'Echo Info');
$texts[] = str_repeat('x', 947); // the oversized one, e.g. the "Drei Lichter..." row
$oversized = array_fill(0, 76, false);
$oversized[] = true;
$result1 = translateChunkFreeReplica($texts, $oversized);
assert($result1 !== null, 'THE FIX: a batch containing one oversized text must NOT come back null overall');
assert(count($result1) === 77, 'All 77 positions must be present in the result (76 real translations + 1 empty placeholder)');
assert($result1[0] === 'Echo Info', 'A normal, short text (e.g. "Echo Info") must still translate successfully');
assert($result1[76] === '', 'The oversized text itself must resolve to an empty placeholder, not abort the batch');
echo "Test 1 (one oversized text among 77 no longer discards the other 76 successful translations) OK\n";

// Test 2: the oversized text can be FIRST in the batch too - same outcome,
// order must not matter.
$texts2 = [str_repeat('y', 900), 'Echo Info', 'Hola'];
$oversized2 = [true, false, false];
$result2 = translateChunkFreeReplica($texts2, $oversized2);
assert($result2 !== null, 'An oversized FIRST text must not abort the batch either');
assert($result2[0] === '' && $result2[1] === 'Echo Info' && $result2[2] === 'Hola', 'Only the oversized position is empty, the rest translate normally regardless of position');
echo "Test 2 (position of the oversized text within the batch does not matter) OK\n";

// Test 3: a batch of exclusively normal-sized texts is completely unaffected -
// no regression for the common case.
$texts3 = ['Hallo', 'Welt', 'Test'];
$oversized3 = [false, false, false];
$result3 = translateChunkFreeReplica($texts3, $oversized3);
assert($result3 === ['Hallo', 'Welt', 'Test'], 'A batch with no oversized text must translate exactly as before');
echo "Test 3 (a batch with no oversized text is completely unaffected) OK\n";

// Test 4: symmetry check - the real module.php must return '' (not null) for
// the oversized-text branch inside TranslateSingleFree(). A null there would
// abort the ENTIRE batch (see TranslateChunkFree) - exactly the bug this test
// was written for.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$functionStart = strpos($moduleSource, 'private function TranslateSingleFree');
$functionSnippet = substr($moduleSource, $functionStart, 2500);
$branchStart = strpos($functionSnippet, '> 500');
assert($branchStart !== false, 'die 500-Byte-Grenze muss weiterhin geprueft werden');
// Zweig-Ende: bis zur naechsten Anweisung nach dem Block (dem $email-Lesen).
$branchBody = substr($functionSnippet, $branchStart, strpos($functionSnippet, '$email') - $branchStart);
assert(strpos($branchBody, "return '';") !== false, 'TranslateSingleFree() must return an empty string when the text exceeds the 500-byte limit');
assert(strpos($branchBody, 'return null') === false, 'DER BUG: ein null in diesem Zweig wuerde den GESAMTEN Batch abbrechen (siehe TranslateChunkFree)');
echo "Test 4 (the real function returns '' instead of null for an oversized text) OK\n";

// Test 5 (Build 150, live gemeldeter Diagnose-Fehlgriff): das Ueberspringen
// muss GELOGGT werden. Vorher passierte es wortlos - fuer den Aufrufer sah das
// aus wie "nichts zu uebersetzen", die Zelle blieb leer, und im Log stand
// nichts. Die Fehlersuche landete dadurch zwangslaeufig bei den falschen
// Verdaechtigen (Kontingent, Sprachpaarung, Anbieter).
assert(strpos($branchBody, 'LogTranslateMessage') !== false, 'DER DIAGNOSE-FEHLGRIFF: ein wegen der Byte-Grenze uebersprungener Text muss im Log auftauchen, sonst sucht der Nutzer die Ursache garantiert an der falschen Stelle');
assert(strpos($branchBody, '$byteLength') !== false, 'die Meldung muss die tatsaechliche Bytezahl nennen - die sichtbare Textlaenge fuehrt bei Umlauten in die Irre');
echo "Test 5 (das Überspringen wegen der Byte-Grenze wird geloggt, mit echter Bytezahl) OK\n";

echo "\nAll tests passed.\n";
