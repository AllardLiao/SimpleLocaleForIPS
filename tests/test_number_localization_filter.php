<?php
declare(strict_types=1);
// Standalone replica test for build 70 (2026-08-19):
// User-Wunsch: "Zahlen bleiben Zahlen, '%' '°C' etc. bleiben auch gleich" - ein
// Text-Fragment OHNE jeden Buchstaben (reine Zahlen, Prozent-/Grad-Zeichen, Uhrzeiten,
// Satzzeichen) geht nicht mehr an die Übersetzungs-API. Zusätzlich (Nutzer-Ergänzung
// während der Umsetzung): eine erkannte REINE Zahl wird nicht nur unverändert
// durchgereicht, sondern über PHPs eingebaute NumberFormatter-Klasse (ext-intl) lokal
// in die landesübliche Schreibweise der Zielsprache umgerechnet (z.B. deutsches
// "1.234,56" -> englisches "1,234.56") - komplett ohne API-Aufruf.

function textNeedsTranslation(string $text): bool
{
    return preg_match('/\p{L}/u', $text) === 1;
}

function localizeNumericFragment(string $text, string $sourceLanguage, string $targetLanguage): ?string
{
    if (!class_exists('NumberFormatter')) {
        return null;
    }

    if (!preg_match('/^(\s*)([+\-]?[0-9][0-9.,\x{00A0}\x{202F}]*)(.*)$/u', $text, $matches)) {
        return null;
    }
    [, $leading, $numberPart, $trailing] = $matches;

    $sourceFormatter = new NumberFormatter($sourceLanguage, NumberFormatter::DECIMAL);
    $decimalSeparator = $sourceFormatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    $groupingSeparator = $sourceFormatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);

    $lastDecimalPos = $decimalSeparator !== '' ? strrpos($numberPart, $decimalSeparator) : false;
    $integerPart = $lastDecimalPos !== false ? substr($numberPart, 0, $lastDecimalPos) : $numberPart;
    $fractionDigits = 0;
    if ($lastDecimalPos !== false) {
        $fractionDigits = strlen(preg_replace('/[^0-9]/u', '', substr($numberPart, $lastDecimalPos + strlen($decimalSeparator))));
    }
    $hadGrouping = $groupingSeparator !== '' && str_contains($integerPart, $groupingSeparator);

    $parsed = $sourceFormatter->parse($numberPart);
    if ($parsed === false) {
        return null;
    }

    $targetFormatter = new NumberFormatter($targetLanguage, NumberFormatter::DECIMAL);
    $targetFormatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $fractionDigits);
    $targetFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $fractionDigits);
    $targetFormatter->setAttribute(NumberFormatter::GROUPING_USED, $hadGrouping ? 1 : 0);
    $formatted = $targetFormatter->format($parsed);

    return $formatted === false ? null : $leading . $formatted . $trailing;
}

function resolveNonTranslatableText(string $text, string $sourceLanguage, string $targetLanguage): string
{
    return localizeNumericFragment($text, $sourceLanguage, $targetLanguage) ?? $text;
}

// --- TextNeedsTranslation ---

// Test 1: reine Zahlen, Symbole, Satzzeichen brauchen keine Übersetzung.
foreach (['78', '1.234,56', '%', '°', ':', '21:34', '18.08.2026', '-5,5', ''] as $text) {
    assert(textNeedsTranslation($text) === false, "\"$text\" enthält keinen Buchstaben und darf nicht als übersetzungsbedürftig gelten");
}
echo "Test 1 (reine Zahlen/Symbole/Satzzeichen brauchen keine Übersetzung) OK\n";

// Test 2: jedes Fragment mit mindestens einem Buchstaben braucht weiterhin echte
// Übersetzung - auch eine einzelne Einheit wie "h" in "21 km/h" oder "°C".
foreach (['Küche', 'a', '21 km/h', '°C', 'Living room', 'WLAN'] as $text) {
    assert(textNeedsTranslation($text) === true, "\"$text\" enthält einen Buchstaben und muss weiterhin übersetzt werden");
}
echo "Test 2 (jedes Fragment mit mindestens einem Buchstaben geht weiterhin durch die Übersetzungs-API) OK\n";

