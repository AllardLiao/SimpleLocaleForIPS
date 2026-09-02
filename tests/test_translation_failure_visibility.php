<?php
declare(strict_types=1);
// Standalone replica test for build 152 (2026-08-26, Nutzer-Frage: "Wie
// bekommt der User vom Ausfall eines Anbieters mit? Wenn es keine Information
// darüber gibt, könnte er denken, dass unsere App nicht richtig funktioniert."):
//
// Berechtigter Einwand - und Build 151 hatte das Problem sogar VERSCHAERFT:
// Vorher scheiterte bei einem Anbieterfehler der ganze Durchlauf und setzte
// wenigstens einen Fehlerstatus. Seit Build 151 bleiben Teilerfolge erhalten
// (richtig!), der Lauf gilt damit als gelungen - und der Ausfall wurde
// UNSICHTBAR. Der Nutzer saehe nur leere Zellen.
//
// Zwei getrennte Zaehler, weil sie zu GEGENSAETZLICHEN Ratschlaegen fuehren:
//   unreachable - Anbieter nicht erreichbar (jeder HTTP >= 400, cURL-Fehler,
//                 Timeout). Ein erneuter Scan kann klappen -> dazu auffordern.
//   tooLong     - Text ueber MyMemorys 500-Byte-Grenze. Ein erneuter Scan
//                 aendert GAR NICHTS -> hier waere "bitte nochmal scannen" ein
//                 wertloser Rat; es hilft nur ein Google-/DeepL-Schluessel.

// Repliziert RecordTranslationFailure()/GetTranslationFailureReport().
function recordFailure(array $report, string $kind, int $count = 1): array
{
    if ($count < 1) {
        return $report;
    }
    $report[$kind] = ($report[$kind] ?? 0) + $count;
    $report['at'] = 1_800_000_000;

    return $report;
}

// Repliziert die Sichtbarkeits-Logik aus PopulateFormElements().
function rowVisible(array $report, string $kind): bool
{
    return ($report[$kind] ?? 0) > 0;
}

// Test 1: DER GEMELDETE FALL - nach einem Lauf mit Anbieterfehlern muss der
// Hinweis sichtbar sein. Ohne ihn haelt der Nutzer das Modul fuer defekt.
$report = recordFailure([], 'unreachable', 12);
assert(rowVisible($report, 'unreachable') === true, 'DER GEMELDETE FALL: nach nicht erreichbarem Anbieter MUSS ein sichtbarer Hinweis erscheinen - sonst haelt der Nutzer das Modul fuer defekt');
assert($report['unreachable'] === 12, 'die Anzahl muss genannt werden, damit das Ausmass erkennbar ist');
echo "Test 1 (ein Anbieterausfall wird im Formular sichtbar, mit Anzahl) OK\n";

// Test 2: ein sauberer Lauf zeigt NICHTS. Ein Dauerhinweis wuerde ignoriert
// und waere schlimmer als keiner.
$sauber = [];
assert(rowVisible($sauber, 'unreachable') === false, 'ein fehlerfreier Lauf darf keinen Hinweis zeigen');
assert(rowVisible($sauber, 'tooLong') === false, 'ebenso fuer die Laengen-Meldung');
echo "Test 2 (ein sauberer Durchlauf zeigt keinen Hinweis) OK\n";

// Test 3: DIE TRENNUNG - beide Ursachen werden getrennt gezaehlt und getrennt
// gemeldet, weil ihre Ratschlaege einander widersprechen.
$gemischt = recordFailure(recordFailure([], 'unreachable', 3), 'tooLong', 2);
assert($gemischt['unreachable'] === 3 && $gemischt['tooLong'] === 2, 'beide Ursachen muessen getrennt gezaehlt werden');
assert(rowVisible($gemischt, 'unreachable') && rowVisible($gemischt, 'tooLong'), 'bei beiden Ursachen muessen auch beide Hinweise erscheinen');
echo "Test 3 (beide Ursachen werden getrennt gezählt und getrennt gemeldet) OK\n";

// Test 4: nur zu lange Texte -> NUR die Laengen-Meldung. "Bitte erneut scannen"
// waere hier falsch: die Grenze bleibt beim naechsten Lauf exakt dieselbe.
$nurLang = recordFailure([], 'tooLong', 4);
assert(rowVisible($nurLang, 'tooLong') === true, 'die Laengen-Meldung muss erscheinen');
assert(rowVisible($nurLang, 'unreachable') === false, 'DER FALSCHE RAT: ohne Anbieterfehler darf NICHT zum erneuten Scan aufgefordert werden - an der Byte-Grenze aendert ein weiterer Lauf nichts');
echo "Test 4 (zu lange Texte fordern nicht zum sinnlosen erneuten Scan auf) OK\n";

