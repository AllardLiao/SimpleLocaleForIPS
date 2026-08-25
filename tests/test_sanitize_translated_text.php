<?php
declare(strict_types=1);
// Standalone replica test for build 69 (2026-08-19):
// MyMemory's translation-memory database occasionally carries an invisible
// trailing artifact character on a stored entry - live observed via the
// Debug log: a match entry showing "translation":"Position " (a
// non-breaking space, U+00A0, appended after the word) instead of the clean
// "Position". PHP's trim() only strips ASCII whitespace (space/tab/newline),
// never Unicode characters like NBSP or zero-width space - so a translated
// result carrying one of these would flow straight through into the stored
// property/cache, looking visually identical to a clean value in almost
// every rendering context, but not actually equal to it.
//
// Fixed at the single point every provider funnels through (TranslateChunk):
// results are now stripped of leading/trailing NBSP (U+00A0) and zero-width
// space (U+200B) - but deliberately NEVER a plain ASCII space, since a
// single HTML text node (see SplitHtmlIntoTextNodes) may have an
// intentional leading/trailing space that's needed for correct spacing
// between two adjacent inline elements.

function sanitizeTranslatedText(string $text): string
{
    return preg_replace('/^[\x{00A0}\x{200B}]+|[\x{00A0}\x{200B}]+$/u', '', $text) ?? $text;
}

// Test 1: THE reported artifact - a trailing non-breaking space (U+00A0)
// must be stripped.
$withNbsp = "Position\u{00A0}";
assert(sanitizeTranslatedText($withNbsp) === 'Position', 'A trailing non-breaking space (U+00A0) must be stripped from the translated result');
echo "Test 1 (trailing NBSP artifact is stripped, matching the reported MyMemory quirk) OK\n";

// Test 2: a leading NBSP must also be stripped (the artifact could appear
// on either edge, not just trailing).
$leadingNbsp = "\u{00A0}Zimmertemperatur";
assert(sanitizeTranslatedText($leadingNbsp) === 'Zimmertemperatur', 'A leading non-breaking space must also be stripped');
echo "Test 2 (leading NBSP artifact is also stripped) OK\n";

// Test 3: a zero-width space (U+200B) - another common invisible-character
// artifact - must likewise be stripped.
$withZwsp = "Room\u{200B}";
assert(sanitizeTranslatedText($withZwsp) === 'Room', 'A trailing zero-width space (U+200B) must be stripped');
echo "Test 3 (trailing zero-width space is also stripped) OK\n";

// Test 4: THE critical safety property - a completely normal, clean
// translation must pass through byte-for-byte unchanged, including any
// legitimate internal spaces between words.
$clean = 'Room temperature';
assert(sanitizeTranslatedText($clean) === $clean, 'A clean, artifact-free translation must pass through completely unchanged');
echo "Test 4 (a clean translation is never altered) OK\n";

// Test 5: THE key design constraint - a regular ASCII space at the edge of
// an HTML text node must NEVER be stripped, since it may be semantically
// required spacing between two adjacent inline elements (e.g. "Hello " +
// "World" reassembling to "Hello World", not "HelloWorld"). Only the
// specific invisible Unicode artifact characters are touched.
$intentionalTrailingSpace = 'Hello ';
assert(sanitizeTranslatedText($intentionalTrailingSpace) === 'Hello ', 'A normal ASCII trailing space must NEVER be stripped - it may be required spacing between adjacent HTML text nodes');
$intentionalLeadingSpace = ' World';
assert(sanitizeTranslatedText($intentionalLeadingSpace) === ' World', 'A normal ASCII leading space must NEVER be stripped, for the same reason');
echo "Test 5 (a normal ASCII space at either edge is deliberately preserved, unlike the invisible artifact characters) OK\n";

// Test 6: multiple stacked artifact characters (e.g. NBSP immediately
// followed by a zero-width space) must all be stripped, not just the
// first one encountered.
$stacked = "Test\u{00A0}\u{200B}";
assert(sanitizeTranslatedText($stacked) === 'Test', 'Multiple stacked invisible artifact characters must all be stripped, not just one');
echo "Test 6 (multiple stacked invisible artifact characters are all stripped) OK\n";

echo "\nAll tests passed.\n";
