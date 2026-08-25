<?php
declare(strict_types=1);
// Standalone replica test for build 85 (2026-08-20):
// User request: ship default translations for the module's own fixed guest-facing UI
// texts (see build 78/propertyOwnUiTexts) in de/en/es/it/fr/nl plus all
// TRIAL_LANGUAGE_CODES, so a fresh install never has to spend provider quota just to
// translate Simple Locale's OWN interface strings into any of these languages.

$definitions = ['pausedNoticePrefix' => 'Übersetzung pausiert bis', 'statsHourlyLabel' => 'Stündlich:'];
$bundled = [
    'en' => ['pausedNoticePrefix' => 'Translation paused until', 'statsHourlyLabel' => 'Hourly:'],
    'nl' => ['pausedNoticePrefix' => 'Vertaling gepauzeerd tot', 'statsHourlyLabel' => 'Per uur:'],
];

function mergeOwnUiTextRowsReplica(array $existingRows, array $definitions, array $bundled): array
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
        $sourceChanged = ($row['ORIGINAL_IMPORT'] ?? null) !== $germanText;
        if ($sourceChanged) {
            $row['ORIGINAL_IMPORT'] = $germanText;
            $row['_sourceChangedAt'] = 100; // stand-in for MarkRowSourceChanged's timestamp
        }
        $row['Quellsprache'] = 'de';

        foreach ($bundled as $language => $translationsByKey) {
            if (($row[$language] ?? '') === '' && isset($translationsByKey[$key])) {
                $row[$language] = $translationsByKey[$key];
                $row['_translatedAt'][$language] = 100; // stand-in for MarkRowLanguageTranslated
            }
        }

        $result[] = $row;
    }

    return $result;
}

// Test 1: THE FEATURE - a fresh install (no existing rows) gets every bundled
// language pre-filled for every key, with zero provider calls.
$fresh = mergeOwnUiTextRowsReplica([], $definitions, $bundled);
assert($fresh[0]['en'] === 'Translation paused until' && $fresh[0]['nl'] === 'Vertaling gepauzeerd tot', 'A fresh install must have the bundled en/nl translations pre-filled with zero API calls');
echo "Test 1 (fresh install: bundled translations pre-filled for every configured language) OK\n";

// Test 2: CRITICAL - a freshly bundled cell must be marked as translated (not just
// filled) so IsRowLanguageTranslationCurrent() doesn't consider it stale and send it
// to the live translation API on the very next Rescan - defeating the whole point.
assert(isset($fresh[0]['_translatedAt']['en']) && $fresh[0]['_translatedAt']['en'] >= $fresh[0]['_sourceChangedAt'], 'THE CRITICAL FIX: a bundled translation must be marked as translated at a timestamp >= the row source-changed timestamp, or it would be wrongly re-sent to the live API on the next rescan');
echo "Test 2 (bundled translations are marked current, preventing a wasted live re-translation on the next rescan) OK\n";

// Test 3: an already-machine-translated cell (from a real provider, in a language
// NOT covered by the bundle) is untouched - bundling only fills EMPTY cells.
$existingWithRealTranslation = [['Key' => 'pausedNoticePrefix', 'ORIGINAL_IMPORT' => 'Übersetzung pausiert bis', 'fr' => 'Traduction en pause manuel-corrigee', 'Quellsprache' => 'de']];
$merged = mergeOwnUiTextRowsReplica($existingWithRealTranslation, ['pausedNoticePrefix' => 'Übersetzung pausiert bis'], ['fr' => ['pausedNoticePrefix' => 'Traduction en pause jusqu\'à']]);
assert($merged[0]['fr'] === 'Traduction en pause manuel-corrigee', 'An already-filled cell (from the live provider, or from a bundled seed on a prior run) must never be overwritten by the bundled default');
echo "Test 3 (an already-filled cell, e.g. a real provider translation, is never overwritten by the bundled default) OK\n";

// Test 4: a language not in the bundled map at all (e.g. a rare language only ever
// reachable via a live provider) is left completely untouched by the bundling step.
assert(!isset($fresh[0]['ja']), 'A language absent from the bundled map must not be touched at all - it still relies entirely on the live translation provider');
echo "Test 4 (languages outside the bundled set are unaffected - still translated live as before) OK\n";

echo "\nAll tests passed.\n";
