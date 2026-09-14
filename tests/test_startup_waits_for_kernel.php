<?php
// Build 210 (live: SymBox startete neun Tage lang nicht mehr). Symcon ruft
// ApplyChanges() schon waehrend des eigenen Starts auf. Simple Locale fing dort
// sofort mit dem Quellsprachen-Abgleich an - bei 630 Zeilen dauerte das auf der
// SymBox mehrere Minuten, Symcon wurde nie fertig, nach etwa zwei Minuten neu
// gestartet und speicherte nie etwas. Beim naechsten Start dasselbe von vorn.
//
// Symmetrie-Check: ApplyChanges() wartet beim Start auf IPS_KERNELSTARTED, bevor
// es irgendetwas anderes tut, und MessageSink holt den Durchlauf danach nach.
declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');

$applyStart = strpos($source, 'public function ApplyChanges(): void');
$applyEnd = strpos($source, 'public function MessageSink(', $applyStart);
$apply = substr($source, $applyStart, $applyEnd - $applyStart);

$parentCall = strpos($apply, 'parent::ApplyChanges();');
$register = strpos($apply, '$this->RegisterMessage(0, IPS_KERNELSTARTED);');
$guard = strpos($apply, "if (IPS_GetKernelRunlevel() !== KR_READY) {\n            return;\n        }");
$firstWork = strpos($apply, '$this->FlushPendingTrackedRowUpdates();');

assert($parentCall !== false && $register !== false && $guard !== false && $firstWork !== false, 'Startsperre in ApplyChanges() fehlt');
assert($parentCall < $register && $register < $guard, 'die Startsperre muss direkt nach parent::ApplyChanges() kommen');
assert($guard < $firstWork, 'vor der Startsperre darf keine eigentliche Arbeit stattfinden');
echo "Test 1 (ApplyChanges wartet beim Start auf den Kernel) OK\n";

$sinkStart = strpos($source, 'public function MessageSink(');
$sinkEnd = strpos($source, "\n    }\n", $sinkStart);
$sink = substr($source, $sinkStart, $sinkEnd - $sinkStart);
assert(strpos($sink, "if (\$Message === IPS_KERNELSTARTED) {\n            \$this->ApplyChanges();") !== false, 'MessageSink muss den zurueckgestellten ApplyChanges()-Durchlauf nachholen');
echo "Test 2 (MessageSink holt den Durchlauf nach dem Start nach) OK\n";

echo "\nAll tests passed.\n";
