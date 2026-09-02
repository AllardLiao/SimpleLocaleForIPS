<?php
declare(strict_types=1);
// Standalone replica tests for the 2026-08-16 emergency-stop fix (build 53):
// after the per-row Quellsprache feature (build 52) went live, ApplyChanges()
// runs ReconcileRowSourceLanguageChanges() on every invocation - including the
// re-entrant ApplyChanges() call ApplyTrackedVariableUpdate() fires on every
// single VM_UPDATE for a tracked variable. For an active weather/sensor
// variable that can happen many times a minute, and burned through the
// configured translation providers' quotas ("User Rate Limit Exceeded" /
// "USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY" seen in the live debug log).
//
// Two independent mitigations are tested here:
// 1. A cheap fingerprint short-circuit: the expensive per-row reconcile scan
//    only runs when a row's Quellsprache actually changed since the last
//    ApplyChanges() call, not on every single call.
// 2. A hard "Aktiv" kill switch: when off, ScanRootTree/HandleTrackedVariableUpdate
//    short-circuit before any translation work, regardless of anything else.

// --- Fingerprint circuit breaker replica -----------------------------------

function computeFingerprint(array $propertiesRows): string
{
    $parts = [];
    foreach ($propertiesRows as $rows) {
        foreach ($rows as $row) {
            $parts[] = (string) ($row['Source language'] ?? '');
        }
    }
    return md5(implode('|', $parts));
}

function applyChangesReconcileGate(array $propertiesRows, string &$storedFingerprint, int &$reconcileCallCount): void
{
    $current = computeFingerprint($propertiesRows);
    if ($current !== $storedFingerprint) {
        $reconcileCallCount++;
        $storedFingerprint = $current;
    }
}

// Test 1: identical row state across many repeated ApplyChanges() calls (the
// VM_UPDATE storm scenario) must trigger the expensive reconcile scan AT MOST
// ONCE, not on every call.
$rows = [
    'ObjectNames' => [
        ['ObjectID' => 1, 'Source language' => 'de'],
        ['ObjectID' => 2, 'Source language' => 'de'],
    ],
    'ObjectTexts' => [
        ['ObjectID' => 3, 'Source language' => 'de'],
    ],
];
$storedFingerprint = ''; // fresh attribute default
$reconcileCallCount = 0;
for ($i = 0; $i < 50; $i++) {
    applyChangesReconcileGate($rows, $storedFingerprint, $reconcileCallCount);
}
assert($reconcileCallCount === 1, 'Reconcile must run exactly once across 50 identical-state ApplyChanges() calls, not 50 times - this is the actual quota-exhaustion bug');
echo "Test 1 (50x identical ApplyChanges calls -> reconcile runs exactly once) OK\n";

// Test 2: a genuine Quellsprache edit on one row must still trigger exactly
// one more reconcile pass (the mechanism must not be permanently disabled).
$rows['ObjectNames'][0]['Source language'] = 'en'; // admin edits row 1's Quellsprache
applyChangesReconcileGate($rows, $storedFingerprint, $reconcileCallCount);
assert($reconcileCallCount === 2, 'A genuine Quellsprache change must still trigger exactly one more reconcile pass');
// ...and then further repeated calls with the now-stable new state must again
// settle back to zero additional reconciles.
for ($i = 0; $i < 20; $i++) {
    applyChangesReconcileGate($rows, $storedFingerprint, $reconcileCallCount);
}
assert($reconcileCallCount === 2, 'After the state stabilizes again, repeated ApplyChanges() calls must not keep re-triggering reconcile');
echo "Test 2 (genuine Quellsprache edit still reconciles once, then settles again) OK\n";

// --- Active kill switch replica ---------------------------------------------

function scanRootTreeGated(bool $active, int &$translateCallCount): string
{
    if (!$active) {
        return 'IS_INACTIVE';
    }
    $translateCallCount++; // stand-in for the real (expensive) scan+translate work
    return 'IS_ACTIVE';
}

function handleTrackedVariableUpdateGated(bool $active, int &$translateCallCount): void
{
    if (!$active) {
        return;
    }
    $translateCallCount++;
}

// Test 3: with Active=false, neither Rescan nor VM_UPDATE-triggered live
// retranslation may ever call into the (expensive/quota-consuming) translate
// path, no matter how many times they fire.
$translateCallCount = 0;
$status = scanRootTreeGated(false, $translateCallCount);
for ($i = 0; $i < 30; $i++) {
    handleTrackedVariableUpdateGated(false, $translateCallCount);
}
assert($status === 'IS_INACTIVE', 'ScanRootTree must report IS_INACTIVE when the Active switch is off');
assert($translateCallCount === 0, 'With Active=false, zero translation work may happen regardless of how many VM_UPDATEs or Rescans fire - this is the emergency stop');
echo "Test 3 (Active=false: zero translation calls, no matter how many triggers fire) OK\n";

// Test 4: flipping Active back to true must immediately restore normal
// operation (not a one-way/sticky lock).
$translateCallCount = 0;
$status = scanRootTreeGated(true, $translateCallCount);
handleTrackedVariableUpdateGated(true, $translateCallCount);
assert($status === 'IS_ACTIVE', 'ScanRootTree must resume normally once Active is true again');
assert($translateCallCount === 2, 'Translation work must resume immediately once re-activated');
echo "Test 4 (Active=true again: normal operation resumes immediately) OK\n";

echo "\nAll tests passed.\n";
