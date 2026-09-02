<?php
declare(strict_types=1);
// Standalone replica test for build 148 (2026-08-25, Nutzer-Vorgaben zum
// Abo-Modell):
//
// Ziel des Nutzers: "Abo-Verwaltung rein Website-Arbeit" - die App darf danach
// keine neuen Anforderungen mehr an uebermittelte Lizenzinformationen stellen.
//
// Entschieden wurde:
//  1. Kulanz rechnet der SERVER in expiresAt ein - die App bleibt dumm und
//     kennt gar keine Grace-Logik. Dadurch bleibt die Kulanz-Politik jederzeit
//     serverseitig aenderbar, ohne je ein Modul-Update.
//  2. KEIN frei belegbares Hinweisfeld vom Server - die beiden festen Zustaende
//     (laeuft bald ab / ist abgelaufen) decken den Bedarf ab.
//  3. 'interval' (monatlich/jaehrlich) kommt in den SIGNIERTEN Schluessel: es
//     ist statisch und laesst sich nicht aus expiresAt ableiten (ein Jahresabo
//     kurz vor Ablauf sieht aus wie ein Monatsabo).
//
// Verhalten bei Ablauf (Nutzer-Vorgabe): Uebersetzungen fallen auf die
// Quellsprache zurueck, die Kachel weist auf den Ablauf hin, das
// Konfigurationsformular bleibt bedienbar - aber die ZIELSPRACHEN sind
// gesperrt, weil eine neue Zielsprache eine nicht mehr erworbene Uebersetzung
// ausloesen wuerde.

const TAG = 86400;
const WARNTAGE = 7;

// Repliziert die Zustandsermittlung aus BuildLicenseExpiryNoticeHtml().
// Rueckgabe: 'expired' | 'warning' | 'none'
function hinweisZustand(int $expiresAt, bool $valid, bool $expired, int $jetzt): string
{
    if ($expiresAt === 0) {
        return 'none';   // Einmalkauf - laeuft nie ab
    }
    if ($expired) {
        return 'expired';
    }
    if ($valid && $expiresAt - $jetzt <= WARNTAGE * TAG) {
        return 'warning';
    }

    return 'none';
}

$jetzt = 1_800_000_000;

// Test 1: DIE VORWARNUNG - genau ab 7 Tagen vor Ablauf, nicht frueher.
assert(hinweisZustand($jetzt + 6 * TAG, true, false, $jetzt) === 'warning', 'sechs Tage vor Ablauf muss gewarnt werden');
assert(hinweisZustand($jetzt + 7 * TAG, true, false, $jetzt) === 'warning', 'exakt sieben Tage vor Ablauf muss gewarnt werden (Grenze inklusive)');
assert(hinweisZustand($jetzt + 8 * TAG, true, false, $jetzt) === 'none', 'acht Tage vor Ablauf noch NICHT - sonst wird der Hinweis zum Dauerzustand und wird ignoriert');
assert(hinweisZustand($jetzt + 300 * TAG, true, false, $jetzt) === 'none', 'ein frisch verlaengertes Jahresabo darf keinen Hinweis zeigen');
echo "Test 1 (die Vorwarnung erscheint genau ab 7 Tagen vor Ablauf, nicht früher) OK\n";

// Test 2: NACH Ablauf der andere Text.
assert(hinweisZustand($jetzt - TAG, false, true, $jetzt) === 'expired', 'nach Ablauf muss der Abgelaufen-Hinweis erscheinen');
echo "Test 2 (nach Ablauf erscheint der Abgelaufen-Hinweis) OK\n";

// Test 3: EIN EINMALKAUF darf NIE einen Ablaufhinweis bekommen - expiresAt 0
// heisst "laeuft nie ab". Das waere der peinlichste Fehler: eine
// Kaufaufforderung an jemanden, der bereits unbefristet gekauft hat.
assert(hinweisZustand(0, true, false, $jetzt) === 'none', 'DER PEINLICHE FEHLER: ein unbefristeter Einmalkauf darf nie zur Verlaengerung aufgefordert werden');
echo "Test 3 (ein unbefristeter Einmalkauf bekommt nie einen Ablaufhinweis) OK\n";

// Test 4: DIE VERLAENGERUNG - der Server schiebt expiresAt per taeglicher
// Pruefung nach hinten, der Hinweis verschwindet dadurch von selbst. Genau
// hierauf beruht "Abo-Verwaltung rein Website-Arbeit": die App muss nichts tun.
$vorVerlaengerung = $jetzt + 2 * TAG;
assert(hinweisZustand($vorVerlaengerung, true, false, $jetzt) === 'warning', 'kurz vor Ablauf wird gewarnt');
$nachVerlaengerung = $jetzt + 32 * TAG;   // Server hat einen Monat draufgelegt
assert(hinweisZustand($nachVerlaengerung, true, false, $jetzt) === 'none', 'DER KERN: nach serverseitiger Verlaengerung muss der Hinweis von selbst verschwinden - ohne jede App-Aenderung');
echo "Test 4 (eine serverseitige Verlängerung lässt den Hinweis von selbst verschwinden) OK\n";

