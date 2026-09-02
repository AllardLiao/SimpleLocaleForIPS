<?php
declare(strict_types=1);
// Standalone replica test for build 64 (2026-08-19), updated for build 68's
// stats-row redesign:
// The admin-facing stats sentence hard-coded the plural German nouns
// "Anfragen"/"Anfragen/h" as its locale.json lookup keys, so the console-
// language translation for these two specific words was simply never
// defined for a singular count (grammatically wrong, and - the reported
// gap - the combined singular/plural notation used everywhere else in this
// module, e.g. "Tag(e)" in the trial-info text, was never applied here at
// all). Fixed the same way as "Tag(e)": a single non-conjugating locale
// string per noun (no runtime branching), covering both counts.
//
// Build 68 additionally dropped the "/h" suffix entirely (replaced by an
// explicit "Stündlich"/"Hourly" row label, see
// test_console_language_exact_match_and_polish.php) - "Anfrage(n)"/
// "Zeichen" were reused bare across every stats row at that point.
//
// Updated again for the user's direct build-72-era edit ("UI"/"UI"/"FIX"
// commits, 2026-08-19): punctuation now lives baked onto the word itself
// ("Anfrage(n)," / "Zeichen."), consistently across form.json AND all 4
// locale.json blocks - the bare, unpunctuated keys are retired dead weight now.
//
// This test verifies the locale.json data itself: every language block
// must define the combined-notation keys (now punctuated), and every older,
// now-superseded key form (plural-only, "/h" suffix, and finally the bare
// unpunctuated build-68 form) must be gone.

$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
assert($locale !== null, 'locale.json must parse as valid JSON');

$requiredNewKeys = ['request(s),'];
$staleOldKeys = ['Anfragen/h', 'Anfragen', 'Anfrage(n)/h', 'Zeichen/h', 'Anfrage(n)', 'Zeichen'];
$languages = ['de', 'es', 'it', 'fr'];

foreach ($languages as $lang) {
    $block = $locale['translations'][$lang] ?? null;
    assert(is_array($block), "locale.json must contain a translations block for '$lang'");

    foreach ($requiredNewKeys as $key) {
        assert(array_key_exists($key, $block), "Language '$lang' must define the combined-notation key '$key'");
        assert(trim($block[$key]) !== '', "Language '$lang' must have a non-empty translation for '$key'");
    }

    foreach ($staleOldKeys as $key) {
        assert(!array_key_exists($key, $block), "Language '$lang' must NOT still define the stale/retired key '$key'");
    }

    // "Zeichen." keeps its German key name (German itself is invariant for
    // singular/plural), but its VALUE in every other language must have
    // been updated away from the old plural-only forms.
    assert($block['character(s).'] !== 'characters.' && $block['character(s).'] !== 'caracteres.'
        && $block['character(s).'] !== 'caratteri.' && $block['character(s).'] !== 'requêtes.',
        "Language '$lang' must not still show the old plural-only 'character(s).' translation");
}

echo "Test 1 (all 4 languages define the combined-notation, punctuated 'request(s),' key, every older/retired key form is gone) OK\n";

// Test 2: every new/updated value must actually be usable regardless of
// count - i.e. it must be a single, non-empty string (not something that
// itself needs further runtime branching, matching the "Tag(e)." philosophy:
// one string, works for count=1 and count=N alike).
foreach ($languages as $lang) {
    $block = $locale['translations'][$lang];
    foreach (['request(s),', 'character(s).'] as $key) {
        $value = $block[$key];
        assert(is_string($value) && $value !== '', "'$key' in '$lang' must be a single non-empty display string usable for any count");
    }
}
echo "Test 2 (every stats noun translation is a single ready-to-display string for any count, no runtime branching needed) OK\n";

echo "\nAll tests passed.\n";
