<?php
declare(strict_types=1);
// Standalone replica test for build 78 (2026-08-20):
// User requests: (1) bold info-popup heading - alert() is plain text with no HTML,
// so "Mathematical Sans-Serif Bold" Unicode characters (U+1D5D4 block) are used as
// a visual approximation instead; (2) the pause paragraph must explain WHY it's
// paused ("alle Provider melden Limit erreicht"), not just until when.

function toBoldUnicodeReplica(string $text): string
{
    $result = '';
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
        $codepoint = mb_ord($char, 'UTF-8');
        if ($codepoint >= 0x41 && $codepoint <= 0x5A) {
            $result .= mb_chr(0x1D5D4 + ($codepoint - 0x41), 'UTF-8');
        } elseif ($codepoint >= 0x61 && $codepoint <= 0x7A) {
            $result .= mb_chr(0x1D5EE + ($codepoint - 0x61), 'UTF-8');
        } elseif ($codepoint >= 0x30 && $codepoint <= 0x39) {
            $result .= mb_chr(0x1D7EC + ($codepoint - 0x30), 'UTF-8');
        } else {
            $result .= $char;
        }
    }

    return $result;
}

// Test 1: every letter/digit is mapped to its bold Unicode equivalent.
$bold = toBoldUnicodeReplica('Simple Locale - Pro Edition');
assert($bold !== 'Simple Locale - Pro Edition', 'The bold-converted heading must differ from the plain-ASCII original - alert() has no other way to show emphasis');
assert(mb_strlen($bold, 'UTF-8') === mb_strlen('Simple Locale - Pro Edition', 'UTF-8'), 'Character COUNT must stay identical - only each character maps to a different Unicode codepoint, nothing is added/removed');
echo "Test 1 (heading text converts to visually-bold Unicode characters, same character count) OK\n";

// Test 2: non-letter characters (space, hyphen) are left completely unchanged -
// only the Mathematical Alphanumeric Symbols block covers A-Z/a-z/0-9.
assert(str_contains($bold, ' '), 'Spaces must remain literal ASCII spaces, unaffected by the bold conversion');
assert(str_contains($bold, '-'), 'The hyphen must remain a literal ASCII hyphen - no bold variant exists for punctuation in this Unicode block');
echo "Test 2 (non-alphanumeric characters like spaces and hyphens are left untouched) OK\n";

// Test 3: round-trip sanity - converting back via a reverse map would recover the
// original (not implemented here, just confirming the forward mapping is
// deterministic and reversible in principle: same input always produces same output).
assert(toBoldUnicodeReplica('Test') === toBoldUnicodeReplica('Test'), 'The conversion must be deterministic');
echo "Test 3 (bold conversion is deterministic) OK\n";

// --- Pause reason ---

function buildGuestPauseInfoTextReplica(?int $globalPauseUntil, array $rowsByKey): string
{
    if ($globalPauseUntil === null) {
        return '';
    }
    $prefix = $rowsByKey['pausedNoticePrefix'] ?? 'Übersetzung pausiert bis';
    $reason = $rowsByKey['pausedReason'] ?? 'Grund: Alle konfigurierten Übersetzungsanbieter melden aktuell ihr Limit erreicht.';
    $reassurance = $rowsByKey['pausedReassurance'] ?? 'Bereits vorhandene Übersetzungen bleiben nutzbar.';

    return $prefix . ' ' . date('d.m. H:i', $globalPauseUntil) . "\n" . $reason . "\n" . $reassurance;
}

// Test 4: the pause paragraph now includes three lines - prefix+time, reason, and
// reassurance - matching the user's explicit request to explain WHY it's paused.
$pauseText = buildGuestPauseInfoTextReplica(1755720000, [
    'pausedNoticePrefix' => 'Translation paused until',
    'pausedReason' => 'Reason: All configured translation providers currently report their limit reached.',
    'pausedReassurance' => 'Existing translations remain usable.',
]);
$lines = explode("\n", $pauseText);
assert(count($lines) === 3, 'The pause paragraph must now have exactly three lines: until-when, reason, reassurance');
assert(str_contains($lines[1], 'Reason:'), 'The second line must explain WHY the pause is active, not just until when');
echo "Test 4 (pause paragraph now explains the reason, in addition to until-when and the reassurance) OK\n";

echo "\nAll tests passed.\n";
