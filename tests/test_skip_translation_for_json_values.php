<?php
declare(strict_types=1);
// Standalone replica test for build 84 (2026-08-20):
// User found a live bug: a string variable inside the scanned root tree held JSON
// config data for a DIFFERENT module (a favorites list: {"musicProvider":"CLOUDPLAYER",
// "searchPhrase":"Mein Discovery Mix"}) - Simple Locale translated it like ordinary
// text, and Google Translate HTML-escaped the quotes (&quot; instead of "), breaking
// the JSON for whatever script consumes it. Decision (confirmed with the user):
// don't attempt to parse+selectively-translate JSON (unsafe in general - structural
// keys/enum values like "CLOUDPLAYER" can't be reliably told apart from genuine
// display text within the same blob) - detect JSON and skip translation entirely,
// relying on the existing raw-text fallback (ResolveRowValue) to serve the
// untouched original for every guest language.

function looksLikeJsonReplica(string $text): bool
{
    $trimmed = trim($text);
    if ($trimmed === '' || !in_array($trimmed[0], ['{', '['], true)) {
        return false;
    }
    json_decode($trimmed);

    return json_last_error() === JSON_ERROR_NONE;
}

// Test 1: THE REPORTED BUG - a JSON object (the favorites-list config) must be
// detected as JSON and therefore excluded from translation.
assert(looksLikeJsonReplica('{"musicProvider":"CLOUDPLAYER","searchPhrase":"Mein Discovery Mix"}') === true, 'THE FIX: a JSON object raw value must be detected so it never gets sent to the translation API');
echo "Test 1 (JSON object content is correctly detected) OK\n";

// Test 2: a JSON array is also detected (not just objects).
assert(looksLikeJsonReplica('["Wohnzimmer","Küche","Bad"]') === true, 'A JSON array must be detected the same way as a JSON object');
echo "Test 2 (JSON array content is correctly detected) OK\n";

// Test 3: CRITICAL - a JSON *scalar* (a bare number, boolean, or quoted string) is
// technically valid JSON per json_decode(), but must NOT be treated as "JSON content
// to skip" - ordinary translatable text/object names must never be accidentally
// excluded from translation just because they happen to parse as a JSON scalar.
assert(looksLikeJsonReplica('42') === false, 'A bare number must NOT be treated as JSON content to skip - it is ordinary translatable text (e.g. an object name)');
assert(looksLikeJsonReplica('true') === false, 'A bare boolean word must NOT be treated as JSON content to skip');
assert(looksLikeJsonReplica('"Hallo"') === false, 'A quoted single word is technically a valid JSON string scalar, but must still be treated as normal translatable text, not skipped');
echo "Test 3 (JSON scalars are NOT mistaken for structured content - normal text keeps translating) OK\n";

// Test 4: ordinary prose, including text that happens to CONTAIN braces/brackets
// without being valid JSON, is never mistaken for JSON.
assert(looksLikeJsonReplica('Wetter heute') === false, 'Ordinary prose must never be treated as JSON');
assert(looksLikeJsonReplica('{unclosed') === false, 'Text starting with a brace but not valid JSON must not be treated as JSON (avoids false positives from malformed-looking text)');
echo "Test 4 (ordinary prose and near-JSON-looking but invalid text are never mistaken for real JSON) OK\n";

// Test 5: empty string is never treated as JSON (matches the existing "nothing to
// translate" short-circuit for empty raw fields elsewhere in the codebase).
assert(looksLikeJsonReplica('') === false, 'An empty string must not be treated as JSON');
echo "Test 5 (empty raw text is not treated as JSON) OK\n";

// Test 6: simulates the actual filtering behavior inside FillLanguageColumn's
// pending-detection - a JSON row is never added to the translation batch, regardless
// of whether its target cell is already empty or already (mis)translated from a
// prior run - matching the "leave it alone going forward" recovery path.
function isPendingReplica(string $fromText, string $toFieldValue): bool
{
    $isCurrentlyTranslated = $toFieldValue !== ''; // simplified staleness stand-in
    return $fromText !== '' && !looksLikeJsonReplica($fromText) && !$isCurrentlyTranslated;
}
assert(isPendingReplica('{"musicProvider":"CLOUDPLAYER"}', '') === false, 'A JSON row with an empty target cell must never be queued for translation');
assert(isPendingReplica('Wetter heute', '') === true, 'Ordinary text with an empty target cell must still be queued for translation as normal');
echo "Test 6 (JSON rows are excluded from the translation batch regardless of whether their target cell is empty) OK\n";

echo "\nAll tests passed.\n";
