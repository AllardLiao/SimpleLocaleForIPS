<?php
declare(strict_types=1);
// Standalone replica test for build 165 (2026-08-26, live gemeldet):
// "Greeting-Refresh. Update auf das Greeting vorgenommen, gespeichert. Das
// Update wird nicht übernommen. ... Sonst wird der alte Wert beim Sprachwechsel
// wieder zurückgeschrieben."
//
// URSACHE: Im Modus "Name" steckt die Begruessung in der Property GreetingName
// der Visualisierungs-Instanz - dafuer gibt es kein VM_UPDATE. Aufgefrischt
// wurde sie nur bei einem Rescan, und auch dann nur, wenn zufaellig die
// Basissprache aktiv war (MergeGreetingRows, $IsSourceLanguageActive). Wer die
// Begruessung bearbeitet, waehrend eine Zielsprache laeuft, kam nie durch - und
// der naechste Sprachwechsel schrieb den alten Stand zurueck.
//
// Der Guard selbst war richtig: bei aktiver Zielsprache steht in GreetingName
// unsere EIGENE Uebersetzung, die nicht zum Rohtext werden darf. Es fehlte die
// Unterscheidung "eigene Uebersetzung" vs. "Aenderung von aussen" - genau wie
// bei "Eigene Texte" ueber einen Selbst-Schreib-Marker.

// Repliziert die Uebernahme-Entscheidung aus MergeGreetingRows().
function uebernimmt(string $alterRohtext, string $gefunden, string $marker, bool $basisspracheAktiv, bool $mitMarker): bool
{
    $extern = $mitMarker && $gefunden !== $marker;

    return ($basisspracheAktiv || $extern) && $alterRohtext !== $gefunden;
}

// Test 1: DER GEMELDETE FALL - Admin aendert die Begruessung, waehrend Englisch
// aktiv ist. Vor Build 165 wurde das ignoriert.
assert(uebernimmt('Guten Tag', 'Hallo zusammen', 'Good afternoon', false, false) === false,
    'DER BUG: bei aktiver Zielsprache wurde eine Aenderung nie uebernommen');
assert(uebernimmt('Guten Tag', 'Hallo zusammen', 'Good afternoon', false, true) === true,
    'DER FIX: weicht der Text vom zuletzt selbst geschriebenen ab, ist er von aussen gesetzt und gilt als neuer Rohtext');
echo "Test 1 (eine Änderung bei aktiver Zielsprache wird jetzt übernommen) OK\n";

// Test 2: DIE GEGENPROBE - steht dort unsere EIGENE Uebersetzung, darf sie NIE
// zum Rohtext werden. Genau das war der Grund fuer den urspruenglichen Guard.
assert(uebernimmt('Guten Tag', 'Good afternoon', 'Good afternoon', false, true) === false,
    'DER KERN: die eigene Uebersetzung darf nicht zum Rohtext werden - sonst uebersetzt sich das Modul im Kreis');
echo "Test 2 (die eigene Übersetzung wird nicht zum Rohtext) OK\n";

// Test 3: bei aktiver Basissprache gilt weiterhin der alte, einfache Weg.
assert(uebernimmt('Guten Tag', 'Hallo zusammen', '', true, true) === true, 'bei aktiver Basissprache wird wie bisher aufgefrischt');
echo "Test 3 (bei aktiver Basissprache bleibt es beim bisherigen Verhalten) OK\n";

// Test 4: unveraenderter Text aendert nichts - kein unnoetiges Leeren der
// Uebersetzungsspalten.
assert(uebernimmt('Guten Tag', 'Guten Tag', 'Good afternoon', false, true) === false, 'ein unveraenderter Rohtext darf nichts ausloesen');
echo "Test 4 (ein unveränderter Text löst nichts aus) OK\n";

// Test 5: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

assert(strpos($constantsSource, "attributeLastSelfWrittenGreetingName = 'LastSelfWrittenGreetingName'") !== false, 'der Marker muss deklariert sein');
assert(strpos($constantsSource, "attributeRegisteredVisuInstanceID = 'RegisteredVisuInstanceID'") !== false, 'die Registrierungs-Buchfuehrung muss deklariert sein');
assert(strpos($moduleSource, '$isExternalGreetingEdit = $newRawText !== $this->ReadAttributeString(self::attributeLastSelfWrittenGreetingName);') !== false,
    'DER FIX: MergeGreetingRows muss die externe Aenderung erkennen');
