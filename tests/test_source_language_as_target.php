<?php
declare(strict_types=1);
// Standalone replica test for build 79 (2026-08-19):
// User request: hide the "Original import" pseudo-language entirely and instead
// always guarantee the instance's actual source language is a REAL, persisted entry
// in propertyTargetLanguages - so it never disappears from the guest dropdown just
// because propertySourceLanguage was changed later, and old rows scanned under a
// now-abandoned source language stay reachable. Confirmed design constraint from the
// user: this must NOT let an admin bypass a licensed language-count limit by
// repeatedly switching the source language - each auto-added entry is subject to the
// EXACT SAME EnforceLicensedLanguageLimit() trimming as any manually-added target.

function ensureSourceLanguageIsTargetReplica(array $targetRows, string $sourceLanguage): array
{
    if ($sourceLanguage === '') {
        return $targetRows;
    }
    foreach ($targetRows as $row) {
        if (($row['code'] ?? '') === $sourceLanguage) {
            return $targetRows; // no-op: already present
        }
    }
    $targetRows[] = ['code' => $sourceLanguage];

    return $targetRows;
}

function enforceLicensedLanguageLimitReplica(array $rows, array $allowed, int $limit): array
{
    $filtered = $allowed === []
        ? $rows
        : array_values(array_filter($rows, function ($row) use ($allowed) {
            return in_array($row['code'] ?? '', $allowed, true);
        }));

    if ($limit > 0 && count($filtered) > $limit) {
        $filtered = array_slice($filtered, 0, $limit);
    }

    return $filtered;
}

function getSelectableLanguageCodesReplica(array $targetRows): array
{
    $codes = [];
    foreach ($targetRows as $row) {
        if (isset($row['code']) && $row['code'] !== '') {
            $codes[] = $row['code'];
        }
    }

    return $codes;
}

// Test 1: a fresh instance (empty target list) gets the source language added.
$rows = ensureSourceLanguageIsTargetReplica([], 'de');
assert($rows === [['code' => 'de']], 'A fresh instance with no configured targets must get the source language added as its first real entry');
echo "Test 1 (fresh instance: source language becomes the first real target entry) OK\n";

// Test 2: idempotent - calling again when already present changes nothing (this is
// what keeps ApplyChanges()'s re-entrant IPS_SetProperty+IPS_ApplyChanges pattern
// from looping forever).
$rows = ensureSourceLanguageIsTargetReplica([['code' => 'de'], ['code' => 'en']], 'de');
assert($rows === [['code' => 'de'], ['code' => 'en']], 'Must be a pure no-op when the source language is already a target entry');
echo "Test 2 (already-present source language: no duplicate added, no-op) OK\n";

// Test 3: THE ORIGINAL BUG SCENARIO - source language changes from de to en after a
// scan already produced rows/targets under de. The OLD source language (de) must
// stay selectable because it is now a real target entry, not because of a vanishing
// "Original" pseudo-slot tied to whatever propertySourceLanguage currently is.
$targetsAfterFirstScan = ensureSourceLanguageIsTargetReplica([], 'de'); // scan 1: source=de
assert(getSelectableLanguageCodesReplica($targetsAfterFirstScan) === ['de'], 'After the first scan, German must be selectable as the source language');
// admin now switches propertySourceLanguage to "en" and rescans
$targetsAfterSourceSwitch = ensureSourceLanguageIsTargetReplica($targetsAfterFirstScan, 'en');
$selectable = getSelectableLanguageCodesReplica($targetsAfterSourceSwitch);
assert(in_array('de', $selectable, true), 'THE FIX: German must remain selectable after switching source to English - it is now a real target entry, not a vanished pseudo-slot');
assert(in_array('en', $selectable, true), 'English (the new source) must also be selectable');
echo "Test 3 (switching source language no longer makes the OLD source language disappear from the guest dropdown - the original reported bug) OK\n";

// Test 4: license-limit abuse prevention - a license capped at 1 target language,
// already at its cap, has its source language changed. The newly-added source entry
// is subject to the SAME trim as any manual entry - it can itself be cut if it
// pushes the count over the limit, exactly as the user required ("sonst könnte ein
// User einfach verschiedene Quellsprachen konfigurieren ... unbegrenzt Zielsprachen").
$rowsAtCap = [['code' => 'fr']]; // license limit=1, already fully used by "fr"
$rowsWithNewSource = ensureSourceLanguageIsTargetReplica($rowsAtCap, 'es'); // source switched to "es"
assert(count($rowsWithNewSource) === 2, 'Before license enforcement runs, the new source entry IS added (EnsureSourceLanguageIsTarget itself does not know about the license)');
$rowsAfterLimit = enforceLicensedLanguageLimitReplica($rowsWithNewSource, [], 1);
assert($rowsAfterLimit === [['code' => 'fr']], 'EnforceLicensedLanguageLimit() must trim the just-added source-language entry like any other when the license cap is already exhausted - no free bypass');
echo "Test 4 (license-limit abuse prevention: an auto-added source-language entry is trimmed by the existing cap exactly like a manual one, no unlimited-language loophole via repeated source switching) OK\n";

// Test 5: repeated source-language churn (the exact abuse scenario described by the
// user) never grows the selectable set beyond the license limit, because each
// EnsureSourceLanguageIsTarget() call is immediately followed by the SAME
// EnforceLicensedLanguageLimit() trim in the real ApplyChanges() flow.
$rows = [];
foreach (['de', 'en', 'fr', 'es', 'it'] as $sourceLanguage) {
    $rows = ensureSourceLanguageIsTargetReplica($rows, $sourceLanguage);
    $rows = enforceLicensedLanguageLimitReplica($rows, [], 2); // license limit=2
}
assert(count($rows) <= 2, 'Repeatedly switching the source language must never let the selectable set exceed the licensed limit, even after many switches');
echo "Test 5 (churning through 5 different source languages under a limit=2 license never exceeds 2 selectable languages) OK\n";

echo "\nAll tests passed.\n";
