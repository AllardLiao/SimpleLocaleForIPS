<?php
declare(strict_types=1);
// Build 129 (Nutzer-Wunsch, live per Debug-Log bestätigt): seit Build 127 wird
// der KOMPLETTE Zeilen-Rohtext eines HTML-Widgets nie mehr im Cache
// gespeichert (nur noch seine einzelnen Knoten). TranslateBatch() fragte
// trotzdem weiterhin bedingungslos GetCachedTranslation() fuer den ganzen
// Rohtext ab, bevor es ueberhaupt in die Knoten-Aufteilung ging - bei
// $IsHtml=true ist das ein STRUKTURELL GARANTIERTER Fehlschlag (kann gar
// nicht anders sein), kostet aber trotzdem Semaphor-Erwerb, volles
// Cache-Lesen/Dekodieren (bis zu 10.000 Eintraege) und eine Hash-Berechnung
// ueber ein komplettes HTML-Dokument. Live bestaetigt per Debug-Log:
// wiederholte "GetCachedTranslation MISS" fuer ganze <!doctype html>/
// <style>/<table>-Bloecke.
//
// Fix: der aeussere Zeilen-Ebene-Cache-Check wird bei $IsHtml komplett
// uebersprungen - die (tatsaechlich wirksame) Knotenebene in
// TranslateBatchUncached() ist davon unberuehrt.

function translateBatchCacheCheckReplica(array $texts, bool $isHtml, callable $getCached): array
{
    $cacheCheckCallCount = 0;
    $freshTexts = [];
    foreach ($texts as $text) {
        if (!$isHtml) {
            $cacheCheckCallCount++;
            $cached = $getCached($text);
            if ($cached !== null) {
                continue;
            }
        }
        $freshTexts[] = $text;
    }

    return ['freshTexts' => $freshTexts, 'cacheCheckCallCount' => $cacheCheckCallCount];
}

// Test 1: DER GEMELDETE BUG - fuer HTML-Inhalt darf der aeussere
// Zeilen-Ebene-Cache-Check gar nicht erst aufgerufen werden, da er
// strukturell nie treffen kann (seit Build 127 wird die ganze Zeile nie
// gecacht).
$htmlDoc = '<!doctype html><html><head><style>.a{color:red}</style></head><body>Test</body></html>';
$cacheCheckCallLog = [];
$getCachedSpy = function (string $text) use (&$cacheCheckCallLog): ?string {
    $cacheCheckCallLog[] = $text;

    return null;
};
$resultHtml = translateBatchCacheCheckReplica([$htmlDoc], true, $getCachedSpy);
assert($resultHtml['cacheCheckCallCount'] === 0, 'DER BUG: fuer HTML-Inhalt darf GetCachedTranslation() auf Zeilenebene gar nicht erst aufgerufen werden - garantierter Fehlschlag, reine Verschwendung');
assert($resultHtml['freshTexts'] === [$htmlDoc], 'der Text muss trotzdem normal in die Knoten-Aufteilung (TranslateBatchUncached) weitergereicht werden');
echo "Test 1 (HTML-Inhalt: der äußere, strukturell nie treffende Zeilen-Cache-Check wird komplett übersprungen) OK\n";

// Test 2: keine Regression - fuer NICHT-HTML-Inhalt (z.B. ein
// Automations-/Objektname) bleibt der bisherige Zeilen-Ebene-Cache-Check
// unveraendert bestehen, da der dort durchaus treffen kann.
$resultPlain = translateBatchCacheCheckReplica(['Gehen'], false, fn (string $t): ?string => $t === 'Gehen' ? 'Salir' : null);
assert($resultPlain['cacheCheckCallCount'] === 1, 'keine Regression: fuer Nicht-HTML-Zeilen muss der Zeilen-Ebene-Cache-Check weiterhin laufen');
assert($resultPlain['freshTexts'] === [], 'ein Zeilen-Ebene-Cache-Treffer bei Nicht-HTML-Inhalt muss weiterhin normal wirken (kein Anbieter-Aufruf noetig)');
echo "Test 2 (Nicht-HTML-Inhalt: der Zeilen-Cache-Check bleibt unverändert aktiv, keine Regression) OK\n";

// Test 3: Symmetrie-Check - die reale TranslateBatch() muss den
// !$IsHtml-Schutz tatsächlich vor dem GetCachedTranslation()-Aufruf auf
// Zeilenebene haben.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$funcStart = strpos($moduleSource, 'private function TranslateBatch(');
$funcEnd = strpos($moduleSource, 'private function TranslateBatchUncached(');
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, "if (!\$IsHtml) {\n                \$cached = \$this->GetCachedTranslation(") !== false, 'DER BUG: TranslateBatch() muss den äußeren Zeilen-Ebene-Cache-Check für $IsHtml-Inhalte tatsächlich überspringen');
echo "Test 3 (der \$IsHtml-Schutz um den Zeilen-Ebene-Cache-Check ist tatsächlich in der realen TranslateBatch() verdrahtet) OK\n";

echo "\nAll tests passed.\n";
