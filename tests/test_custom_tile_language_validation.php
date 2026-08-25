<?php
declare(strict_types=1);
// Standalone replica test for build 142 (2026-08-24, live gemeldeter Bug):
//
// Szenario: frische Instanz mit zugewiesener Visualisierung, Testversion, dann
// die eigene Sprachauswahl-Kachel (Pro-Feature "custom_tile") aktiviert. Deren
// mitgeliefertes BEISPIEL zeigt zwei feste Flaggen mit fest eingetragenen
// Sprachcodes ('ORIGINAL_IMPORT' und 'en'). Ein Klick auf die englische Flagge
// schickte 'en' als gewuenschte Sprache - obwohl 'en' auf dieser Instanz gar
// keine konfigurierte Zielsprache war.
//
// Folge: 'en' landete ungeprueft in propertyCurrentLanguage. Symcons
// Konfigurationsformular baut daraus ein Select, das nur die tatsaechlich
// konfigurierten Sprachen kennt - und verweigerte daraufhin JEDES weitere
// Speichern der Instanz mit "Invalid configuration / Current value "en" is not
// available". Der Admin sass auf einer Instanz, die sich nicht mehr uebernehmen
// liess. Behebbar war das nur, indem er die Ursache selbst fand.
//
// Da praktisch jeder Nutzer das mitgelieferte Beispiel einmal ausprobiert (das
// ist ja sein Zweck), traf das potenziell sehr viele Neuinstallationen. Und ein
// Nutzer kann in seiner eigenen Kachel jederzeit beliebige weitere ungueltige
// Codes eintragen.

const LANG_ORIGINAL_IMPORT = 'ORIGINAL_IMPORT';

// Repliziert IsSelectableGuestLanguage().
function isSelectableGuestLanguageReplica(string $language, string $sourceLanguage, array $targetLanguages): bool
{
    if ($language === '') {
        return false;
    }
    if ($language === LANG_ORIGINAL_IMPORT || $language === $sourceLanguage) {
        return true;
    }

    return in_array($language, $targetLanguages, true);
}

// Repliziert den Sprachwechsel-Zweig aus RequestAction(): liefert die Sprache,
// die danach aktiv ist, plus ob der Wechsel abgelehnt wurde.
function requestLanguageReplica(string $requested, string $currentLanguage, string $sourceLanguage, array $targetLanguages): array
{
    if (!isSelectableGuestLanguageReplica($requested, $sourceLanguage, $targetLanguages)) {
        return ['language' => $currentLanguage, 'rejected' => true];
    }

    return ['language' => $requested, 'rejected' => false];
}

// Repliziert die Selbstheilung aus ApplyChanges().
function healCurrentLanguageReplica(string $currentLanguage, string $sourceLanguage, array $targetLanguages): string
{
    if (!isSelectableGuestLanguageReplica($currentLanguage, $sourceLanguage, $targetLanguages)) {
        return $sourceLanguage;
    }

    return $currentLanguage;
}

// Test 1: DER GEMELDETE BUG - die englische Flagge des mitgelieferten Beispiels
// auf einer Instanz, die nur Deutsch (Quelle) und Latein konfiguriert hat.
// 'en' darf NICHT aktiv werden, sonst laesst sich die Instanz danach nie wieder
// speichern.
$result = requestLanguageReplica('en', 'de', 'de', ['de', 'la']);
assert($result['rejected'] === true, 'THE BUG: ein Sprachcode, der nicht konfiguriert ist, muss abgelehnt werden - sonst blockiert Symcons Formular danach jedes Speichern der Instanz');
assert($result['language'] === 'de', 'die bisher aktive Sprache muss dabei unveraendert stehen bleiben - nur der ungueltige Wechsel wird verweigert');
echo "Test 1 (die Flagge des Beispiels mit nicht konfiguriertem Code wird abgelehnt, aktive Sprache bleibt unveraendert) OK\n";

// Test 2: ein KONFIGURIERTER Code wird ganz normal akzeptiert - die Pruefung
// darf den regulaeren Sprachwechsel nicht behindern.
$ok = requestLanguageReplica('la', 'de', 'de', ['de', 'la']);
assert($ok['rejected'] === false && $ok['language'] === 'la', 'ein konfigurierter Zielsprachen-Code muss weiterhin ganz normal akzeptiert werden');
echo "Test 2 (ein konfigurierter Sprachcode wird weiterhin normal akzeptiert) OK\n";

// Test 3: die Quellsprache muss auch dann durchgehen, wenn sie (noch) nicht als
// eigener Eintrag in den Zielsprachen steht - EnsureSourceLanguageIsTarget()
// traegt sie erst beim naechsten ApplyChanges() nach, in der Zwischenzeit waere
// sie sonst faelschlich ungueltig.
$src = requestLanguageReplica('de', 'la', 'de', ['la']);
assert($src['rejected'] === false && $src['language'] === 'de', 'THE BUG: die Quellsprache muss immer waehlbar sein, auch bevor EnsureSourceLanguageIsTarget() sie als Zielsprachen-Eintrag nachgetragen hat');
echo "Test 3 (die Quellsprache ist immer wählbar, auch vor dem Nachtragen als Zielsprachen-Eintrag) OK\n";

