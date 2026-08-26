<?php
declare(strict_types=1);
/*
 * REPARATUR: ueberschriebene ORIGINAL_IMPORT_Text-Spalte in "Eigene Texte"
 * =======================================================================
 *
 * Als Symcon-Skript einfuegen (Objektbaum -> Skript anlegen -> Inhalt ersetzen)
 * und mit dem Play-Knopf ausfuehren. Ausgabe erscheint im Skript-Fenster.
 *
 * HINTERGRUND
 * Das Modul hat seinen EIGENEN Schreibvorgang (die uebersetzte Fassung, die es
 * in die getrackte String-Variable schreibt) faelschlich fuer eine externe
 * Aenderung gehalten und den uebersetzten Text als neuen Rohtext uebernommen.
 * Dadurch steht in ORIGINAL_IMPORT_Text nicht mehr das Deutsche, sondern die
 * Uebersetzung - und der naechste Lauf uebersetzt die Uebersetzung.
 *
 * REIHENFOLGE - bitte genau so:
 *   1. MODUS = 'diagnose'  -> nur lesen, nichts aendern. Bericht anschauen.
 *   2. MODUS = 'freeze'    -> Instanz deaktivieren, damit nichts weiter kaputtgeht.
 *   3a. MODUS = 'copy'    -> Rohtexte aus einer intakten zweiten Instanz holen
 *                            (der genaueste Weg - kopiert Property zu Property,
 *                            ohne Umweg ueber die Variablen).
 *   3b. MODUS = 'restore'  -> ersatzweise Wiederherstellung aus dem Symcon-Archiv.
 *   4. Build 154 einspielen, danach Instanz wieder aktivieren.
 *
 * Schritt 3 funktioniert NUR fuer Variablen, fuer die die Protokollierung
 * (Archiv) eingeschaltet ist. Der Diagnoselauf sagt dir pro Zeile, ob das so
 * ist. Wo kein Archiv vorliegt, hilft nur ein Symcon-Backup von VOR dem
 * Vorfall - die Diagnose schreibt dafuer den aktuellen Stand in eine Datei,
 * damit sich beides spaeter zusammenfuehren laesst.
 */

// ---------------------------------------------------------------- Einstellungen
$INSTANCE_ID = 0;                              // <<< ID der Simple-Locale-Instanz eintragen
$MODUS       = 'diagnose';                     // 'diagnose' | 'freeze' | 'copy' | 'backup' | 'restore' | 'reset_texts'

// Nur fuer 'copy': ID einer ZWEITEN Simple-Locale-Instanz, deren Spalte
// ORIGINAL_IMPORT_Text noch die unverfaelschten deutschen Texte enthaelt.
// Zugeordnet wird ueber ValueObjectID (ersatzweise ObjectID) - beide Instanzen
// muessen also dieselben String-Variablen verfolgen.
$QUELL_INSTANZ = 0;

// Nur fuer 'restore': der letzte im Archiv protokollierte Wert VOR diesem
// Zeitpunkt gilt als unverfaelschtes Original. Grosszuegig vor den ersten
// Vorfall legen - lieber zu weit zurueck als zu knapp.
$ORIGINAL_VOR = strtotime('2026-08-20 00:00:00');

// Nach dem Heilen die abgeleiteten Uebersetzungen leeren: sie stammen aus dem
// kaputten Quelltext und sind wertlos. Sie werden beim naechsten Rescan neu
// erzeugt (kostet Kontingent, liefert aber wieder korrekte Texte).
$UEBERSETZUNGEN_LEEREN = true;

// Nur fuer 'backup': Pfad zu einer settings.json aus einem Symcon-Backup von VOR
// dem Vorfall. Das Skript sucht darin selbst nach der ObjectTexts-Konfiguration.
$BACKUP_DATEI = '';