// --- LocalizeNumericFragment / ResolveNonTranslatableText ---

// Test 3: deutsches Dezimalzahl-Format -> englisches Format (Tausender-/Dezimaltrennzeichen vertauscht).
assert(localizeNumericFragment('1.234,56', 'de', 'en') === '1,234.56', 'Deutsches "1.234,56" muss zu englischem "1,234.56" werden');
echo "Test 3 (deutsche -> englische Zahlenschreibweise) OK\n";

// Test 4: KRITISCH - eine reine 4-stellige Zahl OHNE Tausendertrennzeichen im
// Original (z.B. eine Jahreszahl, Zimmernummer, ID) darf NICHT nachträglich mit
// einem künstlich eingefügten Tausendertrennzeichen versehen werden - ICU würde das
// beim Formatieren sonst standardmäßig ab 4 Ziffern tun.
assert(localizeNumericFragment('2026', 'de', 'en') === '2026', 'Eine reine Zahl ohne Gruppierung im Original darf beim Zielformat keine künstliche Gruppierung bekommen (z.B. eine Jahreszahl)');
assert(localizeNumericFragment('1234', 'de', 'en') === '1234', 'Dasselbe gilt für eine reine ID-/Zimmernummer ohne Tausendertrennzeichen');
echo "Test 4 (keine künstliche Gruppierung bei ungruppierten Original-Zahlen wie Jahreszahlen/IDs) OK\n";

// Test 5: hatte das Original eine Gruppierung, bleibt sie (in der Zielsprachen-
// Schreibweise) erhalten.
assert(localizeNumericFragment('10.000', 'de', 'en') === '10,000', 'Eine bereits gruppierte Zahl muss auch im Ziel gruppiert bleiben, nur mit dem Zielsprachen-Trennzeichen');
echo "Test 5 (vorhandene Gruppierung bleibt erhalten, nur das Trennzeichen wechselt) OK\n";

// Test 6: negative Zahlen und Dezimalstellen werden korrekt übernommen.
assert(localizeNumericFragment('-5,5', 'de', 'en') === '-5.5', 'Negative Dezimalzahl muss korrekt umformatiert werden');
echo "Test 6 (negative Dezimalzahlen korrekt umformatiert) OK\n";

// Test 7: ein Fragment ganz ohne erkennbare Zahl (z.B. nur "%") liefert null - der
// Aufrufer (ResolveNonTranslatableText) reicht es dann unverändert durch.
assert(localizeNumericFragment('%', 'de', 'en') === null, 'Ein Fragment ohne Zahl darf nicht "erfolgreich" umformatiert werden');
assert(resolveNonTranslatableText('%', 'de', 'en') === '%', 'ResolveNonTranslatableText muss bei einem Nicht-Zahlen-Fragment unverändert durchreichen');
echo "Test 7 (Fragmente ohne Zahl bleiben über ResolveNonTranslatableText unverändert) OK\n";

// Test 8: ResolveNonTranslatableText nutzt für eine echte Zahl die Umformatierung.
assert(resolveNonTranslatableText('1.234,56', 'de', 'en') === '1,234.56', 'ResolveNonTranslatableText muss für eine erkannte Zahl die lokale Umformatierung verwenden');
echo "Test 8 (ResolveNonTranslatableText reicht erkannte Zahlen umformatiert durch) OK\n";

// Test 9: ein Fragment MIT Buchstaben (z.B. "km/h") würde laut TextNeedsTranslation
// ohnehin nie an ResolveNonTranslatableText übergeben - zur Doku hier trotzdem
// geprüft, dass LocalizeNumericFragment bei einem Präfix-Treffer plus Buchstaben-
// Suffix den Suffix unangetastet lässt (falls es doch aufgerufen würde).
assert(localizeNumericFragment('21 km/h', 'de', 'en') === '21 km/h', 'Ein Buchstaben-Suffix nach der Zahl bleibt unverändert erhalten (wird hier real aber nie erreicht, siehe TextNeedsTranslation)');
echo "Test 9 (Buchstaben-Suffix nach einer Zahl bleibt unangetastet) OK\n";

echo "\nAll tests passed.\n";
