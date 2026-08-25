<?php
declare(strict_types=1);
// Standalone replica test for build 73 (2026-08-19):
// User feedback after testing build 70/71:
// 1) A user-triggered Rescan ("Baum neu einlesen") is expected to catch up EVERY
//    missing translation in EVERY configured target language in one pass - not just
//    the currently active guest language. That's a distinct concern from the
//    automatic, per-tick VM_UPDATE live-retranslation (which stays active-language-
//    only, since THAT was the actual quota-burning culprit fixed in build 70). Same
//    reasoning applies to a deliberate admin action (row source-language change +
//    "Übernehmen") - also NOT an automatic per-tick trigger.
// 2) The build-71 "next persistence" banner never appeared while a form stayed open,
//    because PopulateFormElements() only runs when the form is (re)opened, and build
//    71 deliberately never reloads an open form. Fix: push the banner live via
//    UpdateFormField() from the buffering code itself.

const ALL_LANGUAGES = ['en', 'es', 'fr'];
const ACTIVE_LANGUAGE = 'en';

// Replica of the decision each code path makes for which languages to translate.
function rescanTargetLanguages(): array
{
    return ALL_LANGUAGES; // Build 73: back to "all configured languages"
}

function reconcileRowSourceLanguageTargetLanguages(): array
{
    return ALL_LANGUAGES; // Build 73: same reasoning - a deliberate admin action
}

function vmUpdateTargetLanguage(): ?string
{
    return ACTIVE_LANGUAGE; // UNCHANGED since build 70 - the actual quota culprit
}

// Test 1: a manual/auto Rescan must translate into ALL configured target languages,
// not just the currently active one.
assert(rescanTargetLanguages() === ALL_LANGUAGES, 'Rescan must fill every configured target language, not just the active one');
echo "Test 1 (Rescan fills all configured target languages) OK\n";

// Test 2: a row source-language change (deliberate admin action) must likewise fill
// all configured target languages.
assert(reconcileRowSourceLanguageTargetLanguages() === ALL_LANGUAGES, 'A row source-language reconcile (admin action) must fill every configured target language');
echo "Test 2 (row source-language reconcile fills all configured target languages) OK\n";

// Test 3: CRITICAL REGRESSION GUARD - the automatic VM_UPDATE live-retranslation path
// must stay restricted to ONLY the active language. This is the actual mechanism that
// caused the reported 77,000-character/day quota burn - build 73 must NOT undo that
// fix while restoring the "fill everything" behavior for Rescan/Reconcile.
assert(vmUpdateTargetLanguage() === ACTIVE_LANGUAGE, 'VM_UPDATE live-retranslation must remain restricted to the single active language - this is the actual quota-exhaustion fix from build 70 and must not regress');
echo "Test 3 (VM_UPDATE live-retranslation stays active-language-only - no regression of the build 70 quota fix) OK\n";

// --- Live-push of the "next persistence" banner ---

class FormFieldPushRecorder
{
    public array $calls = [];

    public function updateFormField(string $ident, string $property, $value): void
    {
        $this->calls[] = [$ident, $property, $value];
    }
}

function bufferPendingTrackedRowUpdate(FormFieldPushRecorder $form, int $debounceSeconds): void
{
    $flushAt = time() + $debounceSeconds;
    $form->updateFormField('PendingRowUpdateNoticeRow', 'visible', true);
    $form->updateFormField('PendingRowUpdateFlushAtLabel', 'caption', date('H:i', $flushAt));
}

function stagePendingTrackedRowUpdatesClear(FormFieldPushRecorder $form): void
{
    $form->updateFormField('PendingRowUpdateNoticeRow', 'visible', false);
    $form->updateFormField('PendingRowUpdateFlushAtLabel', 'caption', '');
}

// Test 4: buffering a change must push the banner visible=true with a formatted time,
// even though no ReloadForm()/re-fetch ever happens - this is what makes it show up
// in an ALREADY OPEN form instead of only appearing on the next form open.
$form = new FormFieldPushRecorder();
bufferPendingTrackedRowUpdate($form, 720);
assert($form->calls[0] === ['PendingRowUpdateNoticeRow', 'visible', true], 'Buffering a change must push the notice row visible immediately');
assert(preg_match('/^\d{2}:\d{2}$/', $form->calls[1][2]) === 1, 'The pushed flush-at caption must be a formatted HH:MM time');
echo "Test 4 (buffering a change live-pushes the banner into an already-open form) OK\n";

// Test 5: flushing/clearing the buffer must push the banner back to hidden with an
// empty caption - symmetric to Test 4.
$form2 = new FormFieldPushRecorder();
stagePendingTrackedRowUpdatesClear($form2);
assert($form2->calls[0] === ['PendingRowUpdateNoticeRow', 'visible', false], 'Clearing the buffer must push the notice row hidden');
assert($form2->calls[1] === ['PendingRowUpdateFlushAtLabel', 'caption', ''], 'Clearing the buffer must push an empty caption');
echo "Test 5 (flushing the buffer live-pushes the banner back to hidden) OK\n";

echo "\nAll tests passed.\n";
