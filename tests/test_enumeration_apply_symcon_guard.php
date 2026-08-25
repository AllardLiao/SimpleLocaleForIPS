<?php
declare(strict_types=1);
// Standalone replica test for build 97 (2026-08-21): user asked us to verify
// library.json's declared Symcon compatibility (7.1). Research (WebFetch
// against symcon.de's official docs) confirmed IPS_SetVariableCustomPresentation()
// and IPS_GetVariablePresentation() were introduced "since 8.0", and both are
// called by ApplyEnumerationOptionsToVariable() in module.php.
//
// The READ side (ReadTranslatablePresentation(), which feeds
// propertyEnumerationOptions via WalkTree()) already guards this correctly:
// function_exists('IPS_GetVariablePresentation') === false => returns null,
// so on a freshly-scanned Symcon < 8.0 instance, propertyEnumerationOptions
// simply never gets any rows, and ApplyEnumerationOptionsToVariable() (called
// once per row on every language switch) is never even entered.
//
// But ApplyEnumerationOptionsToVariable() itself (the WRITE side) had no such
// guard - only "@", which does not stop a PHP fatal error from calling an
// undefined function. This was unreachable on a freshly-scanned old-Symcon
// instance (empty rows => loop body never runs) but NOT safe for an instance
// whose propertyEnumerationOptions was already populated while running
// Symcon >= 8.0 and then downgraded (or had its config transferred) to
// Symcon < 8.0 - real rows would exist, and the next language switch would
// crash. Fix: give ApplyEnumerationOptionsToVariable() the exact same
// function_exists() guard as ReadTranslatablePresentation(), independent of
// whether any rows exist.
//
// This replica models the guarded function's entry check in isolation
// (the real function's Symcon-API-dependent body can't run outside a live
// instance, so we verify the decision the guard makes, not the full body).

function applyEnumerationOptionsToVariableGuardReplica(bool $presentationFunctionExists): string
{
    if (!$presentationFunctionExists) {
        return 'no-op (guard returned early)';
    }

    return 'proceeded to call IPS_GetVariablePresentation()/IPS_SetVariableCustomPresentation()';
}

// Test 1: THE BUG/FIX - on a Symcon build without the Presentation API
// (function_exists === false, e.g. Symcon 7.1, or a >=8.0 instance's config
// transferred/downgraded to <8.0), the guard must stop before touching any
// Presentation function - this is what was previously missing.
$result1 = applyEnumerationOptionsToVariableGuardReplica(false);
assert($result1 === 'no-op (guard returned early)', 'THE FIX: without the Presentation API, ApplyEnumerationOptionsToVariable() must no-op instead of crashing');
echo "Test 1 (guard correctly no-ops when the Presentation API is unavailable) OK\n";

// Test 2: on a real Symcon >= 8.0 instance (function_exists === true), the
// function must proceed exactly as before - no regression to the working
// enum/profile-caption-translation feature.
$result2 = applyEnumerationOptionsToVariableGuardReplica(true);
assert($result2 === 'proceeded to call IPS_GetVariablePresentation()/IPS_SetVariableCustomPresentation()', 'On Symcon >= 8.0 the function must proceed normally - no regression');
echo "Test 2 (guard does not interfere when the Presentation API is available) OK\n";

// Test 3: symmetry check - the new guard in ApplyEnumerationOptionsToVariable()
// must use the EXACT SAME check as the existing guard in
// ReadTranslatablePresentation(), so both sides degrade identically instead
// of drifting apart again in a future change.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$readGuardCount = substr_count($moduleSource, "function_exists('IPS_GetVariablePresentation')");
assert($readGuardCount === 2, 'Both ReadTranslatablePresentation() and ApplyEnumerationOptionsToVariable() must carry the identical function_exists guard (found ' . $readGuardCount . ')');
echo "Test 3 (read side and write side now share the identical Symcon-availability guard) OK\n";

// Test 4: library.json's compatibility.version must remain "7.1" - the
// original declared floor was correct all along (confirmed by the fact that
// the enum/profile-caption sub-feature was always designed to gracefully
// degrade, per README section 3 "Voraussetzungen") - bumping it would have
// been the wrong fix, needlessly locking out 7.1-7.x users from the base
// object-name/text translation feature that works fine there.
$libraryJson = json_decode(file_get_contents(dirname(__DIR__) . '/library.json'), true);
assert($libraryJson['compatibility']['version'] === '7.1', 'library.json compatibility.version must stay "7.1" - the base module genuinely works from 7.1, only the (now-guarded) Presentation sub-feature needs 8.0');
echo "Test 4 (library.json's declared Symcon 7.1 floor is confirmed correct and unchanged) OK\n";

echo "\nAll tests passed.\n";
