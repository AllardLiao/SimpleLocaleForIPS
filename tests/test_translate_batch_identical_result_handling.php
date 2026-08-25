<?php
declare(strict_types=1);
// Standalone replica test for build 87 (2026-08-20):
// User found two real cases: (1) German "Cover" translated to Spanish "Cover" -
// MyMemory confirmed this with match=1 (a genuine loanword, correctly identical
// across languages) - but the module discarded it as if it were a failure, so the
// Spanish cell stayed empty AND got re-queried on every subsequent rescan forever
// (the user's feared "Deadlock"). (2) "SetVisibilityOff" (an internal technical
// identifier, not natural-language text) also came back unchanged from MyMemory -
// here discarding IS the correct outcome, but the OLD blanket "identical = failure"
// heuristic couldn't distinguish this from case (1), and ALSO caused the same
// endless-retry problem for content that can never resolve any other way.
//
// Root cause: TranslateBatchUncached() already correctly falls back to the raw
// source text ONLY on a genuine per-node API failure (TranslateChunk returning an
// empty string) - a reliable signal. But it used to return only the reassembled
// TEXT, discarding that distinction. TranslateBatch() then guessed at it via a
// text-equality comparison, which cannot tell "genuinely failed, fell back to raw"
// apart from "genuinely succeeded, translation happens to equal the source". Fix:
// TranslateBatchUncached() now returns ['text' => ..., 'failed' => bool] per item,
// propagating the REAL signal instead of re-guessing it downstream.

function translateBatchUncachedReplica(array $texts, string $source, string $target, array $chunkResultsByText): array
{
    if ($source === $target) {
        return array_map(static fn (string $t): array => ['text' => $t, 'failed' => false], $texts);
    }

    $result = [];
    foreach ($texts as $text) {
        // Simulates TranslateChunk(): returns '' on a genuine provider failure,
        // otherwise the (possibly identical-to-source) real translation.
        $apiResult = $chunkResultsByText[$text] ?? '';
        $failed = $apiResult === '';
        $result[] = ['text' => $failed ? $text : $apiResult, 'failed' => $failed];
    }

    return $result;
}

function translateBatchReplica(array $texts, string $source, string $target, array $chunkResultsByText): array
{
    if ($texts === [] || $source === $target) {
        return $texts;
    }

    $uncached = translateBatchUncachedReplica($texts, $source, $target, $chunkResultsByText);
    $results = [];
    foreach ($uncached as $i => $item) {
        $results[$i] = $item['failed'] ? '' : $item['text'];
    }

    return $results;
}

// Test 1: THE REPORTED BUG - "Cover" (de) -> "Cover" (es), a genuine, high-confidence
// MyMemory match (match=1). Must be accepted as a real, cacheable translation, not
// discarded to an empty string.
$result1 = translateBatchReplica(['Cover'], 'de', 'es', ['Cover' => 'Cover']);
assert($result1[0] === 'Cover', 'THE FIX: a genuine identical-across-languages translation (a real loanword, confirmed by the provider) must be accepted, not discarded as a false failure');
echo "Test 1 (genuine loanword translation \"Cover\"->\"Cover\" is accepted, not wrongly discarded) OK\n";

// Test 2: a REAL failure (all providers paused, TranslateChunk returns '') for a
// text whose reassembled fallback happens to equal the source text must STILL be
// correctly detected as a failure (empty result), exactly like before this fix -
// this is the ORIGINAL bug this heuristic was protecting against, and must not
// regress.
$result2 = translateBatchReplica(['Automatisierung'], 'de', 'en', ['Automatisierung' => '']); // '' = provider chain failed
assert($result2[0] === '', 'A genuine provider failure must still be detected as a failure (empty result) - this must not regress just because the heuristic changed');
echo "Test 2 (a genuine provider failure is still correctly detected as a failure - no regression) OK\n";

// Test 3: "SetVisibilityOff" - a technical identifier MyMemory also returns
// unchanged (this time NOT a failure - the provider genuinely has no better
// answer, match=0.85, a real machine-translation attempt). Per the new logic this
// is now accepted as "successfully translated" (identical is the objectively
// correct and STABLE answer) rather than being endlessly retried forever.
$result3 = translateBatchReplica(['SetVisibilityOff'], 'de', 'es', ['SetVisibilityOff' => 'SetVisibilityOff']);
assert($result3[0] === 'SetVisibilityOff', 'A genuine (non-empty) provider response for an untranslatable technical identifier must be accepted as a stable, cacheable result instead of retried forever');
echo "Test 3 (an untranslatable identifier that a provider genuinely confirms unchanged is accepted, breaking the endless-retry loop) OK\n";

// Test 4: Source===Target early-return path still returns texts unmodified as
// 'not failed' (nothing to translate, not an error condition).
$result4 = translateBatchUncachedReplica(['Hallo'], 'de', 'de', []);
assert($result4[0]['text'] === 'Hallo' && $result4[0]['failed'] === false, 'Source===Target must return the text unchanged, marked as NOT failed (nothing to translate is not an error)');
echo "Test 4 (identical source/target language is never treated as a failure) OK\n";

echo "\nAll tests passed.\n";
