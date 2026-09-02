<?php
declare(strict_types=1);
// Standalone replica tests for build 68 (2026-08-19):
// Applying the same row-based console-language fix (build 67) to the two
// remaining composed-string areas: the trial-info text (three mutually
// exclusive variants: fresh/running/expired) and the "Übersetzungsanbieter
// prüfen" popup result (variable-length list of up to 3 provider rows,
// delivered via UpdateFormField() from RequestAction rather than
// PopulateFormElements()).

// --- Trial info visibility + raw-value replicas ------------------------------

function computeTrialInfoVisibility(int $expiresAt, int $daysLeft): array
{
    return [
        'fresh'   => $expiresAt === 0,
        'running' => $expiresAt !== 0 && $daysLeft > 0,
        'expired' => $expiresAt !== 0 && $daysLeft <= 0,
    ];
}

// Test 1: exactly one variant is visible at a time, matching the three
// mutually exclusive states of the original single composed sentence.
$fresh = computeTrialInfoVisibility(0, 0);
assert($fresh === ['fresh' => true, 'running' => false, 'expired' => false], 'Before the first ApplyChanges (expiresAt=0), only the "fresh" variant must be visible');
echo "Test 1 (fresh trial state shows exactly one variant) OK\n";

$running = computeTrialInfoVisibility(2000000000, 5);
assert($running === ['fresh' => false, 'running' => true, 'expired' => false], 'A trial with days remaining must show exactly the "running" variant');
echo "Test 2 (running trial state shows exactly one variant) OK\n";

$expired = computeTrialInfoVisibility(1000000000, -3);
assert($expired === ['fresh' => false, 'running' => false, 'expired' => true], 'An expired trial (daysLeft <= 0) must show exactly the "expired" variant');
echo "Test 3 (expired trial state shows exactly one variant) OK\n";

// Test 4: the running-variant raw value must combine date+days WITHOUT any
// German word baked in (the words "Tag(e) verbleibend" live in separate,
// static, locale.json-registered elements).
function formatTrialInfoRunningValue(int $expiresAt, int $daysLeft): string
{
    return date('d.m.Y', $expiresAt) . ' (' . $daysLeft;
}
$value = formatTrialInfoRunningValue(1755100000, 12);
assert(strpos($value, 'Tag') === false, 'The running trial-info raw value must not contain the German word "Tag" - that lives in a separate static element');
assert($value === date('d.m.Y', 1755100000) . ' (12', 'The running trial-info raw value must combine date and days-left with only punctuation, no words');
echo "Test 4 (running trial-info raw value carries only date/days/punctuation, no German words) OK\n";

// --- CheckProviders row-based UpdateFormField replica -------------------------

function computeCheckProviderUpdates(array $results): array
{
    $byProvider = [];
    foreach ($results as $r) {
        $byProvider[$r['provider']] = $r;
    }

    $updates = [];
    foreach (['google' => 'Google', 'deepl' => 'DeepL', 'free' => 'Free'] as $provider => $prefix) {
        $result = $byProvider[$provider] ?? null;
        $updates["ProviderCheck{$prefix}Row.visible"] = $result !== null;
        if ($result === null) {
            continue;
        }
        $updates["ProviderCheck{$prefix}IconLabel.caption"] = $result['succeeded'] ? '✅' : '⚠️';
        $updates["ProviderCheck{$prefix}StatusLabel.caption"] = $result['succeeded']
            ? 'successful'
            : 'failed - see the message log for details';
        $updates["ProviderCheck{$prefix}DetailLabel.caption"] = $result['succeeded'] ? ' ("' . $result['translation'] . '")' : '';
        $updates["ProviderCheck{$prefix}PauseClearedLabel.visible"] = $result['succeeded'] && $result['wasPaused'];
    }

    return $updates;
}

// Test 5: THE reported gap - a provider not checked this run (e.g. DeepL
// key removed since the last check) must be explicitly hidden, not left
// showing stale results from an earlier check (UpdateFormField only
// touches elements it's explicitly told to - an unchecked provider's row
// would otherwise silently keep whatever it last showed).
$results = [
    ['provider' => 'free', 'succeeded' => true, 'wasPaused' => false, 'translation' => 'Test query'],
    ['provider' => 'google', 'succeeded' => true, 'wasPaused' => true, 'translation' => 'Test query'],
];
$updates = computeCheckProviderUpdates($results);
assert($updates['ProviderCheckGoogleRow.visible'] === true, 'A checked provider (Google) must have its row shown');
assert($updates['ProviderCheckDeepLRow.visible'] === false, 'An UNCHECKED provider (DeepL, no key configured) must be explicitly hidden, not left stale');
assert($updates['ProviderCheckFreeRow.visible'] === true, 'The always-checked free provider must have its row shown');
echo "Test 5 (an unchecked provider is explicitly hidden every run, never left showing stale results) OK\n";

// Test 6: the status caption is always ONE of exactly two complete,
// pre-registered German strings (never composed with the icon or a name) -
// this is what makes it safely per-viewer translatable.
assert($updates['ProviderCheckGoogleStatusLabel.caption'] === 'successful', 'A successful check must use the exact, unmodified registered "erfolgreich" string');
assert($updates['ProviderCheckGooglePauseClearedLabel.visible'] === true, 'A provider that succeeded AND was previously paused must show the "pause cleared" label');
assert($updates['ProviderCheckFreePauseClearedLabel.visible'] === false, 'A provider that succeeded but was NOT previously paused must not show the "pause cleared" label');
echo "Test 6 (status captions are exact pre-registered strings; pause-cleared label only shows when actually relevant) OK\n";

// Test 7: a failed provider's detail label must be empty (no translation
// preview to show), and its status caption must be the exact failure
// string, not composed with anything else.
$failedResults = [['provider' => 'free', 'succeeded' => false, 'wasPaused' => false, 'translation' => null]];
$failedUpdates = computeCheckProviderUpdates($failedResults);
assert($failedUpdates['ProviderCheckFreeStatusLabel.caption'] === 'failed - see the message log for details', 'A failed check must use the exact, unmodified registered failure string');
assert($failedUpdates['ProviderCheckFreeDetailLabel.caption'] === '', 'A failed check must have an empty detail label - no translation to preview');
echo "Test 7 (a failed check shows the exact failure string with no detail preview) OK\n";

echo "\nAll tests passed.\n";
