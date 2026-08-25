<?php
declare(strict_types=1);
// Standalone replica test for the 2026-08-18 status-consistency fix (build 57):
// live observed a second inconsistency after the escalating-backoff fix - the
// config-form panel breakdown (BuildProviderPauseStatusText, always computed
// fresh from the raw pause attribute) correctly listed ALL THREE providers as
// paused, but the instance status badge still showed generic "Active" (102),
// because SetStatus(STATUS_TRANSLATE_PAUSED) was previously only ever called
// REACTIVELY from inside TranslateChunk() at the moment of an actual translate
// attempt - if no Rescan/VM_UPDATE had fired since providers became fully
// paused, the status badge just kept showing whatever it was set to last.
// Fix: RefreshTranslateChainStatus() re-evaluates 102 vs 205 fresh every time
// ApplyChanges() runs AND every time the config form is merely opened.

const STATUS_ACTIVE = 102;
const STATUS_TRANSLATE_ERROR = 203;
const STATUS_TRANSLATE_PAUSED = 205;
const STATUS_ROOT_CATEGORY_MISSING = 201;
const STATUS_TRIAL_EXPIRED = 204;

function refreshTranslateChainStatus(int $currentStatus, ?int $globalPauseUntil): int
{
    // Only the three "generic" translate-chain statuses are re-evaluated here -
    // root-missing/trial-expired take priority and are left untouched.
    if (!in_array($currentStatus, [STATUS_ACTIVE, STATUS_TRANSLATE_ERROR, STATUS_TRANSLATE_PAUSED], true)) {
        return $currentStatus;
    }

    return $globalPauseUntil !== null ? STATUS_TRANSLATE_PAUSED : STATUS_ACTIVE;
}

// ---------------------------------------------------------------------------
// Test 1: THE reported bug - status was "Active" (stale, from before providers
// became fully paused), no translate attempt has happened since, but the panel
// breakdown (i.e. GetGlobalPauseUntil) now says all three are paused. Merely
// opening the config form (or the next ApplyChanges()) must correct the badge.
$status = refreshTranslateChainStatus(STATUS_ACTIVE, 1000000 + 3600);
assert($status === STATUS_TRANSLATE_PAUSED, 'A stale "Active" status must flip to "paused" as soon as the pause state is checked again, even without a fresh translate attempt');
echo "Test 1 (stale Active status corrects itself to Paused on next check) OK\n";

// Test 2: symmetric case - status was "Paused" from before, but providers have
// since recovered (GetGlobalPauseUntil now null) - must flip back to Active.
$status2 = refreshTranslateChainStatus(STATUS_TRANSLATE_PAUSED, null);
assert($status2 === STATUS_ACTIVE, 'A stale "Paused" status must flip back to Active once providers have recovered, without waiting for a new translate attempt');
echo "Test 2 (stale Paused status recovers to Active once providers are free again) OK\n";

// Test 3: STATUS_TRANSLATE_ERROR (a genuine one-off failure, not a full pause)
// must ALSO be reconciled - if it later turns out all providers are paused, the
// more informative "Paused" status should take over.
$status3 = refreshTranslateChainStatus(STATUS_TRANSLATE_ERROR, 1000000 + 900);
assert($status3 === STATUS_TRANSLATE_PAUSED, 'A generic translate-error status must be superseded by Paused once the pause state is confirmed');
echo "Test 3 (translate-error status upgrades to Paused when applicable) OK\n";

// ---------------------------------------------------------------------------
// Test 4: higher-priority statuses (root category missing, trial expired) must
// NEVER be silently overwritten by this refresh, regardless of pause state -
// those are decided by entirely different, more urgent checks.
$status4 = refreshTranslateChainStatus(STATUS_ROOT_CATEGORY_MISSING, 1000000 + 900);
assert($status4 === STATUS_ROOT_CATEGORY_MISSING, 'Root-category-missing must never be overwritten by the pause-status refresh - it has priority');

$status5 = refreshTranslateChainStatus(STATUS_TRIAL_EXPIRED, 1000000 + 900);
assert($status5 === STATUS_TRIAL_EXPIRED, 'Trial-expired must never be overwritten by the pause-status refresh - it has priority');
echo "Test 4 (higher-priority statuses are never touched by the pause refresh) OK\n";

// Test 5: no-op case - status is already correct, refreshing must not change it.
assert(refreshTranslateChainStatus(STATUS_ACTIVE, null) === STATUS_ACTIVE, 'Already-correct Active status must stay Active');
assert(refreshTranslateChainStatus(STATUS_TRANSLATE_PAUSED, 1000000 + 900) === STATUS_TRANSLATE_PAUSED, 'Already-correct Paused status must stay Paused');
echo "Test 5 (already-correct status is a no-op) OK\n";

echo "\nAll tests passed.\n";
