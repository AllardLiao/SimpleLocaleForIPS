<?php
// Standalone simulation of the "CustomTileHtmlButton" gating logic added to
// PopulateFormElements() in module.php: visible follows UseCustomTile
// (checkbox), enabled follows the custom_tile license feature - independent
// dimensions, mirroring the real switch-case body.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

function computeButtonElement(bool $useCustomTile, bool $hasFeature): array
{
    $element = ['visible' => true, 'enabled' => true, 'caption' => 'Eigenen Kachel-HTML-Code bearbeiten'];

    $element['visible'] = $useCustomTile;
    if (!$hasFeature) {
        $element['enabled'] = false;
        $element['caption'] = 'Eigenen Kachel-HTML-Code bearbeiten (Pro Edition erforderlich)';
    }

    return $element;
}

$r = computeButtonElement(false, true);
assertTrue($r['visible'] === false, 'UseCustomTile off + licensed -> hidden');

$r = computeButtonElement(false, false);
assertTrue($r['visible'] === false, 'UseCustomTile off + unlicensed -> hidden');

$r = computeButtonElement(true, true);
assertTrue($r['visible'] === true && $r['enabled'] === true, 'UseCustomTile on + licensed -> visible and enabled');

$r = computeButtonElement(true, false);
assertTrue($r['visible'] === true && $r['enabled'] === false, 'UseCustomTile on + unlicensed -> visible but grayed out (discoverable upsell)');
assertTrue(str_contains($r['caption'], 'Pro Edition erforderlich'), 'unlicensed caption carries the static Pro-Edition suffix');

echo "\nAll CustomTileHtmlButton visibility simulations passed.\n";
