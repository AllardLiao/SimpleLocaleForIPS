<?php
// Standalone simulation of the translation cache (TranslateBatch wrapper,
// GetCachedTranslation/StoreCachedTranslation) and the fixed
// ApplyTrackedVariableUpdate() behavior in SimpleLocaleForIPS/SimpleLocale/
// module.php. Mirrors the real method bodies (copy-adapted) since there is
// no live Symcon instance available here. Verifies: (a) the correctness bug
// - switching to a non-active language after an external VM_UPDATE no longer
// shows stale/raw content, every configured target language gets refreshed
// immediately - and (b) the cache makes repeat values (the daily greeting
// cycle) cheap after their first occurrence.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

final class TranslateSim
{
    public array $cache = []; // "src|tgt|hash" => translated
    public int $apiCalls = 0;
    public array $fakeTranslations = []; // "src|tgt|text" => translated (backs the "API")

    private function cacheKey(string $src, string $tgt, string $text): string
    {
        return $src . '|' . $tgt . '|' . hash('sha256', $text);
    }

    // Mirrors TranslateBatch().
    public function translateBatch(array $texts, string $src, string $tgt): array
    {
        if ($texts === [] || $src === $tgt) {
            return $texts;
        }

        $results = [];
        $freshIndexes = [];
        $freshTexts = [];
        foreach ($texts as $i => $text) {
            $key = $this->cacheKey($src, $tgt, $text);
            if (isset($this->cache[$key])) {
                $results[$i] = $this->cache[$key];
            } else {
                $freshIndexes[] = $i;
                $freshTexts[] = $text;
            }
        }

        if ($freshTexts !== []) {
            $this->apiCalls++; // one simulated "API call" per uncached batch
            foreach ($freshIndexes as $pos => $originalIndex) {
                $text = $freshTexts[$pos];
                $translated = $this->fakeTranslations[$src . '|' . $tgt . '|' . $text] ?? '';
                $results[$originalIndex] = $translated;
                if ($translated !== '') {
                    $this->cache[$this->cacheKey($src, $tgt, $text)] = $translated;
                }
            }
        }

        ksort($results);

        return array_values($results);
    }

    // Mirrors the fixed ApplyTrackedVariableUpdate(): translates into ALL
    // configured target languages immediately, not just the active one.
    public function applyTrackedVariableUpdate(array &$row, string $sourceLanguage, array $targetLanguages, string $currentLanguage, string $newValue): string
    {
        $row['ORIGINAL_IMPORT_Text'] = $newValue;
        $displayText = $newValue;

        foreach ($targetLanguages as $lang) {
            $translated = $this->translateBatch([$newValue], $sourceLanguage, $lang);
            $translatedText = $translated[0] ?? '';
            $row['Text_' . $lang] = $translatedText;
            if ($lang === $currentLanguage && $translatedText !== '') {
                $displayText = $translatedText;
            }
        }

        return $displayText;
    }
}

$sim = new TranslateSim();
$sim->fakeTranslations = [
    'de|en|Guten Morgen' => 'Good Morning',
    'de|fr|Guten Morgen' => 'Bonjour',
    'de|en|Guten Tag' => 'Good Day',
    'de|fr|Guten Tag' => 'Bonjour (day)',
    'de|en|Guten Abend' => 'Good Evening',
    'de|fr|Guten Abend' => 'Bonsoir',
    'de|en|Gute Nacht' => 'Good Night',
    'de|fr|Gute Nacht' => 'Bonne Nuit',
];

$targetLanguages = ['en', 'fr'];
$row = ['ORIGINAL_IMPORT_Text' => '', 'Text_en' => '', 'Text_fr' => ''];

// ---- THE CORRECTNESS BUG: external update while English is active, then a
// switch to French. -------------------------------------------------------
$display = $sim->applyTrackedVariableUpdate($row, 'de', $targetLanguages, 'en', 'Guten Morgen');
assertTrue($row['ORIGINAL_IMPORT_Text'] === 'Guten Morgen', 'raw source field updated to the fresh external value');
assertTrue($display === 'Good Morning', 'currently active language (en) gets the fresh translation written to the variable');
assertTrue($row['Text_fr'] === 'Bonjour', 'French cell is ALSO refreshed immediately, not left empty/stale (the bug fix)');

// Simulate switching to French now - ResolveRowValue would read row['Text_fr'].
assertTrue($row['Text_fr'] === 'Bonjour', 'switching to French after the update shows the correct fresh translation, not raw/stale content');

// ---- THE CACHE: the daily greeting cycle. First full cycle costs API calls,
// every later cycle is free. -------------------------------------------------
$sim2 = new TranslateSim();
$sim2->fakeTranslations = $sim->fakeTranslations;
$row2 = ['ORIGINAL_IMPORT_Text' => '', 'Text_en' => '', 'Text_fr' => ''];

$greetingCycle = ['Guten Morgen', 'Guten Tag', 'Guten Abend', 'Gute Nacht'];
foreach ($greetingCycle as $greeting) {
    $sim2->applyTrackedVariableUpdate($row2, 'de', $targetLanguages, 'en', $greeting);
}
$callsAfterFirstCycle = $sim2->apiCalls;
assertTrue($callsAfterFirstCycle === 8, 'first full cycle through all 4 greetings x 2 target languages costs 8 API calls (no cache hits yet)');

// Second, third, ... cycle: every value has been seen before -> pure cache hits.
foreach (range(1, 5) as $day) {
    foreach ($greetingCycle as $greeting) {
        $sim2->applyTrackedVariableUpdate($row2, 'de', $targetLanguages, 'en', $greeting);
    }
}
assertTrue($sim2->apiCalls === $callsAfterFirstCycle, 'every subsequent cycle (5 more days simulated) makes ZERO additional API calls - fully served from cache');
assertTrue(count($sim2->cache) === 8, 'cache holds exactly the 4 greetings x 2 languages = 8 entries, no unbounded growth for a cyclical value');

// ---- Cross-check: cache correctly keyed by (source, target, text) - a
// DIFFERENT source language for the same text must NOT hit a wrong cache
// entry. ---------------------------------------------------------------------
$sim3 = new TranslateSim();
$sim3->fakeTranslations = ['de|en|Hallo' => 'Hello', 'en|de|Hallo' => 'Hallo (mistranslated, different pair)'];
$sim3->translateBatch(['Hallo'], 'de', 'en');
$result = $sim3->translateBatch(['Hallo'], 'en', 'de');
assertTrue($sim3->apiCalls === 2, 'different (source,target) pairs for the same text are cached independently, no false-positive cache hit');

echo "\nAll translation-cache and multi-language-refresh simulations passed.\n";