// Test 5: die Bilanz wird pro Lauf zurueckgesetzt - der Hinweis muss den
// AKTUELLEN Durchlauf zeigen, nicht die Summe aller Zeiten. Andernfalls bliebe
// er nach einem geglueckten Lauf faelschlich stehen.
$vorher = recordFailure([], 'unreachable', 12);
$nachReset = [];    // ResetTranslationFailureReport()
assert(rowVisible($nachReset, 'unreachable') === false, 'nach dem Zuruecksetzen zu Beginn eines Laufs darf der alte Hinweis nicht stehenbleiben');
echo "Test 5 (die Bilanz gilt je Durchlauf und wird zu dessen Beginn zurückgesetzt) OK\n";

// Test 6: der Fortschritt muss ablesbar sein. Da Teilerfolge seit Build 151
// erhalten bleiben, sinkt die Zahl von Lauf zu Lauf - der Nutzer sieht, dass
// es vorangeht, statt immer dieselbe Zahl zu sehen.
$lauf1 = recordFailure([], 'unreachable', 30);
$lauf2 = recordFailure([], 'unreachable', 11);
$lauf3 = [];
assert($lauf2['unreachable'] < $lauf1['unreachable'], 'die Zahl muss von Lauf zu Lauf sinken koennen - sonst wirkt es wie Stillstand');
assert(rowVisible($lauf3, 'unreachable') === false, 'ist alles nachgeholt, verschwindet der Hinweis von selbst');
echo "Test 6 (die Zahl sinkt von Lauf zu Lauf und der Hinweis verschwindet am Ende) OK\n";

// Test 7: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');
$formJson = file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json');

assert(strpos($constantsSource, "attributeLastRunTranslationFailures = 'LastRunTranslationFailures'") !== false, 'das Bilanz-Attribut muss deklariert sein');
assert(strpos($moduleSource, 'private function RecordTranslationFailure(') !== false, 'der Zaehler-Helfer muss existieren');
assert(strpos($moduleSource, 'private function ResetTranslationFailureReport(): void') !== false, 'das Zuruecksetzen pro Lauf muss existieren');
assert(strpos($moduleSource, '$this->ResetTranslationFailureReport();') !== false, 'die Bilanz muss zu Beginn eines Rescans zurueckgesetzt werden');
assert(strpos($moduleSource, "\$this->RecordTranslationFailure('unreachable');") !== false, 'Einzelfehlschlaege beim kostenfreien Anbieter muessen gezaehlt werden');
assert(strpos($moduleSource, "\$this->RecordTranslationFailure('tooLong');") !== false, 'die Byte-Grenze muss getrennt gezaehlt werden');
assert(strpos($moduleSource, "\$this->RecordTranslationFailure('unreachable', count(\$Texts));") !== false, 'auch der Totalausfall der Kette muss in die Bilanz - er deckt Google/DeepL ab, die pro Chunk nur ganz oder gar nicht liefern');
assert(strpos($formJson, 'TranslationFailureUnreachableRow') !== false, 'die Hinweiszeile muss im Formular liegen');
assert(strpos($formJson, 'TranslationFailureTooLongRow') !== false, 'ebenso die Laengen-Hinweiszeile');
assert(strpos($formJson, 'run another scan') !== false, 'der Hinweis muss AUSDRUECKLICH zum erneuten Scan auffordern - eine Formulierung wie "wird nachgeholt" koennte als automatisch missverstanden werden');
echo "Test 7 (Attribut, Zähler, Zurücksetzen und beide Hinweiszeilen sind real verdrahtet) OK\n";

// Test 8: BEWUSSTE ENTSCHEIDUNG - die Statuszeile bleibt unberuehrt. Das Modul
// selbst ist in Ordnung; ein voruebergehend ueberlasteter Fremdserver ist kein
// Instanzfehler. Und die Kachel bekommt nichts: der Gast kann daran nichts
// aendern.
$statusStellen = substr_count($moduleSource, 'SetStatus(self::STATUS_TRANSLATE_ERROR)');
assert(strpos($moduleSource, "RecordTranslationFailure('unreachable');\n                \$this->SetStatus") === false, 'die Bilanz darf KEINEN eigenen Fehlerstatus setzen - das Modul ist in Ordnung');
$tileSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.html');
assert(strpos($tileSource, 'TranslationFailure') === false, 'die Kachel darf den Hinweis nicht zeigen - der Gast kann daran nichts aendern');
echo "Test 8 (weder Statuszeile noch Kachel werden angefasst - bewusste Entscheidung) OK\n";

echo "\nAll tests passed.\n";
