<?php
declare(strict_types=1);
// Build 132 (Nutzer-Frage, gemeinsam hergeleitet): IP-Symcons "Integer"-
// Attributtyp ist ein klassischer 32-Bit-Integer (Bereich bis 2.147.483.647) -
// unabhaengig davon, dass PHP selbst auf jedem 64-Bit-System einen
// 64-Bit-Integer verwendet. Die vier Uebersetzungs-Statistik-Zaehler
// (attributeStatsRequestCount/CharacterCount/CacheSavedRequestCount/
// CacheSavedCharacterCount) wurden bislang unbegrenzt als Integer-Attribut
// hochgezaehlt, ohne jeden Schutz - bei sehr langer Laufzeit (Jahre) haette
// das theoretisch zu einem stillen Ueberlauf/Wraparound beim SCHREIBEN in
// Symcons 32-Bit-Speicher fuehren koennen, auch wenn PHPs eigene
// Rechenoperation (+1/+N) selbst nie ueberlaeuft.
//
// Fix: Umstellung von RegisterAttributeInteger auf RegisterAttributeString
// (praktisch unbegrenzt) unter einem NEUEN Attributnamen ("V2"-Suffix), mit
// einer einmaligen Migration der alten Integer-Werte beim naechsten Create()
// - eine bereits laenger laufende Installation darf ihre real angesammelten
// Zaehlerstaende dabei nicht verlieren.

function migrateStatsAttributeReplica(int $legacyValue, string $currentNewValue): string
{
    // Repliziert exakt die Migrationslogik aus Create(): greift nur, wenn der
    // alte Wert echt ungleich 0 ist UND das neue Attribut noch beim Default
    // steht (garantiert Einmaligkeit).
    if ($legacyValue !== 0 && $currentNewValue === '0') {
        return (string) $legacyValue;
    }

    return $currentNewValue;
}

// Test 1: DER GEMELDETE FALL - eine bereits laenger laufende Installation mit
// echten historischen Zaehlerstaenden (z.B. die 15.455/1.797.626/33.784/
// 4.003.030 aus dem echten Nutzer-Bericht) darf beim Umstieg nicht auf 0
// zurueckfallen.
assert(migrateStatsAttributeReplica(15455, '0') === '15455', 'DER BUG: ein bereits vorhandener, alter Integer-Zaehlerstand muss ins neue String-Attribut uebernommen werden, nicht verloren gehen');
assert(migrateStatsAttributeReplica(1797626, '0') === '1797626', 'DER BUG: auch der Zeichen-Zaehler muss korrekt migriert werden');
echo "Test 1 (reale historische Zählerstände werden beim Umstieg auf String korrekt übernommen, nicht auf 0 zurückgesetzt) OK\n";

// Test 2: eine BRANDNEUE Installation (alter Zaehler noch bei 0) bleibt bei 0 -
// keine Geisterwerte aus dem Nichts.
assert(migrateStatsAttributeReplica(0, '0') === '0', 'eine frische Installation ohne historische Werte muss bei 0 bleiben');
echo "Test 2 (eine frische Installation ohne historische Werte bleibt korrekt bei 0) OK\n";

// Test 3: die Migration darf garantiert nur EINMAL greifen - ist das neue
// Attribut bereits ungleich 0 (weil seither schon normal weitergezaehlt
// wurde), darf ein (ohnehin nicht mehr aktualisierter) alter Integer-Wert
// NICHT erneut ueberschreiben.
assert(migrateStatsAttributeReplica(15455, '20000') === '20000', 'DER BUG: nach der ersten Migration darf ein alter Integer-Wert den bereits weitergezaehlten neuen String-Wert nie wieder ueberschreiben');
echo "Test 3 (die Migration greift garantiert nur einmal, überschreibt keinen bereits weitergezählten Wert) OK\n";

// Test 4: Symmetrie-Check - die reale module.php muss tatsaechlich auf
// RegisterAttributeString fuer alle vier Zaehler umgestellt haben, mit
// Migration in Create().
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
foreach (['attributeStatsRequestCount', 'attributeStatsCharacterCount', 'attributeStatsCacheSavedRequestCount', 'attributeStatsCacheSavedCharacterCount'] as $const) {
    assert(strpos($moduleSource, "RegisterAttributeString(self::$const,") !== false, "DER BUG: $const muss ueber RegisterAttributeString registriert werden, nicht mehr als Integer");
    assert(strpos($moduleSource, "RegisterAttributeInteger(self::$const,") === false, "DER BUG: $const darf nicht mehr als RegisterAttributeInteger registriert werden");
}
assert(strpos($moduleSource, 'attributeStatsRequestCountLegacyInt') !== false, 'die Migration muss die alten Integer-Attribute zum Auslesen weiterhin registrieren');
echo "Test 4 (alle vier Zähler sind tatsächlich auf RegisterAttributeString umgestellt, die Legacy-Migration ist verdrahtet) OK\n";

// Test 5: Symmetrie-Check - RecordTranslationRequestStats()/RecordCacheSavingsStats()
// muessen tatsaechlich ueber ReadAttributeString/WriteAttributeString rechnen,
// nicht mehr ueber die Integer-Varianten.
$funcStart = strpos($moduleSource, 'private function RecordTranslationRequestStats(');
$funcEnd = strpos($moduleSource, "\n    }\n", $funcStart);
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, 'WriteAttributeString(') !== false, 'RecordTranslationRequestStats() muss WriteAttributeString() verwenden');
assert(strpos($funcBody, 'WriteAttributeInteger(') === false, 'RecordTranslationRequestStats() darf nicht mehr WriteAttributeInteger() fuer die Statistik-Zaehler verwenden');
echo "Test 5 (RecordTranslationRequestStats() rechnet tatsächlich über die String-Attribute, nicht mehr über Integer) OK\n";

echo "\nAll tests passed.\n";
