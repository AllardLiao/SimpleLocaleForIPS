<?php
declare(strict_types=1);
// Build 125 (Nutzer-Wunsch, direkter Nachbericht der Automations/Objektnamen-
// Korruptions-Untersuchung, siehe README Change-Log): eine manuelle Korrektur
// einer Zielsprachen-Zelle (z.B. "Salir" statt der maschinell übersetzten
// "Andar" für die Automation "Gehen") landet nur in der jeweiligen
// Zeilen-Property, nie im persistenten Übersetzungs-Cache
// (StoreCachedTranslation wird ausschließlich nach einem frischen
// Anbieter-Aufruf befüllt). Wird die Zeile später aus irgendeinem Grund
// erneut als "veraltet" erkannt (siehe ReconcileRowFields), liefert ein
// Cache-Treffer die ALTE, vor-korrigierte Maschinenübersetzung zurück - und
// die landet dann ganz normal wieder in der Property, die manuelle
// Korrektur wird persistiert überschrieben. Fix: ApplyLanguage() synct jetzt
// bei jedem tatsächlichen Lauf den aktuell aufgelösten Zellwert jeder Zeile
// in den Cache zurück, unabhängig davon, ob der Wert von einem Anbieter
// oder von Hand kam.

function syncCurrentLanguageIntoCacheReplica(array $fieldGroupsByProperty, array $rowsByProperty, array &$cache, string $Language, string $InstanceSourceLanguage, callable $getRowSourceLanguage, callable $buildKey): bool
{
    $updates = [];
    foreach ($fieldGroupsByProperty as $property => $fieldGroups) {
        foreach ($rowsByProperty[$property] ?? [] as $row) {
            $rowSourceLanguage = $getRowSourceLanguage($row, $InstanceSourceLanguage);
            if ($Language === $rowSourceLanguage) {
                continue;
            }
            foreach ($fieldGroups as $group) {
                $rawText = (string) ($row[$group['raw']] ?? '');
                $cellValue = (string) ($row[$group['prefix'] . $Language] ?? '');
                if ($rawText === '' || $cellValue === '') {
                    continue;
                }
                $updates[$rowSourceLanguage][$rawText] = $cellValue;
            }
        }
    }

    if ($updates === []) {
        return false;
    }

    $changed = false;
    foreach ($updates as $rowSourceLanguage => $byRawText) {
        foreach ($byRawText as $rawText => $value) {
            // Build 127-Nachbesserung: ein rein numerischer Rohtext wird als
            // Array-Schlüssel von PHP automatisch zu einem echten Integer -
            // erneutes (string)-Casting stellt den Typ wieder her, den
            // BuildTranslationCacheKey() zwingend erwartet.
            $key = $buildKey($rowSourceLanguage, $Language, (string) $rawText);
            if (($cache[$key]['v'] ?? null) === $value) {
                continue;
            }
            $cache[$key] = ['v' => $value, 'h' => (int) ($cache[$key]['h'] ?? 0), 't' => time()];
            $changed = true;
        }
    }

    return $changed;
}

$getRowSourceLanguage = static fn (array $row, string $fallback): string => ($row['Quellsprache'] ?? '') !== '' ? $row['Quellsprache'] : $fallback;
$buildKey = static fn (string $s, string $t, string $text): string => "v1|$s|$t|" . hash('sha256', $text);
$fieldGroups = ['ObjectAutomations' => [['raw' => 'ORIGINAL_IMPORT', 'prefix' => '']]];

// Test 1: DER GEMELDETE BUG - eine manuell korrigierte Zelle ("Salir" statt
// der ursprünglich gecachten "Andar") muss die vorhandene, veraltete
// Cache-Antwort für denselben Rohtext überschreiben.
$cache = [$buildKey('de', 'es', 'Gehen') => ['v' => 'Andar', 'h' => 3, 't' => 1000]];
$rows = ['ObjectAutomations' => [
    ['AutomationID' => 29653, 'ORIGINAL_IMPORT' => 'Gehen', 'Quellsprache' => 'de', 'es' => 'Salir'],
]];
$changed = syncCurrentLanguageIntoCacheReplica($fieldGroups, $rows, $cache, 'es', 'de', $getRowSourceLanguage, $buildKey);
assert($changed === true, 'eine abweichende manuelle Korrektur muss als Aenderung erkannt werden');
assert($cache[$buildKey('de', 'es', 'Gehen')]['v'] === 'Salir', 'DER BUG: der Cache muss nach dem Sync die manuelle Korrektur zeigen, nicht mehr die alte Maschinenuebersetzung');
echo "Test 1 (eine manuelle Korrektur überschreibt die alte, gecachte Maschinenübersetzung für denselben Rohtext) OK\n";

// Test 2: keine Aenderung, wenn die Zelle bereits mit dem Cache uebereinstimmt
// - kein unnoetiger Schreibvorgang.
$cache2 = [$buildKey('de', 'es', 'Gehen') => ['v' => 'Salir', 'h' => 3, 't' => 1000]];
$changed2 = syncCurrentLanguageIntoCacheReplica($fieldGroups, $rows, $cache2, 'es', 'de', $getRowSourceLanguage, $buildKey);
assert($changed2 === false, 'stimmt die Zelle bereits mit dem Cache ueberein, darf kein Schreibvorgang gemeldet werden');
echo "Test 2 (bereits synchroner Cache-Eintrag meldet keine Änderung, kein unnötiger Schreibvorgang) OK\n";