// Test 5: KULANZ IST SERVERSACHE - die App kennt keine Grace-Logik. Ein
// grosszuegiges expiresAt vom Server verhaelt sich schlicht wie ein spaeterer
// Ablauf. Das ist die Entscheidung "App bleibt dumm": die Kulanzpolitik bleibt
// jederzeit serverseitig aenderbar, ohne Modul-Update.
$mitKulanz = $jetzt + 3 * TAG;   // eigentlich abgelaufen, Server gewaehrt 3 Tage
assert(hinweisZustand($mitKulanz, true, false, $jetzt) === 'warning', 'waehrend serverseitig gewaehrter Kulanz bleibt die Lizenz gueltig und es wird nur gewarnt');
assert(hinweisZustand($mitKulanz, false, true, $jetzt + 4 * TAG) === 'expired', 'nach Ablauf der Kulanz greift der normale Abgelaufen-Zustand');
echo "Test 5 (serverseitige Kulanz wirkt ohne jede Grace-Logik in der App) OK\n";

// Repliziert die Normalisierung von 'interval' aus ValidateLicenseKey().
function intervalReplica($roh): string
{
    return in_array($roh, ['month', 'year'], true) ? $roh : '';
}

// Test 6: 'interval' wird streng normalisiert - alles Unerwartete wird zu ''
// und blendet die Zeile aus, statt Muell anzuzeigen.
assert(intervalReplica('month') === 'month', 'monatlich muss durchkommen');
assert(intervalReplica('year') === 'year', 'jaehrlich muss durchkommen');
foreach ([null, '', 'weekly', 'MONTH', 42, ['month']] as $muell) {
    assert(intervalReplica($muell) === '', 'unerwartete Werte muessen zu leer normalisiert werden');
}
echo "Test 6 ('interval' wird streng normalisiert, Unerwartetes blendet die Zeile aus) OK\n";

// Test 7: RUECKWAERTSKOMPATIBILITAET - ein vor Einfuehrung des Feldes
// ausgestellter Schluessel hat kein 'interval'. Er muss unveraendert
// funktionieren, die Zeile bleibt einfach unsichtbar.
$alterSchluessel = ['type' => 'subscription', 'expiresAt' => $jetzt + 30 * TAG];
assert(intervalReplica($alterSchluessel['interval'] ?? null) === '', 'ein alter Abo-Schluessel ohne interval muss weiter funktionieren');
$zeileSichtbar = fn (array $info): bool => ($info['type'] ?? '') === 'subscription' && ($info['interval'] ?? '') !== '';
assert($zeileSichtbar(['type' => 'subscription', 'interval' => '']) === false, 'ohne interval bleibt die Zeile unsichtbar statt leer angezeigt zu werden');
assert($zeileSichtbar(['type' => 'subscription', 'interval' => 'month']) === true, 'mit interval wird die Zeile gezeigt');
assert($zeileSichtbar(['type' => 'one_time', 'interval' => 'month']) === false, 'ein Einmalkauf zeigt nie einen Abozeitraum, selbst wenn das Feld gesetzt waere');
echo "Test 7 (alte Schlüssel ohne 'interval' funktionieren weiter, die Zeile bleibt unsichtbar) OK\n";

// Test 8: Symmetrie-Check gegen die reale module.php.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, "\$payload['interval'] = in_array(\$payload['interval'] ?? null, ['month', 'year'], true)") !== false, "'interval' muss im Schluessel normalisiert werden");
assert(strpos($moduleSource, "'interval'         => \$payload['interval'],") !== false, "'interval' muss aus GetLicenseInfo() herauskommen");
assert(strpos($moduleSource, 'private function BuildLicenseExpiryNoticeHtml(') !== false, 'der Ablaufhinweis fuer die Kachel muss existieren');
assert(strpos($moduleSource, 'private const LICENSE_EXPIRY_WARNING_DAYS = 7;') !== false, 'die Vorwarnzeit muss als benannte Konstante gepflegt sein');
// Der Hinweis muss in der Kachel auch tatsaechlich eingehaengt sein.
assert(strpos($moduleSource, '$this->BuildLicenseExpiryNoticeHtml($ownUiTextRows, $currentLanguage)') !== false, 'der Ablaufhinweis muss in die Kachel eingehaengt sein');
echo "Test 8 (Schlüsselfeld, Hinweis und Vorwarnzeit sind real vorhanden und verdrahtet) OK\n";

// Test 9: DIE SPERRE - bei abgelaufener Lizenz duerfen die Zielsprachen nicht
// mehr geaendert werden (eine neue Zielsprache wuerde eine nicht erworbene
// Uebersetzung ausloesen). Das restliche Formular MUSS bedienbar bleiben,
// sonst koennte nie ein neuer Schluessel eingetragen werden - der einzige Weg
// zurueck.
assert(strpos($moduleSource, "if (\$this->IsTrialLocked()) {\n                        \$element['enabled'] = false;") !== false, 'die Zielsprachen muessen bei abgelaufener Lizenz gesperrt werden');
assert(strpos($moduleSource, 'Target languages (licence expired - please enter a valid licence key above)') !== false, 'die Sperre muss den Ausweg nennen (neuen Schluessel eintragen)');
// Gegenprobe: das Lizenzfeld selbst darf NICHT mitgesperrt werden.
$lizenzFeldGesperrt = preg_match('/case self::propertyLicenseKey:.*?\$element\[.enabled.\]\s*=\s*false/s', $moduleSource) === 1;
assert(!$lizenzFeldGesperrt, 'DER SACKGASSEN-FEHLER: das Lizenzschluessel-Feld darf bei abgelaufener Lizenz niemals gesperrt werden - sonst gaebe es keinen Weg zurueck');
echo "Test 9 (Zielsprachen gesperrt, Lizenzfeld bleibt bedienbar - keine Sackgasse) OK\n";

echo "\nAll tests passed.\n";
