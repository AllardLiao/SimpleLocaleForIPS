<?php
declare(strict_types=1);
// Standalone replica test for build 171 (2026-08-26, live gemeldet, per
// Screenshot belegt): "die Kachel zeigt den Text nicht mehr komplett an."
//
// Der Ablauf-Hinweis gab die komplette Kauf-URL als LINKTEXT aus:
//   "Deine Lizenz läuft ab am 28.08.2026. Verlängern:
//    https://www.synergetix.de/simplelocale/pricing.php"
// Das umbrach auf drei Zeilen. Bei Visu-Hoehe 1 ist die Kachelhoehe fest (siehe
// Build 143), der Text wurde also unten abgeschnitten - im Screenshot brach er
// mitten in "Verlängern:" ab.
//
// Verlinkt wird jetzt das Wort selbst, die URL steckt im title-Attribut.

// Repliziert den Aufbau aus BuildLicenseExpiryNoticeHtml().
const URL = 'https://www.synergetix.de/simplelocale/pricing.php';

function noticeReplica(string $text, string $renew, bool $mitFix): string
{
    if (!$mitFix) {
        return htmlspecialchars($text . ' ' . $renew, ENT_QUOTES, 'UTF-8')
            . ' <a href="' . URL . '">' . URL . '</a>';
    }
    $label = rtrim($renew, ': ');

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . ' <a href="' . URL . '" title="' . URL . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
}

// Sichtbarer Text ohne Auszeichnung - das ist es, was Platz braucht.
function sichtbar(string $html): string
{
    return html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
}

$text = 'Deine Lizenz läuft ab am 28.08.2026.';
$renew = 'Verlängern:';

// Test 1: DER GEMELDETE FALL - die URL als Linktext macht den sichtbaren Text
// mehr als doppelt so lang.
$vorher = sichtbar(noticeReplica($text, $renew, false));
$nachher = sichtbar(noticeReplica($text, $renew, true));
assert(strpos($vorher, URL) !== false, 'DER BUG: die komplette URL stand als sichtbarer Text in der Kachel');
assert(strpos($nachher, URL) === false, 'DER FIX: die URL darf nicht mehr im sichtbaren Text stehen');
assert(mb_strlen($nachher) < mb_strlen($vorher) / 1.8, 'der sichtbare Text muss deutlich kuerzer werden - sonst bricht er weiterhin um');
echo "Test 1 (die URL verschwindet aus dem sichtbaren Text) OK\n";

// Test 2: die Kauf-URL bleibt trotzdem erreichbar - als Ziel UND beim
// Darueberfahren. Sonst waere der Hinweis fuer den Gast eine Sackgasse.
$html = noticeReplica($text, $renew, true);
assert(strpos($html, 'href="' . URL . '"') !== false, 'die URL muss weiterhin das Linkziel sein');
assert(strpos($html, 'title="' . URL . '"') !== false, 'und beim Darueberfahren sichtbar bleiben');
echo "Test 2 (die Kauf-URL bleibt Ziel und Tooltip) OK\n";

// Test 3: DER DOPPELPUNKT - die Vorgabe lautet "Verlängern:", was vor einer URL
// richtig war. Als Linkbeschriftung zeigt er ins Leere und muss weg.
assert(strpos($nachher, 'Verlängern:') === false, 'der abschliessende Doppelpunkt muss abgeschnitten werden');
assert(strpos($nachher, 'Verlängern') !== false, 'das Wort selbst bleibt');
echo "Test 3 (der abschließende Doppelpunkt fällt weg) OK\n";

// Test 4: DIE KUNDEN-UEBERSETZUNG - abgeschnitten wird am WERT, nicht an der
// Vorgabe. Eine bereits uebersetzte Zeile traegt ihren eigenen Doppelpunkt.
$fr = sichtbar(noticeReplica('Votre licence expire le 28.08.2026.', 'Renouveler :', true));
assert(strpos($fr, 'Renouveler') !== false, 'die uebersetzte Beschriftung muss erhalten bleiben');
assert(substr(rtrim($fr), -1) !== ':', 'auch bei einer uebersetzten Zeile darf kein Doppelpunkt am Ende stehen');
echo "Test 4 (auch übersetzte Beschriftungen verlieren ihren Doppelpunkt) OK\n";

// Test 5: eine Beschriftung OHNE Doppelpunkt bleibt unangetastet.
$ohne = sichtbar(noticeReplica($text, 'Jetzt verlängern', true));
assert(strpos($ohne, 'Jetzt verlängern') !== false, 'eine Beschriftung ohne Doppelpunkt darf nicht beschnitten werden');
echo "Test 5 (eine Beschriftung ohne Doppelpunkt bleibt unverändert) OK\n";

// Test 6: Symmetrie-Check gegen die reale Umsetzung.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$start = strpos($moduleSource, 'private function BuildLicenseExpiryNoticeHtml');
$ende = strpos($moduleSource, "\n    private function ", $start + 10);
$body = substr($moduleSource, $start, $ende - $start);

assert(strpos($body, '$renewLabel = rtrim($renew, \': \');') !== false, 'der Doppelpunkt muss am WERT abgeschnitten werden');
assert(strpos($body, 'htmlspecialchars($renewLabel, ENT_QUOTES, \'UTF-8\')') !== false, 'die Beschriftung wird zum Linktext');
assert(substr_count($body, 'htmlspecialchars(self::LICENSE_PURCHASE_URL') === 2,
    'die URL darf nur noch zweimal vorkommen - als href und als title, nicht mehr als sichtbarer Text');
assert(strpos($body, "title=\"' . htmlspecialchars(self::LICENSE_PURCHASE_URL") !== false, 'das title-Attribut muss die URL tragen');
echo "Test 6 (die reale Umsetzung verlinkt das Wort und trägt die URL im title) OK\n";

echo "\nAll tests passed.\n";
