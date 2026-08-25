<?php
declare(strict_types=1);
// Standalone replica test for build 75 (2026-08-19):
// Live reported (screenshot): dozens of variables with IDENTICAL enum caption
// content ("Ja"/"Nein" -> "Yes"/"No", etc.) each appeared as their OWN separate row
// in "Captions", instead of being merged into one shared row like profile- and
// template-based presentations already correctly are. Root cause:
// GetPresentationSourceKey() fell back to a purely per-variable key
// ('variable:<ID>') whenever a variable's VariableCustomPresentation was set
// INLINE (no shared PROFILE name, no shared TEMPLATE GUID) - a very common pattern,
// since many Symcon device drivers write the same JSON structure directly into
// every variable's presentation rather than referencing one shared template
// object. Even though the CONTENT was byte-identical across many variables, the
// lack of a shared Symcon object identity meant each got its own unique key.
//
// Fixed by hashing the actual extracted translatable content (field path => text)
// as a fallback key when no profile/template identity exists - two variables with
// identical content now merge into the same row regardless of whether Symcon
// considers them "the same object" under the hood. Profile/template-based
// deduplication (already working correctly per the user) is completely unchanged.

function extractTranslatableFieldsReplica(array $presentation): array
{
    // Simplified replica: presentation is already {path => text}.
    return $presentation;
}

function getPresentationSourceKey(?string $profileName, ?string $templateGUID, array $fields): string
{
    if ($profileName !== null && $profileName !== '') {
        return 'profile:' . $profileName;
    }
    if ($templateGUID !== null && $templateGUID !== '') {
        return 'template:' . $templateGUID;
    }
    ksort($fields);

    return 'content:' . substr(hash('sha256', json_encode($fields)), 0, 12);
}

// Test 1: two variables with a shared PROFILE keep working exactly as before -
// identity-based key, unaffected by the content-hash fallback.
$key1 = getPresentationSourceKey('TagNacht', null, ['OPTIONS.1.Caption' => 'Tag']);
$key2 = getPresentationSourceKey('TagNacht', null, ['OPTIONS.1.Caption' => 'Tag']);
assert($key1 === 'profile:TagNacht' && $key1 === $key2, 'Profile-based presentations must keep using the identity key, unaffected by the content-hash fallback');
echo "Test 1 (profile-based dedup unaffected, still identity-keyed) OK\n";

// Test 2: two variables with a shared TEMPLATE keep working exactly as before.
$key3 = getPresentationSourceKey(null, '{ABC-123}', ['OPTIONS.0.Caption' => 'An']);
$key4 = getPresentationSourceKey(null, '{ABC-123}', ['OPTIONS.0.Caption' => 'An']);
assert($key3 === 'template:{ABC-123}' && $key3 === $key4, 'Template-based presentations must keep using the identity key, unaffected by the content-hash fallback');
echo "Test 2 (template-based dedup unaffected, still identity-keyed) OK\n";

// Test 3: THE reported bug - two DIFFERENT variables with no shared profile/
// template but IDENTICAL inline caption content must now merge onto the SAME
// content-hash key (previously: two separate 'variable:<ID>' keys).
$keyVarA = getPresentationSourceKey(null, null, ['OPTIONS.1.Caption' => 'Ja']);
$keyVarB = getPresentationSourceKey(null, null, ['OPTIONS.1.Caption' => 'Ja']);
assert($keyVarA === $keyVarB, 'Two variables with identical inline caption content (no shared profile/template) must now merge onto the same content-hash key');
assert(str_starts_with($keyVarA, 'content:'), 'The fallback key must be clearly marked as content-hash-based for the admin-facing "Profile/template" column');
echo "Test 3 (identical inline content merges onto the same content-hash key - the actual reported fix) OK\n";

// Test 4: two variables with DIFFERENT inline content must still land on
// different keys - the fix must not over-merge unrelated content.
$keyDifferent = getPresentationSourceKey(null, null, ['OPTIONS.1.Caption' => 'Nein']);
assert($keyVarA !== $keyDifferent, 'Variables with genuinely different content must NOT be merged onto the same row');
echo "Test 4 (variables with different content stay on separate keys - no over-merging) OK\n";

// Test 5: field order must not affect the hash - two logically identical field
// maps built in a different key order must still produce the same key (ksort
// before hashing).
$fieldsA = ['OPTIONS.0.Caption' => 'X', 'OPTIONS.1.Caption' => 'Y'];
$fieldsB = ['OPTIONS.1.Caption' => 'Y', 'OPTIONS.0.Caption' => 'X'];
assert(getPresentationSourceKey(null, null, $fieldsA) === getPresentationSourceKey(null, null, $fieldsB), 'Field insertion order must not affect the content-hash key - two logically identical field maps must merge');
echo "Test 5 (field order does not affect the content-hash key) OK\n";

echo "\nAll tests passed.\n";
