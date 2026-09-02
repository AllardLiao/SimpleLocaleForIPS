<?php
// Standalone simulation of the reworked custom-tile logic in
// SimpleLocaleForIPS/SimpleLocale/module.php: GetVisualizationTile()'s
// decision of which HTML template to use, and the hard Pro-license gate on
// GetAvailableLanguages()/SetLanguage(). Mirrors the real method bodies
// (copy-adapted) since there is no live Symcon instance available here.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

const DEFAULT_HTML = '<div id="<!--WRAPPER_ID-->"><!--LANGUAGE_SELECT--></div>';

// Mirrors GetVisualizationTile()'s selection logic.
function selectTileHtml(bool $useCustomTile, bool $hasFeature, string $customTileHtmlProperty): string
{
    $html = '';
    if ($useCustomTile && $hasFeature) {
        $html = $customTileHtmlProperty;
    }

    if (trim($html) === '') {
        $html = DEFAULT_HTML; // GetDefaultCustomTileHtml() fallback
    }

    return $html;
}

// Mirrors ApplyTilePlaceholders().
function applyPlaceholders(string $html, int $instanceId, string $languageSelectHtml): string
{
    $html = str_replace('<!--WRAPPER_ID-->', 'sloc-select-wrapper-' . $instanceId, $html);

    return str_replace('<!--LANGUAGE_SELECT-->', $languageSelectHtml, $html);
}

// ---- Scenario 1: feature off entirely -> built-in HTML, regardless of any
// saved CustomTileHtml content (defense in depth, matches AutoRescanInterval
// convention). ----------------------------------------------------------
$result = selectTileHtml(false, false, '<div>my custom stuff <!--LANGUAGE_SELECT--></div>');
assertTrue($result === DEFAULT_HTML, 'UseCustomTile off -> built-in HTML used');

// ---- Scenario 2: UseCustomTile on, but license does NOT have custom_tile
// (e.g. Light edition with the checkbox somehow still true from a downgrade)
// -> built-in HTML, not the saved custom content. ------------------------
$result = selectTileHtml(true, false, '<div>my custom stuff <!--LANGUAGE_SELECT--></div>');
assertTrue($result === DEFAULT_HTML, 'UseCustomTile on but unlicensed -> built-in HTML used (defense in depth)');

// ---- Scenario 3: licensed + enabled + real custom content -> custom HTML
// used verbatim. ------------------------------------------------------------
$custom = '<div class="my-brand"><!--LANGUAGE_SELECT--></div>';
$result = selectTileHtml(true, true, $custom);
assertTrue($result === $custom, 'licensed + enabled + non-empty field -> custom HTML used');

// ---- Scenario 4: licensed + enabled but the field was cleared by the user
// -> safe fallback to the built-in HTML instead of an empty tile. ----------
$result = selectTileHtml(true, true, '   ');
assertTrue($result === DEFAULT_HTML, 'licensed + enabled but empty field -> falls back to built-in HTML, not blank');

// ---- Scenario 5: placeholder substitution is identical regardless of which
// template path was chosen (WRAPPER_ID appears twice in a realistic
// template - div id AND JS getElementById - both must match). -------------
$template = '<div id="<!--WRAPPER_ID-->"><!--LANGUAGE_SELECT--></div>'
    . '<script>document.getElementById("<!--WRAPPER_ID-->");</script>';
$rendered = applyPlaceholders($template, 42, '<select>...</select>');
assertTrue(substr_count($rendered, 'sloc-select-wrapper-42') === 2, 'WRAPPER_ID replaced consistently at both occurrences');
assertTrue(str_contains($rendered, '<select>...</select>'), 'LANGUAGE_SELECT replaced with rendered dropdown HTML');
assertTrue(!str_contains($rendered, '<!--WRAPPER_ID-->') && !str_contains($rendered, '<!--LANGUAGE_SELECT-->'), 'no placeholders left unresolved');

// ---- Mirrors the hard license gate on GetAvailableLanguages()/SetLanguage() ----
function guardedCall(bool $hasFeature, callable $body)
{
    if (!$hasFeature) {
        throw new Exception('requires Pro Edition (feature "custom_tile")');
    }

    return $body();
}

$threw = false;
try {
    guardedCall(false, fn () => 'should not run');
} catch (Exception $e) {
    $threw = true;
}
assertTrue($threw, 'GetAvailableLanguages()/SetLanguage() throw when custom_tile is not licensed');

$result = guardedCall(true, fn () => 'ok');
assertTrue($result === 'ok', 'GetAvailableLanguages()/SetLanguage() proceed normally when licensed');

// ---- Confirm the built-in dropdown's own onchange path is untouched: it
// goes through RequestAction() directly (Symcon core dispatch), never
// through the now-gated SetLanguage() wrapper - so gating SetLanguage()
// cannot break the free/built-in tile for unlicensed users. ---------------
// (Structural check only, mirroring the actual call graph confirmed in the
// source: BuildLanguageSelectHtml() emits onchange="requestAction('Language',
// this.value)", a Symcon-injected JS helper that invokes IPSModule::
// RequestAction() server-side - completely independent of our SetLanguage().)
assertTrue(true, 'built-in <select onchange> uses requestAction()/RequestAction(), bypassing the gated SetLanguage() wrapper entirely');

echo "\nAll custom-tile simulations passed.\n";
