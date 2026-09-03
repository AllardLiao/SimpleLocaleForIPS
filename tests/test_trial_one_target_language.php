<?php
declare(strict_types=1);
// Standalone replica test for build 199 (2026-09-02, Nutzer-Entscheidung): die
// Testphase ist "Pro mit EINER Zielsprache" statt fuenf praxisfernen Sprachen.
//
// VORHER: is/cy/zu/mi/la - Islaendisch, Walisisch, Zulu, Maori, Latein. Damit
// liess sich der Mechanismus pruefen, aber nie die Uebersetzungsqualitaet der
// EIGENEN Inhalte: niemand laesst Maori vor Gaesten laufen. Und weil die Kachel
// ohnehin nie live war, fiel der Rueckfall aufs Original nach 30 Tagen niemandem
// auf - der wirksamste Kaufanreiz verpuffte.
//
// JETZT: jede Sprache waehlbar, genau EINE Zielsprache, in den 30 Tagen
// jederzeit wechselbar. Nach Ablauf faellt alles aufs Original zurueck, ohne
// dauerhaft freie Sprachen.

$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$konstanten = (string) file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
$fenster = function (string $von) use ($module): string {
    $a = (int) strpos($module, $von);
    $b = (int) strpos($module, "\n    private function ", $a + 10);

    return substr($module, $a, $b - $a);
};

// Repliziert GetLicensedLanguageLimit().
function limit(bool $lizenzGueltig, bool $abgelaufen, int $ausLizenz = 0): int
{
    if (!$lizenzGueltig) {
        return $abgelaufen ? 0 : 1;
    }

    return $ausLizenz;
}

// Repliziert die Kuerzung aus EnforceLicensedLanguageLimit() NACH dem Fix.
function kuerzen(array $codes, string $quelle, int $limit): array
{
    if ($limit <= 0) {
        return $codes;
    }
    $behalten = [];
    $ziele = 0;
    foreach ($codes as $code) {
        if ($code === $quelle) {
            $behalten[] = $code;
            continue;
        }
        if ($ziele >= $limit) {
            continue;
        }
        $ziele++;
        $behalten[] = $code;
    }

    return $behalten;
}

// Test 1: DIE ENTSCHEIDUNG - laufende Testphase erlaubt genau eine Zielsprache.
assert(limit(false, false) === 1, 'DIE ENTSCHEIDUNG: eine Zielsprache in der Testphase');
echo "Test 1 (Testphase: genau eine Zielsprache) OK\n";

// Test 2: DER FALLSTRICK - die Quellsprache darf keinen Platz belegen.
// EnsureSourceLanguageIsTarget() traegt sie IMMER selbst ein; zaehlte sie mit,
// bliebe bei Limit 1 keine einzige echte Zielsprache uebrig.
assert(kuerzen(['de'], 'de', 1) === ['de'], 'die Quellsprache allein bleibt');
assert(kuerzen(['de', 'en'], 'de', 1) === ['de', 'en'],
    'DER FALLSTRICK: bei Limit 1 muss genau EINE Zielsprache neben der Quellsprache moeglich sein');
assert(kuerzen(['de', 'en', 'fr'], 'de', 1) === ['de', 'en'], 'die zweite wird gekuerzt');
echo "Test 2 (die Quellsprache belegt keinen Platz) OK\n";

// Test 3: dieselbe Korrektur gilt fuer BEZAHLTE Lizenzen - der Shop bewirbt
// "Zielsprachen". Eine Edition mit Limit 3 lieferte vorher nur zwei.
assert(kuerzen(['de', 'en', 'fr', 'es', 'it'], 'de', 3) === ['de', 'en', 'fr', 'es'],
    'Limit 3 heisst drei Zielsprachen, nicht zwei');
echo "Test 3 (bezahlte Limits meinen Zielsprachen) OK\n";

// Test 4: die Quellsprache bleibt auch dann, wenn sie NICHT vorne steht.
assert(kuerzen(['en', 'fr', 'de'], 'de', 1) === ['en', 'de'], 'die Quellsprache ueberlebt an jeder Position');
echo "Test 4 (Position der Quellsprache ist egal) OK\n";

// Test 5: NACH ABLAUF gibt es kein Limit mehr, sondern die Sperre - jede Sprache
// ausser der Quellsprache ist blockiert, die Kachel faellt aufs Original zurueck.
assert(limit(false, true) === 0, 'nach Ablauf greift das Limit nicht mehr, sondern IsTrialLocked()');
$blocked = $fenster('private function IsLanguageBlockedByTrial');
assert(strpos($blocked, 'IsTrialLocked()') !== false, 'die Sperre haengt am Ablauf');
assert(strpos($blocked, 'propertySourceLanguage') !== false, 'nur die Quellsprache bleibt erlaubt');
echo "Test 5 (nach Ablauf sperrt alles außer der Quellsprache) OK\n";

