<?php
declare(strict_types=1);
// Standalone replica test for build 166 (2026-08-26, live gemeldet):
// "Translation active ja/nein: Wenn das im Konfigform geändert wird, wird bei
// ApplyChanges dies zwar in der Tabelle gespeichert, aber nicht in der Visu
// angepasst. Getestet bei Greeting und Charts - vermutlich aber überall."
//
// Die Vermutung stimmte: es betraf ALLE Zeilen-Tabellen.
//
// URSACHE: ApplyChanges() entscheidet ueber
// ComputeActiveLanguageContentFingerprint(), ob ApplyLanguage() anlaufen muss -
// aendert sich der fuer die aktive Sprache aufgeloeste Inhalt irgendeiner Zeile,
// wird geschrieben. Der Fingerabdruck loeste aber mit $CurrentLanguage auf,
// waehrend JEDE Schreibstelle GetEffectiveSelectedLanguage() verwendet und damit
// die Checkbox beruecksichtigt. Ein Umschalten aenderte den Fingerabdruck also
// nicht - gespeichert war die Aenderung, sichtbar wurde sie erst beim naechsten
// Sprachwechsel oder Rescan.
//
// Der Fingerabdruck muss abbilden, was tatsaechlich geschrieben WUERDE.

// Repliziert ResolveRowValue().
function resolve(array $row, string $selected, string $languageField, string $sourceLanguage, string $rawField): string
{
    if ($selected === 'ORIGINAL_IMPORT' || $selected === $sourceLanguage) {
        return (string) ($row[$rawField] ?? '');
    }
    if ((string) ($row[$languageField] ?? '') !== '') {
        return (string) $row[$languageField];
    }

    return (string) ($row[$rawField] ?? '');
}

function effective(array $row, string $language): string
{
    return ($row['TranslationActive'] ?? true) ? $language : 'ORIGINAL_IMPORT';
}

// Repliziert ComputeActiveLanguageContentFingerprint() vorher/nachher.
function fingerprint(array $rows, string $current, bool $mitFlag): string
{
    $parts = [];
    foreach ($rows as $row) {
        $selected = $mitFlag ? effective($row, $current) : $current;
        $parts[] = resolve($row, $selected, $current, 'de', 'ORIGINAL_IMPORT');
    }

    return md5(implode("\x00", $parts));
}

$an  = [['ORIGINAL_IMPORT' => 'Guten Tag', 'en' => 'Good afternoon', 'TranslationActive' => true]];
$aus = [['ORIGINAL_IMPORT' => 'Guten Tag', 'en' => 'Good afternoon', 'TranslationActive' => false]];

// Test 1: DER GEMELDETE FALL - ohne das Flag ist der Fingerabdruck vor und nach
// dem Umschalten identisch, ApplyLanguage() laeuft also nie an.
assert(fingerprint($an, 'en', false) === fingerprint($aus, 'en', false),
    'DER BUG: der Fingerabdruck ignorierte die Checkbox - deshalb blieb die Visu unveraendert');
echo "Test 1 (der gemeldete Fall wird reproduziert: identischer Fingerabdruck) OK\n";

// Test 2: DER FIX - mit dem Flag unterscheiden sich die Fingerabdruecke, und
// ApplyChanges() stoesst ApplyLanguage() an.
assert(fingerprint($an, 'en', true) !== fingerprint($aus, 'en', true),
    'DER FIX: das Umschalten muss den Fingerabdruck aendern, sonst laeuft ApplyLanguage() nicht an');
echo "Test 2 (mit dem Fix ändert das Umschalten den Fingerabdruck) OK\n";

// Test 3: DER GRUND - der Fingerabdruck muss das abbilden, was geschrieben
// WUERDE. Bei deaktivierter Uebersetzung ist das der Rohtext.
$geschrieben = resolve($aus[0], effective($aus[0], 'en'), 'en', 'de', 'ORIGINAL_IMPORT');
assert($geschrieben === 'Guten Tag', 'bei deaktivierter Uebersetzung wird der Rohtext geschrieben');
$geschriebenAn = resolve($an[0], effective($an[0], 'en'), 'en', 'de', 'ORIGINAL_IMPORT');
assert($geschriebenAn === 'Good afternoon', 'bei aktivierter Uebersetzung die Uebersetzung');
echo "Test 3 (der Fingerabdruck bildet ab, was tatsächlich geschrieben würde) OK\n";

// Test 4: KEIN Fehlalarm - ohne Aenderung bleibt der Fingerabdruck gleich,
// sonst liefe ApplyLanguage() bei JEDEM ApplyChanges (auch dem re-entranten aus
// jedem VM_UPDATE) unnoetig an.
assert(fingerprint($an, 'en', true) === fingerprint($an, 'en', true), 'ohne Aenderung darf sich nichts aendern');
echo "Test 4 (ohne Änderung kein unnötiger Durchlauf) OK\n";

// Test 5: bei aktiver BASISSPRACHE ist die Checkbox wirkungslos - dort wird
// ohnehin der Rohtext geschrieben. Der Fingerabdruck darf deshalb gleich
// bleiben.
assert(fingerprint($an, 'de', true) === fingerprint($aus, 'de', true),
    'bei aktiver Basissprache aendert die Checkbox nichts - also auch nicht den Fingerabdruck');
echo "Test 5 (bei aktiver Basissprache bleibt der Fingerabdruck gleich) OK\n";

// Test 6: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function ComputeActiveLanguageContentFingerprint');
assert($start !== false, 'die Funktion muss existieren');
$ende = strpos($moduleSource, "\n    // Läuft über alle sechs", $start);
$body = substr($moduleSource, $start, ($ende !== false ? $ende - $start : 2600));
$code = implode("\n", array_filter(
    explode("\n", $body),
    static fn (string $z): bool => strpos(ltrim($z), '//') !== 0
));

assert(strpos($code, '$effectiveLanguage = $this->GetEffectiveSelectedLanguage($row, $CurrentLanguage);') !== false,
    'DER FIX: der Fingerabdruck muss die Checkbox ueber GetEffectiveSelectedLanguage beruecksichtigen');
assert(strpos($code, '$effectiveLanguage,') !== false, 'und den ermittelten Wert auch verwenden');
// Das Sprachspalten-FELD bleibt bewusst an $CurrentLanguage gebunden - nur die
// AUSWAHL folgt dem Flag, genau wie an den Schreibstellen.
assert(strpos($code, "\$group['prefix'] . \$CurrentLanguage,") !== false,
    'das Spaltenfeld muss weiterhin an der aktiven Sprache haengen, nur die Auswahl folgt dem Flag');
echo "Test 6 (die reale Umsetzung berücksichtigt das Flag im Fingerabdruck) OK\n";

echo "\nAll tests passed.\n";
