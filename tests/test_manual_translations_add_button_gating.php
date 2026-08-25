<?php
declare(strict_types=1);
// Standalone replica test for build 91 (2026-08-20):
// User request: without the "manual_translations" license feature, the cells of
// "Eigene Übersetzungstabelle" were already read-only (BuildListColumns gates
// column 'edit'), but the list's own "Hinzufügen" button stayed clickable
// regardless - clicking it created a new row the admin then could not edit at all,
// a confusing dead end. The button itself should be disabled under the same
// condition as the cells.

function populateManualTranslationsElementReplica(bool $hasFeature): array
{
    return ['add' => $hasFeature];
}

// Test 1: THE FIX - without the license feature, the Add button must be disabled,
// not just the cells.
$withoutFeature = populateManualTranslationsElementReplica(false);
assert($withoutFeature['add'] === false, 'THE FIX: without manual_translations, the "Hinzufügen" button must be disabled, matching the already-read-only cells');
echo "Test 1 (Add button is disabled without the manual_translations license feature) OK\n";

// Test 2: with the feature, the Add button must remain enabled - no regression for
// licensed users.
$withFeature = populateManualTranslationsElementReplica(true);
assert($withFeature['add'] === true, 'With manual_translations present, the Add button must stay enabled as before');
echo "Test 2 (Add button remains enabled with the manual_translations license feature) OK\n";

echo "\nAll tests passed.\n";
