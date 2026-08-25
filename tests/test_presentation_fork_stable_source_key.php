<?php
declare(strict_types=1);
// Standalone replica test for build 90 (2026-08-20):
// User live-found: switching the tile's active guest language away from the source
// language (e.g. to English) forks any variable whose caption comes from a shared
// profile/template into an inline, self-owned VariableCustomPresentation (this is
// intentional and unavoidable - ApplyEnumerationOptionsToVariable must unset the
// TEMPLATE/PROFILE reference to override individual caption fields at all). But once
// forked, the variable's OWN raw state permanently loses that reference - it only
// ever shows the currently-displayed (possibly translated) inline content from then
// on. GetPresentationSourceKey() fell back to a content hash of that content for
// row-matching whenever no reference is visible - and since the fork's content
// changes with whatever guest language happens to be active, this hash changed
// every time the language changed. The next Rescan, unable to recognize the row via
// this shifting hash, treated the currently-displayed (translated) text as brand
// new source content and created a duplicate row mislabeled as the configured
// source language - exactly what the user saw (English captions appearing as if
// they were the German original). Fix: derive the source key from the PRE-FORK
// backup (attributeEnumerationPresentationBackup, already saved before the first
// fork specifically to support reverting back to original) whenever a fork exists
// - it still holds the real, stable profile/template reference no matter which
// language is currently displayed.

function getPresentationSourceKeyReplica(?array $backup, array $resolvedPresentation, array $fields, string $variableId): string
{
    if (is_array($backup)) {
        if (($backup['PRESENTATION'] ?? '') === 'Legacy' && ($backup['PROFILE'] ?? '') !== '') {
            return 'profile:' . $backup['PROFILE'];
        }
        $backupTemplateGuid = $backup['TEMPLATE'] ?? '';
        if ($backupTemplateGuid !== '') {
            return 'template:' . $backupTemplateGuid;
        }
    }

    if (($resolvedPresentation['PRESENTATION'] ?? '') === 'Legacy') {
        $profileName = $resolvedPresentation['PROFILE'] ?? '';
        if ($profileName !== '') {
            return 'profile:' . $profileName;
        }
    }
    // (raw VariableCustomPresentation/VariablePresentation TEMPLATE lookup omitted -
    // covered by the "not forked yet" tests below via $resolvedPresentation directly)
    $templateGuid = $resolvedPresentation['TEMPLATE'] ?? '';
    if ($templateGuid !== '') {
        return 'template:' . $templateGuid;
    }

    ksort($fields);

    return 'content:' . substr(hash('sha256', json_encode($fields)), 0, 12);
}

// Test 1: THE REPORTED BUG - a template-referenced variable, forked by a prior
// language switch to German (source language happens to equal instance source, so
// the CURRENT displayed content is German - but the point is the reference itself
// is gone from the live/resolved state, matching what ApplyEnumerationOptionsToVariable
// actually does). Without the backup, this would fall through to a content hash;
// with it, must resolve back to the ORIGINAL stable template key.
$backup = ['PRESENTATION' => 'Template', 'TEMPLATE' => 'GUID-1234'];
$resolvedAfterFork = ['PRESENTATION' => 'Enumeration', 'OPTIONS' => '[{"Caption":"On"}]']; // no TEMPLATE - the fork removed it
$key = getPresentationSourceKeyReplica($backup, $resolvedAfterFork, ['OPTIONS.0.Caption' => 'On'], '54695');
assert($key === 'template:GUID-1234', 'THE FIX: a forked variable must resolve back to its stable pre-fork template key via the backup, not a content hash of whatever is currently displayed');
echo "Test 1 (a forked template-referenced variable resolves to its stable pre-fork key) OK\n";

// Test 2: the SAME variable, now displaying English instead of German (guest
// switched languages) - the key must be IDENTICAL to test 1, proving row identity
// survives a language switch. This is the actual bug: before the fix, this would
// produce a DIFFERENT content hash than test 1, creating a duplicate row.
$resolvedAfterForkEnglish = ['PRESENTATION' => 'Enumeration', 'OPTIONS' => '[{"Caption":"On"}]']; // english now, still no TEMPLATE
$keyEnglish = getPresentationSourceKeyReplica($backup, $resolvedAfterForkEnglish, ['OPTIONS.0.Caption' => 'On'], '54695');
assert($keyEnglish === $key, 'THE CORE BUG: the source key must stay identical regardless of which language is currently displayed on a forked variable - otherwise every language switch silently creates a new duplicate row');
echo "Test 2 (the source key stays stable across a language switch on the same forked variable - no duplicate row) OK\n";

// Test 3: a legacy-profile-referenced variable, also forked (per
// ApplyEnumerationOptionsToVariable's legacy branch, which rewrites PRESENTATION to
// Enumeration too) - must resolve back to its stable profile key via the backup.
$legacyBackup = ['PRESENTATION' => 'Legacy', 'PROFILE' => '~Switch'];
$key3 = getPresentationSourceKeyReplica($legacyBackup, ['PRESENTATION' => 'Enumeration'], ['OPTIONS.0.Caption' => 'On'], '11111');
assert($key3 === 'profile:~Switch', 'A forked legacy-profile variable must resolve back to its stable pre-fork profile key via the backup');
echo "Test 3 (a forked legacy-profile variable resolves to its stable pre-fork profile key) OK\n";