assert(strpos($moduleSource, 'if (($IsSourceLanguageActive || $isExternalGreetingEdit) && $row[self::langOriginalImport] !== $newRawText) {') !== false,
    'und sie zusaetzlich zum bisherigen Weg zulassen');

// Der Marker MUSS vor dem Schreibvorgang stehen - dieselbe Falle wie in Build 154.
$applyStart = strpos($moduleSource, 'private function ApplyGreetingLanguage');
$applyBody = substr($moduleSource, $applyStart, 3500);
$markerPos = strpos($applyBody, 'WriteAttributeString(self::attributeLastSelfWrittenGreetingName');
$writePos = strpos($applyBody, "IPS_SetProperty(\$webFrontID, 'GreetingName'");
assert($markerPos !== false && $writePos !== false, 'Marker und Schreibvorgang muessen vorkommen');
assert($markerPos < $writePos, 'DIE FALLE AUS BUILD 154: der Marker muss VOR dem Schreibvorgang gesetzt werden');
echo "Test 5 (Marker, Erkennung und Reihenfolge sind real verdrahtet) OK\n";

// Test 6: DER LISTENER - ohne ihn griffe der Fix erst beim naechsten Rescan, und
// bis dahin schriebe ein Sprachwechsel den alten Stand zurueck. Genau das war
// die Beschwerde.
assert(strpos($moduleSource, 'RegisterMessage($visuID, IM_CHANGESETTINGS)') !== false, 'die Visu-Instanz muss auf IM_CHANGESETTINGS ueberwacht werden');
assert(strpos($moduleSource, 'UnregisterMessage($registeredVisuID, IM_CHANGESETTINGS)') !== false, 'eine Umkonfiguration darf keine verwaiste Registrierung hinterlassen');
assert(strpos($moduleSource, 'if ($Message === IM_CHANGESETTINGS) {') !== false, 'MessageSink muss die Nachricht behandeln');
assert(strpos($moduleSource, 'private function HandleVisuInstanceSettingsChange(): void') !== false, 'der Handler muss existieren');

$handlerStart = strpos($moduleSource, 'private function HandleVisuInstanceSettingsChange');
$handlerBody = substr($moduleSource, $handlerStart, 3200);
assert(strpos($handlerBody, 'self::attributeLastSelfWrittenGreetingName') !== false,
    'DIE RUECKKOPPLUNG: der Handler muss den eigenen Schreibvorgang erkennen - ApplyGreetingLanguage loest ueber IPS_ApplyChanges dieselbe Nachricht erneut aus');
assert(strpos($handlerBody, "(int) (\$rows[0]['ValueObjectID'] ?? 0) !== 0") !== false,
    'im Modus "Variable" laeuft die Aktualisierung bereits ueber VM_UPDATE - der Handler muss sich dort heraushalten');
echo "Test 6 (der Listener ist verdrahtet und gegen Rückkopplung gesichert) OK\n";

// Test 7: die Begruessungstabelle traegt genau eine Zeile - mehr kann es nicht
// geben (eine einzelne Einstellung, kein gescannter Baum).
$form = json_decode(file_get_contents(dirname(__DIR__) . '/SimpleLocale/form.json'), true);
$greeting = null;
$suche = function ($n) use (&$suche, &$greeting): void {
    if (is_array($n)) {
        if (($n['name'] ?? '') === 'ObjectGreeting') { $greeting = $n; }
        foreach ($n as $v) { $suche($v); }
    }
};
$suche($form);
assert($greeting !== null, 'die Begruessungsliste muss existieren');
assert(($greeting['rowCount'] ?? 0) === 1, 'die Begruessungstabelle darf nur eine Zeile hoch sein - mehr kann es dort nicht geben');
assert(($greeting['add'] ?? true) === false, 'und es darf keine Zeile hinzugefuegt werden koennen');
echo "Test 7 (die Begrüßungstabelle ist genau eine Zeile hoch) OK\n";

echo "\nAll tests passed.\n";
