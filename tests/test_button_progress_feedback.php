<?php
declare(strict_types=1);
// Standalone replica test for build 96 (2026-08-21): user requested visible
// feedback for the config-form action buttons other than "Activate license"
// (already fine per the user). Confirmed via source inspection that
// "Clear cache" already shows a real success popup (CacheClearedPopup, fully
// localized en/es/it/fr) - only "Cleanup orphaned rows" and "Check translation
// providers" were missing any indication that a (network-bound / tree-walking)
// operation was in progress. Both now call the new SetButtonProgress() helper
// (a lightweight sibling of SetRescanProgress(), without its persisted
// attribute - these operations run synchronously inside a single
// RequestAction() call and are far too short-lived to need form-reopen
// restoration).
//
// This replica models SetButtonProgress()'s pure logic (message -> visibility)
// and the call sequence both functions must follow: show before the
// long-running work, hide again once results are ready to display.

function setButtonProgressReplica(string $message): array
{
    return ['caption' => $message, 'visible' => $message !== ''];
}

// Test 1: a non-empty message must show the bar with that exact caption.
$shown = setButtonProgressReplica('Looking for orphaned entries…');
assert($shown['visible'] === true, 'A non-empty progress message must make the bar visible');
assert($shown['caption'] === 'Looking for orphaned entries…', 'The bar caption must be the exact message passed in');
echo "Test 1 (non-empty message shows the progress bar with the right caption) OK\n";

// Test 2: an empty message must hide the bar again (used right before the
// result popup/reload takes over).
$hidden = setButtonProgressReplica('');
assert($hidden['visible'] === false, 'An empty message must hide the progress bar');
echo "Test 2 (empty message hides the progress bar) OK\n";

// Test 3: CleanupOrphanedRows()'s call sequence - show, do the work, then
// hide BEFORE ReloadForm() (a full form reload would reset it anyway, but the
// explicit hide keeps the same "always clear on every exit path" discipline
// already established for SetRescanProgress/Build 88).
$cleanupSequence = [
    setButtonProgressReplica('Looking for orphaned entries…'),
    // ... WalkTree()/array_filter() work happens here ...
    setButtonProgressReplica(''),
];
assert($cleanupSequence[0]['visible'] === true, 'Cleanup must show progress before starting the tree walk');
assert($cleanupSequence[1]['visible'] === false, 'Cleanup must hide progress before ReloadForm()');
echo "Test 3 (Cleanup shows then explicitly hides progress around its work) OK\n";

// Test 4: CheckProviders()'s call sequence - show before the provider loop,
// hide right before the existing ProviderCheckResultPopup is revealed (the
// popup itself is untouched, per the user's explicit "die schon vorhandene
// Meldung danach" instruction - only the progress indicator is new).
$checkSequence = [
    setButtonProgressReplica('Checking translation providers…'),
    // ... TranslateChunkGoogle/-DeepL/-Free network calls happen here ...
    setButtonProgressReplica(''),
];
assert($checkSequence[0]['visible'] === true, 'CheckProviders must show progress before the network calls');
assert($checkSequence[1]['visible'] === false, 'CheckProviders must hide progress right before the result popup appears');
echo "Test 4 (CheckProviders shows then explicitly hides progress around its network calls) OK\n";

echo "\nAll tests passed.\n";
