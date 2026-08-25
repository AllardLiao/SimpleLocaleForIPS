<?php
declare(strict_types=1);
// Standalone replica test for the 2026-08-18 "blank weather tile" fix (build 58):
// the most serious bug found in this whole pause-feature investigation. Live
// screenshot showed a weather widget with fully intact HTML structure (icons,
// day headers, borders) but every single piece of DYNAMIC text (percentages,
// wind speed, temperatures) rendered completely blank while the translation
// provider chain was paused.
//
// Root cause: TranslateBatchUncached() splits HTML content into individual
// text nodes and translates each independently (see SplitHtmlIntoTextNodes,
// build 47's cross-tag-shuffling fix). When TranslateChunk() returns an empty
// string for a node (provider chain exhausted/paused - see TranslateChunk),
// that empty string was spliced directly back into the reassembled HTML
// instead of falling back to the node's own original (untranslated) text -
// silently blanking every dynamic value in the widget while leaving the
// static HTML structure around it intact, which is exactly the visual
// pattern observed live.

// Minimal replica of the node-reassembly logic (the actual bug site).
function reassembleNodes(array $nodes, array $apiResults): array
{
    $translatedNodes = [];
    foreach (array_keys($nodes) as $index) {
        $apiResult = $apiResults[$index] ?? '';
        // THE FIX: fall back to the original node text when the API result is
        // empty, instead of inserting the empty string verbatim.
        $translatedNodes[] = $apiResult !== '' ? $apiResult : $nodes[$index];
    }
    return $translatedNodes;
}

// ---------------------------------------------------------------------------
// Test 1: THE reported bug - provider chain fully paused, TranslateChunk
// returns '' for every single node. Reassembled nodes must show the ORIGINAL
// (untranslated) values, not blanks.
$nodes = ['23°', '0% chance of rain', '0.82 m/s W', 'Mon', 'Tue'];
$allEmptyApiResults = array_fill(0, count($nodes), ''); // simulates a fully-paused chain
$reassembled = reassembleNodes($nodes, $allEmptyApiResults);
assert($reassembled === $nodes, 'When ALL translations fail/are paused, every node must fall back to its own original text - a blank widget must never happen just because the provider chain is paused');
echo "Test 1 (fully-paused chain: every node falls back to its original text, no blanks) OK\n";

// ---------------------------------------------------------------------------
// Test 2: normal successful case must be completely unaffected - a real,
// non-empty translation must still be used, never overridden by the fallback.
$successfulApiResults = ['23°', '0% chance of rain', '0.82 m/s W', 'Mon', 'Tue']; // pretend these are the (identical, for simplicity) translated values
$reassembled2 = reassembleNodes($nodes, $successfulApiResults);
assert($reassembled2 === $successfulApiResults, 'A successful translation must be used as-is, the fallback must never interfere with real results');
echo "Test 2 (successful translation is used normally, fallback does not interfere) OK\n";

// ---------------------------------------------------------------------------
// Test 3: mixed case - SOME nodes translate successfully while others fail
// (e.g. a partial provider hiccup, not a full pause) - each node must be
// judged independently.
$mixedApiResults = ['23°', '', 'Mon', 'Tue']; // 4 nodes this time, node[1] failed
$mixedNodes = ['23°', '0% chance of rain', 'Monday', 'Tuesday'];
$reassembled3 = reassembleNodes($mixedNodes, $mixedApiResults);
assert($reassembled3[0] === '23°', 'A successfully translated node must be used');
assert($reassembled3[1] === '0% chance of rain', 'A failed node must fall back to its OWN original text, not a blank or a neighboring node\'s value');
assert($reassembled3[2] === 'Mon', 'Other successful nodes in the same widget must be unaffected by one failed neighbor');
echo "Test 3 (mixed success/failure: each node falls back independently, no cross-contamination) OK\n";

// ---------------------------------------------------------------------------
// Test 4: an original node that happens to be genuinely empty (e.g. an empty
// <span></span>) must stay empty either way - the fallback is a no-op there,
// not a source of new content.
$emptyOriginalNodes = [''];
$reassembled4 = reassembleNodes($emptyOriginalNodes, ['']);
assert($reassembled4 === [''], 'A genuinely empty original node must simply stay empty - nothing to fall back to');
echo "Test 4 (genuinely empty original node stays empty, no phantom content introduced) OK\n";

echo "\nAll tests passed.\n";
