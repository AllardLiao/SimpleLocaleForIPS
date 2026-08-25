<?php
declare(strict_types=1);
// Standalone replica test for build 82 (2026-08-20):
// User request: on Rescan, if a row's own source language IS one of the configured
// target languages (see build 79/81), that column should not be left empty just
// because there is nothing to translate - it should be filled directly with the raw
// text (no API call needed, since source==target). The user's explicit reasoning:
// the raw text is presumably already good, and the admin can always correct it
// manually later, same as any other translated cell.

function isRowLanguageTranslationCurrentReplica(array $row, string $toField): bool
{
    return ($row[$toField] ?? '') !== ''; // simplified: build 70's staleness check, empty=never current
}

function fillLanguageColumnFromRawSourceReplica(array $rows, string $fromField, string $toField, bool $capitalizeFirst, ?array $rowIndices = null): array
{
    foreach (($rowIndices ?? array_keys($rows)) as $index) {
        $row = $rows[$index];
        $fromText = $row[$fromField] ?? '';
        if ($fromText === '' || isRowLanguageTranslationCurrentReplica($row, $toField)) {
            continue;
        }
        $rows[$index][$toField] = $capitalizeFirst ? (mb_strtoupper(mb_substr($fromText, 0, 1)) . mb_substr($fromText, 1)) : $fromText;
    }

    return $rows;
}

// Test 1: THE FEATURE - a row whose own source language matches a configured target
// (e.g. "de" is both the row's source AND now, per build 79, a real target language)
// gets that column filled with the raw text directly, instead of staying empty.
$rows = [['ORIGINAL_IMPORT' => 'Wetter', 'de' => '']];
$filled = fillLanguageColumnFromRawSourceReplica($rows, 'ORIGINAL_IMPORT', 'de', false);
assert($filled[0]['de'] === 'Wetter', 'THE FIX: an empty column matching the rows own source language must be filled with the raw text directly, not left empty');
echo "Test 1 (source-language column gets filled with the raw text directly, no translation needed) OK\n";

// Test 2: an already-filled cell (e.g. the admin manually corrected it, or it was
// already copied on a previous rescan) is never overwritten - matches the
// established "never clobber a filled/corrected cell" rule used everywhere else.
$rowsAlreadyFilled = [['ORIGINAL_IMPORT' => 'Wetter', 'de' => 'Wetterbericht (manuell korrigiert)']];
$stillFilled = fillLanguageColumnFromRawSourceReplica($rowsAlreadyFilled, 'ORIGINAL_IMPORT', 'de', false);
assert($stillFilled[0]['de'] === 'Wetterbericht (manuell korrigiert)', 'An already-filled cell (e.g. a manual admin correction) must never be overwritten by the raw-copy fallback');
echo "Test 2 (a manually-corrected cell is never overwritten by the raw-copy fallback - respects existing edits) OK\n";

// Test 3: capitalizeFirst is honored identically to a real translated column, for
// consistency between the two code paths (Object names capitalizes, Object texts
// values do not).
$rowsLowercase = [['ORIGINAL_IMPORT' => 'wetter heute', 'de' => '']];
$capitalized = fillLanguageColumnFromRawSourceReplica($rowsLowercase, 'ORIGINAL_IMPORT', 'de', true);
assert($capitalized[0]['de'] === 'Wetter heute', 'capitalizeFirst must be applied identically to the raw-copy path as to a real translation, for consistency between Object names (capitalized) and Object texts (not)');
echo "Test 3 (capitalizeFirst behaves identically on the raw-copy path as on a real translation) OK\n";

// Test 4: an empty raw source text is never copied (nothing to copy) - matches
// FillLanguageColumn's own "pending" detection, which also skips empty raw fields.
$rowsEmptySource = [['ORIGINAL_IMPORT' => '', 'de' => '']];
$stillEmpty = fillLanguageColumnFromRawSourceReplica($rowsEmptySource, 'ORIGINAL_IMPORT', 'de', false);
assert($stillEmpty[0]['de'] === '', 'An empty raw source field must never be copied into the target column - nothing to copy');
echo "Test 4 (an empty raw source is never copied - consistent with the real translation paths pending-detection) OK\n";

echo "\nAll tests passed.\n";
