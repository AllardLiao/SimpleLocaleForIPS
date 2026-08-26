<?php
declare(strict_types=1);
// Standalone replica test for build 161 (2026-08-26, Nutzer-Wunsch):
// "Google/DeepL kombiniert vor dem kostenfreien Anbieter deaktiv - ... Der
// Beschreibungstext ist für solche Editionen auch nicht korrekt."
//
// Ohne das Feature "paid_providers" beschrieben beide Texte im Anbieter-Panel
// eine Funktion, die es in dieser Edition gar nicht gibt: "sind beide
// eingetragen, wird zuerst der bevorzugte versucht" gilt nur bei voller
// Verkettung. Ohne das Feature bleibt der kostenfreie Anbieter primaer und
// hoechstens EIN bezahlter greift als Rueckfall dahinter.
//
// BEWUSST NICHT ausgegraut: der Nutzer erwartete zunaechst ein deaktiviertes
// Auswahlfeld. Es ist dort aber weiterhin wirksam - es entscheidet, WELCHER der
// beiden eingetragenen Schluessel dieser eine Rueckfall ist (siehe
// GetProviderChain). Ausgrauen wuerde echte Funktion wegnehmen.

// Repliziert GetProviderChain() fuer beide Lizenzlagen.
function chainReplica(bool $hatFeature, bool $google, bool $deepl, string $bevorzugt): array
{
    $available = [];
    if ($google) { $available['google'] = true; }
    if ($deepl)  { $available['deepl']  = true; }

    if ($hatFeature) {
        $chain = [];
        if (isset($available[$bevorzugt])) { $chain[] = $bevorzugt; unset($available[$bevorzugt]); }
        foreach (array_keys($available) as $p) { $chain[] = $p; }
        $chain[] = 'free';

        return $chain;
    }

    $einer = null;
    if (isset($available[$bevorzugt])) { $einer = $bevorzugt; }
    elseif ($available !== []) { $einer = array_key_first($available); }

    return $einer === null ? ['free'] : ['free', $einer];
}

// Test 1: DIE BEGRUENDUNG, warum das Feld bedienbar bleiben MUSS - ohne das
// Feature entscheidet es weiterhin, welcher Anbieter der Rueckfall ist.
assert(chainReplica(false, true, true, 'deepl') === ['free', 'deepl'], 'ohne Feature muss die Praeferenz den einen Rueckfall bestimmen');
assert(chainReplica(false, true, true, 'google') === ['free', 'google'], 'und zwar in beide Richtungen - das Feld ist wirksam, nicht dekorativ');
echo "Test 1 (das Auswahlfeld bleibt ohne das Feature wirksam) OK\n";

// Test 2: ist nur EIN Schluessel eingetragen, gewinnt dieser unabhaengig von der
// Praeferenz - sonst gaebe es gar keinen Rueckfall.
assert(chainReplica(false, true, false, 'deepl') === ['free', 'google'], 'der einzige eingetragene Anbieter wird genutzt, egal was bevorzugt ist');
echo "Test 2 (bei nur einem Schlüssel gewinnt dieser) OK\n";

// Test 3: MIT dem Feature gilt das, was der urspruengliche Text verspricht -
// beide kombiniert und VOR dem kostenfreien.
assert(chainReplica(true, true, true, 'deepl') === ['deepl', 'google', 'free'], 'mit Feature: beide bezahlten kombiniert und vor dem kostenfreien');
echo "Test 3 (mit dem Feature gilt die volle Verkettung) OK\n";

// Test 4: die reale Umsetzung - beide Texte werden ersetzt, das Feld aber NICHT
// deaktiviert.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
foreach (["case 'ProviderIntroLabel':", 'case self::propertyPreferredPaidProvider:'] as $fall) {
    assert(strpos($moduleSource, $fall) !== false, "$fall muss verdrahtet sein");
}
$start = strpos($moduleSource, "case 'ProviderIntroLabel':");
$ende = strpos($moduleSource, 'case self::propertyAutoRescanInterval:', $start);
$body = substr($moduleSource, $start, $ende - $start);
assert(substr_count($body, "!\$this->HasLicenseFeature('paid_providers')") === 2, 'beide Texte muessen an der Umkehrung des Features haengen');
assert(strpos($body, "\$element['enabled'] = false;") === false, 'DIE ENTSCHEIDUNG: das Auswahlfeld darf NICHT deaktiviert werden - es ist ohne das Feature weiterhin wirksam');
echo "Test 4 (beide Texte hängen am Feature, nichts wird deaktiviert) OK\n";

// Test 5: das Label muss im Formular einen Namen tragen - ohne ihn liefe der
// Fall oben ins Leere. Genau das war beim ersten Versuch passiert.
$form = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);
$namen = [];
$suche = function ($n) use (&$suche, &$namen): void {
    if (is_array($n)) {
        if (isset($n['name']) && is_string($n['name'])) { $namen[] = $n['name']; }
        foreach ($n as $v) { $suche($v); }
    }
};
$suche($form);
assert(in_array('ProviderIntroLabel', $namen, true), 'das Einleitungs-Label muss einen Namen tragen, sonst greift der Fall nie');
echo "Test 5 (das Einleitungs-Label trägt einen Namen) OK\n";

// Test 6: beide Ersatztexte muessen in ALLEN Sprachen registriert sein - sonst
// staenden sie bei fremdsprachiger Konsole deutsch da (siehe Build 156).
$locale = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/locale.json'), true);
preg_match_all("/\\\$element\['caption'\] = '((?:[^'\\\\]|\\\\.)*)';/", $body, $treffer);
assert(count($treffer[1]) === 2, 'es muessen genau zwei Ersatztexte gesetzt werden');
foreach ($treffer[1] as $text) {
    $text = stripslashes($text);
    foreach ($locale['translations'] as $sprache => $eintraege) {
        assert(isset($eintraege[$text]), "Ersatztext fehlt in \"$sprache\": " . substr($text, 0, 60) . '...');
    }
}
echo "Test 6 (beide Ersatztexte sind in allen Sprachen registriert) OK\n";

echo "\nAll tests passed.\n";
