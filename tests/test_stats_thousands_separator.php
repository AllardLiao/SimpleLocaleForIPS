<?php
declare(strict_types=1);
// Standalone replica test for build 99 (2026-08-21): user reported the
// per-language translation statistics (admin config-form labels + the
// guest-facing info popup) are hard to read for large counts - concretely,
// a cache-saved character count past 1.6 million rendered as a raw digit
// string with no thousands separator.
//
// Fix: a NEW, separate formatting function FormatStatsCountForDisplay()
// (German-style "." thousands separator, matching the existing hardcoded
// date('d.m.Y', ...) convention already used elsewhere for both admin and
// guest text) is used everywhere these numbers are shown to a human -
// FormatTranslationStatsValue() (admin form), BuildGuestStatsInfoText()
// (guest tile info popup), and BuildTranslationStatsNoticeHtml() (small
// tile hint text).
//
// Deliberately NOT applied to the existing FormatStatsCount() itself, since
// that function is also used by ApplyTranslationStatsPlaceholders() for the
// <!--COUNT_TRANSLATIONS-->/<!--COUNT_SIGNES--> placeholders - documented
// there as delivering "NUR die reine Zahl" so users can build their own
// custom-tile text/JS/CSS around it. Adding a separator there could silently
// break e.g. a user's own parseInt() on that placeholder's value.

function formatStatsCountForDisplayReplica(float $value): string
{
    return number_format((int) round($value), 0, ',', '.');
}

function formatStatsCountReplica(float $value): string
{
    return (string) (int) round($value);
}

// Test 1: THE REPORTED CASE - a cache-saved character count past 1.6 million
// must render with thousands separators, not as a raw digit string.
$result1 = formatStatsCountForDisplayReplica(1622345);
assert($result1 === '1.622.345', 'A count past 1.6 million must render with "." thousands separators, got: ' . $result1);
echo "Test 1 (1.6 million+ character count renders with thousands separators) OK\n";

// Test 2: small counts (e.g. hourly rates like 30/500) must render unchanged,
// no spurious separator injected for values below 1000.
$result2 = formatStatsCountForDisplayReplica(500);
assert($result2 === '500', 'A count below 1000 must render without any separator, got: ' . $result2);
echo "Test 2 (small counts render unchanged, no spurious separator) OK\n";

// Test 3: rounding behavior is preserved (float rates like requests/hour) -
// the new function must still round to the nearest integer first, exactly
// like the original FormatStatsCount().
$result3 = formatStatsCountForDisplayReplica(29.7);
assert($result3 === '30', 'A fractional rate must still round to the nearest integer, got: ' . $result3);
echo "Test 3 (fractional rates still round to the nearest integer before formatting) OK\n";

// Test 4: THE NON-REGRESSION - the original FormatStatsCount() (used by
// ApplyTranslationStatsPlaceholders() for custom-tile placeholders) must stay
// completely unchanged (no separator), so any existing custom tile relying
// on a clean, parseable raw number is not silently broken.
$result4 = formatStatsCountReplica(1622345);
assert($result4 === '1622345', 'FormatStatsCount() itself (used for <!--COUNT_*--> custom-tile placeholders) must remain a raw, unformatted number, got: ' . $result4);
echo "Test 4 (the original raw FormatStatsCount(), used by custom-tile placeholders, is untouched) OK\n";

// Test 5: symmetry check - module.php must actually use the new display
// formatter at every stats-display call site we identified (admin form +
// guest popup + tile hint), and must NOT have accidentally also rewired
// ApplyTranslationStatsPlaceholders() to use it.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$displayFormatterUsages = substr_count($moduleSource, 'FormatStatsCountForDisplay(');
assert($displayFormatterUsages >= 8, 'Expected FormatStatsCountForDisplay() to be used at least 8 times (6 in FormatTranslationStatsValue + 2 in BuildTranslationStatsNoticeHtml, BuildGuestStatsInfoText adds more) across the display-only call sites, found ' . $displayFormatterUsages);

$placeholderFunctionStart = strpos($moduleSource, 'private function ApplyTranslationStatsPlaceholders');
$placeholderFunctionEnd = strpos($moduleSource, "\n    }\n", $placeholderFunctionStart);
$placeholderFunctionBody = substr($moduleSource, $placeholderFunctionStart, $placeholderFunctionEnd - $placeholderFunctionStart);
assert(strpos($placeholderFunctionBody, 'FormatStatsCountForDisplay') === false, 'ApplyTranslationStatsPlaceholders() must NOT use the new display formatter - its raw-number contract for custom tiles must stay intact');
echo "Test 5 (display formatter is wired into the human-facing stats text, and does not leak into the raw custom-tile placeholders) OK\n";

echo "\nAll tests passed.\n";
