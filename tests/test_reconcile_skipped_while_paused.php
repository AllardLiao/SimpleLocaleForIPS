<?php
declare(strict_types=1);
// Standalone replica test for the 2026-08-19 fix: ApplyChanges() now skips the
// entire fingerprint-check-and-reconcile pass while the provider chain is
// globally paused - not just the per-column data (already fixed separately),
// but the attempt itself. Critically, it must also NOT update the stored
// fingerprint attribute while skipping, so the reconcile is guaranteed to
// actually run again once at least one provider recovers.

function applyChangesReconcileGate(bool $globallyPaused, string $currentFingerprint, string &$storedFingerprint, int &$reconcileAttempts): void
{
    if ($globallyPaused) {
        return; // skip entirely - stored fingerprint is NOT touched
    }
    if ($currentFingerprint !== $storedFingerprint) {
        $reconcileAttempts++;
        $storedFingerprint = $currentFingerprint;
    }
}

// Test 1: a genuine Quellsprache change happens WHILE the chain is paused -
// the reconcile attempt must be skipped, and the stored fingerprint must stay
// stale so it's retried later.
$storedFingerprint = 'old-fingerprint';
$reconcileAttempts = 0;
applyChangesReconcileGate(true, 'new-fingerprint', $storedFingerprint, $reconcileAttempts);
assert($reconcileAttempts === 0, 'No reconcile attempt may happen while the provider chain is globally paused');
assert($storedFingerprint === 'old-fingerprint', 'The stored fingerprint must remain stale while paused, so the change is not silently "forgotten"');
echo "Test 1 (reconcile skipped entirely while paused, fingerprint stays stale) OK\n";

// Test 2: once providers recover (no longer globally paused), the SAME change
// (fingerprint still differs, since it was never updated) must now trigger a
// real reconcile attempt - nothing was lost by skipping it earlier.
applyChangesReconcileGate(false, 'new-fingerprint', $storedFingerprint, $reconcileAttempts);
assert($reconcileAttempts === 1, 'Once providers recover, the previously-skipped change must be reconciled exactly once');
assert($storedFingerprint === 'new-fingerprint', 'The fingerprint must now be updated, since the reconcile actually ran');
echo "Test 2 (recovery triggers the deferred reconcile exactly once, nothing lost) OK\n";

// Test 3: normal operation (never paused) is completely unaffected - a
// genuine change reconciles immediately, no change is a no-op, exactly as
// before this fix.
$storedFingerprint2 = 'fp-a';
$attempts2 = 0;
applyChangesReconcileGate(false, 'fp-a', $storedFingerprint2, $attempts2); // unchanged
assert($attempts2 === 0, 'No change, no pause -> no reconcile attempt (unchanged behavior)');
applyChangesReconcileGate(false, 'fp-b', $storedFingerprint2, $attempts2); // genuine change
assert($attempts2 === 1, 'A genuine change with no pause must still reconcile immediately (unchanged behavior)');
echo "Test 3 (normal, never-paused operation is unaffected by this fix) OK\n";

echo "\nAll tests passed.\n";
