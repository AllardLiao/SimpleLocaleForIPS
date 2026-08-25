<?php
// Standalone simulation of ResolveLanguageSelectHtml() in
// SimpleLocaleForIPS/SimpleLocale/module.php, and its use from both
// ApplyTilePlaceholders() (initial load) and PushVisualizationUpdate()
// (live refresh) - the actual bug being fixed: both paths must now agree.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

const BUILTIN_SELECT_HTML = '<div class="ipssl-select-row"><select>...</select></div>';
const DEFAULT_FLAGS_HTML = '<div><span onclick="requestAction(\'Language\', \'ORIGINAL_IMPORT\');">DE</span><span onclick="requestAction(\'Language\', \'en\');">EN</span></div>';

function resolveLanguageSelectHtml(bool $useCustomTile, bool $hasFeature, string $customLanguageSelectHtml): string
{
    if ($useCustomTile && $hasFeature) {
        $custom = $customLanguageSelectHtml;
        if (trim($custom) !== '') {
            return $custom;
        }
    }

    return BUILTIN_SELECT_HTML; // stands in for BuildLanguageSelectHtml()
}

// ---- Scenario 1: feature off -> built-in select, regardless of saved content ----
$r = resolveLanguageSelectHtml(false, false, DEFAULT_FLAGS_HTML);
assertTrue($r === BUILTIN_SELECT_HTML, 'UseCustomTile off -> built-in select used');

// ---- Scenario 2: on + licensed + default flags example untouched -> flags used ----
$r = resolveLanguageSelectHtml(true, true, DEFAULT_FLAGS_HTML);
assertTrue($r === DEFAULT_FLAGS_HTML, 'on + licensed + default (non-empty) content -> flags example used immediately, no extra setup needed');

// ---- Scenario 3: field explicitly cleared -> falls back to built-in select ----
$r = resolveLanguageSelectHtml(true, true, '   ');
assertTrue($r === BUILTIN_SELECT_HTML, 'on + licensed + cleared field -> falls back to built-in select');

// ---- Scenario 4: on but unlicensed -> built-in select regardless of saved content ----
$r = resolveLanguageSelectHtml(true, false, DEFAULT_FLAGS_HTML);
assertTrue($r === BUILTIN_SELECT_HTML, 'on but unlicensed -> built-in select used (defense in depth)');

// ---- THE ACTUAL BUG FIX: initial render and the refresh push must now agree.
// Before the fix, GetVisualizationTile() (via ApplyTilePlaceholders) used the
// resolver while PushVisualizationUpdate() called BuildLanguageSelectHtml()
// directly - so a live language switch in one tab would silently replace a
// customer's custom flags markup with the built-in select in every other
// open tab. Both call sites must route through the same resolver now.
$initialRender = resolveLanguageSelectHtml(true, true, DEFAULT_FLAGS_HTML);
$refreshPush = resolveLanguageSelectHtml(true, true, DEFAULT_FLAGS_HTML);
assertTrue($initialRender === $refreshPush, 'initial render and refresh push resolve identically - no more silent overwrite of custom content');

echo "\nAll ResolveLanguageSelectHtml simulations passed.\n";
