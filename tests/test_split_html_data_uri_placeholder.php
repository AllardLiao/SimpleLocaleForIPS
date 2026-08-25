<?php
declare(strict_types=1);
// Build 121 (Nutzer-Report, live gefunden per Debug-Log): ein "Echo Info"-Widget
// (Alexa/Echo-Medienplayer-Kachel) enthaelt ein <img src="data:image/png;base64,...">
// mit einem eingebetteten Cover-Bild (mehrere zehntausend Zeichen, kein einziges
// '<'/'>' darin). SplitHtmlIntoTextNodes()s Tag-Aufteilungs-Regex
// (preg_split('/(<[^>]*>)/s', ...)) scheiterte daran an PHPs PCRE-Backtrack-
// Grenze - preg_split() lieferte false, der vorhandene Fallback griff: der
// KOMPLETTE Rohinhalt (inklusive Bilddaten) wurde als EIN einziger "Textknoten"
// an den Uebersetzer geschickt. Live bestaetigt per Debug-Log: ueber 22.000
// Zeichen fuer ein Widget ganz ohne echten Text, wiederholt bei JEDEM
// Medienplayer-Update (VM_UPDATE), nicht nur bei einem Rescan - erheblicher
// Kontingent-Verbrauch.
//
// Fix: Data-URIs werden VOR der Tag-Aufteilung durch kurze Platzhalter ersetzt
// (macht die Regex wieder unproblematisch kurz) und beim Zusammenbau exakt
// wieder eingesetzt - unabhaengig davon, ob am Ende noch ein Fallback greift.
//
// Build 122 (direkter Nachbericht, live per Debug-Log bestaetigt): selbst nach
// Build 121 landete ein Segment OHNE jeden echten Text (z.B. nur leere <div>s
// rund um das jetzt per Platzhalter ersetzte Bild) weiterhin im "ganzer Block
// als ein Knoten"-Fallback - unnoetig, und live als wiederholte identische
// Anfrage bestaetigt (derselbe leere Block bleibt ja unveraendert). Ein echter
// Parse-Fehler (preg_split liefert false) bekommt weiterhin den konservativen
// Ganzer-Block-Fallback (dort koennte echter Text drinstecken), aber ein
// Segment MIT erfolgreich geparsten, aber ausschliesslich aus Tags/Leerraum
// bestehenden Tokens braucht ueberhaupt keine Uebersetzung mehr.

function splitHtmlIntoTextNodesReplica(string $Html): array
{
    $dataUris = [];
    $html = preg_replace_callback(
        '/data:[a-zA-Z0-9\/+.-]+;base64,[A-Za-z0-9+\/=]+/',
        function (array $match) use (&$dataUris): string {
            $placeholder = '@@SIMPLELOCALE_DATAURI_' . count($dataUris) . '@@';
            $dataUris[$placeholder] = $match[0];

            return $placeholder;
        },
        $Html
    ) ?? $Html;

    $restore = static function (string $text) use ($dataUris): string {
        return $dataUris === [] ? $text : strtr($text, $dataUris);
    };

    $noop = ['nodes' => [], 'reassemble' => static function (array $translated) use ($html, $restore) {
        return $restore($html);
    }];

    $parseErrorFallback = ['nodes' => [$html], 'reassemble' => function (array $translated) use ($html, $restore) {
        return $restore($translated[0] ?? $html);
    }];

    if (trim($html) === '') {
        return $noop;
    }

    $tokens = preg_split('/(<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($tokens === false || $tokens === []) {
        return $parseErrorFallback;
    }

    $nodes = [];
    $textTokenIndexes = [];
    foreach ($tokens as $tokenIndex => $token) {
        if ($token[0] === '<' || trim($token) === '') {
            continue;
        }
        $nodes[] = $token;
        $textTokenIndexes[] = $tokenIndex;
    }

    if ($nodes === []) {
        return $noop;
    }

    $reassemble = function (array $translatedTexts) use ($tokens, $textTokenIndexes, $restore) {
        foreach ($textTokenIndexes as $position => $tokenIndex) {
            $tokens[$tokenIndex] = $translatedTexts[$position] ?? $tokens[$tokenIndex];
        }

        return $restore(implode('', $tokens));
    };

    return ['nodes' => $nodes, 'reassemble' => $reassemble];
}

// Test 1: DER GEMELDETE BUG (Build 121) - ein grosses Base64-Bild darf NICHT
// als Teil eines Textknotens an den Uebersetzer gehen, egal wie gross es ist.
$hugeBase64 = str_repeat('QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo=', 3000); // ~111KB, kein '<'/'>' darin
$html = '<head><title>Echo Info</title></head><body><main><img src="data:image/png;base64,' . $hugeBase64 . '" alt="cover"></main></body>';
$split = splitHtmlIntoTextNodesReplica($html);

foreach ($split['nodes'] as $node) {
    assert(strpos($node, 'QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo') === false, 'DER BUG: kein Textknoten darf Base64-Bilddaten enthalten');
    assert(strlen($node) < 1000, 'DER BUG: kein Textknoten darf durch eingebettete Bilddaten aufgeblaeht sein (Knoten war ' . strlen($node) . ' Zeichen lang)');
}
echo "Test 1 (ein eingebettetes Base64-Bild taucht in KEINEM an den Übersetzer gehenden Textknoten auf, egal wie groß) OK\n";