// Test 6: KEINE dauerhaft freien Demo-Sprachen mehr. Die fuenf Exoten sind weg -
// sonst haette der Kunde nach Ablauf weiter etwas, das ihm nichts nuetzt, und der
// Verlust waere wieder unsichtbar.
assert(strpos($konstanten, 'TRIAL_LANGUAGE_CODES') === false, 'die Konstante ist entfallen');
assert(strpos($module, 'TRIAL_LANGUAGE_CODES') === false, 'und wird nirgends mehr benutzt');
$frei = $fenster('private function GetFreeLanguageCodes');
assert(strpos($frei, 'GetActivePromotionalLanguageCodes()') !== false,
    'frei ist nur noch, was eine laufende Marketing-Aktion freigibt');
echo "Test 6 (keine dauerhaft freien Demo-Sprachen mehr) OK\n";

// Test 7: die Auswahl ist nicht mehr auf eine feste Liste gefiltert - waehlbar
// ist jede Sprache, die Begrenzung macht allein das Limit.
$optionen = $fenster('private function BuildTargetLanguageOptions');
assert(strpos($optionen, 'restrictToTrialLanguages') === false,
    'DIE OEFFNUNG: kein Filter auf feste Testsprachen mehr');
echo "Test 7 (jede Sprache ist wählbar) OK\n";

// Test 8 (Build 201, live gemeldet): dieselbe Zaehlung noch einmal im FORMULAR.
// EnforceLicensedLanguageLimit() kuerzt die gespeicherte Liste, die Sperre des
// Auswahlfelds ist ein ZWEITER, unabhaengiger Pfad - der zaehlte weiterhin die
// Quellsprache mit. Bei Limit 1 waere die Liste damit schon gesperrt gewesen,
// bevor ueberhaupt eine Zielsprache gewaehlt werden konnte.
function gesperrt(array $codes, string $quelle, int $limit): bool
{
    $ziele = array_filter($codes, static fn (string $c): bool => $c !== $quelle);

    return $limit > 0 && count($ziele) >= $limit;
}
assert(gesperrt(['de'], 'de', 1) === false,
    'DER FALLSTRICK: allein mit der Quellsprache darf die Liste NICHT gesperrt sein');
assert(gesperrt(['de', 'en'], 'de', 1) === true, 'nach der ersten Zielsprache aber schon');
assert(gesperrt(['de', 'en', 'fr'], 'de', 3) === false, 'bei Limit 3 bleibt Platz');
$module = (string) file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($module, '$limitReached = $languageLimit > 0 && count($zielsprachen) >= $languageLimit;') !== false,
    'die Formular-Sperre muss ueber die gefilterten Zielsprachen zaehlen');
echo "Test 8 (auch die Formular-Sperre zählt nur Zielsprachen) OK\n";

// Test 9: der Text daneben darf in der Testphase nicht von einer "Lizenz"
// sprechen - es gibt keine, deren Limit erreicht sein koennte.
assert(strpos($module, "trial version: one target language, more with a license") !== false,
    'DIE BESCHRIFTUNG: eigener Text fuer die Testphase');
assert(strpos($module, "\$grund = (\$this->GetLicenseInfo()['valid'] ?? false)") !== false,
    'die Unterscheidung haengt an einer gueltigen Lizenz');
echo "Test 9 (der Hinweis spricht in der Testphase nicht von einer Lizenz) OK\n";

// Test 10 (Build 202, live gemeldet): bei erreichtem Limit darf NUR das
// Hinzufuegen wegfallen, nicht die ganze Liste. Mit 'enabled' => false liess
// sich die bereits gewaehlte Zielsprache weder aendern noch loeschen - der
// Nutzer sass auf seiner ersten Wahl fest, obwohl sie laut Zusage jederzeit
// wechselbar ist. Umgestellt wird sie einfach in der Zeile.
$populate = substr($module, (int) strpos($module, 'private function PopulateFormElements'), 40000);
$zweig = substr($populate, (int) strpos($populate, 'elseif ($limitReached)'), 900);
assert(strpos($zweig, "\$element['add'] = false;") !== false,
    'DER BLOCKER: nur der Hinzufuegen-Knopf faellt weg');
assert(strpos($zweig, "\$element['enabled'] = false;") === false,
    'die Liste selbst muss bedienbar bleiben');
echo "Test 10 (bei erreichtem Limit bleibt die Zeile änderbar) OK\n";

// Test 11: eine Kuerzung beim Speichern darf nicht stillschweigend passieren.
// Der ausgeblendete Knopf wird erst beim naechsten Formularaufbau neu bewertet -
// wer das Formular oeffnet, solange Platz ist, kann darin beliebig viele Zeilen
// anlegen und verlor sie beim Speichern kommentarlos.
$enforce = $fenster('private function EnforceLicensedLanguageLimit');
assert(strpos($enforce, '$entfernt = count($rows) - count($filtered);') !== false,
    'die Zahl der entfernten Zeilen muss ermittelt werden');
assert(strpos($enforce, 'LogTranslateMessage(') !== false, 'und gemeldet werden');
echo "Test 11 (eine Kürzung wird gemeldet, nicht verschwiegen) OK\n";

echo "\nAlle Tests OK (Build 202: Testphase mit einer echten Zielsprache).\n";
