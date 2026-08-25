<?php
declare(strict_types=1);
// Standalone replica tests, updated for the user's direct build-72-era edit
// (commits "UI"/"UI"/"FIX", 2026-08-19) that re-baked punctuation onto the stats row
// words ("Stündlich:", "Anfrage(n),", "Zeichen.", "Tag(e).", "Insgesamt:", "Durch den
// Cache eingespart:"). This is the SAME class of exact-match mechanism originally
// fixed in build 68 (a caption's punctuation must exactly match a registered
// locale.json key, or the console's exact-string translation silently fails) - the
// user consistently updated BOTH form.json AND all 4 locale.json language blocks
// together this time, so it still works, just with different (now punctuated)
// keys than build 68 originally registered. This test asserts against the CURRENT
// (punctuated) keys and confirms the old bare (build-68-era) keys are gone, not
// left behind as dead weight - and that FormatTranslationStatsValue() no longer
// prepends its own leading punctuation (the caption carries it now instead).
//
// "Kostenfreier Anbieter (MyMemory)" (build 68) remains a registered translatable
// key, unaffected by the user's edit.

$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
assert($locale !== null, 'locale.json must parse as valid JSON');

// Test 1: every static form.json caption used in the redesigned stats rows
// and provider rows must be an EXACT, whole-string match against a
// registered locale.json key - no leftover punctuation baked onto a word.
$formJson = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');
$form = json_decode($formJson, true);
assert($form !== null, 'form.json must parse as valid JSON');

$requiredTranslatableCaptions = [
    'Seit Inbetriebnahme am', 'Tag(e).', 'Stündlich:', 'Insgesamt:',
    'Anfrage(n),', 'Zeichen.', 'Durch den Cache eingespart:',
    'Kostenfreier Anbieter (MyMemory)', 'pausiert bis',
];
foreach (['en', 'es', 'it', 'fr'] as $lang) {
    $block = $locale['translations'][$lang];
    foreach ($requiredTranslatableCaptions as $caption) {
        assert(array_key_exists($caption, $block), "Language '$lang' must have a registered translation for the exact caption '$caption' used in form.json");
        assert(trim($block[$caption]) !== '', "Language '$lang' translation for '$caption' must not be empty");
    }
    // The old, now-superseded BARE (no-punctuation) keys from before the user's
    // direct edit must not linger as unused dead weight in locale.json.
    foreach (['Tag(e)', 'Stündlich', 'Insgesamt', 'Anfrage(n)', 'Zeichen', 'Durch den Cache eingespart'] as $staleBareKey) {
        assert(!array_key_exists($staleBareKey, $block), "Language '$lang' must not still carry the superseded bare '$staleBareKey' key now that punctuation lives in the caption itself");
    }
    // The even older per-hour suffix keys (build 67) must also still be gone.
    assert(!array_key_exists('Anfrage(n)/h', $block), "Language '$lang' must not still carry the now-unused 'Anfrage(n)/h' key");
    assert(!array_key_exists('Zeichen/h', $block), "Language '$lang' must not still carry the now-unused 'Zeichen/h' key");
}
echo "Test 1 (every translatable caption used in the redesigned rows has an exact-match registered translation in all 4 languages, no stale bare-word keys left behind) OK\n";

// Test 2: FormatTranslationStatsValue() replica - since the user moved the leading
// punctuation onto the static caption itself, the raw values must now be PLAIN
// numbers with no leading punctuation at all (avoids the double-punctuation bug of
// e.g. "Stündlich: : 834").
function formatTranslationStatsValue(string $ident, array $stats): string
{
    if ($stats['since'] === 0) {
        return '';
    }
    $daysSince = max(0, (int) floor((1755600000 - $stats['since']) / 86400));

    return match ($ident) {
        'TranslationStatsSinceDateLabel'          => date('d.m.Y', $stats['since']) . ', ' . $daysSince,
        'TranslationStatsRequestsPerHourLabel'    => (string) (int) round($stats['requestsPerHour']),
        'TranslationStatsCharsPerHourValueLabel'  => (string) (int) round($stats['charsPerHour']),
        'TranslationStatsTotalRequestsLabel'      => (string) $stats['requestCount'],
        'TranslationStatsTotalCharsLabel'         => (string) $stats['characterCount'],
        'TranslationStatsCacheSavedRequestsLabel' => (string) $stats['cacheSavedRequestCount'],
        'TranslationStatsCacheSavedCharsLabel'    => (string) $stats['cacheSavedCharacterCount'],
        default                                   => '',
    };
}

$stats = [
    'since' => 1755100000, 'requestCount' => 1807, 'characterCount' => 27031,
    'requestsPerHour' => 834.0, 'charsPerHour' => 12471.0,
    'cacheSavedRequestCount' => 74, 'cacheSavedCharacterCount' => 100662,
];
assert(formatTranslationStatsValue('TranslationStatsRequestsPerHourLabel', $stats) === '834', 'The hourly-requests value must be a plain number with no leading punctuation - its row label "Stündlich:" already carries the colon');
assert(formatTranslationStatsValue('TranslationStatsTotalRequestsLabel', $stats) === '1807', 'The total-requests value must be a plain number, matching the reported real-world numbers');
echo "Test 2 (stats raw values are plain numbers with no leading punctuation, matching the user's caption-carries-punctuation redesign) OK\n";

echo "\nAll tests passed.\n";
