<?php
declare(strict_types=1);
// Standalone replica test for build 98 (2026-08-21): user-reported bug - for
// buttons that also trigger an automatic form refresh (ReloadForm(), needed so
// an already-open config form doesn't submit its stale "Übernehmen" buffer over
// the just-changed list content - see ScanRootTree's own comment on the same
// topic), the newly-added result popup ("Ergebnis von Aufräumen") got hidden
// again immediately, because ReloadForm() tore down/rebuilt the whole form's
// DOM right after (or in the same script tick as) the popup was shown - giving
// the user no real chance to read the result.
//
// Fix: CleanupOrphanedRows() no longer calls ReloadForm() synchronously.
// Instead it:
//   1. Live-pushes CleanupResultPopup visible=true + the count label (the same
//      proven pattern already used by CacheClearedPopup/ProviderCheckResultPopup,
//      which never needed a reload at all) - immediate visual feedback on the
//      still-open form.
//   2. Arms a one-shot timer (CLEANUP_RELOAD_DELAY_SECONDS) that fires
//      ProcessDeferredCleanupReload(), which performs the actual ReloadForm()
//      several seconds later - giving the user time to read the popup before
//      the list refresh happens in the background. The popup then continues to
//      show seamlessly across that reload too, since PopulateFormElements
//      still reads (and only THEN consumes/resets) attributeLastCleanupRemovedCount.
//
// This replica models the call sequence and the one-shot consume semantics;
// the actual IPS_* calls can't run outside a live instance.

function cleanupOrphanedRowsSequenceReplica(int $removedCount): array
{
    $calls = [];
    // Persist first (must happen before either the live push or the deferred
    // reload can meaningfully show anything).
    $attributeLastCleanupRemovedCount = $removedCount;
    $calls[] = ['op' => 'WriteAttributeInteger', 'value' => $removedCount];

    // Live push - happens synchronously, on the currently open form.
    $calls[] = ['op' => 'UpdateFormField', 'field' => 'CleanupResultCountLabel', 'value' => (string) $removedCount];
    $calls[] = ['op' => 'UpdateFormField', 'field' => 'CleanupResultPopup', 'value' => true];

    // Deferred reload armed, NOT called synchronously.
    $calls[] = ['op' => 'SetTimerInterval', 'target' => 'CleanupReloadTimer', 'seconds' => 5];

    return ['calls' => $calls, 'attributeLastCleanupRemovedCount' => $attributeLastCleanupRemovedCount];
}

function populateFormElementsConsumeReplica(int &$attributeLastCleanupRemovedCount): array
{
    $cleanupResultCount = $attributeLastCleanupRemovedCount;
    if ($cleanupResultCount >= 0) {
        $attributeLastCleanupRemovedCount = -1;
    }

    return [
        'CleanupResultPopup.visible' => $cleanupResultCount >= 0,
        'CleanupResultCountLabel.caption' => $cleanupResultCount >= 0 ? (string) $cleanupResultCount : '',
    ];
}

// Test 1: THE FIX - CleanupOrphanedRows() itself must NOT call ReloadForm()
// synchronously anymore; it must arm the deferred timer instead.
$result1 = cleanupOrphanedRowsSequenceReplica(3);
$hasSyncReload = false;
foreach ($result1['calls'] as $call) {
    if (($call['op'] ?? '') === 'ReloadForm') {
        $hasSyncReload = true;
    }
}
assert($hasSyncReload === false, 'CleanupOrphanedRows() must not call ReloadForm() synchronously anymore');
$hasDeferredTimer = false;
foreach ($result1['calls'] as $call) {
    if (($call['op'] ?? '') === 'SetTimerInterval' && ($call['target'] ?? '') === 'CleanupReloadTimer') {
        $hasDeferredTimer = true;
    }
}
assert($hasDeferredTimer === true, 'CleanupOrphanedRows() must arm the deferred CleanupReloadTimer instead');
echo "Test 1 (no synchronous ReloadForm - a deferred timer is armed instead) OK\n";

// Test 2: THE FIX - the result popup must be live-pushed immediately, so the
// user sees it right away instead of waiting for the delayed reload.
$hasLivePushPopup = false;
$hasLivePushLabel = false;
foreach ($result1['calls'] as $call) {
    if (($call['op'] ?? '') === 'UpdateFormField' && ($call['field'] ?? '') === 'CleanupResultPopup' && $call['value'] === true) {
        $hasLivePushPopup = true;
    }
    if (($call['op'] ?? '') === 'UpdateFormField' && ($call['field'] ?? '') === 'CleanupResultCountLabel' && $call['value'] === '3') {
        $hasLivePushLabel = true;
    }
}
assert($hasLivePushPopup === true, 'The result popup must be pushed visible=true immediately on the still-open form');
assert($hasLivePushLabel === true, 'The result count label must be pushed with the correct count immediately');
echo "Test 2 (popup and count label are live-pushed immediately, no reload needed to see them) OK\n";

// Test 3: when the deferred reload eventually fires, the popup must STILL show
// (seamless continuation) because the attribute has not been consumed yet -
// only PopulateFormElements() (called by the reload) consumes it.
$attr = $result1['attributeLastCleanupRemovedCount'];
$onReload = populateFormElementsConsumeReplica($attr);
assert($onReload['CleanupResultPopup.visible'] === true, 'The popup must remain visible across the deferred ReloadForm() too');
assert($onReload['CleanupResultCountLabel.caption'] === '3', 'The count label must remain correct across the deferred reload too');
echo "Test 3 (popup remains visible seamlessly through the deferred reload) OK\n";

// Test 4: the attribute must be a true one-shot - a LATER, unrelated form open
// must not spuriously re-show the popup a second time.
$onLaterUnrelatedOpen = populateFormElementsConsumeReplica($attr);
assert($onLaterUnrelatedOpen['CleanupResultPopup.visible'] === false, 'A later, unrelated form open must not re-show the already-consumed popup');
echo "Test 4 (the popup does not spuriously reappear on a later, unrelated form open) OK\n";

echo "\nAll tests passed.\n";
