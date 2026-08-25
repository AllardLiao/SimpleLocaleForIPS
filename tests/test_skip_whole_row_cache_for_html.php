<?php
declare(strict_types=1);
// Build 127 (Nutzer-Report, live per Debug-Log bestätigt: Cache dauerhaft bei
// "1000 Eintraege", staendige Verdraengung): TranslateBatch() cachte bisher
// zusaetzlich zum (viel wertvolleren) Knoten-Cache in TranslateBatchUncached()
// auch den GANZEN Zeilen-Rohtext. Fuer ein HTML-Widget (Wetter-/Medienplayer-
// Kachel), dessen Gesamtinhalt sich durch neue Messwerte/Songtitel bei JEDER
// Aktualisierung aendert, ist so ein Eintrag praktisch NIE wiederverwendbar -
// belegt aber dauerhaft einen der 1000 begrenzten Cache-Plaetze und verdraengt
// dadurch echte, oft wiederverwendete Knoten-Eintraege (z.B. den festen Text
// "Echo Info", der bei JEDEM Update gleich bleibt), noch bevor die ueberhaupt
// einen zweiten Treffer landen konnten. Live bestaetigt: der Cache stand bei
// jedem Zugriff exakt bei "1000 Eintraege" (voll, staendige Verdraengung),
// und selbst "Echo Info" ging trotz stabilem Inhalt zehn Minuten in Folge
// wiederholt an den Anbieter.
//
// Fix: fuer $IsHtml=true wird die ganze Zeile nicht mehr zusaetzlich gecacht -
// nur noch die (bereits vorhandene) Knotenebene.

function translateBatchReplica(array $texts, bool $isHtml, callable $getCached, callable $storeCached, callable $translateUncached): array
{
    $results = [];
    $freshTexts = [];
    $freshIndexes = [];
    foreach ($texts as $i => $text) {
        $cached = $getCached($text);
        if ($cached !== null) {
            $results[$i] = $cached;
            continue;
        }
        $freshIndexes[] = $i;
        $freshTexts[] = $text;
    }

    if ($freshTexts !== []) {
        $translated = $translateUncached($freshTexts);
        foreach ($freshIndexes as $position => $originalIndex) {
            $results[$originalIndex] = $translated[$position];
            if ($translated[$position] !== '' && !$isHtml) {
                $storeCached($freshTexts[$position], $translated[$position]);
            }
        }
    }

    return $results;
}

// Test 1: DER GEMELDETE BUG - fuer HTML-Inhalt (ein Widget, dessen Gesamttext
// sich staendig aendert) darf die ganze Zeile NICHT zusaetzlich gecacht werden.
$storedWholeRow = [];
$storeCached = function (string $text, string $translated) use (&$storedWholeRow): void {
    $storedWholeRow[$text] = $translated;
};
$htmlWidget = '<html><title>Echo Info</title><div>Más Cara [Explicit]</div></html>';
translateBatchReplica(
    [$htmlWidget],
    true,
    fn (string $t): ?string => null,
    $storeCached,
    fn (array $t): array => ['<html><title>Información de eco</title><div>Más Cara [Explicit]</div></html>']
);
assert($storedWholeRow === [], 'DER BUG: fuer HTML-Inhalt darf der ganze Zeilen-Rohtext nicht im Whole-Row-Cache landen - er ist praktisch nie wiederverwendbar und verdraengt echte Knoten-Treffer');
echo "Test 1 (HTML-Inhalt: der ganze Zeilen-Rohtext wird nicht mehr zusätzlich gecacht) OK\n";

// Test 2: keine Regression - fuer NICHT-HTML-Inhalt (z.B. ein Automations-Name)
// bleibt das bisherige Whole-Row-Caching unveraendert bestehen, da solche
// Zeilen typischerweise kurz UND exakt wiederkehrend sind.
$storedWholeRow2 = [];
$storeCached2 = function (string $text, string $translated) use (&$storedWholeRow2): void {
    $storedWholeRow2[$text] = $translated;
};
translateBatchReplica(
    ['Gehen'],
    false,
    fn (string $t): ?string => null,
    $storeCached2,
    fn (array $t): array => ['Salir']
);
assert($storedWholeRow2 === ['Gehen' => 'Salir'], 'keine Regression: nicht-HTML-Zeilen (z.B. Automations-/Objektnamen) muessen weiterhin normal auf Zeilenebene gecacht werden');
echo "Test 2 (Nicht-HTML-Inhalt: das bisherige Zeilen-Caching bleibt unverändert, keine Regression) OK\n";

// Test 3: Symmetrie-Check - die reale module.php muss den !$IsHtml-Schutz
// tatsächlich vor dem Whole-Row-StoreCachedTranslation()-Aufruf in
// TranslateBatch() haben.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$funcStart = strpos($moduleSource, 'private function TranslateBatch(');
$funcEnd = strpos($moduleSource, 'private function TranslateBatchUncached(');
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, "if (\$translated !== '' && !\$IsHtml) {") !== false, 'DER BUG: TranslateBatch() muss das Whole-Row-Caching für $IsHtml-Inhalte tatsächlich überspringen');
echo "Test 3 (der \$IsHtml-Schutz ist tatsächlich in der realen TranslateBatch() verdrahtet) OK\n";

echo "\nAll tests passed.\n";
