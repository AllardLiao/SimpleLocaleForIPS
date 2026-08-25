<?php
declare(strict_types=1);
// Standalone replica test for build 74 (2026-08-19):
// Live reported (screenshot, found twice): a plain object name ("N-JOY", no HTML
// whatsoever) came back from DeepL in Spanish as
// '<g id="1">N-JOY</g>                    <g id="2"><g id="3"/></g>' - synthetic
// placeholder tags DeepL injects into its OUTPUT when "tag_handling": "html" is
// requested, even for input that never contained a single real HTML tag. Root cause:
// TranslateChunkDeepL() unconditionally sent "tag_handling": "html" for EVERY
// request, copying Google's pattern of always requesting format=html - but DeepL's
// tag_handling is a fundamentally different, heavier mechanism (full markup
// parsing/segmentation) than Google's format=html (which just governs entity
// encoding), and is only actually needed for genuine "Eigene Texte" HTMLBox content.
// Fixed by threading $IsHtml through TranslateChunk() down to both
// TranslateChunkGoogle()/TranslateChunkDeepL(), so tag/markup handling is requested
// ONLY for real HTML content - never for plain fields like object names.

function buildGoogleRequestBody(array $texts, string $source, string $target, bool $isHtml): array
{
    return [
        'q'      => $texts,
        'source' => $source,
        'target' => $target,
        'format' => $isHtml ? 'html' : 'text',
    ];
}

function buildDeepLRequestBody(array $texts, string $source, string $target, bool $isHtml): array
{
    $body = [
        'text'        => $texts,
        'source_lang' => $source,
        'target_lang' => $target,
    ];
    if ($isHtml) {
        $body['tag_handling'] = 'html';
    }

    return $body;
}

// Test 1: THE reported bug - a plain, non-HTML text (e.g. an object name) must NOT
// request DeepL's tag_handling mode at all - no key in the request body.
$plainBody = buildDeepLRequestBody(['N-JOY'], 'de', 'es', false);
assert(!array_key_exists('tag_handling', $plainBody), 'A plain (non-HTML) DeepL request must not include tag_handling at all - that is what let synthetic <g> placeholder tags leak into plain-text output like "N-JOY"');
echo "Test 1 (plain-text DeepL requests omit tag_handling entirely - the actual fix for the <g> tag leak) OK\n";

// Test 2: genuine HTML content ("Eigene Texte" widgets) must still request DeepL's
// html tag handling, unchanged from before - needed to protect real markup tags.
$htmlBody = buildDeepLRequestBody(['<p>Hallo</p>'], 'de', 'en', true);
assert(($htmlBody['tag_handling'] ?? null) === 'html', 'Genuine HTML content must still request tag_handling=html so real markup tags are protected during translation');
echo "Test 2 (genuine HTML content still requests DeepL tag_handling=html, unchanged) OK\n";

// Test 3: same distinction for Google - plain text now explicitly requests
// format=text (was always format=html before, risking literal "&"/"<" corruption
// in a plain object name and an unnecessary entity-encode/decode round trip).
$plainGoogleBody = buildGoogleRequestBody(['Bad & WC'], 'de', 'en', false);
assert($plainGoogleBody['format'] === 'text', 'A plain (non-HTML) Google request must use format=text - format=html risks misinterpreting a literal "&"/"<" in an object name as markup');
echo "Test 3 (plain-text Google requests use format=text, avoiding literal &/< misinterpretation) OK\n";

// Test 4: genuine HTML content still requests Google format=html, unchanged.
$htmlGoogleBody = buildGoogleRequestBody(['<p>Hallo</p>'], 'de', 'en', true);
assert($htmlGoogleBody['format'] === 'html', 'Genuine HTML content must still request format=html so tags are protected during translation');
echo "Test 4 (genuine HTML content still requests Google format=html, unchanged) OK\n";

echo "\nAll tests passed.\n";