// Nur fuer 'reset_texts': Sicherung gegen einen unbeabsichtigten Lauf. Erst auf
// true setzen, wenn der Trockenlauf die quellsprachigen Texte gezeigt hat.
$RESET_BESTAETIGT = false;

// Nur fuer 'backup': dieselbe Sicherung. Erst auf true setzen, wenn der
// Trockenlauf den richtigen Kandidaten und eine plausible Trefferzahl zeigt.
$BACKUP_BESTAETIGT = false;

$EXPORT_DATEI = IPS_GetKernelDir() . 'simplelocale_objecttexts_backup.json';

// ---------------------------------------------------------------- ab hier nichts aendern
const FELD_ROH      = 'ORIGINAL_IMPORT_Text';
const FELD_PREFIX   = 'Text_';
const PROP_TEXTE    = 'ObjectTexts';
const PROP_AKTIV    = 'Active';
const ARCHIVE_GUID  = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

function zeile(string $s = ''): void { echo $s . "\n"; }
// Baut aus einer Quellinstanz die Zuordnung ValueObjectID => unverfaelschter
// Rohtext. Zeilen, deren Rohtext mit einer ihrer EIGENEN Uebersetzungen
// identisch ist, sind selbst betroffen und werden verworfen - sonst wandert die
// Korruption nur von einer Instanz in die andere.
function quellZeilenNachSchluessel(int $quellInstanz, ?int &$verworfen = null): array
{
    $verworfen = 0;
    $rows = json_decode((string) IPS_GetProperty($quellInstanz, PROP_TEXTE), true);
    if (!is_array($rows)) { return []; }

    return zeilenNachSchluessel($rows, $verworfen);
}

// Dieselbe Logik, aber auf einem beliebigen Zeilen-Array (Instanz oder Backup).
function zeilenNachSchluessel(array $rows, ?int &$verworfen = null): array
{
    $verworfen = 0;
    $map = [];
    foreach ($rows as $z) {
        $schluessel = (int) ($z['ValueObjectID'] ?? $z['ObjectID'] ?? 0);
        $roh = (string) ($z[FELD_ROH] ?? '');
        if ($schluessel === 0 || $roh === '') { continue; }
        $betroffen = false;
        foreach ($z as $key => $wert) {
            if (is_string($key) && strpos($key, FELD_PREFIX) === 0 && is_string($wert) && $wert !== '' && $wert === $roh) {
                $betroffen = true;
                break;
            }
        }
        if ($betroffen) { $verworfen++; continue; }
        $map[$schluessel] = $roh;
    }

    return $map;
}

// Durchsucht eine beliebig verschachtelte Backup-Struktur nach ObjectTexts-
// Konfigurationen. Bewusst strukturunabhaengig: erkannt wird jeder String, der
// sich zu einem Array von Zeilen mit dem Feld ORIGINAL_IMPORT_Text dekodieren
// laesst - egal, wie das jeweilige Symcon-Backup drumherum aufgebaut ist.
function findeObjectTextsImBackup($knoten, array &$treffer): void
{
    if (is_string($knoten)) {
        if (strpos($knoten, FELD_ROH) === false) { return; }
        $dekodiert = json_decode($knoten, true);
        if (is_array($dekodiert) && isset($dekodiert[0]) && is_array($dekodiert[0])
            && array_key_exists(FELD_ROH, $dekodiert[0])) {
            $treffer[] = $dekodiert;
        }

        return;
    }
    if (!is_array($knoten)) { return; }
    foreach ($knoten as $key => $wert) {
        if ($key === PROP_TEXTE && is_array($wert) && isset($wert[0][FELD_ROH])) {
            $treffer[] = $wert;
            continue;
        }
        findeObjectTextsImBackup($wert, $treffer);
    }
}

function kuerze(string $s, int $n = 78): string {
    $s = str_replace(["\n", "\r"], ' ', $s);
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '...' : $s;
}

