<?php
declare(strict_types=1);
// Standalone replica test for build 95 (2026-08-21): live-confirmed bug via
// debug log (IPSSL_GreetingDiag, Build 94 diagnostic instrumentation).
//
// Sequence, confirmed by the actual log dump:
//   1. Guest switches guest visu language de -> en. ApplyGreetingLanguage()
//      writes the English translation into the tracked greeting variable via
//      WriteTrackedValueString(). At least once, the self-write guard
//      (attributeLastSelfWrittenValues) failed to recognize this as our own
//      write, so HandleTrackedVariableUpdate() -> ApplyTrackedVariableUpdate()
//      treated it as a genuine EXTERNAL content change and buffered
//      (BufferPendingTrackedRowUpdate) a pending patch: ORIGINAL_IMPORT =
//      "Good afternoon Connor!" (the English text, wrongly captured as new
//      German raw source text).
//   2. That pending patch sat unflushed.
//   3. A later, completely unrelated Rescan ran: ScanRootTree() correctly
//      computed and persisted the row (MergeGreetingRows' IsSourceLanguageActive
//      guard worked exactly as designed - German raw text preserved). But
//      ScanRootTree()'s own closing IPS_ApplyChanges() call reenters
//      ApplyChanges(), which calls FlushPendingTrackedRowUpdates() as one of
//      its first steps - flushing the STALE, wrongly-captured patch from step 1
//      right on top of the row ScanRootTree() had just correctly written.
//   Net effect, confirmed in the live log: ORIGINAL_IMPORT flips to English
//   while de/en/es stay at their previously-correct values (since the flush
//   only patches specific fields, not the whole row).
//
// Root fix: ApplyTrackedVariableUpdate() gets a self-echo guard, independent
// of the (apparently sometimes unreliable) self-write-guard timing: if the
// freshly observed value is IDENTICAL to what the row already has stored as
// ITS OWN translation for the currently active language, it is treated as an
// echo of our own translation write, not genuine new source content - no raw
// text update, no buffering, no write-back. A real external update (e.g. a
// time-of-day greeting script) would essentially never coincide exactly with
// an existing stored translation, so the legitimate external-update path
// (Build 70's weather-widget case) is untouched.

function applyTrackedVariableUpdateReplica(
    array $Rows,
    int $RowIndex,
    string $RawField,
    string $TranslatedPrefix,
    string $NewValue,
    string $CurrentLanguage,
    string $RowSourceLanguage,
    ?string $Translated = null
): array {
    // Build 95 fix: self-echo guard.
    if ($CurrentLanguage !== 'ORIGINAL_IMPORT'
        && ($Rows[$RowIndex][$TranslatedPrefix . $CurrentLanguage] ?? null) === $NewValue) {
        return $Rows;
    }

    $Rows[$RowIndex][$RawField] = $NewValue;
    $displayText = $NewValue;

    if ($RowSourceLanguage !== $CurrentLanguage && $CurrentLanguage !== 'ORIGINAL_IMPORT') {
        $translatedText = $Translated ?? '';
        if ($translatedText !== '') {
            $Rows[$RowIndex][$TranslatedPrefix . $CurrentLanguage] = $translatedText;
            $displayText = $translatedText;
        }
    }

    // Marker so the test can see whether a buffer/write-back would have happened.
    $Rows[$RowIndex]['_bufferedRaw'] = $Rows[$RowIndex][$RawField];
    $Rows[$RowIndex]['_wouldWriteBack'] = ($displayText !== $NewValue);

    return $Rows;
}

// Test 1: THE REPORTED BUG, replicated exactly. Row's own "en" column already
// holds "Good afternoon Connor!" (the module's own earlier translation write).
// A VM_UPDATE observes the SAME value while currentLanguage=en - must be
// treated as a self-echo, not a fresh German raw-text change.
$rows = [[
    'ORIGINAL_IMPORT' => 'Guten Tag Connor!',
    'de' => 'Guten Tag Connor!',
    'en' => 'Good afternoon Connor!',
    'es' => '¡Buenos días Connor!',
]];
$result1 = applyTrackedVariableUpdateReplica($rows, 0, 'ORIGINAL_IMPORT', '', 'Good afternoon Connor!', 'en', 'de');
assert($result1[0]['ORIGINAL_IMPORT'] === 'Guten Tag Connor!', 'THE FIX: a VM_UPDATE whose value matches the row\'s own stored translation for the active language must NOT overwrite ORIGINAL_IMPORT');
assert(!isset($result1[0]['_bufferedRaw']), 'No pending row update may be buffered for a self-echo');
echo "Test 1 (self-echo of the module's own translation write is ignored, raw text preserved) OK\n";

// Test 2: legitimate external update while a non-source language IS active
// (Build 70's weather-widget use case) - new value does NOT match the stored
// translation for the active language, so it must still be captured normally.
$rows2 = [[
    'ORIGINAL_IMPORT' => 'Aussentemperatur: 18°C',
    'de' => 'Aussentemperatur: 18°C',
    'en' => 'Outside temperature: 18°C',
]];
$result2 = applyTrackedVariableUpdateReplica($rows2, 0, 'ORIGINAL_IMPORT', '', 'Aussentemperatur: 19°C', 'en', 'de', 'Outside temperature: 19°C');
assert($result2[0]['ORIGINAL_IMPORT'] === 'Aussentemperatur: 19°C', 'A genuine external content change (does not match the stored translation) must still be captured as fresh raw text');
assert($result2[0]['en'] === 'Outside temperature: 19°C', 'The currently active language must still get an immediate live re-translation for a genuine external update');
echo "Test 2 (genuine external update, distinct from any stored translation, is still captured normally) OK\n";

// Test 3: source language active (currentLanguage === rowSourceLanguage) - the
// guard must not interfere with normal source-language edits, since
// TranslatedPrefix.currentLanguage for the source-language-as-target column
// mirrors the raw text (Build 82 raw-copy), so a genuine edit differs from it
// too and is captured normally.
$rows3 = [[
    'ORIGINAL_IMPORT' => 'Guten Tag Connor!',
    'de' => 'Guten Tag Connor!',
    'en' => 'Good afternoon Connor!',
]];
$result3 = applyTrackedVariableUpdateReplica($rows3, 0, 'ORIGINAL_IMPORT', '', 'Guten Abend Connor!', 'de', 'de');
assert($result3[0]['ORIGINAL_IMPORT'] === 'Guten Abend Connor!', 'A genuine raw-text edit while the source language is active must still be captured');
echo "Test 3 (genuine edit while source language is active is unaffected by the guard) OK\n";

// Test 4: ORIGINAL_IMPORT sentinel active language - guard must not apply (no
// "currently active language" translation column to compare against).
$rows4 = [[
    'ORIGINAL_IMPORT' => 'Guten Tag Connor!',
    'de' => 'Guten Tag Connor!',
]];
$result4 = applyTrackedVariableUpdateReplica($rows4, 0, 'ORIGINAL_IMPORT', '', 'Guten Abend Connor!', 'ORIGINAL_IMPORT', 'de');
assert($result4[0]['ORIGINAL_IMPORT'] === 'Guten Abend Connor!', 'The sentinel "ORIGINAL_IMPORT" active-language state must never trigger the self-echo guard');
echo "Test 4 (ORIGINAL_IMPORT sentinel active language bypasses the guard as expected) OK\n";

echo "\nAll tests passed.\n";