// Test 4: ORIGINAL_IMPORT muss passieren duerfen - seit Build 79 keine waehlbare
// Gast-Sprache mehr, wird aber intern weiterhin als Rueckfall gesetzt (siehe
// ResetToOriginalLanguageIfNeeded bei abgelaufener Testphase). Genau diesen Code
// schickt uebrigens auch die deutsche Flagge des mitgelieferten Beispiels.
$orig = requestLanguageReplica(LANG_ORIGINAL_IMPORT, 'la', 'de', ['de', 'la']);
assert($orig['rejected'] === false, 'THE BUG: ORIGINAL_IMPORT muss weiterhin gesetzt werden duerfen - es ist der interne Rueckfall (z.B. bei abgelaufener Testphase) und der Code der deutschen Beispiel-Flagge');
echo "Test 4 (ORIGINAL_IMPORT bleibt als interner Rückfall zulässig) OK\n";

// Test 5: ein leerer Code (z.B. aus einer fehlerhaften eigenen Kachel) wird
// abgelehnt, statt die aktive Sprache auf Leer zu setzen.
assert(requestLanguageReplica('', 'de', 'de', ['de'])['rejected'] === true, 'ein leerer Sprachcode muss abgelehnt werden');
echo "Test 5 (ein leerer Sprachcode wird abgelehnt) OK\n";

// Test 6: SELBSTHEILUNG - eine Instanz, die bereits im kaputten Zustand
// feststeckt (CurrentLanguage = 'en', nicht konfiguriert), muss sich beim
// naechsten ApplyChanges() selbst auf die Quellsprache zuruecksetzen. Ohne das
// bliebe sie unrettbar: ihr Formular laesst sich ja gerade nicht mehr
// uebernehmen, um den Wert von Hand zu korrigieren.
assert(healCurrentLanguageReplica('en', 'de', ['de', 'la']) === 'de', 'THE BUG: eine bereits betroffene Instanz muss sich selbst heilen - ihr Formular laesst sich sonst nie wieder speichern');
echo "Test 6 (eine bereits blockierte Instanz heilt sich beim nächsten ApplyChanges selbst) OK\n";

// Test 7: die Selbstheilung darf eine gueltige aktive Sprache NIE anfassen.
assert(healCurrentLanguageReplica('la', 'de', ['de', 'la']) === 'la', 'eine gueltige aktive Sprache darf von der Selbstheilung nie veraendert werden');
assert(healCurrentLanguageReplica(LANG_ORIGINAL_IMPORT, 'de', ['de']) === LANG_ORIGINAL_IMPORT, 'ORIGINAL_IMPORT darf von der Selbstheilung nicht wegoptimiert werden');
echo "Test 7 (die Selbstheilung lässt gültige Sprachen unangetastet) OK\n";

// Test 8: der Admin entfernt eine Zielsprache, die gerade noch aktiv war -
// derselbe Mechanismus faengt auch diesen (ganz ohne Custom Tile erreichbaren)
// Weg in den blockierten Zustand ab.
assert(healCurrentLanguageReplica('la', 'de', ['de']) === 'de', 'wird die gerade aktive Zielsprache entfernt, muss die Instanz ebenfalls auf die Quellsprache zurueckfallen');
echo "Test 8 (das Entfernen der gerade aktiven Zielsprache führt ebenfalls sauber zurück zur Quellsprache) OK\n";

// Test 9: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, 'private function IsSelectableGuestLanguage(string $Language): bool') !== false, 'der Validierungs-Helfer muss existieren');
assert(strpos($moduleSource, 'if (!$this->IsSelectableGuestLanguage($language)) {') !== false, 'RequestAction() muss den gewuenschten Sprachcode vor jeder Verarbeitung validieren');
assert(strpos($moduleSource, 'if (!$this->IsSelectableGuestLanguage($currentLanguageForValidation)) {') !== false, 'ApplyChanges() muss die gespeicherte aktive Sprache validieren und ggf. selbst heilen');
// Die Validierung muss VOR den beiden bestehenden Sonderfaellen greifen - sonst
// koennte ein ungueltiger Code ueber IsLanguageBlockedByTrial/
// IsLanguageSwitchRateLimited an ihr vorbeilaufen.
$validationPos = strpos($moduleSource, 'if (!$this->IsSelectableGuestLanguage($language)) {');
$trialPos = strpos($moduleSource, 'if ($this->IsLanguageBlockedByTrial($language)) {');
assert($validationPos < $trialPos, 'THE BUG: die Gueltigkeitspruefung muss VOR der Testphasen-/Rate-Limit-Behandlung laufen, sonst kann ein ungueltiger Code an ihr vorbei in die Property gelangen');
// Das mitgelieferte Beispiel muss den vom Nutzer gewuenschten Hinweis tragen.
assert(strpos($moduleSource, 'Custom tile example:') !== false, 'das mitgelieferte Kachel-Beispiel muss als solches beschriftet sein ("Custom tile example:")');
echo "Test 9 (die reale module.php validiert an beiden Stellen, in der richtigen Reihenfolge, und beschriftet das Beispiel) OK\n";

echo "\nAll tests passed.\n";