// Test 2: das Bild muss nach der Rekonstruktion UNVERÄNDERT wieder da sein -
// keine Datenverlust/Kaputtheit durch den Platzhalter-Mechanismus.
$translated = array_map(static fn (string $t): string => strtoupper($t), $split['nodes']);
$result = ($split['reassemble'])($translated);
assert(strpos($result, 'data:image/png;base64,' . $hugeBase64) !== false, 'das Originalbild muss nach der Rekonstruktion exakt (inkl. Anführungszeichen-Kontext) wieder vorhanden sein');
assert(strpos($result, '@@SIMPLELOCALE_DATAURI_') === false, 'kein Platzhalter-Rest darf im finalen Ergebnis übrig bleiben');
echo "Test 2 (das Bild kommt nach der Rekonstruktion exakt unverändert zurück, kein Platzhalter-Rest sichtbar) OK\n";

// Test 3: normaler Text ohne jede Data-URI bleibt komplett unverändert im Verhalten.
$plainHtml = '<div class="a">Hallo <b>Welt</b></div>';
$plainSplit = splitHtmlIntoTextNodesReplica($plainHtml);
assert($plainSplit['nodes'] === ['Hallo ', 'Welt'], 'keine Regression: normales HTML ohne Data-URI muss weiterhin sauber in einzelne Textknoten zerlegt werden');
echo "Test 3 (HTML ohne Data-URI verhält sich exakt wie vorher, keine Regression) OK\n";

// Test 4: DER GEMELDETE BUG (Build 122) - ein Segment mit einem eingebetteten
// Bild, aber ganz OHNE echten Text drumherum (leere divs), darf NACH Ersetzung
// des Bildes durch den Platzhalter KEINEN einzigen Knoten mehr an den
// Uebersetzer liefern - es gibt nichts zu uebersetzen.
$emptyWidgetHtml = '<body><main><section><img src="data:image/png;base64,' . $hugeBase64 . '" alt="cover"></section>'
    . '<section><div class="title"></div><div class="subtitle"></div></section></main></body>';
$emptySplit = splitHtmlIntoTextNodesReplica($emptyWidgetHtml);
assert($emptySplit['nodes'] === [], 'DER BUG: ein Segment ganz ohne echten Text darf keinen einzigen zu uebersetzenden Knoten liefern');
$emptyResult = ($emptySplit['reassemble'])([]);
assert(strpos($emptyResult, 'data:image/png;base64,' . $hugeBase64) !== false, 'das Bild muss trotz null Uebersetzungs-Knoten unveraendert im Ergebnis stehen');
echo "Test 4 (ein Segment ganz ohne echten Text liefert KEINEN Übersetzungs-Knoten mehr, Bild bleibt trotzdem exakt erhalten) OK\n";

// Test 5: keine Regression - ein Segment MIT echtem Text NEBEN einem
// eingebetteten Bild liefert weiterhin genau diesen Text als Knoten.
$mixedHtml = '<div><img src="data:image/png;base64,' . $hugeBase64 . '"></div><div class="title">Echo Info</div>';
$mixedSplit = splitHtmlIntoTextNodesReplica($mixedHtml);
assert($mixedSplit['nodes'] === ['Echo Info'], 'ein Segment mit echtem Text neben einem Bild muss weiterhin genau diesen Text als Knoten liefern');
echo "Test 5 (ein Segment mit echtem Text neben einem eingebetteten Bild liefert weiterhin genau diesen einen Textknoten, keine Regression) OK\n";

// Test 6: Symmetrie-Check - die reale module.php muss beide Mechanismen
// (Platzhalter UND den No-Op-Fall fuer textlose Segmente) tatsächlich in
// SplitHtmlIntoTextNodes() verdrahtet haben.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$funcStart = strpos($moduleSource, 'private function SplitHtmlIntoTextNodes(');
$funcEnd = strpos($moduleSource, "\n    }\n", strpos($moduleSource, 'return [\'nodes\' => $nodes', $funcStart));
$funcBody = substr($moduleSource, $funcStart, $funcEnd - $funcStart);
assert(strpos($funcBody, 'SIMPLELOCALE_DATAURI_') !== false, 'SplitHtmlIntoTextNodes() muss Data-URIs vor der Tag-Aufteilung durch Platzhalter ersetzen');
assert(strpos($funcBody, 'base64') !== false, 'der Data-URI-Erkennungsschritt muss tatsächlich in der realen Funktion stehen');
assert(strpos($funcBody, '$noop') !== false, 'ein textloses Segment muss ueber einen eigenen No-Op-Pfad ohne Uebersetzungs-Knoten behandelt werden');
assert(strpos($funcBody, '$parseErrorFallback') !== false, 'ein echter Parse-Fehler muss weiterhin konservativ den ganzen Block als einen Knoten behandeln');
echo "Test 6 (Platzhalter-Mechanismus UND der No-Op-Pfad für textlose Segmente sind tatsächlich in der realen SplitHtmlIntoTextNodes() verdrahtet) OK\n";

echo "\nAll tests passed.\n";
