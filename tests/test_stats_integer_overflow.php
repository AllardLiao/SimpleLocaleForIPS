<?php
declare(strict_types=1);
// Build 132 (Nutzer-Frage, gemeinsam hergeleitet): IP-Symcons "Integer"-
// Attributtyp ist ein klassischer 32-Bit-Integer (Bereich bis 2.147.483.647) -
// unabhaengig davon, dass PHP selbst auf jedem 64-Bit-System einen
// 64-Bit-Integer verwendet. Die vier Uebersetzungs-Statistik-Zaehler
// (attributeStatsRequestCount/CharacterCount/CacheSavedRequestCount/
// CacheSavedCharacterCount) wurden bislang unbegrenzt als Integer-Attribut
// hochgezaehlt, ohne jeden Schutz - bei sehr langer Laufzeit (Jahre) haette
// das zu einem stillen Ueberlauf/Wraparound beim SCHREIBEN in Symcons
// 32-Bit-Speicher fuehren koennen, auch wenn PHPs eigene Rechenoperation
// (+1/+N) selbst nie ueberlaeuft. Eine Instanz, die jahrelang unbeaufsichtigt
// laeuft, ist genau das erwartete Einsatzszenario dieses Moduls.
//
// Fix: Umstellung von RegisterAttributeInteger auf RegisterAttributeString
// (praktisch unbegrenzt, gerechnet wird weiterhin mit normalen PHP-Ints).
//
// Build 149: die damals mitgelieferte einmalige Migration alter Integer-Werte
// wurde wieder entfernt - sie war fuer jede kuenftige Installation
// strukturell unerreichbar (die alten Attribute werden von keinem Codepfad
// mehr beschrieben, stehen also dauerhaft auf 0, und die Migration sprang nur
// bei einem Wert ungleich 0 an). Die zugehoerigen Tests sind damit ebenfalls
// entfallen; geprueft wird hier weiterhin der eigentliche Ueberlaufschutz.

// Test 1: Symmetrie-Check - die reale module.php muss alle vier Zaehler
// tatsaechlich als String fuehren, nicht mehr als Integer.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
foreach (['attributeStatsRequestCount', 'attributeStatsCharacterCount', 'attributeStatsCacheSavedRequestCount', 'attributeStatsCacheSavedCharacterCount'] as $const) {
    assert(strpos($moduleSource, "RegisterAttributeString(self::$const,") !== false, "DER BUG: $const muss ueber RegisterAttributeString registriert werden, nicht mehr als Integer");
    assert(strpos($moduleSource, "RegisterAttributeInteger(self::$const,") === false, "DER BUG: $const darf nicht mehr als RegisterAttributeInteger registriert werden");
}
// Gegenprobe: die entfernte Migration darf nicht versehentlich wieder
// auftauchen - sie laege in Create() an einer Stelle, an der ReadAttribute*
// nicht zuverlaessig den persistierten Wert liefert (siehe Build 149).
assert(strpos($moduleSource, 'LegacyInt') === false, 'die entfernte Legacy-Migration darf nicht wieder eingefuehrt werden - wertlesende Migrationen gehoeren nach ApplyChanges(), nicht in Create()');
echo "Test 1 (alle vier Zähler werden als String geführt, keine Legacy-Migration in Create()) OK\n";

// Test 2: Symmetrie-Check - RecordTranslationRequestStats()/RecordCacheSavingsStats()
// muessen tatsaechlich ueber ReadAttributeString/WriteAttributeString rechnen,
// nicht mehr ueber die Integer-Varianten.
$funcStart = strpos($moduleSource, 'private function RecordTranslationRequestStats(');
$funcEnd = strpos($moduleSource, "\n    }\n", $funcStart);
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, 'WriteAttributeString(') !== false, 'RecordTranslationRequestStats() muss WriteAttributeString() verwenden');
assert(strpos($funcBody, 'WriteAttributeInteger(') === false, 'RecordTranslationRequestStats() darf nicht mehr WriteAttributeInteger() fuer die Statistik-Zaehler verwenden');
echo "Test 2 (RecordTranslationRequestStats() rechnet tatsächlich über die String-Attribute, nicht mehr über Integer) OK\n";

echo "\nAll tests passed.\n";
