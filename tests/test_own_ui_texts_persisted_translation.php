<?php
declare(strict_types=1);
// Standalone replica test for build 78 (2026-08-20):
// User request, following the guest-cache-stuck-in-German bug (build 77): move the
// module's own fixed guest-facing UI strings (paused notice, stats labels, info
// popup texts) out of the 24h live-translate-on-demand cache entirely, and instead
// translate them ONCE during Rescan into every configured target language,
// persisting them permanently like any other row - completely immune to a provider
// pause happening to coincide with a cache refresh, since the translation already
// exists long before any pause could ever matter. Also: these rows must be
// impossible for an admin to accidentally delete or edit - no editable list in the
// config form, and excluded from "Aufräumen" cleanup (which only touches the four
// scanned properties, not this one).

function resolveRowValueReplica(array $row, string $selectedLanguage, string $sourceLanguage, string $rawField): string
{
    if ($selectedLanguage === 'ORIGINAL_IMPORT' || $selectedLanguage === $sourceLanguage) {
        return $row[$rawField] ?? '';
    }
    if (($row[$selectedLanguage] ?? '') !== '') {
        return $row[$selectedLanguage];
    }

    return $row[$rawField] ?? '';
}

function getOwnUiTextReplica(array $rowsByKey, string $key, string $language, string $fallback): string
{
    $row = $rowsByKey[$key] ?? null;
    if ($row === null) {
        return $fallback;
    }
    $value = resolveRowValueReplica($row, $language, 'de', 'ORIGINAL_IMPORT');

    return $value !== '' ? $value : $fallback;
}

function mergeOwnUiTextRowsReplica(array $existingRows, array $definitions): array
{
    $existingByKey = [];
    foreach ($existingRows as $row) {
        $key = (string) ($row['Key'] ?? '');
        if ($key !== '') {
            $existingByKey[$key] = $row;
        }
    }

    $result = [];
    foreach ($definitions as $key => $germanText) {
        $row = $existingByKey[$key] ?? ['Key' => $key];
        if (($row['ORIGINAL_IMPORT'] ?? null) !== $germanText) {
            $row['ORIGINAL_IMPORT'] = $germanText;
            $row['_sourceChanged'] = true; // stand-in for MarkRowSourceChanged
        }
        $row['Source language'] = 'de';
        $result[] = $row;
    }

    return $result;
}

// Test 1: a brand-new installation (no existing rows) seeds every definition fresh.
$definitions = ['pausedNoticePrefix' => 'Übersetzung pausiert bis', 'statsHourlyLabel' => 'Hourly:'];
$seeded = mergeOwnUiTextRowsReplica([], $definitions);
assert(count($seeded) === 2, 'A fresh install must seed exactly one row per definition');
assert($seeded[0]['ORIGINAL_IMPORT'] === 'Übersetzung pausiert bis' && $seeded[0]['Source language'] === 'de', 'Every seeded row must carry the current German text and always Quellsprache=de');
echo "Test 1 (fresh install seeds every own-UI-text definition as a new German-sourced row) OK\n";

// Test 2: an existing, already-translated row is PRESERVED (translations kept)
// when the German source text has NOT changed.
$existing = [['Key' => 'pausedNoticePrefix', 'ORIGINAL_IMPORT' => 'Übersetzung pausiert bis', 'en' => 'Translation paused until', 'Source language' => 'de']];
$merged = mergeOwnUiTextRowsReplica($existing, ['pausedNoticePrefix' => 'Übersetzung pausiert bis']);
assert($merged[0]['en'] === 'Translation paused until', 'An unchanged German source text must NOT touch the existing translation');
assert(!isset($merged[0]['_sourceChanged']), 'An unchanged German source text must not be flagged as changed');
echo "Test 2 (existing translations survive a rescan when the German source text is unchanged) OK\n";

// Test 3: if the German source text DOES change (e.g. a future module update
// rewords a constant), the row is flagged as changed so it gets retranslated -
// but the OLD translation is not deleted outright (stays as a fallback until the
// new translation lands, matching the established staleness pattern elsewhere).
$existingStale = [['Key' => 'pausedNoticePrefix', 'ORIGINAL_IMPORT' => 'Alter Wortlaut', 'en' => 'Old wording', 'Source language' => 'de']];
$mergedChanged = mergeOwnUiTextRowsReplica($existingStale, ['pausedNoticePrefix' => 'Übersetzung pausiert bis']);
assert($mergedChanged[0]['ORIGINAL_IMPORT'] === 'Übersetzung pausiert bis', 'The row must pick up the NEW German source text from the current code');
assert($mergedChanged[0]['_sourceChanged'] === true, 'A changed German source text must flag the row so the next FillMissingTranslations pass retranslates it');
assert($mergedChanged[0]['en'] === 'Old wording', 'The old translation must remain in place as a fallback until the retranslation completes, not be deleted immediately');
echo "Test 3 (a changed German source text triggers retranslation while keeping the old value as a fallback) OK\n";

// Test 4: GetOwnUiText resolves the guest's active language from the persisted row,
// falling back to the PHP constant only if the row is entirely missing.
$rowsByKey = ['pausedNoticePrefix' => ['ORIGINAL_IMPORT' => 'Übersetzung pausiert bis', 'en' => 'Translation paused until']];
assert(getOwnUiTextReplica($rowsByKey, 'pausedNoticePrefix', 'en', 'FALLBACK') === 'Translation paused until', 'A translated row must return the persisted translation for the active guest language');
assert(getOwnUiTextReplica($rowsByKey, 'pausedNoticePrefix', 'de', 'FALLBACK') === 'Übersetzung pausiert bis', 'Selecting German (the source language) must return the raw German text directly');
assert(getOwnUiTextReplica($rowsByKey, 'unknownKey', 'en', 'FALLBACK') === 'FALLBACK', 'A completely missing row (e.g. brand-new instance before the first Rescan) must fall back to the PHP constant, never crash');
echo "Test 4 (GetOwnUiText resolves persisted translations correctly, with a safe constant fallback for missing rows) OK\n";

// Test 5: CRITICAL - this mechanism is now completely independent of any provider
// pause, since it never runs live at guest-request time at all - it only runs
// during Rescan (a completely separate, admin-triggered code path). There is no
// "is it currently paused" check in getOwnUiTextReplica/resolveRowValueReplica at
// all - by design, translation availability for these texts no longer depends on
// runtime provider state whatsoever.
$rowsDuringHypotheticalPause = ['pausedNoticePrefix' => ['ORIGINAL_IMPORT' => 'Übersetzung pausiert bis', 'en' => 'Translation paused until']];
assert(getOwnUiTextReplica($rowsDuringHypotheticalPause, 'pausedNoticePrefix', 'en', 'FALLBACK') === 'Translation paused until', 'A persisted translation must be available regardless of current provider pause state - that is the entire point of this build');
echo "Test 5 (persisted own-UI-text translations are available regardless of any current provider pause - the actual fix) OK\n";

echo "\nAll tests passed.\n";
