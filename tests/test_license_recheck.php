<?php
// Standalone simulation of the fixed TrackLicenseActivationIfNew() in
// SimpleLocaleForIPS/SimpleLocale/module.php. Mirrors the real method body
// (copy-adapted, $this-> calls replaced by injected fixture state/closures)
// since there is no live Symcon instance available here. Verifies the bug
// fix: switching back to a previously-activated key must re-check with the
// server, instead of silently trusting the old local log entry forever.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

/**
 * Mirrors TrackLicenseActivationIfNew() + RecordLicenseActivation(), backed
 * by injected mutable state instead of $this->Read/WriteAttributeString.
 */
final class LicenseTrackerSim
{
    public string $lastCheckedHash = '';
    public string $blockedHash = '';
    public array $log = [];
    public array $serverCalls = []; // sequence of keyHash values actually reported
    public array $serverBlockResponses = []; // keyHash => bool, what the fake server says

    public function track(string $key, bool $valid, bool $blockedLocally, bool $allowRecheck = false): void
    {
        // GetLicenseInfo(): 'valid' or 'blocked' must be true to proceed - mirrors
        // the real early-return for an empty/invalid/expired key.
        if (!$valid && !$blockedLocally) {
            $this->lastCheckedHash = '';

            return;
        }

        $keyHash = hash('sha256', $key);
        $licensee = 'max@mustermann.de';
        $recheckBlocked = $allowRecheck && $this->blockedHash === $keyHash;

        if (!$recheckBlocked && $this->lastCheckedHash === $keyHash) {
            return;
        }

        $this->lastCheckedHash = $keyHash;

        $this->recordActivation($keyHash, $licensee);
    }

    private function recordActivation(string $keyHash, string $licensee): void
    {
        $this->log[] = ['licenseKeyHash' => $keyHash, 'licensee' => $licensee, 'activatedAt' => time()];
        $this->serverCalls[] = $keyHash;

        $blocked = $this->serverBlockResponses[$keyHash] ?? false;
        if ($blocked) {
            $this->blockedHash = $keyHash;
        } elseif ($this->blockedHash === $keyHash) {
            $this->blockedHash = '';
        }
    }
}

$hashA = hash('sha256', 'KEY_A_LIGHT');
$hashB = hash('sha256', 'KEY_B_STANDARD');

// ---- Scenario 1: repeated ApplyChanges() with the SAME unchanged key must
// NOT spam the server (noise-reduction goal preserved). --------------------
$sim = new LicenseTrackerSim();
$sim->track('KEY_A_LIGHT', true, false);
$sim->track('KEY_A_LIGHT', true, false); // e.g. user toggled an unrelated checkbox + "Übernehmen"
$sim->track('KEY_A_LIGHT', true, false);
assertTrue($sim->serverCalls === [$hashA], 'unchanged key across repeated ApplyChanges: server contacted exactly once');

// ---- Scenario 2: switching to a genuinely new key re-checks (this already
// worked before the fix). ---------------------------------------------------
$sim->track('KEY_B_STANDARD', true, false);
assertTrue($sim->serverCalls === [$hashA, $hashB], 'switching to a new key triggers a fresh server check');

// ---- Scenario 3 (THE BUG): switching BACK to key A - which was already
// logged once in scenario 1, and has since become blocked server-side (e.g.
// consumed by an upgrade purchase) - must re-check and pick up the block.
// The OLD code compared against the full historical log, found the old
// (hashA, licensee) entry, and skipped the server call entirely here.
$sim->serverBlockResponses[$hashA] = true; // shop/upgrade webhook marked key A as upgraded/blocked in the meantime
$sim->track('KEY_A_LIGHT', true, false);
assertTrue($sim->serverCalls === [$hashA, $hashB, $hashA], 'switching back to a previously-seen key re-checks the server (bug fix)');
assertTrue($sim->blockedHash === $hashA, 'server-reported block on the reused key is now picked up locally');

// ---- Scenario 4: once blocked, GetLicenseInfo() would report valid=false,
// blocked=true for key A - HasFullLicense()/trial gating downstream would
// therefore correctly stop treating this instance as licensed. Confirm the
// "invalid but blocked" branch still allows a recheck when explicitly
// requested (AllowRecheck=true, from the "Lizenz aktivieren" button), even
// though the hash didn't change since the last (blocking) check.
$sim->serverBlockResponses[$hashA] = false; // Kai manually unblocked it server-side
$sim->track('KEY_A_LIGHT', false, true, true); // valid=false but blocked=true, AllowRecheck=true (button click)
assertTrue(end($sim->serverCalls) === $hashA, 'explicit "Lizenz aktivieren" click on a known-blocked key rechecks even without a hash change');
assertTrue($sim->blockedHash === '', 'server-side unblock is picked up locally after the explicit recheck');

// ---- Scenario 5: removing the key entirely, then re-entering the SAME key
// later, must also re-check (closes the same class of loophole via the
// empty/invalid state instead of an intermediate different key). ----------
$sim2 = new LicenseTrackerSim();
$sim2->track('KEY_A_LIGHT', true, false);
$sim2->track('', false, false); // key field cleared
assertTrue($sim2->lastCheckedHash === '', 'clearing the key resets the last-checked marker');
$sim2->serverBlockResponses[$hashA] = true; // meanwhile blocked server-side
$sim2->track('KEY_A_LIGHT', true, false); // re-entering the exact same key
assertTrue($sim2->serverCalls === [$hashA, $hashA], 'removing then re-entering the same (now-blocked) key re-checks too');
assertTrue($sim2->blockedHash === $hashA, 'block picked up on re-entry after removal');

echo "\nAll license-recheck simulations passed.\n";