if ($INSTANCE_ID <= 0 || !IPS_InstanceExists($INSTANCE_ID)) {
    zeile('FEHLER: $INSTANCE_ID oben eintragen (ID der Simple-Locale-Instanz).');
    return;
}

$rohJson = IPS_GetProperty($INSTANCE_ID, PROP_TEXTE);
$zeilen  = json_decode((string) $rohJson, true);
if (!is_array($zeilen)) {
    zeile('FEHLER: Property "' . PROP_TEXTE . '" liess sich nicht lesen.');
    return;
}

// Archiv suchen
$archivID = 0;
$acListe = IPS_GetInstanceListByModuleID(ARCHIVE_GUID);
if ($acListe !== []) { $archivID = (int) $acListe[0]; }

zeile('== Simple Locale - Reparatur ORIGINAL_IMPORT_Text ==');
zeile('Instanz:  ' . $INSTANCE_ID . ' (' . IPS_GetName($INSTANCE_ID) . ')');
zeile('Modus:    ' . $MODUS);
zeile('Zeilen:   ' . count($zeilen));
zeile('Archiv:   ' . ($archivID > 0 ? 'gefunden (ID ' . $archivID . ')' : 'NICHT gefunden'));
zeile(str_repeat('-', 80));

// ------------------------------------------------------------------- FREEZE
if ($MODUS === 'freeze') {
    IPS_SetProperty($INSTANCE_ID, PROP_AKTIV, false);
    IPS_ApplyChanges($INSTANCE_ID);
    zeile('Instanz deaktiviert. Das Modul schreibt ab jetzt keine Werte mehr');
    zeile('und uebernimmt keine externen Aenderungen mehr als Rohtext.');
    zeile('Weiter mit MODUS = \'restore\'.');
    return;
}

// -------------------------------------------------------- Zeilen analysieren
$befunde = [];
foreach ($zeilen as $i => $z) {
    $valueID = (int) ($z['ValueObjectID'] ?? $z['ObjectID'] ?? 0);
    $roh     = (string) ($z[FELD_ROH] ?? '');

    // Alle Zielsprachen-Zellen dieser Zeile einsammeln.
    $uebersetzungen = [];
    foreach ($z as $key => $wert) {
        if (is_string($key) && strpos($key, FELD_PREFIX) === 0 && is_string($wert) && $wert !== '') {
            $uebersetzungen[substr($key, strlen(FELD_PREFIX))] = $wert;
        }
    }

    // Signatur der Korruption: der Rohtext ist identisch mit einer der
    // gespeicherten Uebersetzungen dieser Zeile.
    $verdacht = null;
    foreach ($uebersetzungen as $sprache => $wert) {
        if ($wert === $roh && $roh !== '') { $verdacht = $sprache; break; }
    }

    // Archiv befragen
    $archivWert = null;
    $protokolliert = false;
    if ($archivID > 0 && $valueID > 0 && IPS_VariableExists($valueID)) {
        $status = @AC_GetLoggingStatus($archivID, $valueID);
        $protokolliert = ($status === true);
        if ($protokolliert) {
            $werte = @AC_GetLoggedValues($archivID, $valueID, 0, $ORIGINAL_VOR, 1);
            if (is_array($werte) && isset($werte[0]['Value'])) {
                $archivWert = (string) $werte[0]['Value'];
            }
        }
    }

    $befunde[$i] = [
        'valueID'        => $valueID,
        'roh'            => $roh,
        'uebersetzungen' => $uebersetzungen,
        'verdacht'       => $verdacht,
        'protokolliert'  => $protokolliert,
        'archivWert'     => $archivWert,
    ];
}