// Test 3: ein brandneuer Rohtext ohne vorherigen Cache-Eintrag wird ergaenzt.
$cache3 = [];
$changed3 = syncCurrentLanguageIntoCacheReplica($fieldGroups, $rows, $cache3, 'es', 'de', $getRowSourceLanguage, $buildKey);
assert($changed3 === true, 'ein neuer Rohtext ohne Cache-Eintrag muss ebenfalls als Aenderung gelten');
assert($cache3[$buildKey('de', 'es', 'Gehen')]['v'] === 'Salir', 'der neue Eintrag muss den aktuellen Zellwert tragen');
echo "Test 3 (ein bisher ungecachter Rohtext wird beim Sync neu ergänzt) OK\n";

// Test 4: die Quellsprache selbst wird niemals gesynct (Language === rowSourceLanguage).
$cache4 = [];
$rowsSameLang = ['ObjectAutomations' => [
    ['AutomationID' => 1, 'ORIGINAL_IMPORT' => 'Gehen', 'Quellsprache' => 'de', 'de' => 'Gehen'],
]];
$changed4 = syncCurrentLanguageIntoCacheReplica($fieldGroups, $rowsSameLang, $cache4, 'de', 'de', $getRowSourceLanguage, $buildKey);
assert($changed4 === false, 'die Quellsprache selbst darf nie in den Cache gesynct werden - keine echte Uebersetzung');
echo "Test 4 (die Quellsprache selbst wird nie gesynct, keine unnötigen/falschen Cache-Einträge) OK\n";

// Test 5: eine leere Zielzelle (noch nicht übersetzt) wird nicht gesynct.
$cache5 = [];
$rowsEmpty = ['ObjectAutomations' => [
    ['AutomationID' => 1, 'ORIGINAL_IMPORT' => 'Gehen', 'Quellsprache' => 'de', 'es' => ''],
]];
$changed5 = syncCurrentLanguageIntoCacheReplica($fieldGroups, $rowsEmpty, $cache5, 'es', 'de', $getRowSourceLanguage, $buildKey);
assert($changed5 === false, 'eine leere Zielzelle darf keinen (falschen) Cache-Eintrag erzeugen');
echo "Test 5 (eine noch leere Zielzelle wird nicht gesynct) OK\n";

// Test 6: DER GEMELDETE BUG (Build 127-Nachbesserung, live als Fatal Error
// bestätigt): ein rein numerischer Rohtext (z.B. "15821408") wird als
// Array-Schlüssel von PHP automatisch in einen echten Integer umgewandelt -
// ohne erneutes (string)-Casting wirft der anschließende
// BuildTranslationCacheKey()-Aufruf (der zwingend einen String erwartet)
// einen TypeError. Live bestätigt: "Argument #3 ($SourceText) must be of
// type string, int given" bei jedem ApplyChanges()-Lauf, sobald irgendeine
// getrackte Zeile einen rein numerischen Original-Import-Wert hatte.
$cache6 = [];
$rowsNumeric = ['ObjectAutomations' => [
    ['AutomationID' => 1, 'ORIGINAL_IMPORT' => '15821408', 'Quellsprache' => 'de', 'es' => '15821408'],
]];
$changed6 = syncCurrentLanguageIntoCacheReplica($fieldGroups, $rowsNumeric, $cache6, 'es', 'de', $getRowSourceLanguage, $buildKey);
assert($changed6 === true, 'DER BUG: ein rein numerischer Rohtext darf keinen TypeError auslösen, sondern muss normal gesynct werden');
assert($cache6[$buildKey('de', 'es', '15821408')]['v'] === '15821408', 'der numerische Rohtext muss unter dem korrekten String-Schlüssel landen');
echo "Test 6 (ein rein numerischer Rohtext löst keinen TypeError mehr aus und wird korrekt gesynct) OK\n";

// Test 7: Symmetrie-Check - die reale module.php muss SyncCurrentLanguageIntoCache()
// tatsächlich definiert haben und aus ApplyLanguage() heraus aufrufen.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function SyncCurrentLanguageIntoCache(') !== false, 'SyncCurrentLanguageIntoCache() muss in der realen module.php existieren');
$applyLanguageStart = strpos($moduleSource, 'private function ApplyLanguage(');
$applyLanguageEnd = strpos($moduleSource, 'private function SyncCurrentLanguageIntoCache(');
$applyLanguageBody = substr($moduleSource, $applyLanguageStart, $applyLanguageEnd - $applyLanguageStart);
assert(strpos($applyLanguageBody, '$this->SyncCurrentLanguageIntoCache(') !== false, 'ApplyLanguage() muss SyncCurrentLanguageIntoCache() tatsächlich aufrufen, nicht nur definieren');
echo "Test 7 (SyncCurrentLanguageIntoCache() ist tatsächlich in ApplyLanguage() verdrahtet) OK\n";

// Test 8: Symmetrie-Check - die reale SyncCurrentLanguageIntoCache() muss den
// (string)-Cast des aus dem Array-Schlüssel zurückgelesenen Rohtexts
// tatsächlich vor dem BuildTranslationCacheKey()-Aufruf enthalten.
$syncFuncStart = strpos($moduleSource, 'private function SyncCurrentLanguageIntoCache(');
$syncFuncEnd = strpos($moduleSource, "\n    }\n", strpos($moduleSource, 'WriteAttributeString(self::attributeTranslationCache, json_encode($cache));', $syncFuncStart));
$syncFuncBody = substr($moduleSource, $syncFuncStart, $syncFuncEnd - $syncFuncStart);
assert(strpos($syncFuncBody, '(string) $rawText') !== false, 'DER BUG: SyncCurrentLanguageIntoCache() muss den Array-Schlüssel-Rohtext vor BuildTranslationCacheKey() zurück auf string casten');
echo "Test 8 (der (string)-Cast für den Array-Schlüssel-Rohtext ist tatsächlich in der realen SyncCurrentLanguageIntoCache() verdrahtet) OK\n";

echo "\nAll tests passed.\n";
