<?php
declare(strict_types=1);
// Standalone replica tests for build 63 (2026-08-19):
// LogTranslateMessage() must use the console's real "Status Log" severity
// typing (KL_ERROR/KL_WARNING via $this->LogMessage()) so entries are
// color-coded red/yellow instead of showing up gray as "Custom" - EXCEPT
// while dispatching from MessageSink()/VM_UPDATE, where $this->LogMessage()
// is documented to crash ("InstanceInterface is not available") and the
// untyped, crash-safe global IPS_LogMessage() must still be used there.

const KL_WARNING = 10204;
const KL_ERROR = 10205;

function logTranslateMessageSimulated(bool $isInMessageSinkDispatch, bool $isError, array &$calls): void
{
    if (!$isInMessageSinkDispatch) {
        $calls[] = ['method' => 'LogMessage', 'type' => $isError ? KL_ERROR : KL_WARNING];

        return;
    }

    $calls[] = ['method' => 'IPS_LogMessage', 'prefixed' => $isError ? '[FEHLER] ' : '[WARNUNG] '];
}

// Test 1: an error logged from a SAFE context (Rescan/CheckProviders/
// ApplyChanges - isInMessageSinkDispatch = false) must use the typed
// $this->LogMessage() with KL_ERROR, so it shows up red in the Status Log.
$calls = [];
logTranslateMessageSimulated(false, true, $calls);
assert($calls[0]['method'] === 'LogMessage', 'A safe-context error must use the typed LogMessage() method');
assert($calls[0]['type'] === KL_ERROR, 'A safe-context error must be typed as KL_ERROR (red in the Status Log)');
echo "Test 1 (safe-context error uses typed LogMessage/KL_ERROR - shows red, not gray Custom) OK\n";

// Test 2: a warning logged from a safe context must use KL_WARNING (yellow).
$calls2 = [];
logTranslateMessageSimulated(false, false, $calls2);
assert($calls2[0]['method'] === 'LogMessage', 'A safe-context warning must use the typed LogMessage() method');
assert($calls2[0]['type'] === KL_WARNING, 'A safe-context warning must be typed as KL_WARNING (yellow in the Status Log)');
echo "Test 2 (safe-context warning uses typed LogMessage/KL_WARNING) OK\n";

// Test 3: THE crash-safety requirement - an error logged while dispatching
// from MessageSink()/VM_UPDATE must NEVER call the typed LogMessage()
// method (documented to crash there) - it must fall back to the untyped,
// context-independent IPS_LogMessage().
$calls3 = [];
logTranslateMessageSimulated(true, true, $calls3);
assert($calls3[0]['method'] === 'IPS_LogMessage', 'Inside MessageSink dispatch, LogMessage() must NEVER be called - it crashes there');
assert($calls3[0]['prefixed'] === '[FEHLER] ', 'Inside MessageSink dispatch, the severity must still be conveyed via a text prefix, since IPS_LogMessage() has no type');
echo "Test 3 (MessageSink dispatch still avoids the crash-prone LogMessage(), falls back to prefixed IPS_LogMessage()) OK\n";

// Test 4: a warning inside MessageSink dispatch must also use the safe
// fallback, with the warning prefix.
$calls4 = [];
logTranslateMessageSimulated(true, false, $calls4);
assert($calls4[0]['method'] === 'IPS_LogMessage', 'A MessageSink-context warning must also avoid the crash-prone method');
assert($calls4[0]['prefixed'] === '[WARNUNG] ', 'A MessageSink-context warning must use the warning text prefix');
echo "Test 4 (MessageSink-context warning also falls back safely, with the warning prefix) OK\n";

// Test 5: the try/finally around HandleTrackedVariableUpdate() must reset
// the flag even if that call throws - otherwise every LATER LogTranslateMessage
// call (in a completely unrelated, safe RequestAction) would incorrectly and
// permanently fall back to the untyped log, forever losing error/warning
// color-coding until the next full instance reload.
function messageSinkSimulated(bool $handlerThrows, array &$flagHistory): bool
{
    $isInMessageSinkDispatch = false;
    $isInMessageSinkDispatch = true;
    $flagHistory[] = $isInMessageSinkDispatch;
    try {
        if ($handlerThrows) {
            throw new RuntimeException('simulated failure inside HandleTrackedVariableUpdate');
        }
    } finally {
        $isInMessageSinkDispatch = false;
        $flagHistory[] = $isInMessageSinkDispatch;
    }

    return $isInMessageSinkDispatch;
}

$flagHistory = [];
try {
    messageSinkSimulated(true, $flagHistory);
    assert(false, 'the exception should have propagated');
} catch (RuntimeException $e) {
    // expected
}
assert($flagHistory === [true, false], 'The dispatch flag must be reset to false even when the handler throws, via try/finally');
echo "Test 5 (the MessageSink dispatch flag is reliably reset via try/finally, even on exception) OK\n";

echo "\nAll tests passed.\n";
