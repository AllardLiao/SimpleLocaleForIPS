<?php
declare(strict_types=1);
// Standalone replica test for build 76 (2026-08-19):
// User feature request ("Aufräumen", inspired by Symcon's own competing solution
// which has an equivalent function): a manual, explicit button that permanently
// removes rows from Objektnamen/Eigene Texte/Beschriftungen/Automations which no
// longer correspond to anything found by a fresh scan of the currently configured
// visualization. Deliberately NOT automatic during a normal Rescan - Rescan/
// Auto-Rescan intentionally KEEP orphaned rows (data-loss prevention against a
// temporarily wrong root category or a temporarily missing object, see
// MergeRows/MergeEnumerationOptions/MergeAutomationRows) - Cleanup is the explicit,
// deliberate opposite for when the admin has confirmed the removal is permanent.
// "Begrüßung" is excluded: it's a single, directly-configured setting, not a
// scanned/growing list, so there is nothing "orphaned" to prune there.

function filterRows(array $rows, callable $isLive, int &$removedCount): array
{
    return array_values(array_filter($rows, function (array $row) use ($isLive, &$removedCount): bool {
        $keep = $isLive($row);
        $removedCount += $keep ? 0 : 1;

        return $keep;
    }));
}

// Test 1: ObjectNames/ObjectTexts rows are kept/removed based on whether their
// ObjectID is still found by the fresh scan.
$liveNames = [10 => true, 11 => true]; // ObjectIDs 10 and 11 currently exist
$objectNameRows = [
    ['ObjectID' => 10, 'Original import' => 'Küche'],
    ['ObjectID' => 11, 'Original import' => 'Bad'],
    ['ObjectID' => 999, 'Original import' => 'Gelöschtes Objekt'],
];
$removed = 0;
$result = filterRows($objectNameRows, fn ($row) => isset($liveNames[(int) $row['ObjectID']]), $removed);
assert(count($result) === 2, 'Only the two rows whose ObjectID is still found by the fresh scan must survive');
assert($removed === 1, 'Exactly one orphaned row (ObjectID 999) must be counted as removed');
assert(array_column($result, 'ObjectID') === [10, 11], 'The surviving rows must be exactly the still-live ones, in original order');
echo "Test 1 (ObjectNames/ObjectTexts rows filtered by ObjectID presence in a fresh scan) OK\n";

// Test 2: EnumerationOptions rows are keyed by SourceKey+FieldPath (not ObjectID) -
// must use the SAME key scheme as the live scan and as MergeEnumerationOptions.
$liveOptions = ['profile:TagNacht:OPTIONS.1.Caption' => true, 'content:abc123456789:OPTIONS.1.Caption' => true];
$enumRows = [
    ['SourceKey' => 'profile:TagNacht', 'FieldPath' => 'OPTIONS.1.Caption', 'Original import' => 'Tag'],
    ['SourceKey' => 'content:abc123456789', 'FieldPath' => 'OPTIONS.1.Caption', 'Original import' => 'Ja'],
    ['SourceKey' => 'variable:99999', 'FieldPath' => 'OPTIONS.1.Caption', 'Original import' => 'Verwaist'],
];
$removed2 = 0;
$result2 = filterRows($enumRows, fn ($row) => isset($liveOptions[$row['SourceKey'] . ':' . $row['FieldPath']]), $removed2);
assert(count($result2) === 2, 'Only rows whose SourceKey:FieldPath combination the fresh scan still finds must survive');
assert($removed2 === 1, 'The orphaned variable:99999 row must be counted as removed');
echo "Test 2 (EnumerationOptions rows filtered by SourceKey:FieldPath, matching the merge key scheme) OK\n";

// Test 3: ObjectAutomations rows are keyed by AutomationID.
$liveAutomationIDs = [5 => true, 7 => true];
$automationRows = [
    ['AutomationID' => 5, 'Original import' => 'Gute Nacht'],
    ['AutomationID' => 7, 'Original import' => 'Kino'],
    ['AutomationID' => 42, 'Original import' => 'Gelöschte Automation'],
];
$removed3 = 0;
$result3 = filterRows($automationRows, fn ($row) => isset($liveAutomationIDs[(int) $row['AutomationID']]), $removed3);
assert(count($result3) === 2 && $removed3 === 1, 'Only automations still present in the tile visualization instance must survive cleanup');
echo "Test 3 (ObjectAutomations rows filtered by AutomationID) OK\n";

// Test 4: an empty/already-clean set removes nothing (no false positives).
$removed4 = 0;
$cleanRows = [['ObjectID' => 10], ['ObjectID' => 11]];
$resultClean = filterRows($cleanRows, fn ($row) => isset($liveNames[(int) $row['ObjectID']]), $removed4);
assert(count($resultClean) === 2 && $removed4 === 0, 'A set with no orphans must remove nothing and report a count of 0');
echo "Test 4 (an already-clean set removes nothing, count is 0) OK\n";

// --- Show-once-then-reset popup result pattern ---

// Test 5: reading the cleanup result attribute must happen exactly ONCE per
// GetConfigurationForm() call, captured BEFORE any recursive PopulateFormElements()
// self-call - otherwise a nested recursive call would see an already-reset (-1)
// value if the read+reset happened inside the recursive function itself.
function getConfigurationFormReplica(int &$storedAttribute): array
{
    $cleanupResultCount = $storedAttribute;
    if ($cleanupResultCount >= 0) {
        $storedAttribute = -1; // reset, exactly once, here - not inside the recursive function
    }

    return populateFormElementsReplica($cleanupResultCount, 2); // simulate 2 levels of nesting
}

function populateFormElementsReplica(int $cleanupResultCount, int $nestingDepth): array
{
    $seenValues = [$cleanupResultCount];
    if ($nestingDepth > 0) {
        $seenValues = array_merge($seenValues, populateFormElementsReplica($cleanupResultCount, $nestingDepth - 1));
    }

    return $seenValues;
}

$storedAttribute = 17;
$seenAtEachNestingLevel = getConfigurationFormReplica($storedAttribute);
assert($seenAtEachNestingLevel === [17, 17, 17], 'Every nesting level (including recursive self-calls) must see the SAME captured count (17), not a partially-reset value - this is why GetConfigurationForm() reads+resets once and threads the value through as a parameter');
assert($storedAttribute === -1, 'The stored attribute must be reset to -1 after being read once, so a later, unrelated form open does not show the same stale result again');
echo "Test 5 (cleanup result is read+reset exactly once and threaded consistently through recursive form population) OK\n";

// Test 6: a form open with nothing pending (-1) must not show anything, and must
// not reset anything that wasn't already -1.
$storedAttributeAlreadyEmpty = -1;
$seenWhenEmpty = getConfigurationFormReplica($storedAttributeAlreadyEmpty);
assert($seenWhenEmpty === [-1, -1, -1], 'With nothing pending, every nesting level must consistently see -1 (nothing to show)');
assert($storedAttributeAlreadyEmpty === -1, 'An already-empty attribute must remain -1, not be touched again');
echo "Test 6 (a form open with no pending cleanup result shows nothing, no unnecessary attribute write) OK\n";

echo "\nAll tests passed.\n";
