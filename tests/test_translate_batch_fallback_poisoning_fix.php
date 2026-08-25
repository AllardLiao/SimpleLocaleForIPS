<?php
declare(strict_types=1);
// Standalone replica test for build 65 (2026-08-19):
// TranslateBatchUncached() deliberately falls back to the UNTRANSLATED SOURCE
// TEXT (never an empty string) whenever the provider chain fails for a node/
// segment - correct for its own purpose (never leave a live HTML widget with
// blank dynamic values, see the build-58 fix). But TranslateBatch() is the
// single gateway EVERY other caller (FillLanguageColumn during Rescan,
// ApplyTrackedVariableUpdate for VM_UPDATE live-retranslation,
// ReconcileRowFields) goes through, and all three of them use "did I get
// back an empty string?" as their ONLY signal for "did this fail, please
// don't persist it / please retry later". Since TranslateBatchUncached()
// never actually returns an empty string, that signal never fires - a
// failed translation attempt gets silently written into the stored
// property as if it were a real, finished translation (the untranslated
// German text, permanently frozen into the target-language column), and
// even gets cached as if it were correct. Live reported: "Automations" and
// "Begrüßung" rows all showing the raw German text in every target-language
// column after a Rescan that ran into a rate-limit pause.
//
// Fixed by re-detecting the fallback at the ONE place all callers already
// go through (TranslateBatch()): if the "translated" result is byte-for-byte
// identical to the untranslated source text, it's treated as a failure
// (empty string) again, before being returned to ANY caller or cached.

function translateBatchUncachedSimulated(array $texts, array $providerResults): array
{
    // $providerResults[i] === null means the provider chain failed for text i
    // (TranslateChunk would return blanks); TranslateBatchUncached's own
    // fallback then substitutes the source text - this function models
    // EXACTLY that already-fallback-applied return value, matching the real
    // TranslateBatchUncached()'s contract.
    $result = [];
    foreach ($texts as $i => $text) {
        $result[$i] = $providerResults[$i] ?? $text;
    }

    return $result;
}

function translateBatchSimulated(array $texts, array $providerResults, array &$cache): array
{
    $freshlyTranslated = translateBatchUncachedSimulated($texts, $providerResults);

    $results = [];
    foreach ($texts as $i => $text) {
        $translated = $freshlyTranslated[$i] ?? '';
        if ($translated !== '' && $translated === $text) {
            $translated = '';
        }
        $results[$i] = $translated;
        if ($translated !== '') {
            $cache[$text] = $translated;
        }
    }

    return $results;
}

// Test 1: THE reported bug - a text whose translation attempt fell back to
// the source (provider chain failed) must come back as an EMPTY string from
// TranslateBatch(), not the silently-substituted source text - so callers
// correctly treat it as "not yet translated, please retry later" instead of
// permanently freezing the untranslated text into the stored column.
$cache = [];
$results = translateBatchSimulated(['Gehen'], [null], $cache);
assert($results[0] === '', 'A translation that fell back to the source text must come back as an empty string, not the frozen German fallback');
assert($cache === [], 'A fallback result must NEVER be cached as if it were a real translation - the cache is not poisoned');
echo "Test 1 (a provider-chain failure now returns empty instead of the frozen source-text fallback, and the cache is not poisoned) OK\n";

// Test 2: a GENUINE successful translation (different from the source) must
// pass through completely unaffected and gets cached normally.
$cache2 = [];
$results2 = translateBatchSimulated(['Gehen'], ['Walking'], $cache2);
assert($results2[0] === 'Walking', 'A genuine, different-from-source translation must be returned unchanged');
assert($cache2['Gehen'] === 'Walking', 'A genuine translation must still be cached normally');
echo "Test 2 (a genuine successful translation is unaffected and still gets cached) OK\n";

// Test 3: a MIXED batch - one text translates successfully, the other falls
// back due to a mid-batch provider failure - each result must be judged
// independently, matching the existing "each column judged independently"
// philosophy from the build-59 data-loss fix.
$cache3 = [];
$results3 = translateBatchSimulated(['Gehen', 'Kommen'], ['Walking', null], $cache3);
assert($results3[0] === 'Walking', 'The successful text in a mixed batch must still translate correctly');
assert($results3[1] === '', 'The failed text in the SAME mixed batch must independently come back empty, not the frozen fallback');
assert($cache3 === ['Gehen' => 'Walking'], 'Only the genuinely successful text in a mixed batch may be cached, never the failed one');
echo "Test 3 (a mixed batch judges each text independently - success caches, failure stays empty) OK\n";

// Test 4: the exact reported real-world scenario - four Automation rows, all
// hit a total provider-chain failure during the SAME Rescan (matches the
// user's screenshot: "Gehen"/"Kommen"/"Schlafen"/"Aufstehen" all frozen as
// German in every target-language column). All four must come back empty,
// so a later Rescan (once providers recover) will still see them as pending
// and retry them, instead of treating the frozen German text as "done".
$cache4 = [];
$automationTexts = ['Gehen', 'Kommen', 'Schlafen', 'Aufstehen'];
$allFailed = array_fill(0, 4, null);
$results4 = translateBatchSimulated($automationTexts, $allFailed, $cache4);
foreach ($results4 as $result) {
    assert($result === '', 'Every row in a fully-failed Rescan batch must come back empty, not frozen with the German source text');
}
assert($cache4 === [], 'A fully-failed batch must leave the cache completely untouched');
echo "Test 4 (a full-batch provider failure, matching the reported Automations/Begrüßung bug, leaves every row empty for retry) OK\n";

echo "\nAll tests passed.\n";
