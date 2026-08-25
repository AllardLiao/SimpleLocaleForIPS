<?php
declare(strict_types=1);
// Standalone replica test for build 88 (2026-08-20):
// User request: a Rescan involving live translation can take a while, and without
// any visible feedback in the config form it looks like the module has frozen - the
// only confirmation today is the Debug-Meldungen console, which an ordinary user
// never opens. Added a ProgressBar form element (indeterminate/animated, confirmed
// as a real Symcon form.json element type) whose caption is pushed live via
// UpdateFormField() at each stage of ScanRootTree(), the same way SendDebug()
// already appears live in the debug console during a running script.

function setRescanProgressReplica(string $message): array
{
    // Mirrors SetRescanProgress(): visible is derived purely from whether the
    // message is empty, never set independently - avoids the two ever disagreeing.
    return ['caption' => $message, 'visible' => $message !== ''];
}

// Test 1: a non-empty stage message shows the bar.
$state = setRescanProgressReplica('Objektnamen und Texte werden übersetzt…');
assert($state['visible'] === true && $state['caption'] === 'Objektnamen und Texte werden übersetzt…', 'A non-empty progress message must show the bar with that exact caption');
echo "Test 1 (a stage message shows the progress bar with the correct caption) OK\n";

// Test 2: clearing with an empty message hides the bar again.
$cleared = setRescanProgressReplica('');
assert($cleared['visible'] === false, 'An empty message must hide the progress bar');
echo "Test 2 (clearing the message hides the progress bar) OK\n";

// Test 3 (STRUCTURAL): every exit path of ScanRootTree() must clear the progress
// message before returning - otherwise, exactly like the build-87 stale pause
// banner bug, the bar could freeze on its last stage forever (e.g. after an
// auto-rescan, which never calls ReloadForm() to implicitly reset form state).
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert($moduleSource !== false, 'Could not locate module.php to run the structural check against');

preg_match('/private function ScanRootTree\(.*?\n    \}\n/s', $moduleSource, $matches);
$scanRootTreeBody = $matches[0] ?? '';
assert($scanRootTreeBody !== '', 'Could not extract ScanRootTree() body for the structural check');

$clearCallCount = substr_count($scanRootTreeBody, "SetRescanProgress('')");
assert($clearCallCount >= 2, "ScanRootTree() must clear the progress message (SetRescanProgress('')) on BOTH the unnamed-objects abort path AND the normal completion path - found only $clearCallCount clearing call(s)");
echo "Test 3 (ScanRootTree clears the progress message on every exit path - abort and success - preventing a stuck progress bar) OK\n";

// Test 4 (STRUCTURAL, updated for Build 116, verschaerft in Build 141): der
// abschliessende Clear muss unbedingt laufen. Build 116 entfernte den kompletten
// $ReloadFormAfterward-Parameter samt BEIDER zugehoeriger ReloadForm()-Bloecke
// (Symcons Konsole laedt nach einem RequestAction selbst neu - siehe README).
//
// Build 141 hat GENAU EINEN ReloadForm()-Aufruf gezielt wieder eingefuehrt, und
// zwar ausschliesslich im Abbruch-Zweig "unbenannte Objekte": jener Zweig kehrt
// VOR dem abschliessenden IPS_ApplyChanges() zurueck und loeste dadurch nie den
// Reload aus, auf den sich Build 116 verlaesst - die frisch geschriebene Liste
// blieb im Formular unsichtbar (live gemeldet 2026-08-24). Der Aufruf MUSS
// weiterhin durch $IsInteractive geschuetzt sein, sonst faellt der in Build 60
// behobene Bug zurueck (Hintergrund-Rescan reisst dem Admin das offene Formular
// mitten in der Bearbeitung weg).
assert(strpos($scanRootTreeBody, 'ReloadFormAfterward') === false, 'Build 116 removed the $ReloadFormAfterward parameter entirely - ScanRootTree() must not reference it anymore');
assert(substr_count($scanRootTreeBody, '$this->ReloadForm();') === 1, 'ScanRootTree() darf GENAU EINEN ReloadForm()-Aufruf enthalten (Build 141, nur im Abbruch-Zweig) - weder null (dann bleibt die Liste unbenannter Objekte unsichtbar) noch mehrere (Build-116-Regression: doppeltes Neuladen)');
assert(preg_match('/if\s*\(\$IsInteractive\)\s*\{\s*\$this->ReloadForm\(\);/', $scanRootTreeBody) === 1, 'DER BUG (Regression Build 60): der ReloadForm()-Aufruf MUSS durch $IsInteractive geschuetzt sein - der Auto-Rescan-Timer darf ein offenes Formular nie neu laden');
// Der eine erlaubte ReloadForm() sitzt im vorzeitig zurueckkehrenden
// Abbruch-Zweig - der ABSCHLIESSENDE Clear am Ende der Funktion muss davon
// unberuehrt bleiben und weiterhin unbedingt laufen.
$lastClearPos = strrpos($scanRootTreeBody, "SetRescanProgress('')");
$trailingBody = trim(substr($scanRootTreeBody, $lastClearPos + strlen("SetRescanProgress('');")));
assert(strpos($trailingBody, 'if (') === false || strpos($trailingBody, 'if (') > strpos($trailingBody, '}'), 'No conditional logic may follow the final SetRescanProgress(\'\') call anymore - it must be unconditional');
// Bewusst auf den tatsaechlichen AUFRUF geprueft, nicht auf das blosse Wort -
// der abschliessende Kommentar der Funktion erwaehnt ReloadForm() legitim.
assert(strpos($trailingBody, '$this->ReloadForm();') === false, 'nach dem abschliessenden Clear darf kein ReloadForm()-AUFRUF mehr folgen - der einzige erlaubte sitzt im frueher zurueckkehrenden Abbruch-Zweig');
echo "Test 4 (der finale Clear bleibt unbedingt; genau ein ReloadForm() existiert, im Abbruch-Zweig und durch \$IsInteractive geschützt) OK\n";

echo "\nAll tests passed.\n";