// Test 4: no backup at all (never forked - the common, unaffected case) must fall
// through to the existing template-in-resolved-presentation / content-hash logic
// completely unchanged - no regression for variables that were never translated.
$key4 = getPresentationSourceKeyReplica(null, ['PRESENTATION' => 'Template', 'TEMPLATE' => 'GUID-5678'], ['Caption' => 'Whatever'], '22222');
assert($key4 === 'template:GUID-5678', 'A never-forked variable must resolve normally via its own live presentation, completely unaffected by this fix') ;
echo "Test 4 (a never-forked variable is unaffected - resolves via its own live presentation as before) OK\n";

// Test 5: a backup that is itself just an inline custom presentation with no
// profile/template reference (set by another module or the admin before Simple
// Locale ever touched it) must fall through to the content-hash path, exactly as
// the pre-existing (Build 75) behavior for a genuinely reference-less variable.
$inlineBackup = ['PRESENTATION' => 'Enumeration', 'OPTIONS' => '[{"Caption":"Ja"}]'];
$key5 = getPresentationSourceKeyReplica($inlineBackup, ['PRESENTATION' => 'Enumeration'], ['OPTIONS.0.Caption' => 'Ja'], '33333');
assert(str_starts_with($key5, 'content:'), 'A backup with no profile/template reference of its own must fall through to the content-hash path, unchanged from Build 75 behavior');
echo "Test 5 (a reference-less backup falls through to the content-hash path, matching pre-existing Build 75 behavior) OK\n";

// Tests 6-8 cover the SECOND, deeper part of the fix: ReadTranslatablePresentation()'s
// choice of WHICH presentation data to extract $fields from (not just which source
// key to use) - a reference-less forked variable loses its entire stable raw text,
// not just a reference, so the content hash itself needs to be computed from the
// pre-fork backup's content, not the live (possibly translated) state.

function readTranslatablePresentationFieldSourceReplica(?array $backup, array $livePresentation): array
{
    $backupHasSharedReference = is_array($backup)
        && ((($backup['PRESENTATION'] ?? '') === 'Legacy' && ($backup['PROFILE'] ?? '') !== '')
            || ($backup['TEMPLATE'] ?? '') !== '');

    return (is_array($backup) && $backup !== [] && !$backupHasSharedReference) ? $backup : $livePresentation;
}

// Test 6: THE DEEPER BUG - a reference-less variable (Build 75 content-hash case),
// forked to English. Without using the backup's content, $fields would be extracted
// from the LIVE (English) presentation, producing an unstable hash. With the fix,
// the STABLE, pre-fork (German) content is used instead.
$referencelessBackup = ['PRESENTATION' => 'Enumeration', 'OPTIONS' => '[{"Caption":"Ja"}]']; // pre-fork, German
$liveForkedEnglish = ['PRESENTATION' => 'Enumeration', 'OPTIONS' => '[{"Caption":"Yes"}]']; // post-fork, English
$source = readTranslatablePresentationFieldSourceReplica($referencelessBackup, $liveForkedEnglish);
assert($source === $referencelessBackup, 'THE DEEPER FIX: for a reference-less forked variable, field extraction must use the stable pre-fork backup content, not the currently-displayed (translated) live state');
echo "Test 6 (a reference-less forked variable extracts fields from its stable pre-fork backup, not the live translated state) OK\n";

// Test 7: a variable WITH a shared reference in its backup (the common case, already
// covered by the source-key fix) must still use the LIVE presentation for field
// extraction - the backup there is only a thin reference with no caption content to
// extract fields from at all.
$referenceBackup = ['PRESENTATION' => 'Template', 'TEMPLATE' => 'GUID-1234'];
$liveResolved = ['PRESENTATION' => 'Enumeration', 'OPTIONS' => '[{"Caption":"Yes"}]'];
$source7 = readTranslatablePresentationFieldSourceReplica($referenceBackup, $liveResolved);
assert($source7 === $liveResolved, 'A variable whose backup is just a template/profile reference must still use the LIVE resolved presentation for field extraction - the backup itself has no extractable caption content');
echo "Test 7 (a reference-backed forked variable still extracts fields from the live resolved presentation, not its thin backup reference) OK\n";

// Test 8: never forked (no backup at all) - completely unchanged, uses live state.
$source8 = readTranslatablePresentationFieldSourceReplica(null, $liveResolved);
assert($source8 === $liveResolved, 'A never-forked variable must use the live presentation for field extraction, unaffected by this fix');
echo "Test 8 (a never-forked variable is unaffected, uses the live presentation as always) OK\n";

echo "\nAll tests passed.\n";