// ----------------------------------------------------------------- DIAGNOSE
if ($MODUS === 'diagnose') {
    $n_verdacht = 0; $n_rettbar = 0; $n_ohneArchiv = 0;
    foreach ($befunde as $i => $b) {
        $name = ($b['valueID'] > 0 && IPS_ObjectExists($b['valueID'])) ? IPS_GetName($b['valueID']) : '?';
        $markierung = $b['verdacht'] !== null ? 'VERDACHT (= Text_' . $b['verdacht'] . ')' : 'unauffaellig';
        if ($b['verdacht'] !== null) { $n_verdacht++; }

        $rettung = 'kein Archiv';
        if ($b['protokolliert'] && $b['archivWert'] !== null) {
            $rettung = ($b['archivWert'] === $b['roh']) ? 'Archiv = aktueller Wert' : 'RETTBAR aus Archiv';
            if ($b['archivWert'] !== $b['roh']) { $n_rettbar++; }
        } else {
            $n_ohneArchiv++;
        }

        zeile('[' . $i . '] ObjectID=' . $b['valueID'] . ' "' . kuerze($name, 30) . '"');
        zeile('    Status:  ' . $markierung . ' | Rettung: ' . $rettung);
        zeile('    jetzt:   ' . kuerze($b['roh']));
        if ($b['archivWert'] !== null && $b['archivWert'] !== $b['roh']) {
            zeile('    Archiv:  ' . kuerze($b['archivWert']));
        }
        zeile();
    }

    @file_put_contents($EXPORT_DATEI, json_encode($zeilen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    zeile(str_repeat('-', 80));
    zeile('Zeilen mit Korruptions-Signatur: ' . $n_verdacht);
    zeile('Aus dem Archiv wiederherstellbar: ' . $n_rettbar);
    zeile('Ohne Protokollierung (Archiv):    ' . $n_ohneArchiv);
    zeile();
    zeile('ACHTUNG: die Signatur erkennt nur Zeilen, deren Rohtext noch exakt einer');
    zeile('gespeicherten Uebersetzung entspricht. Wurde eine betroffene Zeile');
    zeile('inzwischen ERNEUT uebersetzt, faellt sie durch das Raster - die');
    zeile('tatsaechliche Zahl kann also hoeher liegen.');

    // Vorschau: wie weit traegt eine intakte zweite Instanz? Schreibt nichts.
    if ($QUELL_INSTANZ > 0 && IPS_InstanceExists($QUELL_INSTANZ) && $QUELL_INSTANZ !== $INSTANCE_ID) {
        $vorschau = quellZeilenNachSchluessel($QUELL_INSTANZ, $verworfenQuelle);
        $treffer = 0; $abweichend = 0; $ohne = 0;
        foreach ($befunde as $b) {
            if ($b['valueID'] === 0 || !isset($vorschau[$b['valueID']])) { $ohne++; continue; }
            $treffer++;
            if ($vorschau[$b['valueID']] !== $b['roh']) { $abweichend++; }
        }
        zeile();
        zeile(str_repeat('-', 80));
        zeile('VORSCHAU Kopie aus Instanz ' . $QUELL_INSTANZ . ' (' . IPS_GetName($QUELL_INSTANZ) . '):');
        zeile('  brauchbare Quellzeilen:        ' . count($vorschau)
            . ($verworfenQuelle > 0 ? ' (' . $verworfenQuelle . ' selbst betroffen, uebersprungen)' : ''));
        zeile('  Zeilen mit Gegenstueck:        ' . $treffer);
        zeile('  davon abweichend = heilbar:    ' . $abweichend);
        zeile('  ohne Gegenstueck:              ' . $ohne);
        if ($abweichend > 0) {
            zeile();
            zeile('  -> MODUS = \'freeze\', danach MODUS = \'copy\'.');
        }
    } else {
        zeile();
        zeile('Tipp: $QUELL_INSTANZ oben eintragen (intakte zweite Instanz), dann zeigt');
        zeile('dieser Diagnoselauf zusaetzlich, wie viele Zeilen sich von dort holen');
        zeile('liessen - ohne irgendetwas zu schreiben.');
    }
    zeile();
    zeile('Aktueller Stand gesichert nach:');
    zeile('  ' . $EXPORT_DATEI);
    zeile();
    zeile('Naechster Schritt: MODUS = \'freeze\', danach MODUS = \'restore\'.');
    if ($n_ohneArchiv > 0) {
        zeile();
        zeile('HINWEIS: Fuer Zeilen ohne Archiv hilft nur ein Symcon-Backup von VOR');
        zeile('dem Vorfall. Dort liegt die Property "' . PROP_TEXTE . '" der Instanz');
        zeile($INSTANCE_ID . ' mit den unverfaelschten Rohtexten.');
    }
    return;
}

// --------------------------------------------------------------------- COPY
if ($MODUS === 'copy') {
    if (IPS_GetProperty($INSTANCE_ID, PROP_AKTIV) === true) {
        zeile('ABBRUCH: die Zielinstanz ist noch aktiv.');
        zeile('Bitte zuerst mit MODUS = \'freeze\' laufen lassen - sonst schreibt das');
        zeile('Modul die gerade geheilten Zeilen sofort wieder kaputt.');
        return;
    }
    if ($QUELL_INSTANZ <= 0 || !IPS_InstanceExists($QUELL_INSTANZ)) {
        zeile('ABBRUCH: $QUELL_INSTANZ oben eintragen (intakte zweite Instanz).');
        return;
    }
    if ($QUELL_INSTANZ === $INSTANCE_ID) {
        zeile('ABBRUCH: Quell- und Zielinstanz sind identisch.');
        return;
    }

    $quellZeilen = json_decode((string) IPS_GetProperty($QUELL_INSTANZ, PROP_TEXTE), true);
    if (!is_array($quellZeilen)) {
        zeile('ABBRUCH: Property "' . PROP_TEXTE . '" der Quellinstanz nicht lesbar.');
        return;
    }

    $verworfen = 0;
    $nachSchluessel = zeilenNachSchluessel($quellZeilen, $verworfen);

    zeile('Quellinstanz ' . $QUELL_INSTANZ . ' (' . IPS_GetName($QUELL_INSTANZ) . '): '
        . count($nachSchluessel) . ' brauchbare Zeile(n)'
        . ($verworfen > 0 ? ', ' . $verworfen . ' selbst betroffen und uebersprungen' : ''));
    zeile();

    @file_put_contents($EXPORT_DATEI, json_encode($zeilen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    zeile('Sicherung des IST-Standes: ' . $EXPORT_DATEI);
    zeile();

    $geheilt = 0; $unveraendert = 0; $ohneTreffer = 0;
    foreach ($befunde as $i => $b) {
        if ($b['valueID'] === 0 || !isset($nachSchluessel[$b['valueID']])) {
            $ohneTreffer++;
            continue;
        }
        $original = $nachSchluessel[$b['valueID']];
        if ($original === $b['roh']) {
            $unveraendert++;
            continue;
        }
        $zeilen[$i][FELD_ROH] = $original;
        if ($UEBERSETZUNGEN_LEEREN) {
            foreach (array_keys($b['uebersetzungen']) as $sprache) {
                $zeilen[$i][FELD_PREFIX . $sprache] = '';
            }
        }
        $geheilt++;
        zeile('[' . $i . '] ObjectID=' . $b['valueID'] . ' geheilt');
        zeile('    vorher:  ' . kuerze($b['roh']));
        zeile('    nachher: ' . kuerze($original));
    }

    zeile();
    zeile(str_repeat('-', 80));
    zeile('geheilt: ' . $geheilt . ' | bereits korrekt: ' . $unveraendert . ' | ohne Gegenstueck: ' . $ohneTreffer);

    if ($geheilt === 0) {
        zeile('Nichts geschrieben.');
        if ($ohneTreffer > 0) {
            zeile();
            zeile('Kein Gegenstueck gefunden: die beiden Instanzen verfolgen offenbar');
            zeile('verschiedene String-Variablen. Dann hilft nur das Archiv (MODUS');
            zeile('= \'restore\') oder ein Symcon-Backup.');
        }
        return;
    }

    IPS_SetProperty($INSTANCE_ID, PROP_TEXTE, json_encode($zeilen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    IPS_ApplyChanges($INSTANCE_ID);

    zeile();
    zeile($geheilt . ' Zeile(n) aus Instanz ' . $QUELL_INSTANZ . ' wiederhergestellt.');
    if ($UEBERSETZUNGEN_LEEREN) {
        zeile('Die abgeleiteten Uebersetzungen wurden geleert und werden beim');
        zeile('naechsten Rescan aus dem geheilten Quelltext neu erzeugt.');
    }
    zeile();
    zeile('WICHTIG: erst Build 154 einspielen, DANN die Instanz wieder aktivieren.');
    return;
}

// ------------------------------------------------------------- RESET_TEXTS
// Verwirft alle "Eigene Texte"-Zeilen, damit der naechste Rescan sie frisch aus
// den AKTUELLEN Variablenwerten aufbaut. MergeRows() friert Rohtext und
// Uebersetzungen bekannter ObjectIDs dauerhaft ein - ein blosses Leeren des
// Feldes wuerde beim Rescan also NICHT neu befuellt. Nur das Entfernen der
// Zeilen selbst fuehrt zu einem echten Neuaufbau.
//
// VORAUSSETZUNG: in den Variablen muss der QUELLSPRACHIGE Text stehen. Steht
// dort gerade eine Uebersetzung, liest der Rescan genau diese als neuen Rohtext
// ein - dann waere der Schaden reproduziert, nur ohne Rettungsanker.
if ($MODUS === 'reset_texts') {
    zeile('Aktuelle Variablenwerte - BITTE PRUEFEN, ob das die Quellsprache ist:');
    zeile();
    $geprueft = 0;
    foreach ($befunde as $i => $b) {
        if ($b['valueID'] === 0 || !IPS_VariableExists($b['valueID'])) { continue; }
        if ($geprueft++ >= 12) { continue; }
        zeile('  [' . $i . '] ObjectID=' . $b['valueID'] . ': ' . kuerze((string) GetValueString($b['valueID'])));
    }
    if (count($befunde) > 12) {
        zeile('  ... (' . (count($befunde) - 12) . ' weitere)');
    }
    zeile();

    if (!$RESET_BESTAETIGT) {
        zeile(str_repeat('-', 80));
        zeile('TROCKENLAUF. Es wurde nichts geaendert.');
        zeile('Stehen oben die quellsprachigen Originale, dann $RESET_BESTAETIGT = true');
        zeile('setzen und erneut ausfuehren. ' . count($zeilen) . ' Zeile(n) wuerden verworfen');
        zeile('und beim naechsten Rescan neu aufgebaut.');
        return;
    }

    @file_put_contents($EXPORT_DATEI, json_encode($zeilen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    zeile('Sicherung des IST-Standes: ' . $EXPORT_DATEI);

    IPS_SetProperty($INSTANCE_ID, PROP_TEXTE, '[]');
    IPS_SetProperty($INSTANCE_ID, PROP_AKTIV, true);
    IPS_ApplyChanges($INSTANCE_ID);

    zeile();
    zeile(str_repeat('-', 80));
    zeile(count($zeilen) . ' Zeile(n) verworfen, Instanz wieder aktiviert.');
    zeile('Jetzt im Konfigurationsformular einen Rescan ausloesen - die Zeilen');
    zeile('werden dann aus den aktuellen Variablenwerten neu aufgebaut.');
    zeile('Die gesunde Instanz erst DANACH wieder umstellen.');
    return;
}

// ------------------------------------------------------------------- BACKUP
if ($MODUS === 'backup') {
    if (IPS_GetProperty($INSTANCE_ID, PROP_AKTIV) === true) {
        zeile('ABBRUCH: die Instanz ist noch aktiv. Bitte zuerst MODUS = \'freeze\'.');
        return;
    }
    if ($BACKUP_DATEI === '' || !is_readable($BACKUP_DATEI)) {
        zeile('ABBRUCH: $BACKUP_DATEI oben eintragen (lesbare settings.json aus einem');
        zeile('Symcon-Backup von VOR dem Vorfall).');
        return;
    }

    $inhalt = json_decode((string) @file_get_contents($BACKUP_DATEI), true);
    if (!is_array($inhalt)) {
        zeile('ABBRUCH: ' . $BACKUP_DATEI . ' ist kein lesbares JSON.');
        return;
    }

    $treffer = [];
    findeObjectTextsImBackup($inhalt, $treffer);
    if ($treffer === []) {
        zeile('Im Backup wurde keine ObjectTexts-Konfiguration gefunden.');
        zeile('Stammt die Datei wirklich von einer Installation mit dieser Instanz?');
        return;
    }

    zeile('Im Backup gefundene ObjectTexts-Konfigurationen: ' . count($treffer));
    foreach ($treffer as $nr => $kandidat) {
        $probe = zeilenNachSchluessel($kandidat, $weg);
        $deckung = 0;
        foreach ($befunde as $b) {
            if ($b['valueID'] > 0 && isset($probe[$b['valueID']]) && $probe[$b['valueID']] !== $b['roh']) {
                $deckung++;
            }
        }
        zeile('  [' . $nr . '] ' . count($kandidat) . ' Zeile(n), davon ' . count($probe)
            . ' brauchbar, ' . $deckung . ' wuerden hier heilen'
            . ($weg > 0 ? ' (' . $weg . ' selbst betroffen)' : ''));
    }

    // Den Kandidaten mit der groessten Deckung verwenden.
    $besterNr = 0; $besteDeckung = -1; $besteMap = [];
    foreach ($treffer as $nr => $kandidat) {
        $probe = zeilenNachSchluessel($kandidat, $weg);
        $deckung = 0;
        foreach ($befunde as $b) {
            if ($b['valueID'] > 0 && isset($probe[$b['valueID']]) && $probe[$b['valueID']] !== $b['roh']) {
                $deckung++;
            }
        }
        if ($deckung > $besteDeckung) { $besteDeckung = $deckung; $besterNr = $nr; $besteMap = $probe; }
    }

    if ($besteDeckung <= 0) {
        zeile();
        zeile('Keiner der Kandidaten liefert abweichende Rohtexte - im Backup steht');
        zeile('offenbar bereits derselbe (beschaedigte) Stand. Bitte ein aelteres');
        zeile('Backup versuchen.');
        return;
    }

    zeile();
    zeile('Bester Kandidat: [' . $besterNr . '] mit ' . $besteDeckung . ' heilbaren Zeile(n).');
    zeile();

    if (!$BACKUP_BESTAETIGT) {
        zeile('Beispiele, was wiederhergestellt wuerde:');
        $gezeigt = 0;
        foreach ($befunde as $i => $b) {
            if ($b['valueID'] === 0 || !isset($besteMap[$b['valueID']])) { continue; }
            if ($besteMap[$b['valueID']] === $b['roh']) { continue; }
            if ($gezeigt++ >= 8) { break; }
            zeile('  [' . $i . '] ObjectID=' . $b['valueID']);
            zeile('      jetzt:   ' . kuerze($b['roh'], 70));
            zeile('      Backup:  ' . kuerze($besteMap[$b['valueID']], 70));
        }
        zeile();
        zeile(str_repeat('-', 80));
        zeile('TROCKENLAUF. Es wurde nichts geaendert.');
        zeile('Stehen oben unter "Backup:" die quellsprachigen Originale, dann');
        zeile('$BACKUP_BESTAETIGT = true setzen und erneut ausfuehren.');
        return;
    }

    @file_put_contents($EXPORT_DATEI, json_encode($zeilen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    zeile('Sicherung des IST-Standes: ' . $EXPORT_DATEI);
    zeile();

    $geheilt = 0;
    foreach ($befunde as $i => $b) {
        if ($b['valueID'] === 0 || !isset($besteMap[$b['valueID']])) { continue; }
        $original = $besteMap[$b['valueID']];
        if ($original === $b['roh']) { continue; }
        $zeilen[$i][FELD_ROH] = $original;
        if ($UEBERSETZUNGEN_LEEREN) {
            foreach (array_keys($b['uebersetzungen']) as $sprache) {
                $zeilen[$i][FELD_PREFIX . $sprache] = '';
            }
        }
        $geheilt++;
        zeile('[' . $i . '] ObjectID=' . $b['valueID'] . ' geheilt');
        zeile('    vorher:  ' . kuerze($b['roh']));
        zeile('    nachher: ' . kuerze($original));
    }

    IPS_SetProperty($INSTANCE_ID, PROP_TEXTE, json_encode($zeilen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    IPS_ApplyChanges($INSTANCE_ID);

    zeile();
    zeile(str_repeat('-', 80));
    zeile($geheilt . ' Zeile(n) aus dem Backup wiederhergestellt.');
    zeile('WICHTIG: erst Build 154 einspielen, DANN die Instanz wieder aktivieren.');
    return;
}

// ------------------------------------------------------------------ RESTORE
if ($MODUS !== 'restore') {
    zeile('FEHLER: $MODUS muss diagnose, freeze, copy, backup, reset_texts oder restore sein.');
    return;
}

if (IPS_GetProperty($INSTANCE_ID, PROP_AKTIV) === true) {
    zeile('ABBRUCH: die Instanz ist noch aktiv.');
    zeile('Bitte zuerst mit MODUS = \'freeze\' laufen lassen - sonst schreibt das');
    zeile('Modul die gerade geheilten Zeilen sofort wieder kaputt.');
    return;
}

if ($archivID <= 0) {
    zeile('ABBRUCH: kein Archiv-Modul gefunden - automatische Wiederherstellung');
    zeile('ist ohne Protokollierung nicht moeglich. Bitte Symcon-Backup verwenden.');
    return;
}

@file_put_contents($EXPORT_DATEI, json_encode($zeilen, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
zeile('Sicherung des IST-Standes: ' . $EXPORT_DATEI);
zeile();

$geheilt = 0;
foreach ($befunde as $i => $b) {
    if ($b['archivWert'] === null || $b['archivWert'] === $b['roh']) {
        continue;
    }
    $zeilen[$i][FELD_ROH] = $b['archivWert'];
    if ($UEBERSETZUNGEN_LEEREN) {
        foreach (array_keys($b['uebersetzungen']) as $sprache) {
            $zeilen[$i][FELD_PREFIX . $sprache] = '';
        }
    }
    $geheilt++;
    zeile('[' . $i . '] ObjectID=' . $b['valueID'] . ' geheilt');
    zeile('    vorher:  ' . kuerze($b['roh']));
    zeile('    nachher: ' . kuerze($b['archivWert']));
}

if ($geheilt === 0) {
    zeile('Nichts zu tun - keine Zeile hatte einen abweichenden Archivwert.');
    return;
}

IPS_SetProperty($INSTANCE_ID, PROP_TEXTE, json_encode($zeilen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
IPS_ApplyChanges($INSTANCE_ID);

zeile();
zeile(str_repeat('-', 80));
zeile($geheilt . ' Zeile(n) wiederhergestellt.');
if ($UEBERSETZUNGEN_LEEREN) {
    zeile('Die abgeleiteten Uebersetzungen wurden geleert und werden beim');
    zeile('naechsten Rescan aus dem geheilten Quelltext neu erzeugt.');
}
zeile();
zeile('WICHTIG: erst Build 154 einspielen, DANN die Instanz wieder aktivieren.');
