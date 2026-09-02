<?php
declare(strict_types=1);
// Standalone replica test for build 89 (2026-08-20):
// User request ("Eigene Übersetzungstabelle"): an admin-maintained glossary table
// (Source Language + one column per target language, rows added via the list's own
// "Hinzufügen" button) that takes priority over ANY automatic translation - even
// over the internal auto-cache, since a manual entry is a deliberate override that
// should always win. A per-cell override: an empty cell for a specific target
// language does NOT block that language from being auto-translated normally: only
// languages the admin actually filled in are overridden. Gated from Standard
// license upward (feature "manual_translations", distinct from "edit_translations")
// per explicit product decision.

function findManualTranslationReplica(array $rows, string $sourceLanguage, string $targetLanguage, string $text): ?string
{
    foreach ($rows as $row) {
        $rowSourceLanguage = (string) ($row['Source language'] ?? '');
        $rowSourceText = (string) ($row['ORIGINAL_IMPORT'] ?? '');
        if ($rowSourceLanguage !== $sourceLanguage || $rowSourceText !== $text) {
            continue;
        }
        $translation = (string) ($row[$targetLanguage] ?? '');
        if ($translation !== '') {
            return $translation;
        }
    }

    return null;
}

function translateBatchReplica(array $texts, string $source, string $target, array $manualRows, array $cache, array $providerResultsByText, bool $hasFeature): array
{
    $manualTranslations = $hasFeature ? $manualRows : [];
    $results = [];
    foreach ($texts as $i => $text) {
        $manual = findManualTranslationReplica($manualTranslations, $source, $target, $text);
        if ($manual !== null) {
            $results[$i] = $manual;
            continue;
        }
        $cached = $cache["$source|$target|$text"] ?? null;
        if ($cached !== null) {
            $results[$i] = $cached;
            continue;
        }
        $results[$i] = $providerResultsByText[$text] ?? '';
    }

    return $results;
}

// Test 1: THE FEATURE - a manual glossary entry is used instead of the provider
// result, even though the provider WOULD have returned something different.
$rows = [['Source language' => 'de', 'ORIGINAL_IMPORT' => 'Cover', 'es' => 'Funda']]; // deliberately different from what a provider might say
$result1 = translateBatchReplica(['Cover'], 'de', 'es', $rows, [], ['Cover' => 'Cubierta'], true);
assert($result1[0] === 'Funda', 'THE FEATURE: a manual glossary entry must be used instead of the automatic provider result, even when they disagree');
echo "Test 1 (a manual glossary entry overrides the automatic provider result) OK\n";

// Test 2: manual entries take priority over the auto-cache too, not just live
// provider calls - a deliberate admin override must always win.
$result2 = translateBatchReplica(['Cover'], 'de', 'es', $rows, ['de|es|Cover' => 'CachedWrongValue'], [], true);
assert($result2[0] === 'Funda', 'A manual entry must take priority over an already-cached automatic translation too, not just a fresh provider call');
echo "Test 2 (a manual entry outranks the internal auto-cache as well) OK\n";

// Test 3: PER-CELL override - a glossary row with an EMPTY cell for one specific
// target language must NOT block that language from being translated normally;
// only languages the admin actually filled in are overridden.
$rowsPartial = [['Source language' => 'de', 'ORIGINAL_IMPORT' => 'Cover', 'es' => 'Funda']]; // no 'en' cell filled in
$result3 = translateBatchReplica(['Cover'], 'de', 'en', $rowsPartial, [], ['Cover' => 'Cover'], true);
assert($result3[0] === 'Cover', 'An empty cell for a specific target language in an otherwise-matching glossary row must fall through to normal automatic translation for THAT language');
echo "Test 3 (an empty target-language cell in a matching row falls through to normal auto-translation for that language only) OK\n";

// Test 4: exact match required - a different source language or non-identical
// source text must NOT match a glossary row (avoids surprising, hard-to-debug
// partial/fuzzy matches).
assert(findManualTranslationReplica($rows, 'en', 'es', 'Cover') === null, 'A glossary row authored for German source text must not match when translating FROM a different source language');
assert(findManualTranslationReplica($rows, 'de', 'es', 'cover') === null, 'An inexact (case-differing) source text must not match - exact string comparison only, no surprising fuzzy matches');
echo "Test 4 (glossary matching requires an exact source-language AND source-text match, no fuzzy matching) OK\n";

// Test 5: license gating - without the "manual_translations" feature (e.g. a
// license tier below Standard), the glossary is never consulted at all, even if
// rows exist in the property.
$result5 = translateBatchReplica(['Cover'], 'de', 'es', $rows, [], ['Cover' => 'Cubierta'], false);
assert($result5[0] === 'Cubierta', 'Without the manual_translations license feature, existing glossary rows must be completely ignored, falling through to the normal automatic translation');
echo "Test 5 (glossary lookup is fully inert without the manual_translations license feature, per the Standard-tier-and-up decision) OK\n";

echo "\nAll tests passed.\n";
