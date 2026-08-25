<?php
declare(strict_types=1);
// Standalone replica verifying the 2026-08-15 "Greeting variable also
// tracked as Eigene Texte" bug and its fix: the Greeting section is a
// distinct, meaningful UI concept (tied to the WebFront's own "Show
// Greeting" config) and must always keep its row - so instead of dropping
// the Greeting row, the SAME variable is excluded from "Eigene Texte"
// (reversed precedence from the original ScanGreetingText() design, which
// used to make the tree scan win instead).

// --- Part 1: ExcludeGreetingVariableFromTextRows - keeps Greeting row,
// removes the colliding Eigene-Texte row ---
function excludeGreetingVariableFromTextRows(array $Rows, int $greetingValueObjectID): array
{
    if ($greetingValueObjectID === 0) {
        return $Rows;
    }
    return array_values(array_filter($Rows, function ($row) use ($greetingValueObjectID) {
        $valueObjectID = (int) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0);
        return $valueObjectID !== $greetingValueObjectID;
    }));
}

$objectTexts = [
    ['ObjectID' => 99001, 'ValueObjectID' => 51042, 'ORIGINAL_IMPORT_TEXT' => 'Guten Morgen Connor!'], // same var as Greeting
    ['ObjectID' => 17034, 'ValueObjectID' => 40412, 'ORIGINAL_IMPORT_TEXT' => 'Müllabfuhr'], // unrelated
];
$filtered = excludeGreetingVariableFromTextRows($objectTexts, 51042);
assert(count($filtered) === 1, 'Exactly one row must remain (the unrelated one)');
assert($filtered[0]['ObjectID'] === 17034, 'The unrelated row must survive untouched');
foreach ($filtered as $r) {
    assert($r['ObjectID'] !== 99001, 'The row sharing the Greeting variable must be removed from Eigene Texte');
}
echo "Test 1 (Greeting variable excluded from Eigene Texte, Greeting row untouched) OK\n";

$filteredNoGreeting = excludeGreetingVariableFromTextRows($objectTexts, 0);
assert($filteredNoGreeting === $objectTexts, 'No exclusion when no Greeting variable is configured (mode Automatic/Static/None)');
echo "Test 2 (no-op when Greeting mode is not \"Variable\") OK\n";

// --- Part 2: HandleTrackedVariableUpdate routing - once excluded from
// Eigene Texte, a live update for the shared variable now naturally falls
// through to the Greeting row (Eigene Texte no longer matches it at all,
// no special-casing needed in the routing logic itself). ---
function routeUpdate(array $textRows, array $greetingRow, int $valueObjectID): string
{
    foreach ($textRows as $row) {
        if ((int) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0) === $valueObjectID) {
            return 'eigene_texte';
        }
    }
    if (($greetingRow['ValueObjectID'] ?? 0) === $valueObjectID) {
        return 'greeting';
    }
    return 'untracked';
}

// After the fix: Eigene Texte no longer has a row for 51042 (excluded), so
// the update correctly routes to Greeting - the config form's "Begrüßung"
// section now stays live-updated, matching what the user actually sees in
// the tile.
$route = routeUpdate($filtered, ['ValueObjectID' => 51042], 51042);
assert($route === 'greeting', 'After exclusion, live updates for the shared variable must route to Greeting, not silently vanish');
echo "Test 3 (post-fix routing: shared variable updates reach the Greeting row) OK\n";

echo "\nAll tests passed.\n";
