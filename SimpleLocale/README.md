# Simple Locale
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Bekannte Einschränkungen](#2-bekannte-einschränkungen)
3. [Voraussetzungen](#3-voraussetzungen)
4. [Software-Installation](#4-software-installation)
5. [Einrichten der Instanzen in Symcon](#5-einrichten-der-instanzen-in-symcon)
6. [Statusvariablen und Profile](#6-statusvariablen-und-profile)
7. [WebFront](#7-webfront)
8. [Lizenz und Testversion](#8-lizenz-und-testversion)
9. [PHP-Befehlsreferenz](#9-php-befehlsreferenz)
10. [Integration für Modulentwickler](#10-integration-für-modulentwickler)
11. [Change-Log](#11-change-log)

### 1. Funktionsumfang

* Übersetzt live die Namen aller Objekte (Kategorien, Variablen, Kacheln, Links)
  innerhalb des Root der Visualisierung, per `IPS_SetName`.
* Übersetzt zusätzlich den Wert aller String-Variablen innerhalb dieses
  Root der Visualisierung (z. B. Hinweis-/Popup-Texte, auch komplette HTMLBox-Widgets),
  per `SetValueString`. `<style>`- und `<script>`-Blöcke innerhalb solcher
  Werte werden dabei nie an Google geschickt und bleiben beim Übersetzen
  unverändert (verhindert kaputtes CSS/JS durch mitübersetzte Eigenschaften).
* Automatische Übersetzung über die Google Cloud Translate API, inkl.
  persistentem Cache – Google wird nur für neue oder noch unübersetzte Einträge
  aufgerufen, nie für bereits vorhandene (auch manuell korrigierte) Werte.
* **Pro-Feature:** In "Objektnamen", "Eigene Texte", "Aufzählungen", "Charts",
  "Automations" und "Begrüßung" lässt sich pro Zeile per Checkbox "Übersetzung
  aktiv" (standardmäßig angehakt, nur mit Pro-Lizenz überhaupt sichtbar)
  gezielt festlegen, dass ein einzelner Eintrag NIE
  übersetzt wird, sondern immer seinen Rohtext zeigt - unabhängig davon,
  welche Gast-Sprache gerade aktiv ist. Wirkt wie ein dauerhaftes Leeren aller
  Zielsprachen-Zellen dieser einen Zeile, ohne sie tatsächlich zu löschen.
  Gedacht für Eigennamen, Marken oder technische Kürzel, die in jeder Sprache
  gleich bleiben sollen (z. B. ein Personenname in einer Präsenz-Anzeige),
  sowie für einzelne "Eigene Texte"-Variablen, die eigentlich Konfigurations-/
  Steuerdaten für ein anderes Modul statt echten Anzeigetext enthalten. Eine
  deaktivierte Zeile wird beim Rescan zusätzlich gar nicht erst zur
  Übersetzung angefragt - spart unnötige API-Aufrufe für Inhalte, die ohnehin
  nie in übersetzter Form angezeigt würden. Erkennt ein Rescan bei "Eigene
  Texte" automatisch gültiges JSON im Rohtext (z. B. Konfigurations-/
  Steuerdaten für ein anderes Modul statt echten Anzeigetext), wird die
  Checkbox dafür automatisch auf "inaktiv" gesetzt - so ein Inhalt wird
  ohnehin nie übersetzt, unabhängig vom Stand der Checkbox, und die
  Anzeige soll das nicht verschweigen. Diese Automatik wirkt bewusst nur in
  eine Richtung (nie automatisch wieder "aktiv"), damit eine aus einem
  anderen Grund manuell deaktivierte Zeile nicht versehentlich reaktiviert
  wird. Nicht Teil der "Eigenen Übersetzungstabelle" - dort wird ohnehin nie
  automatisch übersetzt.
* Übersetzungen sind direkt im Modul-Formular überprüf- und korrigierbar.
* Der Objektbaum wird manuell (Button) oder automatisch (Timer-Intervall)
  erneut eingelesen; dabei werden nur neue Objekte/Sprachen ergänzt, nichts
  wird überschrieben oder gelöscht. Objekte werden über ihre Objekt-ID
  identifiziert - ein gesetzter Ident ist nicht erforderlich.
* Ein Rescan prüft vor jeder Übersetzung, ob alle Objekte im Root-Baum einen
  echten Namen haben, und bricht andernfalls komplett ab (siehe
  [Abschnitt 2](#2-bekannte-einschränkungen)) - verhindert leere
  Beschriftungen in der Visualisierung.
* Die Instanz selbst bietet eine eigene, schlanke Kachel für die Visualisierung
  (natives HTML-`<select>`-Dropdown statt Symcons Standard-Buttonliste) - dazu
  im WebFront-Baum die Zielkategorie markieren, per Rechtsklick "Instanz
  erstellen" wählen und nach "Simple Locale" suchen, keine zusätzliche
  Variable nötig. Die Sprachnamen im Dropdown (inkl. kleiner Flagge und Google-
  Sprachcode, z. B. "🇬🇧 English - en") werden live in die gerade aktive Sprache
  übersetzt, damit dort nie mehrere Sprachen gemischt angezeigt werden. Ein
  Info-Symbol (ⓘ) neben dem Dropdown erklärt Anwendern auf Klick (ebenfalls live
  übersetzt) die wichtigsten Einschränkungen, siehe
  [Abschnitt 2](#2-bekannte-einschränkungen). Alternativ lässt sich diese
  eingebaute Kachel zugunsten einer selbstgebauten unterdrücken (**Pro-Feature**
  `custom_tile`, siehe [Abschnitt 7](#7-visualisierung)).
* Die aktuell aktive Sprache ist eine reine Instanz-Property (kein Symcon-
  Variablenprofil, das wäre global über alle Instanzen hinweg geteilt) - sie
  ist direkt im Konfigurationsformular sicht- und änderbar.
* Reagiert live auf Wertänderungen, die *andere* Module/Skripte an den in
  "Eigene Texte" (und einer im Modus "Variable" verlinkten Begrüßungs-
  Variable, siehe Abschnitt 7) verfolgten String-Variablen vornehmen (z. B.
  ein Wetter- oder Messwert-Modul, das seinen Text bei jeder Aktualisierung
  selbst neu schreibt, oder eine mehrmals täglich zwischen festen Werten
  wechselnde Begrüßung): der neue Wert wird automatisch als frischer
  Rohtext übernommen und sofort **in alle konfigurierten Zielsprachen**
  nachübersetzt (nicht nur die gerade aktive) - schaltet ein Gast danach in
  eine andere Sprache, bekommt er also ebenfalls sofort die aktuelle
  Übersetzung zu sehen, nicht den unübersetzten Rohtext oder eine veraltete,
  vor der Änderung gecachte Fassung. Ganz ohne Zutun des anderen
  Modulentwicklers. Technisch über Symcons VariableManager-Update-
  Nachrichten (`VM_UPDATE`), siehe [Abschnitt 2](#2-bekannte-einschränkungen)
  für die eine Voraussetzung dabei.

  Damit das bei häufigen Änderungen nicht bei jedem Update erneut komplett
  gegen Google/DeepL/den kostenfreien Anbieter übersetzt werden muss, führt
  Simple Locale einen internen Übersetzungs-Cache (Schlüssel:
  Quellsprache + Zielsprache + Text). Kommt exakt derselbe Text in
  derselben Sprachrichtung erneut vor - z. B. eine Begrüßungs-Variable, die
  sich mehrmals täglich zwischen einer Handvoll fester Werte ("Guten
  Morgen"/"Guten Tag"/"Guten Abend"/"Gute Nacht") abwechselt -, wird ab dem
  zweiten Auftreten die bereits vorhandene Übersetzung wiederverwendet,
  **ganz ohne erneuten API-Aufruf**. Der Cache ist auf die letzten 500
  Einträge begrenzt (älteste zuerst raus) und wird auch von normalen
  Rescans mitgenutzt. Besonders spürbar bei einer per VM_UPDATE verfolgten
  Kachel wie einem Wetter-Widget: bei ca. 20 einzelnen Textbausteinen
  (Wochentage, Vorhersage, Sonnenauf-/-untergang, aktuelle Messwerte) und
  einem Update alle 10 Minuten wären das ohne Cache über 2.700
  Übersetzungsaufrufe pro Tag und Zielsprache - mit Cache nur noch die
  paar Werte, die sich tatsächlich bei jedem Update ändern (z. B.
  Temperatur, Wetterlage, Luftfeuchte), grob geschätzt 70-75 % weniger.
  Wie viele Anfragen/Zeichen dadurch konkret eingespart wurden, zeigt seit
  Build 61 der Nutzungs-Zähler im Konfigurationsformular (siehe
  [Abschnitt 2](#2-bekannte-einschränkungen)) als reine Gesamtsumme seit
  Inbetriebnahme.
  Zeigt ein Text weiterhin hartnäckig eine
  veraltete/falsche Übersetzung, hilft der Button "Übersetzungs-Cache
  leeren" im Formular - er löscht nur diesen internen Zwischenspeicher,
  nicht die bereits in Objektnamen/Eigenen Texten/Beschriftungen
  gespeicherten Übersetzungen selbst (die müssen bei Bedarf weiterhin
  einzeln im jeweiligen Feld geleert werden, damit sie beim nächsten
  Rescan/Sprachwechsel neu übersetzt werden).
* **Ab Standard-Lizenz** enthält die "Eigene Übersetzungstabelle" (siehe
  weiter unten) von Haus aus rund 30 vorbefüllte Einträge für in der
  Hausautomatisierung gängige Maßeinheiten (z. B. °C, kg, km/h, hPa) sowie
  die 16 Kompass-Himmelsrichtungen (inkl. Zwischenrichtungen wie SSW) in 9
  Sprachen (Englisch, Spanisch, Französisch, Italienisch, Portugiesisch,
  Niederländisch, Polnisch, Russisch, Türkisch) - erspart unnötige und bei
  kurzen Zahl-plus-Einheit-Texten ("0.82 m/s") mitunter sogar riskante
  API-Aufrufe. Riskant deshalb, weil ein allgemeiner Übersetzungsanbieter
  hier tatsächlich danebengreifen kann: beobachtet wurde z. B. eine
  fälschliche Übersetzung von "°C" nach "°F", die bei unverändertem
  Zahlenwert eine falsche Anzeige ergeben hätte. Bei Kompassrichtungen wäre
  ein reines 1:1-Durchreichen sogar grundsätzlich falsch - dieselbe
  Buchstabenfolge kann in verschiedenen Sprachen Gegenteiliges bedeuten
  (deutsch "O" = Ost, spanisch "O" = Oeste/**West**) -, deshalb werden diese
  Einträge echt sprachspezifisch vorbelegt statt einfach kopiert. Auch bei den
  Einheiten selbst ist nicht jedes Kürzel wirklich universell: "Stunde" wird
  nicht überall mit dem lateinischen SI-Kürzel "h" abgekürzt - km/h heißt
  umgangssprachlich Spanisch "kph", Niederländisch "km/u" (uur), Türkisch
  "km/sa" (saat). Am deutlichsten weicht Russisch ab - dort werden fast alle
  Einheiten-Kürzel in der Praxis grundsätzlich kyrillisch geschrieben (z. B.
  "кг" statt "kg", "км/ч" statt "km/h", "кВт·ч" statt "kWh"), entsprechend
  sind auch diese Einträge sprachspezifisch statt einfach durchgereicht. Wie
  jeder Glossar-Eintrag ist auch ein vorbefüllter Eintrag jederzeit vom Admin
  löschbar (z. B. falls "SSW" in einer Installation zufällig ein
  Personen-Kürzel statt einer Windrichtung ist) - eine einmal gelöschte
  Vorbelegung kehrt bei einem späteren Rescan nicht zurück. Die
  Light-Edition hat wie beim restlichen Glossar keinen Zugriff darauf und
  ruft für solche Texte weiterhin ganz normal die Übersetzungs-API auf.
* Übersetzt zusätzlich die Beschriftungen von Variablen mit einer
  Wert-Aufzählung (z. B. Integer-Variablen mit klassischem Profil oder
  moderner Enumeration-Presentation, etwa "Abwesend/Anwesend" oder
  "Aktiv/Inaktiv") - unabhängig davon, ob diese Beschriftungen aus einem
  ggf. installationsweit **geteilten** Profil/Template stammen. Das
  zugrunde liegende Profil/Template selbst wird dabei **nie** verändert,
  siehe den Fork-Mechanismus in
  [Abschnitt 2](#2-bekannte-einschränkungen).
* Übersetzt zusätzlich die Legenden-Titel von Symcons eingebautem
  Chart-Element (WebFront-Visualisierung → "Add Chart") - jede Datenreihe
  kann dort einen eigenen, frei editierbaren Titel tragen (z. B.
  "Außentemperatur", "Wohnzimmer"); lässt man das Titel-Feld leer, zeigt
  Symcon selbst live den aktuellen Namen der zugrunde liegenden Variable in
  der Legende an. Ein Chart liegt normal im Root-Baum und wird daher
  automatisch mit erfasst, kein separater Scan nötig - als Quelltext gilt
  der explizite Titel, falls gesetzt, sonst der aktuelle Variablenname. Nur
  im letzteren Fall (leeres Titel-Feld) gilt zusätzlich: steht die zugrunde
  liegende Variable auch als eigenständiges Objekt im Root-Baum (z. B. als
  eigene Anzeige-Kachel), wird sie ohnehin über die normale
  Objektnamen-Übersetzung umbenannt, und Symcon übernimmt diesen neuen
  Namen nachweislich automatisch in die Chart-Legende - dafür gibt es dann
  keine eigene Chart-Zeile. Ein bewusst im Chart selbst gesetzter, eigener
  Titel wird dagegen immer von Simple Locale getrackt/übersetzt, auch wenn
  seine Variable zufällig zusätzlich eigenständig im Baum steht - ein
  eigener Titel hat mit dem Variablennamen nichts zu tun.

### 2. Bekannte Einschränkungen

* **Eine Sprache pro Instanz, nicht pro Besucher.** Die aktuell aktive Sprache
  ist ein Zustand der Instanz, kein Zustand der einzelnen Browser-Sitzung.
  Zwei Anwender, die gleichzeitig dieselbe Visualisierung öffnen, sehen daher
  immer dieselbe Sprache - es gibt keine getrennte Sprache je Person. Werden
  wirklich gleichzeitig unterschiedliche Sprachen für unterschiedliche
  Zielgruppen benötigt, braucht es mehrere Instanzen mit jeweils eigenem
  Root der Visualisierung/eigener Kachel.
* **Dynamisch aktualisierte Inhalte werden automatisch nachübersetzt -
  vorausgesetzt, sie werden in der konfigurierten Scan-Sprache geschrieben.**
  Schreibt ein *anderes* Modul oder Skript den Wert einer verfolgten
  String-Variable neu (z. B. ein Wetter-Skript bei seinem nächsten
  Aktualisierungsintervall), übersetzt Simple Locale live nach (siehe
  [Abschnitt 1](#1-funktionsumfang)) - dabei wird angenommen, dass der neue
  Wert in der Scan-Sprache verfasst ist, genau wie beim ursprünglichen Scan.
  Schreibt das fremde Modul tatsächlich in einer anderen Sprache, fällt die
  automatische Übersetzung entsprechend falsch aus (wie bei jeder
  automatischen Übersetzung, siehe unten) - Scan-Sprache in der
  Modul-Konfiguration also passend zur tatsächlich verwendeten Sprache des
  überwachten Moduls wählen. Beobachtet werden ausschließlich die Variablen
  unter "Eigene Texte" - Objektnamen ändern sich durch Fremdzugriffe
  praktisch nie und werden daher nicht überwacht.

  Dieser Punkt ist auch direkt in der Kachel über das Info-Symbol (ⓘ)
  neben dem Dropdown einsehbar, live in der jeweils aktiven Sprache.
* **Sehr häufig aktualisierte Variablen können viele API-Aufrufe erzeugen.**
  Jede externe Aktualisierung einer verfolgten "Eigene Texte"-Variable löst
  eine eigene Live-Nachübersetzung aus (siehe oben) - bei einer Variable, die
  sich mehrmals pro Minute ändert (z. B. ein sehr aktives Sensor-/Wetter-Skript),
  kann das entsprechend oft passieren. Für genau diesen Fall (Anbieter melden
  Rate-Limits/aufgebrauchte Kontingente) gibt es den Notaus-Schalter "Aktiv"
  (siehe Konfigurationstabelle oben) - sofort per Formular umschaltbar, kein
  Warten auf ein Modul-Update nötig. Fehlerdetails zu jedem fehlgeschlagenen
  Übersetzungsversuch (welcher Anbieter, HTTP-Code, Antwort) landen
  zusätzlich im normalen Symcon-Meldungen-Log der Instanz (nicht nur im
  Debug-Panel).
* **Automatische Pause bei Rate-Limit/Tageskontingent (Build 55).** Meldet ein
  einzelner Übersetzungsanbieter ein Rate-Limit oder ein aufgebrauchtes
  Tageskontingent (erkannt an HTTP 429/456 bzw. HTTP 403 mit "rate limit" in
  der Antwort), wird genau dieser eine Anbieter für eine gewisse Zeit
  automatisch pausiert (kurze Sperre bei einem reinen Burst-Limit, 24h bei
  einem erkannten Tageskontingent) - er wird währenddessen nicht erneut
  angefragt, die übrigen konfigurierten Anbieter werden aber normal
  weiterversucht. Melden ALLE konfigurierten Anbieter gleichzeitig ein
  Limit (bei nur einem konfigurierten Anbieter genügt bereits dieser eine),
  lohnt sich kein weiterer Versuch mehr - die Instanz pausiert dann komplett
  bis zum frühesten Reset-Zeitpunkt: kein einziger weiterer API-Aufruf, bis
  mindestens ein Anbieter wieder verfügbar sein sollte. Sichtbar an drei
  Stellen: ein kleiner roter Hinweis "Übersetzung pausiert bis HH:MM" direkt
  unter dem Dropdown in der Kachel (live in die jeweils aktive Gast-Sprache
  übersetzt), der Instanz-Status "Aktiv, aber pausiert", und eine detaillierte
  Aufschlüsselung (welcher Anbieter pausiert bis wann) im Panel
  "Übersetzungsanbieter" des Konfigurationsformulars. Ein ungültiger/
  abgelaufener API-Key oder ein Netzwerkfehler lösen dagegen NIE eine Pause
  aus (die würde sich ja nie von selbst erledigen) - nur ein tatsächlich als
  Rate-Limit/Kontingent erkannter Fehler.
* **Änderungen, die eine häufig extern aktualisierte Variable auslöst,
  werden für die Formular-Persistierung bis zu 12 Minuten lang gepuffert
  (Debounce).** Der Rohtext, den ein anderes Modul/Skript mehrmals pro
  Minute in einer verfolgten "Eigene Texte"-Variable schreibt, erscheint in
  der Kachel immer sofort und unverzögert; nur die Property-Persistierung
  dieser Änderung (die Buchführung für einen späteren Sprachwechsel) wird
  gepuffert und normalerweise erst nach dieser Ruhezeit tatsächlich
  geschrieben. Er bewahrt nur den externen
  Puffer selbst davor, verworfen zu werden - er schützt NICHT eine eigene,
  gleichzeitige Bearbeitung DERSELBEN Zelle. Beim "Übernehmen" liest
  `ApplyChanges()` die gerade abgeschickten Zeilen (bereits mit dem eigenen
  frischen Wert) und überschreibt darin gezielt genau die Felder, die im
  Puffer stehen (Rohtext + Übersetzung der aktiven Sprache), mit dem
  ÄLTEREN, gepufferten externen Wert. Bearbeitet man eine andere Zeile oder
  eine andere Spalte derselben Zeile (z. B. eine nicht-aktive Zielsprache),
  bleibt die eigene Änderung unangetastet. Korrigiert man dagegen manuell
  genau die Zelle, die gerade auch extern (per `VM_UPDATE`) aktualisiert und
  gepuffert wird, gewinnt beim Speichern der ältere externe Wert - die
  eigene, frisch eingetippte Korrektur geht dabei ersatzlos verloren.
  Bei einer häufig extern aktualisierten Variable also am besten zügig
  speichern bzw. genau diese Zelle meiden, solange der Hinweis oben im
  Formular eine anstehende Persistierung anzeigt.
* **Qualitäts-Hinweis:** für die fünf Testphasen-Sprachen (is/cy/zu/mi/la)
  gibt es keine Konsolensprachen-Referenz zum Abgleich, und die
  Übersetzungsqualität für diese seltener unterstützten Sprachen -
  insbesondere Zulu und Māori - ist spürbar weniger zuverlässig
  einzuschätzen als für die verbreiteten Sprachen. Vor produktivem
  Live-Einsatz wird eine Prüfung durch Muttersprachler empfohlen. Diese
  Zeilen sind (wie alle `propertyOwnUiTexts`-Zeilen) bewusst NICHT über das
  Konfigurationsformular editierbar - eine Korrektur kann aktuell nur über
  ein künftiges Modul-Update erfolgen.
* **Bekannte, strukturelle Einschränkung (dokumentiert, kein Code-Fix
  möglich): live per VM_UPDATE nachübersetzte "Eigene Texte" können für
  einen kurzen Moment unübersetzt sichtbar sein**, wenn eine externe
  Quelle (z. B. ein Wetter-/Sensor-Modul) denselben Wert schreibt, den es
  auch selbst anzeigt (Objekt-ID = Wert-Objekt-ID, der Normalfall ohne
  gesonderte Anzeige-Variable). Symcons WebFront pusht JEDEN
  Schreibvorgang sofort an verbundene Gast-Browser - inklusive des
  externen Rohtext-Schreibvorgangs selbst, BEVOR Simple Locale reagieren
  und die Übersetzung zurückschreiben kann. Wie lange dieses Fenster
  offen bleibt, hängt direkt von der Antwortzeit des jeweiligen
  Übersetzungsanbieters ab (spürbar kürzer bei einem Cache-Treffer, siehe
  `TranslateBatch`/`GetCachedTranslation`, ansonsten vom echten
  API-Antwortverhalten abhängig) - keine feste, garantierbare Ober- oder
  Untergrenze. Wer das vollständig ausschließen möchte, kann dem
  betroffenen "Eigene Texte"-Eintrag eine EIGENE, separate
  Anzeige-Variable zuweisen (`ValueObjectID` abweichend von der
  eigentlich getrackten Quellvariable) und das anzeigende Widget auf
  diese umstellen - dann schreibt die externe Quelle nie direkt in eine
  gast-sichtbare Variable, nur Simple Locales bereits übersetztes
  Ergebnis erreicht sie.
* **Lange Texte über den kostenfreien Anbieter: 500-Byte-Grenze pro Anfrage.**
  Der kostenfreie Anbieter (MyMemory) akzeptiert pro Übersetzungsanfrage
  maximal 500 Byte Text - längere Inhalte lehnt er bewusst sofort ab
  (`TranslateSingleFree()`), statt sie fehlerhaft abzuschneiden. Betrifft in
  der Praxis vor allem längere "Eigene Texte" (z. B. ein vollständiges
  HTMLBox-Widget mit mehreren Absätzen als Hinweistext), selten kurze
  Objektnamen oder Beschriftungen. Ist zusätzlich ein bezahlter Anbieter
  (Google Cloud Translate oder DeepL) konfiguriert, reicht die Anbieterkette
  einen von MyMemory abgelehnten Text automatisch an diesen weiter - beide
  kennen kein vergleichbares Zeichenlimit. Steht dagegen nur der kostenfreie
  Anbieter zur Verfügung, bleibt genau diese eine Zielsprachen-Zelle
  unübersetzt (die Kachel zeigt übergangsweise den unveränderten
  Original-Rohtext, kein Absturz) - live gefunden (2026-08-21): das sieht auf
  den ersten Blick wie ein stiller Fehler ohne erkennbaren Grund aus, ist
  aber genau diese Längenbegrenzung. Der Symcon-Meldungen-Log der Instanz
  nennt den tatsächlichen Grund explizit ("alle Anbieter der Kette ...
  abgelehnt"), sobald wirklich JEDER konfigurierte Anbieter denselben Text
  ablehnt - siehe auch [Abschnitt 5](#5-einrichten-der-instanzen-in-symcon)
  zur Anbieterkette insgesamt. Kein Chunking/Aufteilen langer Texte in v1 -
  ein zu langer Text wird also nicht in mehrere kürzere Anfragen zerlegt,
  sondern komplett über den kostenfreien Anbieter übersprungen.
* **Die automatische Übersetzung kann trotzdem Fehler machen.** Google
  Translate liefert nicht immer eine passende Übersetzung. (Ein früherer,
  strukturell inzwischen ausgeschlossener Fall: Google erkannte bei der
  kurzen Übersetzung ohne fest vorgegebene Scan-Sprache "Haus" fälschlich
  als Hmong und lieferte "Trinken" - deshalb wird die Scan-Sprache inzwischen
  immer fest vorgegeben, siehe [Abschnitt 7](#7-visualisierung).) **Alle
  Übersetzungen in "Objektnamen" und "Eigene Texte" daher nach dem ersten
  Rescan einmal durchsehen** und falsch übersetzte Zellen manuell
  korrigieren - eigene Korrekturen werden nie automatisch überschrieben
  (siehe Abschnitt 5). Soll eine bereits gefüllte Zelle stattdessen neu von
  Google übersetzt werden: Zelleninhalt löschen, "Übernehmen" klicken, dann
  erneut Rescan ausführen - nur leere Zellen werden dabei (neu) übersetzt.
  Für die per VM_UPDATE live nachübersetzten "Eigene Texte" (siehe oben)
  greift diese manuelle Korrektur allerdings nicht dauerhaft: eine erneute
  Wertänderung überschreibt die Übersetzung sofort wieder frisch - eine
  Ausnahme sind die deutschen Wochentags-Kürzel Mo/Di/Mi/Do/Fr/Sa/So
  (häufig in Wetter-Widgets), die für Englisch/Spanisch/Französisch/
  Italienisch/Niederländisch/Portugiesisch als Zielsprache fest und
  garantiert korrekt hinterlegt sind, statt der oft unzuverlässigen
  automatischen Übersetzung zu überlassen (isoliert ohne Kontext ist z. B.
  "So" genauso das deutsche Wort "so" wie die Abkürzung für "Sonntag").
  Greift nur, wenn mindestens 4 dieser Kürzel unmittelbar hintereinander im
  selben Text vorkommen (wie bei einer echten Wochentagsliste) - ein
  einzelnes, isoliertes Kürzel (z. B. eine Windrichtungsangabe "SO" für
  Süd-Ost) bleibt davon unberührt und läuft weiterhin ganz normal durch
  die konfigurierte Übersetzungskette.
* **Alle Objekte im Root-Baum brauchen einen echten Namen.** Ein Rescan
  bricht komplett ab (keine Übersetzung, kein Speichern), sobald er ein
  Objekt ohne Namen findet (leerer Name, oder von Symcon selbst vergebener
  Platzhalter wie "Unnamed Object (ID: ...)"/"Unbenanntes Objekt (ID: ...)").
  Die betroffenen Objekt-IDs samt Pfad erscheinen dann als Liste im
  Konfigurationsformular - erst benennen, dann erneut "Visualisierung neu
  einlesen" klicken.
* **Bei großen Bäumen und/oder vielen Zielsprachen: "Could not load
  configuration form" / "Output-Buffer exceeds Limit (... bytes)".** Das
  Konfigurationsformular überträgt "Objektnamen", "Eigene Texte" und
  "Beschriftungen" vollständig in einer einzigen Antwort - jede Zeile mit
  jeder aktiven Zielsprache als eigener Spalte. Bei vielen Objekten (v. a.
  "Eigene Texte" mit längeren HTMLBox-Inhalten) mal sieben oder mehr
  Sprachen kann diese Antwort größer werden als das von Symcon selbst
  gesetzte Limit für Skript-Ausgaben (`ScriptOutputBufferLimit`,
  Standardwert 1.048.576 Byte = 1 MB) - das Formular lässt sich dann gar
  nicht mehr öffnen. Das ist keine Fehlfunktion des Moduls, sondern eine
  reine Transportgrenze der Symcon-Konsole selbst. Abhilfe: in der Konsole
  unter **License Information ("i"-Symbol oben rechts) → Special Switches
  (unten mittig)** den Wert `ScriptOutputBufferLimit`
  erhöhen (z. B. auf `8388608` = 8 MB) und das Konfigurationsformular
  danach erneut öffnen. Der Wert gilt für die gesamte Symcon-Installation,
  nicht nur für dieses Modul - eine Erhöhung ist unbedenklich und wirkt sich
  auf keine andere Instanz negativ aus.
* **Beschriftungen (Abschnitt 1): der "Fork"-Mechanismus und seine Grenzen.**
  Eine Variable im Root-Baum kann ihre Wert-Beschriftungen von einem
  klassischen Profil oder einer modernen Template-Presentation beziehen -
  beides sind in Symcon **geteilte, benannte/per-GUID-adressierte Objekte**,
  die auch von anderen, nicht getrackten Variablen (in anderen Kategorien,
  anderen Visus, sogar anderen Instanzen) verwendet werden können. Simple
  Locale schreibt beim Sprachwechsel **niemals** in dieses geteilte
  Profil/Template selbst - stattdessen wird für **jede einzelne getrackte
  Variable** eine eigene, in sich geschlossene Kopie der Beschriftungen
  hinterlegt (`IPS_SetVariableCustomPresentation`, ohne Profil-/Template-
  Referenz - ein "Fork"). Andere Variablen, die zufällig dasselbe
  Profil/Template nutzen, aber **nicht** im Root-Baum liegen, lesen es
  unverändert weiter und bleiben komplett unberührt.

  Nutzen dagegen **mehrere Variablen innerhalb des Root-Baums** dasselbe
  Profil/Template (genau dafür sind Profile/Templates da), wird der Text
  nur **einmal** gescannt und übersetzt - eine manuelle Korrektur in der
  Liste "Beschriftungen" wirkt dann automatisch auf alle Variablen, die
  dieses Profil/Template nutzen (jede bekommt beim Sprachwechsel trotzdem
  ihren eigenen, unabhängigen Fork geschrieben - nur die Übersetzung dahinter
  ist geteilt). Beim Zurückwechseln auf die Scan-Sprache wird der Fork **pro
  Variable** wieder vollständig aufgehoben (der exakte Zustand vor dem ersten
  Fork wird dafür je Variable gesichert), sie liest ihre Beschriftungen dann
  wieder live vom ursprünglichen, nie veränderten Profil/Template.

  Verknüpfungen (Links) im Root-Baum, die nicht direkt auf eine Variable,
  sondern auf eine **Kategorie** zeigen (übliche Praxis, um z. B. eine
  "Wetter"-Kategorie per Link identisch in mehrere Visus einzubinden, ohne
  sie zu duplizieren), werden gefolgt - die Objekte darin werden mit
  erfasst, nicht nur der Link selbst.

  Zwei Dinge löst der Fork bewusst **nicht**:
  - Steht dieselbe physische Variable (nicht nur dasselbe Profil) über eine
    Verknüpfung in mehreren Visualisierungen gleichzeitig, ändert sich ihre
    Beschriftung überall dort mit - genau wie bei Objektnamen und Eigene
    Texte auch (siehe die "eine Sprache pro Instanz"-Einschränkung oben).
    Praktisch meist unkritisch, da Gäste einer Ferienwohnung/eines Airbnb
    typischerweise zeitlich versetzt anreisen, nicht gleichzeitig
    unterschiedliche Sprachen benötigen.
  - Übersetzt werden nur Felder, deren Name (unabhängig von Groß-/
    Kleinschreibung) einem festen, in `SimpleLocaleConstants.php`
    gepflegten Satz entspricht (`Caption`, `Prefix`, `Suffix`, `Constant`
    sowie - für die pro Intervall abweichende Symcon-Schreibweise -
    `ConstantValue`, `PrefixValue`, `SuffixValue`) - das deckt klassische
    Profile, Enumeration-Presentations und intervallbasierte
    Numeric-Presentations (z. B. eine Heizungsmodus-Kachel mit
    Wertebereichen statt Einzelwerten) ab. Andere, noch unbekannte
    Präsentationsarten mit anders benannten Textfeldern werden bewusst
    **nicht** geraten übersetzt: die Struktur ist sonst zu variabel, um
    sicher zwischen Anzeigetext und technischen Feldern (Icon-Bezeichner,
    Farbwerte, Schwellwerte) zu unterscheiden - lieber eine unbekannte
    Beschriftung übersehen als versehentlich einen Icon-Bezeichner
    kaputtübersetzen und damit ein Icon zum Verschwinden bringen.
* **Fest in die Symcon-Konsole/Kachel-Visualisierung eingebaute Elemente
  werden nicht übersetzt.** Simple Locale übersetzt ausschließlich Inhalte
  aus dem konfigurierten Root-Baum (Objektnamen, Eigene Texte,
  Beschriftungen) sowie die Automations/Favoriten der Kachel-Visualisierung
  (siehe [Abschnitt 7](#7-visualisierung)) - alles, was Symcon selbst fest
  in seine Oberfläche eingebaut hat, bleibt in der Sprache der Symcon-
  Konsole/App und ist für ein Modul grundsätzlich nicht erreichbar. Dazu
  gehören u. a.: das "Search"-Suchfeld, Hover-Popups/Tooltips auf den
  Symbolen (z. B. zeigt das Glocken-Symbol beim Überfahren "Notifications"),
  das Format von Uhrzeit/Datum, sowie die Beschriftung der
  "Favorites"-Schaltfläche selbst (nicht zu verwechseln mit den darunter
  angezeigten Objektnamen der einzelnen Favoriten, die sehr wohl übersetzt
  werden, siehe [Abschnitt 7](#7-visualisierung)). Ein Sonderfall ist die
  tageszeitabhängige Anrede ("Good Morning"/"Good Evening" etc.) im
  Begrüßungsmodus "Automatic" (siehe [Abschnitt 7](#7-visualisierung)) - die
  folgt laut Test **nicht** der Symcon-Konsolensprache, sondern rein
  clientseitig der Spracheinstellung des jeweiligen **Besucher-Browsers**,
  unabhängig von der in Simple Locale aktiven Sprache. Wählt ein Gast über
  die Sprachauswahl-Kachel z. B. Deutsch, sein Browser ist aber auf Englisch
  eingestellt, bleibt diese Anrede englisch - technisch nicht anders lösbar,
  da Simple Locale keinen Einfluss auf diesen clientseitigen Symcon-
  Mechanismus hat. Das Feld "Name" direkt danach (Property `GreetingName`)
  wird davon nicht berührt und ganz normal übersetzt.

### 3. Voraussetzungen

- Symcon ab Version 7.1
- Für die Übersetzung von Beschriftungen (Abschnitt 1/2): Symcon ab Version
  8.0 (Variable Presentations). Auf älteren Versionen bleibt dieser
  Teilbereich einfach komplett inaktiv (kein Fehler) - Objektnamen und
  Eigene Texte funktionieren unabhängig davon bereits ab 7.1.
- Alle Objekte innerhalb des konfigurierten Root der Visualisierung müssen
  einen echten Namen haben (kein leerer Name, kein von Symcon selbst
  vergebener Platzhalter wie "Unnamed Object (ID: ...)") - siehe
  [Abschnitt 2](#2-bekannte-einschränkungen) für die genaue Prüfung.

### 4. Software-Installation

* Über den Module Store das 'Simple Locale'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 5. Einrichten der Instanzen in Symcon

 Unter 'Instanz hinzufügen' kann das 'Simple Locale'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Das Formular ist in drei aufklappbare Bereiche gegliedert, damit nicht alle
Felder auf einmal überfrachten:

* **Konfiguration** (standardmäßig aufgeklappt): Grundeinrichtung, unabhängig
  davon, ob Google Translate schon funktioniert.
* **Übersetzung** (standardmäßig eingeklappt, klappt automatisch auf, sobald
  ein gültiger API-Key eine echte Sprachliste geladen hat): alles rund um
  Google Cloud Translate, den Rescan und die Übersetzungstabellen.
* **Lizenz** (nur im Testversion-Build sichtbar; klappt automatisch auf, wenn
  die Testphase abgelaufen ist oder bereits ein Schlüssel eingetragen wurde):
  siehe [Abschnitt 8](#8-lizenz-und-testversion).

**Konfiguration:**

Name                            | Beschreibung
-------------------------------- | ------------------
Aktiv                           | Notaus-Schalter (Standard-Konvention "Instanz aktiv"). Deaktiviert **sofort** jeden Rescan (manuell/automatisch), jede Live-Nachübersetzung (`VM_UPDATE`) und jeden Quellsprachen-Abgleich (siehe unten) - z. B. um ein aufgebrauchtes Tageskontingent oder ein Rate-Limit bei einem Übersetzungsanbieter sofort zu stoppen, ohne auf ein neues Modul-Update warten zu müssen. Bereits vorhandene Übersetzungen bleiben nutzbar, die Sprachumschaltung in der Kachel funktioniert normal weiter - es wird nur nichts NEUES mehr übersetzt. Instanz-Status zeigt währenddessen "Deaktiviert". Default: an.
Kachel-Visualisierung           | Instanz der eingebauten Kachel-Visualisierung (WebFront-Kernmodul, nicht Teil von Simple Locale). **Pflichtfeld** - ihre eigene "Startkategorie" wird automatisch als Root der Visualisierung übernommen (Kategorie im Objektbaum, deren Inhalt - Namen + Werte von String-Variablen - übersetzt wird; sollte nur die Sichtbereich-Kacheln enthalten, nicht die Admin-Oberfläche) und sie liefert zusätzlich die Automations/Favoriten-Übersetzung, siehe eigenen Absatz unten. Ohne Auswahl (oder ohne gültige Startkategorie) bleibt die Instanz im Status "Root der Visualisierung fehlt".
Scan-Sprache                    | Sprache, in der die Objektnamen/-werte ursprünglich gepflegt sind. Dient Google Translate als feste Quellsprache für alle Zielsprachen-Übersetzungen; die Scan-Sprache selbst hat keine eigene Übersetzungsspalte, siehe [Abschnitt 7](#7-visualisierung).
Aktuell aktive Sprache          | Welche Sprache gerade angezeigt wird - normalerweise über die Kachel vom Anwender selbst gesteuert (siehe Abschnitt 7), lässt sich hier aber auch manuell umschalten (inkl. aller sonst nur bei einem Kachel-Wechsel ausgelösten Umbenennungen/Wertänderungen - identisches Verhalten zur Kachel selbst). **Wichtig für die eigene Weiterentwicklung der Visualisierung, siehe Warnung unten.**
Simple-Locale-Symbol in der Kachel anzeigen | Blendet das Simple-Locale-Symbol links neben dem Dropdown aus, falls nicht gewünscht (z. B. bei eigenem Kachel-Design). Bis Build 76 die 🌐-Emoji-Glyphe, ab Build 77 das eigentliche, als Base64-Grafik eingebettete Markensymbol (siehe Abschnitt 7). Standardmäßig an.
Info-Symbol in der Kachel anzeigen | Blendet das ⓘ-Symbol (Erklärung der Einschränkungen, siehe Abschnitt 2) aus. Standardmäßig an.
Eigene Sprachauswahl-Kachel verwenden | Unterdrückt die eingebaute Dropdown-Kachel zugunsten einer selbstgebauten (siehe Abschnitt 7). **Pro-Feature** (`custom_tile`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und die eingebaute Kachel aktiv.
Übersetzungsanbieter (Panel)    | Siehe eigenen Abschnitt unten ("Übersetzungsanbieter: Google/DeepL/kostenfrei") - funktioniert ab Werk ohne jede Eingabe.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen der Visualisierung, 0 = nur manuell über den Button "Visualisierung neu einlesen" (siehe unten). **Pro-Feature** (`auto_rescan`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und der Timer aus, der manuelle Rescan-Button bleibt aber in jeder Edition nutzbar.
Übersetzungen gelöschter Elemente in der Visualisierung entfernen | "Aufräumen" (Build 76, siehe eigenen Absatz unten "Aufräumen: verwaiste Zeilen endgültig entfernen") - entfernt dauerhaft Zeilen, die keinem Objekt in der aktuellen Visualisierung mehr zugeordnet werden können. In jeder Edition nutzbar.

**Quellsprache: pro Zeile individuell änderbar**

Die "Scan-Sprache" oben ist nur die VOREINSTELLUNG, die eine neu gefundene Zeile
beim ersten Scan erhält - jede Zeile in "Objektnamen", "Eigene Texte",
"Beschriftungen", "Automations" und "Begrüßung" trägt zusätzlich eine eigene,
editierbare Spalte **"Quellsprache"** (Pro-Feature `edit_translations`, ohne
dieses Feature nur informativ sichtbar). Das macht auch gemischtsprachige
Installationen sauber abbildbar - z. B. ein Fremdmodul, das seine eigenen
Objektnamen/-werte dauerhaft auf Englisch liefert, während der Rest der
Installation auf Deutsch gescannt wird: die betroffenen Zeilen bekommen einfach
"en" als eigene Quellsprache, unabhängig von der instanzweiten Scan-Sprache.

Wichtiger noch: das beantwortet die Frage "was passiert, wenn sich die
Scan-Sprache zwischen zwei Scans ändert?" Vor dieser Funktion (Build 51 und
früher) galt dafür ausschließlich die Warnung im nächsten Abschnitt (neue
Objekte immer nur bei aktiver Scan-Sprache anlegen) - eine bereits bestehende,
korrekt übersetzte Zeile ließ sich nicht sauber auf eine andere Quellsprache
umstellen. Jetzt genügt es, die "Quellsprache" EINER Zeile im Formular zu
ändern und auf "Übernehmen" zu klicken: alle Übersetzungsspalten dieser Zeile
werden automatisch geleert und **sofort** neu gegen die neue Quellsprache
übersetzt (kein Warten auf den nächsten Rescan/Sprachwechsel nötig) - ist die
gerade aktive Sprache betroffen, wird der neue Wert außerdem direkt live in
die Kachel geschrieben. Rohtext, Objekt-/Wert-Objekt-ID und Pfad bleiben dabei
unangetastet, nur die Übersetzungen selbst und die Quellsprache ändern sich.

**Übersetzungsanbieter: Google/DeepL/kostenfrei**

Simple Locale funktioniert **ab Werk ohne jede Konfiguration**: Ganz ohne eingetragenen API-Key läuft die Übersetzung sofort über einen kostenfreien, account-losen Anbieter ([MyMemory](https://mymemory.translated.net)). Google Cloud Translate und DeepL sind beide **optional** und lassen sich unabhängig voneinander zusätzlich eintragen (eigene Panels "Übersetzungsanbieter" im Konfigurationsformular):

- **Nur kostenfrei** (Auslieferungszustand): 5.000 Zeichen/Tag anonym, mit hinterlegter Kontakt-E-Mail (Feld "Kontakt-E-Mail für den kostenfreien Anbieter") 50.000 Zeichen/Tag. Kein Konto, kein Key.
- **Google und/oder DeepL zusätzlich eingetragen, volle Verkettung** (Pro-Feature `paid_providers`, siehe [Abschnitt 8](#8-lizenz-und-testversion)): bezahlte Anbieter werden VOR dem kostenfreien versucht (Reihenfolge über "Bevorzugter Anbieter" wählbar, falls beide eingetragen sind), beide bezahlten Anbieter kombiniert, falls beide konfiguriert - deutlich großzügigere Freikontingente/Qualität, aber jeweils ein eigenes Konto samt API-Key nötig.
- **Google und/oder DeepL eingetragen, ohne `paid_providers`** (z. B. "Light"-Edition): der kostenfreie Anbieter bleibt immer die primäre Grundausstattung, zusätzlich darf höchstens EIN einzelner bezahlter Anbieter als Rückfall danach greifen (nie beide gleichzeitig verkettet) - welcher, entscheidet "Bevorzugter Anbieter", falls beide eingetragen sind.
- **Ausfallsicher durch Verkettung**: Schlägt ein Anbieter fehl (Tageskontingent erschöpft, Preismodell geändert, Key abgelaufen, Netzwerkfehler), übernimmt automatisch der nächste in der jeweiligen Kette - der kostenfreie Anbieter steht dabei immer als garantiert verfügbares Glied bereit (mit `paid_providers` am Ende, ohne dieses Feature am Anfang). Die Übersetzung funktioniert dadurch strukturell auch dann noch, wenn Google/DeepL komplett ausfallen oder ihr Preismodell ändern sollten.
- **Anbieter gezielt prüfen (Build 61):** Der Button "Übersetzungsanbieter prüfen" ganz unten im Konfigurationsformular schickt eine einzelne Testanfrage direkt an jeden eingerichteten Anbieter (Google/DeepL, falls konfiguriert, sowie immer MyMemory) - am Cache vorbei und unabhängig von einer eventuell laufenden Pause, meldet also auch, ob ein eigentlich pausierter Anbieter inzwischen wieder geht. Eine noch laufende Pause wird dabei automatisch beendet, sobald ein Anbieter wieder erfolgreich antwortet - praktisch z. B. direkt nach einem Kontingent-/Abo-Upgrade beim Anbieter.

Ein Anbieterwechsel (z. B. Google zu DeepL) macht bereits gewählte Zielsprachen ungültig, wenn die neue Sprachliste andere Codes verwendet (Google: klein geschrieben "de"/"en", DeepL: teils groß geschrieben mit Regionsvariante "DE"/"EN-GB") - betroffene Zielsprachen müssen dann neu ausgewählt werden. Der kostenfreie Anbieter hat keinen eigenen Sprachlisten-Endpunkt und nutzt eine eingebaute, rund 25 Sprachen umfassende statische Liste.

Bewusst außerhalb von v1: Google und DeepL gleichzeitig für dieselbe Übersetzung anzufragen, um ihre Freikontingente zu kombinieren - die Kette probiert Anbieter nacheinander, nicht parallel. Der kostenfreie Anbieter (MyMemory) unterstützt zudem keinen Batch-Aufruf (ein Text pro Request, siehe `TranslateChunkFree`) und lehnt einzelne Texte über 500 Byte ab (z. B. vollständige HTMLBox-Widgets als "Eigene Texte") - solche Texte scheitern über diesen Anbieter bewusst früh und werden von der Kette an einen bezahlten Anbieter ohne diese Begrenzung weitergereicht, sofern einer konfiguriert ist.

**Übersetzung:**

Name                            | Beschreibung
-------------------------------- | ------------------
Zielsprachen                    | Sprachen, in die übersetzt werden soll. Auswahl-Optionen kommen ab Werk aus der eingebauten Liste, mit konfiguriertem Google-/DeepL-Key aus deren dynamisch geladener Liste (siehe oben). Ausgegraut, wenn ein bezahlter Anbieter konfiguriert ist, aber noch keine Liste laden konnte, oder wenn das Sprachlimit einer "Spezialversion"-Lizenz erreicht ist (siehe Abschnitt 8). Wichtig: Nach dem Klick auf "Sprachliste aktualisieren" die Instanzkonfiguration einmal schließen und neu öffnen, bevor Häkchen gesetzt werden - sonst kann die Konsole falsche Sprachen speichern.
Objektnamen / Eigene Texte (String-Variablen) / Beschriftungen | Listen der gefundenen Objekte mit Quelltext und je einer Spalte pro Zielsprache. Übersetzungen sind hier direkt editierbar; leere Zellen werden beim nächsten Rescan automatisch übersetzt. "Eigene Texte" enthält ausschließlich String-Variablen und trackt dort ausschließlich den WERT - der Name derselben Variable wird ausschließlich in "Objektnamen" geführt (jedes Objekt bekommt dort ohnehin eine eigene Zeile), keine zweite, separat editierbare Namens-Kopie. "Beschriftungen" siehe [Abschnitt 2](#2-bekannte-einschränkungen) (Fork-Mechanismus). **Hinweis:** Das Konfigurationsformular persistiert extern (per `VM_UPDATE`) automatisch geänderte Texte alle 12 Minuten, wenn es Änderungen gibt, was zu einem Refresh dieses Formulars führt - bitte speichere Deine eigene Arbeit rechtzeitig. Solange etwas ansteht, zeigt das Formular oben einen Hinweis mit der nächsten Refresh-Zeit an (siehe [Abschnitt 2](#2-bekannte-einschränkungen) für die genauen Details, was dabei geschützt ist und was nicht).
Automations                     | Liste der gefundenen Automation-Einträge der oben unter "Kachel-Visualisierung" gewählten Instanz mit Quelltext und je einer Spalte pro Zielsprache - funktioniert genauso wie Objektnamen.
Charts                          | Liste der Legenden-Titel gefundener Chart-Elemente (Symcons eingebautes Chart-Widget) im Root-Baum, je Datenreihe eine Zeile (Schlüssel Chart-Objekt-ID + Variablen-ID) - funktioniert genauso wie Objektnamen. Als Quelltext gilt der im Chart selbst gesetzte Titel, oder - falls das Titel-Feld leer gelassen wurde - ersatzweise der aktuelle Name der zugrunde liegenden Variable (genau das zeigt Symcon in diesem Fall selbst in der Legende an). Nur im Leer-Titel-Fall gilt zusätzlich: steht diese Variable auch als eigenständiges Objekt im Root-Baum (z. B. als eigene Anzeige-Kachel), taucht dafür **keine** Zeile hier auf - diese Variable wird ohnehin über "Objektnamen" übersetzt, Symcon übernimmt diesen Namen automatisch in die Chart-Legende. Ein bewusst gesetzter, eigener Titel erscheint dagegen immer hier, unabhängig davon, ob seine Variable zusätzlich eigenständig im Baum steht.
Begrüßung                       | Übersetzt den Begrüßungstext der Kachel-Visualisierung, unabhängig davon, ob "Show Greeting" gerade "Automatic"/"Static" (freier Text, Feld "Name"/Property `GreetingName`) oder "Variable" (Live-Wert einer String-Variable) ist - beide landen in derselben einen Zeile hier, siehe eigenen Absatz unten. Ein Hinweistext direkt über der Liste zeigt an, welcher Modus gerade aktiv ist. Bei "Show Greeting" = "None" bleibt die Liste leer.

**Wann sollte ein Rescan ausgeführt werden?**

Inhaltstyp        | Neue/verschobene Objekte | Inhaltliche Änderungen
------------------ | ------------------------- | ------------------------
Objektnamen        | Nur per Rescan (manuell/Timer) erkannt. | Ändert sich ein Name selten spontan; falls doch, Zelle im Formular leeren + Rescan.
Eigene Texte (Werte) | Nur per Rescan erkannt. | Automatisch (siehe Abschnitt 1, `VM_UPDATE`) - **kein** Rescan nötig, solange die Scan-Sprache stimmt.
Charts              | Nur per Rescan erkannt. | Ändert sich ein im Chart selbst gesetzter Titel selten spontan; falls doch, Zelle im Formular leeren + Rescan. Kein `VM_UPDATE`, da der Titel eine feste Chart-Konfiguration ist, kein Variablenwert.
Begrüßung           | Nur per Rescan erkannt (auch ein Moduswechsel zwischen "Automatic"/"Static"/"Variable"). | Modus "Variable": automatisch, genau wie Eigene Texte (Werte). Modi "Automatic"/"Static" (freier Text im Feld "Name"): nur per Rescan.
Beschriftungen      | Nur per Rescan erkannt. | **Kein** automatisches Erkennen von Änderungen am zugrunde liegenden Profil/Template - Symcon liefert dafür keine Update-Benachrichtigung. Ändert ein anderes Modul/der Admin die Beschriftungen eines Profils, das eine bereits geforkte Variable nutzt, wird das erst nach manuellem Löschen der betroffenen Original-Import-Zelle + Rescan übernommen.

**Aufräumen: verwaiste Zeilen endgültig entfernen (Build 76)**

Ein Rescan (siehe Tabelle oben) erkennt zwar neue/verschobene Objekte, entfernt
aber **nie** von sich aus eine bereits vorhandene Zeile, auch wenn das
zugehörige Objekt inzwischen gelöscht oder aus der Visualisierung entfernt
wurde - das ist Absicht (siehe `MergeRows`/`MergeEnumerationOptions`/
`MergeAutomationRows`/`MergeChartRows`): eine versehentlich falsche oder
unvollständige "Kachel-Visualisierung"-Auswahl soll niemals bereits
geleistete Übersetzungsarbeit stillschweigend vernichten. Solche verwaisten
Zeilen sammeln sich über die Zeit an und mussten bisher manuell einzeln über
das Papierkorb-Symbol gelöscht werden.

Der Button **"Übersetzungen gelöschter Elemente in der Visualisierung
entfernen"** (unterhalb von "Visualisierung neu einlesen") macht genau das in
einem Schritt: er führt intern denselben frischen Scan wie ein Rescan durch
und löscht anschließend **alle** Zeilen in "Objektnamen", "Eigene Texte",
"Beschriftungen", "Automations" und "Charts", die dabei nicht mehr gefunden
wurden -
unwiederbringlich (regenerieren sich automatisch neu, falls das Objekt später
wieder auftaucht). Ein Popup nach dem Klick meldet, wie viele Zeilen entfernt
wurden. **"Begrüßung" ist bewusst ausgenommen** - anders als die anderen vier
Listen ist das keine aus dem Baum gescannte, wachsende Liste, sondern eine
einzelne, direkt konfigurierte Einstellung (siehe Abschnitt 1).

**Wichtig:** genau wie bei einem Rescan gilt auch hier - die "Kachel-
Visualisierung"-Auswahl oben muss wirklich die vollständige, korrekte
Visualisierung referenzieren, bevor "Aufräumen" geklickt wird. Anders als ein
normaler Rescan (der bei einer falschen Auswahl höchstens *nichts Neues*
findet) LÖSCHT "Aufräumen" bei einer falschen/unvollständigen Auswahl aktiv
noch gültige Übersetzungen. Im Zweifel vorher einmal die betroffenen Listen
durchsehen (die "Pfad"-Spalte hilft dabei) oder ein Backup der Instanz-
Konfiguration ziehen.

> **Nur EINE Simple-Locale-Instanz je Visualisierungsbaum.** Zwei aktive
> Instanzen, die sich denselben Baum oder auch nur einzelne String-Variablen
> teilen, arbeiten gegeneinander: Jede schreibt bei einem Sprachwechsel ihre
> eigene Übersetzung per `IPS_SetName()`/`SetValueString()` in dieselben
> Objekte, und jede hält den Schreibvorgang der anderen für eine externe
> Änderung. Bei "Eigene Texte" ist das besonders heikel, weil deren
> String-Variablen per `VM_UPDATE` live verfolgt werden: Instanz A schreibt
> ihre Übersetzung, Instanz B übernimmt sie als neuen Rohtext (der
> Selbst-Schreib-Schutz greift nur für die EIGENEN Schreibvorgänge, siehe
> `WriteTrackedValueString()`), übersetzt sie erneut und schreibt zurück - was
> wiederum Instanz A auslöst. Ergebnis: Übersetzungen von Übersetzungen, die
> sich mit jeder Runde weiter vom Original entfernen, und ein Dauerfeuer an
> API-Anfragen, das jedes Tageskontingent in kurzer Zeit aufbraucht. Sollen
> mehrere Visualisierungen übersetzt werden, braucht jede Instanz einen
> eigenen, überschneidungsfreien Baum.

**Wenn sich ein Text oder Name im Objektbaum nachträglich ändert**

Ein Rescan aktualisiert den "Original-Import" bereits erfasster Zeilen
**nicht**. Das ist Absicht: nur so bleibt der bei der Ersterfassung
vorgefundene Zustand erhalten, auf den sich die Visualisierung zurückstellen
lässt (siehe `MergeRows()`). Ein Rescan findet also ausschließlich *neue*
Objekte. Was das für eine nachträgliche Änderung bedeutet, hängt davon ab, um
welche Art Zeile es geht:

* **"Eigene Texte" und "Begrüßung" (Modus "Variable")** - hier wird nichts
  gebraucht. Das Modul ist auf diese String-Variablen per `VM_UPDATE`
  registriert, übernimmt einen extern geschriebenen Wert sofort als neuen
  Rohtext, markiert die bisherigen Übersetzungen als veraltet und übersetzt die
  gerade aktive Sprache nach. Alle übrigen Zielsprachen holt es nach, sobald
  jemand auf sie umschaltet.
* **Alle anderen Listen** - "Objektnamen", "Beschriftungen", "Automations",
  "Charts", "Aufzählungen". Diese werden **nicht** live verfolgt. Wird ein
  Objekt im Baum umbenannt oder ein Aufzählungstext geändert, merkt das Modul
  davon nichts, und ein Rescan ändert daran nichts. Der Weg ist: **die
  betroffene Zeile im Konfigurationsformular über das Papierkorb-Symbol
  löschen, dann einen Rescan auslösen** - die Zeile wird dann frisch aus dem
  Objektbaum aufgebaut, mit dem neuen Text als "Original-Import" und leeren
  Übersetzungsspalten, die im selben Durchlauf gefüllt werden.

**Eine Ausnahme, die leicht übersehen wird:** solange die Instanz auf
"Aktiv = aus" steht, ist auch die Live-Verfolgung abgeschaltet (Notaus-Schalter,
siehe Tabelle oben). Änderungen an "Eigene Texte"-Variablen aus dieser Zeit
werden nicht bemerkt - und ein späterer Rescan holt sie aus dem oben genannten
Grund ebenfalls nicht nach. Wer während einer Deaktivierung solche Texte
bearbeitet hat, muss die betroffenen Zeilen danach löschen und neu einlesen
lassen, genau wie bei den nicht verfolgten Listen.

> **Wichtig beim Weiterentwickeln der Visualisierung: immer mit "Aktuell aktive
> Sprache" = Scan-Sprache (bzw. "Original") arbeiten, solange neue Objekte
> hinzugefügt werden.** Ein Rescan übernimmt für ein NEUES Objekt immer dessen
> gerade live vorhandenen Namen als "Original-Import" - er kann nicht wissen,
> ob dieser Name vom Admin frisch in der Scan-Sprache getippt wurde, oder ob
> er nur deshalb so aussieht, weil zuvor auf eine andere aktive Sprache
> umgeschaltet war (jeder Sprachwechsel - auch der manuelle über das
> Auswahlfeld oben - benennt alle bereits erfassten Objekte tatsächlich live
> um, siehe `ApplyLanguage()`). Wird also ein neues Objekt angelegt, während
> z. B. gerade Englisch aktiv ist, hält der Admin dessen Namen zwangsläufig
> auf Englisch - der nächste Rescan verbucht diesen Namen aber als Scan-Sprache
> und übersetzt ihn entsprechend falsch (u. a. auch zurück ins vermeintliche
> Original). Vor dem Anlegen neuer Objekte also immer erst über "Aktuell
> aktive Sprache" zurück auf die Scan-Sprache wechseln (oder gleich durchgehend
> in dieser Ansicht entwickeln) - bereits erfasste, bestehende Objekte sind
> davon nicht betroffen (deren Original-Import bleibt beim ersten Fund
> eingefroren, siehe oben), das betrifft ausschließlich neu hinzukommende.
>
> Für die **Begrüßung** (Modus "Variable") ist dasselbe Risiko inzwischen
> abgesichert: ein Rescan aktualisiert deren Original-Import nur noch dann,
> wenn dabei zuverlässig die Scan-Sprache aktiv ist - ist gerade eine
> Zielsprache aktiv, bleibt die Zeile unverändert stehen, statt die gerade
> live angezeigte Übersetzung fälschlich als neuen Rohtext zu übernehmen
> (auch für die bereits bekannte Zeile, die sonst nicht betroffen wäre). Der
> ohnehin bereits sichere Live-Pfad (`VM_UPDATE`, siehe oben) holt die
> Aktualisierung in diesem Fall automatisch nach, sobald die überwachte
> Variable das nächste Mal tatsächlich extern geschrieben wird.
>
> Diese ganze Warnung betrifft ausschließlich NEU hinzukommende Objekte - für
> bereits erfasste, bestehende Zeilen lässt sich die Quellsprache seit der
> pro-Zeile editierbaren "Quellsprache"-Spalte (siehe oben) jederzeit sauber
> nachträglich ändern, ohne dieses Risiko: eine Änderung der instanzweiten
> Scan-Sprache selbst wirkt sich auf bereits bekannte Zeilen gar nicht erst
> aus (deren eigene Quellsprache bleibt eingefroren, siehe oben) - erst eine
> gezielte, manuelle Änderung der "Quellsprache"-Zelle einer konkreten Zeile
> löst die automatische Neuübersetzung aus.

**Kachel-Visualisierung: Root der Visualisierung, Automations und Favoriten**

Die eingebaute Kachel-Visualisierung (WebFront-Kernmodul von IP-Symcon, unabhängig
von Simple Locale) hat selbst eine "Startkategorie" (intern `BaseID`) sowie zwei
besondere Bereiche, die sich als eigene Kacheln ein-/ausblenden lassen
(Instanzkonfiguration der Kachel-Visualisierung selbst, Abschnitte
"Automations"/"Favorites").

Die unter "Kachel-Visualisierung" ausgewählte Instanz ist die EINZIGE Quelle des
Root der Visualisierung - Simple Locale übernimmt deren "Startkategorie"
automatisch, es gibt kein eigenes, manuell wählbares Root-Feld mehr (bis
Build 23 gab es zusätzlich ein solches Feld als Rückfall). Das verhindert
strukturell, dass versehentlich ein anderer Baum konfiguriert wird als der, den
die Visualisierung tatsächlich anzeigt. Ohne ausgewählte Instanz (oder falls
deren Startkategorie leer ist) bleibt die Instanz im Status "Root der
Visualisierung fehlt" und übersetzt nichts.

- **Automations**: jede Zeile verknüpft ein Skript/Ereignis mit einem frei
  vergebenen Anzeigenamen (z. B. "Gehen", "Kommen", "Schlafen") und einem Icon -
  dieser Name lebt komplett unabhängig vom Root-Baum, ähnlich einer Verknüpfung
  mit eigenem Namensfeld. Simple Locale übersetzt ihn, wenn die Instanz oben
  ausgewählt ist - der Rescan-Button liest dann zusätzlich deren
  `Automations`-Property ein und schreibt beim Sprachwechsel die übersetzten
  Namen dort zurück (Icon und Verknüpfung bleiben dabei unangetastet).
- **Favoriten**: zeigen ausschließlich den echten Namen des verlinkten Objekts an,
  keine eigene Namens-Überschreibung. Liegt das favorisierte Objekt innerhalb des
  automatisch übernommenen Root der Visualisierung, wird es also ohnehin bereits
  automatisch mitübersetzt - keine zusätzliche Konfiguration nötig. Liegt es
  außerhalb (kommt vor, ist aber nicht garantiert), wird es zusätzlich erfasst,
  sofern oben dieselbe Instanz ausgewählt ist: der Rescan-Button liest dann
  zusätzlich deren `Favorites`-Property ein und ergänzt jedes noch nicht erfasste
  Objekt als eigene Zeile in "Objektnamen" (Pfad "Favoriten").
- **Begrüßung**: die Kachel-Visualisierung kann optional links oben einen
  Begrüßungstext anzeigen ("Show Greeting" in deren Instanzkonfiguration, vier
  Modi):
  - **None**: kein Begrüßungstext - nichts zu tun.
  - **Automatic**: zeigt eine tageszeitabhängige Anrede ("Good Morning"/"Good
    Afternoon"/"Good Evening" etc.) VOR dem Feld "Name" (Property
    `GreetingName`). Die Anrede selbst wird laut Test rein clientseitig anhand
    der Spracheinstellung des jeweiligen **Besucher-Browsers** erzeugt - völlig
    unabhängig von der in Simple Locale aktiven Sprache - und lässt sich daher
    grundsätzlich nicht beeinflussen, siehe
    [Abschnitt 2](#2-bekannte-einschränkungen). Das Feld "Name" selbst wird
    aber wie unten beschrieben übersetzt.
  - **Static**: zeigt ausschließlich das Feld "Name" (`GreetingName`), keine
    Anrede davor.
  - **Variable**: zeigt den Live-Wert einer echten String-Variable (Property
    `GreetingVariableID`).

  Für die Modi **Automatic** und **Static** trägt `GreetingName` einen frei
  vergebenen Text, der komplett unabhängig vom Root-Baum lebt - genau wie bei
  Automations. Für den Modus **Variable** ist stattdessen der aktuelle Wert
  der verlinkten Variable maßgeblich. **Alle drei Modi landen in derselben
  einen Zeile der Liste "Begrüßung"** im Abschnitt "Übersetzung" (nicht in
  "Eigene Texte") - ein Hinweistext direkt über der Liste zeigt an, welcher
  Modus gerade aktiv ist, damit klar bleibt, was die eine Zeile gerade
  bedeutet. Der Rescan-Button liest die passende Quelle ein (Property bzw.
  Live-Wert der Variable) und schreibt beim Sprachwechsel den übersetzten
  Text zurück - bei Modus **Variable** per `SetValueString` auf die Variable,
  exakt wie bei "Eigene Texte" (inkl. automatischer Live-Nachübersetzung bei
  externen Änderungen der Variable, siehe `VM_UPDATE`-Zeile in der
  Rescan-Tabelle oben). Liegt die Variable zufällig selbst innerhalb des Root
  der Visualisierung (eigener Name/Ident dort), hat der normale Baum-Scan
  Vorrang und sie erscheint stattdessen ganz gewöhnlich unter "Eigene Texte"
  - verhindert zwei unabhängige, gleichzeitig schreibende
  Übersetzungs-Zeilen für dieselbe Variable.

**Lizenz:**

Name                            | Beschreibung
-------------------------------- | ------------------
Lizenzschlüssel                 | Nur in der Testversion sichtbar/relevant, siehe [Abschnitt 8](#8-lizenz-und-testversion). Nach Eingabe über den Button "Lizenz aktivieren" (siehe unten) prüfen lassen.

Unterhalb der drei Bereiche liegen die Aktions-Buttons "Lizenz aktivieren" und
"Visualisierung neu einlesen und fehlende Übersetzungen ergänzen" sowie, ganz
unten, zwei aufklappbare Bereiche: "Produktinformationen" (Logos, Link zum
GitHub-Repository, kurze Lizenzhinweise) und "Nutzungsbedingungen" (Verweis auf
die vollständigen Nutzungs-/Lizenzbedingungen im Shop - ein Klick auf "Lizenz
aktivieren" gilt als Bestätigung, dass diese gelesen und akzeptiert wurden;
beim Kauf im Shop selbst werden sie zusätzlich per Checkbox vor
Zahlungsabschluss abgefragt, siehe [Abschnitt 8](#8-lizenz-und-testversion)).

### 6. Statusvariablen und Profile

Die Statuskategorien werden automatisch angelegt. Das Löschen kann zu
Fehlfunktionen führen. Simple Locale legt bewusst keine eigenen Symcon-
Variablen oder -Profile für die Sprachsteuerung an (siehe Abschnitt 7) - der
gesamte Zustand steckt in Instanz-Properties.

### 7. Visualisierung

Im WebFront-Baum die Zielkategorie markieren, per Rechtsklick "Instanz
erstellen" wählen und nach "Simple Locale" suchen (Drag & Drop einer Instanz
in WebFront funktioniert in aktuellen Symcon-Versionen nicht) - sie liefert
eine eigene, kompakte Kachel mit `<select>`-Dropdown
(Weltkugel-Symbol statt Text-Label "Sprache", damit keine Sprachen gemischt
angezeigt werden) und löst beim Auswählen direkt den Sprachwechsel aus. Die
aktuell aktive Sprache wird als Instanz-Property gespeichert (kein
Symcon-Variablenprofil - das wäre global über alle Instanzen der Installation
hinweg geteilt und würde sich bei mehreren Instanzen gegenseitig überschreiben).

Das Dropdown bietet immer folgende Sprachen zur Auswahl: die Scan-Sprache und
alle konfigurierten Zielsprachen (Flagge, live übersetzter Name, Google-Code,
z. B. "🇬🇧 English - en"). Intern gibt es zusätzlich die Pseudo-Sprache
"Original" (liefert den rohen, unangetasteten Text, exakt so wie er im
Objektbaum vorgefunden wurde, Tippfehler inklusive) - im Dropdown erscheint
dafür aber **kein separater Eintrag** "Original (unbearbeitet)": Google Cloud
Translate lehnt eine Übersetzung von einer Sprache in sich selbst ohnehin ab
(HTTP 400 "Bad language pair"), es gibt für die Scan-Sprache also gar keine
eigene, separat übersetzte Spalte - ihr Inhalt ist identisch mit dem
Rohtext. Der Scan-Sprache-Eintrag im Dropdown liefert deshalb technisch
"Original", zeigt aber ganz normal die Scan-Sprache selbst an (z. B.
"🇩🇪 Deutsch - de") statt einer eigenen "Original"-Beschriftung.

Die Einträge sind alphabetisch nach dem angezeigten Namen sortiert (nicht
nach Sprachcode), und zwar nach den Sortierregeln der jeweils aktiven
Sprache (z. B. korrekte Einordnung von Umlauten/Akzenten) - dafür wird,
falls auf dem Symcon-Server installiert, PHPs `intl`-Erweiterung (Klasse
`Collator`) genutzt; ist sie nicht verfügbar, greift eine einfache
alphabetische Sortierung ohne sprachspezifische Sonderregeln.

Ein Info-Symbol (ⓘ) neben dem Dropdown öffnet auf Klick einen nativen
Browser-Dialog (`alert()`) mit den in [Abschnitt 2](#2-bekannte-einschränkungen)
beschriebenen Einschränkungen, ebenfalls live in der jeweils aktiven
Sprache. Bewusst kein eigenes HTML-Popup: die Kachel läuft in einem
eigenen iframe und eigene Overlays können dessen Grenzen nicht überschreiten -
ein Browser-Dialog dagegen schon.

Für eigene HTMLBox-Popups oder Hinweise außerhalb der live umbenannten
Objekte liefert `IPSSL_TranslateText()` den Text in der aktuell aktiven
Sprache.

**Eigene Sprachauswahl-Kachel (Pro-Feature `custom_tile`):** Es gibt zwei
unabhängige Wege, das Aussehen der Sprachauswahl anzupassen - beide
benötigen dasselbe Pro-Feature `custom_tile` und sind ohne Lizenz **hart
gesperrt** (nicht nur ausgegraut im Formular, siehe unten):

> Weg 1 unten richtet sich an Personen mit HTML-Kenntnissen und einem
> Grundverständnis der IP-Symcon-Kachel-Mechanik (`requestAction()`,
> `handleMessage()`) - er ist bewusst kein reiner Klick-Editor, sondern
> echter, selbst zu pflegender HTML-Code.

1. **Eigener HTML-Code für die eingebaute Kachel (empfohlen für die meisten
   Fälle).** Aktiviere dazu "Eigene Sprachauswahl-Kachel verwenden" im
   Konfigurationsformular - darunter erscheint der Button "Eigenen
   Kachel-HTML-Code bearbeiten" (nur sichtbar, solange die Checkbox aktiv
   ist, damit das Formular sonst nicht unnötig überladen wirkt). Er
   erscheint nicht sofort beim Setzen des Häkchens, sondern erst nach einem
   Klick auf "Übernehmen" (die Konsole baut das Formular dabei live neu auf -
   ein Schließen/Neuöffnen der Instanzkonfiguration ist hierfür anders als
   beim Sprachlisten-Hinweis weiter oben nicht nötig). **Diese Instanz
   liefert die Kachel weiterhin selbst aus** (`GetVisualizationTile()`) -
   nur eben mit dem editierten HTML/CSS/JS statt der eingebauten Optik.

   Das Bearbeiten-Fenster enthält zwei getrennte Felder:

   - **"HTML-Code"** - der äußere Rahmen (Layout/CSS), vorbefüllt mit einer
     1:1-Kopie der eingebauten `module.html`. Muss zwei Platzhalter
     enthalten, die bei jedem Laden der Kachel automatisch ersetzt werden:
     - `<!--WRAPPER_ID-->` - eine pro Instanz eindeutige DOM-ID, verhindert
       ID-Kollisionen, falls mehrere Kacheln im selben DOM landen. Kommt in
       der Standardvorlage **zweimal** vor (als `id`-Attribut des
       Wrapper-`<div>` UND im `getElementById(...)`-Aufruf im `<script>`) -
       beide Stellen müssen exakt gleich bleiben (einfach den Platzhalter
       selbst nicht anfassen, dann passt das automatisch).
     - `<!--LANGUAGE_SELECT-->` - wird durch den Inhalt des zweiten Felds
       (siehe unten) ersetzt.

     Beispiel, wie `<!--LANGUAGE_SELECT-->` standardmäßig gefüllt wird (rein
     zur Orientierung - dieser Block wird automatisch erzeugt, siehe
     `BuildLanguageSelectHtml()`, solange das zweite Feld unten leer bleibt):
     ```html
     <div class="ipssl-select-row">
       <span class="ipssl-globe" aria-hidden="true"><img src="data:image/png;base64,..." alt=""></span>
       <select onchange="requestAction('Language', this.value);">
         <option value="ORIGINAL_IMPORT">Deutsch</option>
         <option value="en" selected>English</option>
       </select>
       <span class="ipssl-info-icon" aria-hidden="true" onclick="alert('...');">ⓘ</span>
     </div>
     <!-- + roter Testphase-Hinweis, nur solange ungelizenziert und Testphase läuft -->
     ```
     Das `<img>` (Build 77) ist das Simple-Locale-Symbol
     (`libs/assets/module_icon_48.png`), als Base64-Data-URI eingebettet -
     kein öffentlicher Pfad/Webhook nötig. Für eine eigene Kachel kann hier
     stattdessen jedes beliebige eigene Icon/Emoji stehen, die CSS-Klasse
     `ipssl-globe` (Name aus historischen Gründen unverändert) liefert
     bereits einen passenden 32×32px-Kreis als Container.

     Zusätzlich zu den beiden oben genannten PFLICHT-Platzhaltern gibt es
     zwei OPTIONALE Platzhalter für die in [Abschnitt 2](#2-bekannte-einschränkungen)
     beschriebene Nutzungsstatistik: `<!--COUNT_TRANSLATIONS-->` und
     `<!--COUNT_SIGNES-->` werden, falls im HTML vorhanden, durch die
     aktuelle durchschnittliche Anzahl an Übersetzungsanfragen bzw.
     übersetzten Zeichen pro Stunde ersetzt - jeweils als reine gerundete
     Ganzzahl ohne Einheit (z. B. "30" bzw. "500"); die passende
     Beschriftung ("Übersetzungen/h", "Zeichen/h" o. ä.) ergänzt man selbst
     im umgebenden HTML. Beide funktionieren unabhängig vom eingebauten
     Toggle "Übersetzungsstatistik in der Kachel anzeigen" (der betrifft nur
     die eingebaute Standard-Kachel) und sowohl im "HTML-Code"-Feld als auch
     im "Sprachauswahl-HTML-Code"-Feld weiter unten. Kommt keiner der beiden
     Platzhalter im HTML vor, entsteht kein zusätzlicher Aufwand. Aktualisiert
     wird alle 10 Minuten über denselben `PushVisualizationUpdate()`-
     Mechanismus, der auch den `REFRESH`-Payload weiter unten auslöst - nie
     über einen Formular-Reload.

     Seit Build 61 gibt es dazu zwei weitere, ebenfalls optionale Platzhalter:
     `<!--COUNT_CACHE_TRANSLATIONS-->` und `<!--COUNT_CACHE_SIGNES-->` liefern
     die reine Gesamtzahl der seit Inbetriebnahme durch den Übersetzungs-Cache
     eingesparten Anfragen bzw. Zeichen (ebenfalls als reine Ganzzahl ohne
     Einheit, aber - anders als die beiden oben - eine Gesamtsumme, keine
     Pro-Stunde-Rate). Gelten dieselben Regeln wie für
     `<!--COUNT_TRANSLATIONS-->`/`<!--COUNT_SIGNES-->`: unabhängig vom Toggle,
     in beiden Feldern nutzbar, kein zusätzlicher Aufwand, falls ungenutzt.

   - **"Sprachauswahl-HTML-Code"** - ersetzt `<!--LANGUAGE_SELECT-->`.
     Standardmäßig **vorbefüllt mit einem funktionierenden Beispiel** (zwei
     Flaggen statt Dropdown für Deutsch/Englisch), damit direkt nach dem
     Aktivieren etwas Sichtbares/Funktionierendes in der Kachel steht:
     ```html
     <div style="display:flex; align-items:center; gap:10px;">
         <span onclick="requestAction('Language', 'ORIGINAL_IMPORT');" style="cursor:pointer; font-size:24px;" title="Deutsch">🇩🇪</span>
         <span onclick="requestAction('Language', 'en');" style="cursor:pointer; font-size:24px;" title="English">🇬🇧</span>
     </div>
     ```
     `requestAction('Language', '<Code>')` ist der eigentliche Mechanismus -
     die von Symcon in jede Kachel injizierte JS-Funktion, die einen
     Sprachwechsel auslöst; sie ist an keine bestimmte HTML-Struktur
     gebunden (kein `<select>` nötig - jedes klickbare Element reicht).
     `<Code>` ist entweder ein echter, konfigurierter Zielsprachcode (z. B.
     `en`, `fr`) oder die interne Pseudo-Sprache `ORIGINAL_IMPORT`
     (unbearbeiteter Rohtext in der Scan-Sprache).

     **Bewusst einfach gehalten, keine Sprachenliste/Wiederholungs-Vorlage:**
     dieses Feld wird 1:1 verwendet, ohne Kenntnis der tatsächlich
     konfigurierten Zielsprachen. Kommen später weitere Zielsprachen dazu
     oder fallen welche weg, bleibt dieser Code unverändert - er muss dann
     manuell angepasst werden (weitere `<span>`s ergänzen/entfernen). Wird
     das Feld komplett geleert, greift stattdessen wieder automatisch die
     eingebaute, live aus den tatsächlich konfigurierten Zielsprachen
     erzeugte Dropdown-Sprachauswahl (siehe Beispiel oben) - für beliebig
     viele Sprachen ohne Pflegeaufwand, nur eben ohne Flaggen-Optik.

   Zusätzlich ruft Symcon bei jeder `UpdateVisualizationValue()`-Aktualisierung
   (siehe `PushVisualizationUpdate()`/`PushTrialExpiredAlert()`) eine globale
   JS-Funktion `handleMessage(data)` in der Kachel auf - die Standardvorlage
   im "HTML-Code"-Feld definiert sie bereits (verarbeitet
   `{"action":"REFRESH", ...}` fürs Live-Nachziehen eines Sprachwechsels in
   anderen offenen Tabs/Geräten sowie `{"action":"ALERT", ...}` für den
   Testphase-abgelaufen-Hinweis). Der `REFRESH`-Payload enthält dabei IMMER
   den aktuellen Inhalt des Felds "Sprachauswahl-HTML-Code" (bzw. die
   automatisch erzeugte Dropdown-Auswahl, falls dieses Feld leer ist) -
   niemals mehr fest die eingebaute `<select>`-Zeile, egal was im
   "HTML-Code"-Feld steht. Wird `handleMessage()` entfernt oder umbenannt,
   funktioniert die Kachel beim ersten Laden weiterhin normal - nur diese
   beiden Live-Aktualisierungen bleiben dann stumm, ohne Fehlermeldung. Wird
   das Feld "HTML-Code" komplett geleert, greift automatisch derselbe
   eingebaute HTML-Code wie ohne aktiviertes Feature (kein Absturz, keine
   leere Kachel).

2. **Komplett eigenständige, separat gebaute Kachel** (z. B. eine eigene
   HTMLBox-Instanz, die gar nicht über `GetVisualizationTile()` dieser
   Instanz läuft) - dafür zwei Befehle:
   - `IPSSL_GetAvailableLanguages(int $InstanzID): string` - liefert die
     wählbaren Sprachen als JSON-Array `[{code, name, current}, ...]`, live
     in die aktuell aktive Sprache übersetzt und alphabetisch sortiert -
     exakt dieselbe Liste wie im eingebauten Dropdown. `code` ist entweder
     ein echter Sprachcode oder die interne Pseudo-Sprache
     `ORIGINAL_IMPORT` (unbearbeiteter Rohtext, siehe oben).
   - `IPSSL_SetLanguage(int $InstanzID, string $Sprachcode): void` -
     wechselt die aktive Sprache, mit derselben Logik wie ein Klick im
     eingebauten Dropdown (Testphase-/Rate-Limit-Prüfung inklusive).

Ohne das Feature `custom_tile` bleiben die Formularfelder aus Weg 1
ausgegraut UND die eingebaute Kachel aktiv (unabhängig vom gespeicherten
Wert), UND die beiden Befehle aus Weg 2 werfen bei jedem Aufruf eine
Exception, statt einfach nichts zu tun - eine selbstgebaute Kachel ließe
sich sonst komplett kostenlos an der Lizenzprüfung vorbei realisieren, siehe
[Abschnitt 8](#8-lizenz-und-testversion).

### 8. Lizenz und Testversion

Simple Locale gibt es als Testversion (aktuell installierte Variante) und als
Vollversion:

* **Testversion:** voller Funktionsumfang, aber auf 5 bewusst wenig
  praxisrelevante Sprachen begrenzt (Isländisch, Walisisch, Zulu, Maori,
  Latein) - genug, um den kompletten Mechanismus zu testen, ohne die in der
  Praxis benötigten Sprachen vorwegzunehmen. Die Testphase beginnt mit der
  ersten gespeicherten Einrichtung der Instanz und läuft 30 Tage; die
  verbleibende Zeit bzw. das Ablaufdatum steht direkt im
  Konfigurationsformular ("Testversion - läuft ab am ..."). Nach Ablauf
  schwenkt die Kachel automatisch auf die unbearbeiteten Original-Importe
  zurück (statt eine irgendwann eingefrorene, ggf. unvollständige Übersetzung
  dauerhaft stehen zu lassen), ein weiterer Rescan ist blockiert. Versucht ein
  Anwender trotzdem, über das Dropdown die Sprache zu wechseln, bleibt es beim
  Original-Text und ein Hinweis-Popup (live in die gewünschte Sprache
  übersetzt) verweist auf den Lizenzerwerb - bis ein gültiger
  Lizenzschlüssel aktiviert wurde.
* **Vollversion:** keine Sprach- oder Zeitbeschränkung. Freigeschaltet über
  einen Lizenzschlüssel im Konfigurationsformular (Feld "Lizenzschlüssel" +
  Button "Lizenz aktivieren"). Ein Lizenzschlüssel deckt sowohl einen
  Einmalkauf (läuft nie ab) als auch ein Abo (läuft zu einem festen Zeitpunkt
  ab, sofern nicht verlängert) ab - welche Variante angeboten wird, steht zum
  Zeitpunkt dieses Dokuments noch nicht fest. Der Schlüssel wird komplett
  offline geprüft (signiert, keine Internetverbindung zur Prüfung nötig).

**Zeitlich begrenzte Marketing-Aktionen:** Zusätzlich zu den 5 dauerhaft
kostenfreien Testversion-Sprachen können für alle Installationen gleichzeitig
weitere Sprachen für einen festgelegten Zeitraum kostenfrei freigeschaltet
werden (z. B. "die Sprachen aller teilnehmenden Nationen sind während der
Fußball-Weltmeisterschaft kostenfrei"). Das gilt sowohl für noch laufende als
auch für bereits abgelaufene Testphasen einzelner Instanzen - ein netter
Anlass, nochmal vorbeizuschauen. Läuft die Aktion ab, ohne dass die
betroffenen Zeilen zuvor tatsächlich übersetzt wurden, zeigt die Kachel für
diese Sprache einfach den unübersetzten Original-Text (kein Absturz, keine
leere Anzeige). Konfiguriert wird das nicht im Formular, sondern bewusst fest
im Modul-Code (`PROMOTIONAL_LANGUAGE_CAMPAIGNS` in `module.php`), damit jede
Installation die Aktion automatisch mit dem nächsten Update mitbekommt.

**"Spezialversionen" mit begrenzter Sprachanzahl:** Der Lizenzschlüssel trägt
neben Typ und Ablaufdatum auch ein `languageLimit`-Feld (0 = unbegrenzt, N =
maximal N frei wählbare Zielsprachen). Damit lassen sich günstigere Varianten
verkaufen, z. B. eine Rabattaktion "kaufe Simple Locale mit einer Sprache
deiner Wahl für 50 % Rabatt - nur diese Woche, zum Tag der Deutschen
Einheit!". Anders als bei den Testversion-Sprachen ist die Sprache dabei frei
wählbar (nicht auf eine feste Liste beschränkt) - nur die Anzahl ist
gedeckelt. Ist das Limit erreicht, wird die Zielsprachen-Liste im Formular
ausgegraut (wie bei fehlendem API-Key); zusätzlich kappt jedes "Übernehmen"
serverseitig auf die ersten N bereits konfigurierten Sprachen, falls z. B.
nach Ablauf einer befristeten Lizenz gegen eine mit kleinerem Limit
ausgetauscht wird. Wie beim Zeitraum von Marketing-Aktionen ist auch der
eigentliche Rabatt-Verkaufsprozess (Preis, Zeitfenster, Bezahlung) nicht Teil
des Moduls - das Modul prüft nur den fertig ausgestellten Schlüssel.

**Promo-Lizenzen mit gezielter Sprachbindung:** Zusätzlich zu `languageLimit`
kann ein Lizenzschlüssel ein `allowedLanguages`-Feld tragen (Liste von
Sprachcodes, leer = keine Einschränkung). Damit lassen sich Aktionen mit
festen statt frei wählbaren Sprachen abbilden, z. B. "Finnisch - die Sprache
des Weihnachtsmanns - kostenfrei zu Nikolaus" (`allowedLanguages: ["fi"]`)
oder "alle neun Nachbarländer zum Tag der Deutschen Einheit"
(`allowedLanguages: [9 Ländercodes]`, kombiniert mit `languageLimit: 0` für
die Standard- bzw. `languageLimit: 1` für die "Spezialversion"-Variante
derselben Aktion - Kunde wählt dann 1 Sprache frei, aber nur aus den 9
Nachbarländern). Beide Felder sind unabhängig kombinierbar: `allowedLanguages`
regelt WELCHE Sprachcodes wählbar sind, `languageLimit` WIE VIELE gleichzeitig.
Durchgesetzt an derselben Stelle wie das Sprachlimit (Formular-Dropdown zeigt
nur erlaubte Sprachen, `EnforceLicensedLanguageLimit` entfernt serverseitig
alles außerhalb der Liste).

**Pro-Feature-Flags:** Ein `features`-Feld (Liste von Flag-Namen, leer =
Standard-Tier) schaltet zusätzliche Fähigkeiten frei - aktuell:

- `edit_translations`: erlaubt das manuelle Korrigieren einzelner
  Übersetzungszellen in den Listen "Objektnamen"/"Eigene Texte"/"Enum-
  Beschriftungen" (ohne das Flag sind diese Spalten rein lesend).
- `auto_rescan`: schaltet den Timer-gesteuerten automatischen Rescan frei
  (Property "Automatischer Rescan (Minuten)"). Ohne das Flag bleibt das Feld
  ausgegraut und der Timer aus - der manuelle Rescan-Button ("Baum neu
  einlesen") ist davon unabhängig und in jeder Edition nutzbar.
- `paid_providers`: schaltet die VOLLE Anbieter-Verkettung frei - beide
  bezahlten Anbieter kombiniert (falls beide konfiguriert), bezahlte
  Anbieter VOR dem kostenfreien versucht, Reihenfolge unter den bezahlten
  frei wählbar (siehe "Übersetzungsanbieter" oben). Ohne dieses Flag (z. B.
  "Light"-Edition) bleibt der kostenfreie Anbieter immer die primäre,
  garantierte Grundausstattung - zusätzlich darf höchstens EIN einzelner
  bezahlter Anbieter als Rückfall danach greifen, nie beide gleichzeitig
  verkettet. Sind beide Keys eingetragen, entscheidet "Bevorzugter
  Anbieter", welcher der beiden genutzt wird; ist nur einer eingetragen,
  wird automatisch dieser eine verwendet. Kette also z. B. `['free',
  'google']` statt `['google', 'deepl', 'free']` bei voller Freischaltung.
  Kein eingetragener Key geht dabei je verloren - ein Upgrade schaltet
  sofort die volle Verkettung frei, ohne dass etwas neu eingegeben werden
  müsste.
- `unlimited_language_switch`: hebt ein sonst geltendes Limit von einem
  Sprachwechsel pro rollierendem 24h-Fenster auf. Ein wiederholter Wechsel
  zur bereits aktiven Sprache oder zurück zur Scan-Sprache/Original zählt nie
  als Wechsel und ist immer erlaubt, auch ohne dieses Flag - nur ein
  tatsächlicher Wechsel zu einer neuen Sprache innerhalb von 24h nach dem
  letzten wird per Hinweis-Popup verweigert.
- `custom_tile`: schaltet beide Wege zur Anpassung der Sprachauswahl-Kachel
  frei (siehe [Abschnitt 7](#7-visualisierung)) - den editierbaren
  Kachel-HTML-Code (Property "Eigene Sprachauswahl-Kachel verwenden" +
  Button "Eigenen Kachel-HTML-Code bearbeiten") UND die Befehle
  `IPSSL_GetAvailableLanguages`/`IPSSL_SetLanguage` in
  [Abschnitt 9](#9-php-befehlsreferenz) für eine komplett eigenständige
  Kachel. Ohne dieses Flag bleibt der Button ausgegraut (samt Hinweis "Pro
  Edition erforderlich"), die eingebaute Kachel bleibt immer aktiv, und die
  beiden Befehle werfen bei jedem Aufruf eine Exception (hart durchgesetzt,
  nicht nur ausgegraut - sonst ließe sich die Sperre per eigenem Skript
  komplett umgehen).

Während der Testphase selbst bleiben alle Features bewusst immer erlaubt,
damit der komplette Mechanismus vor dem Kauf ausprobierbar ist - die Sperre
gilt nur für Vollversion-Lizenzen ohne das jeweilige Flag. Wie bei
`allowedLanguages` einfach als weiteres Feld im selben Payload kombinierbar.

**Erkennung von Lizenzmissbrauch:** Bei jeder Aktivierung (Button "Lizenz
aktivieren" oder einfach nur "Übernehmen" mit einem neuen gültigen Schlüssel)
wird lokal protokolliert, welcher `IPS_GetLicensee()` (die beim Kauf von
Symcon selbst hinterlegte E-Mail-Adresse, eindeutig pro Symcon-Installation)
mit welchem Lizenzschlüssel aktiviert wurde. Taucht derselbe Schlüssel mit
mehreren unterschiedlichen Licensee-Adressen auf, ist das ein Hinweis auf
Weiterverkauf/Weitergabe (z. B. ein Schlüssel, der als "gebraucht" mehrfach
bei Ebay verkauft wird). Die Konstante `LICENSE_ACTIVATION_REPORT_URL` in
`module.php` zeigt auf einen echten Meldeserver-Endpoint (siehe
Synergetix-Website-Repo, `shop/license-activation-report.php`) - jede
Aktivierung wird zusätzlich dorthin gemeldet (Lizenzschlüssel-Hash statt
Klartext-Schlüssel, plus Licensee und Zeitpunkt); lokal (Debug-Log der
Instanz) wird trotzdem immer protokolliert.
**Wichtig:** `IPS_GetLicensee()` liefert eine echte, personenbezogene
E-Mail-Adresse - diese Erhebung/Übermittlung muss in den eigenen
Lizenzbedingungen/Datenschutzhinweisen offengelegt sein.

**Upgrade-Lizenzen und der Blockier-Mechanismus:** Kunden können eine
bestehende Lizenz gegen Aufpreis auf eine höherwertige Edition upgraden
(siehe Synergetix-Website-Repo, `shop/upgrade.php` +
`includes/products.php` `SLIPS_UPGRADE_PRODUCTS`) - technisch wird dabei
einfach ein komplett neuer, vollwertiger Schlüssel für die Zieledition
ausgestellt, keine "Diff"-Lizenz. Der alte Schlüssel bleibt kryptographisch
weiterhin gültig (siehe oben: die Signaturprüfung ist bewusst rein offline,
ein echtes serverseitiges Sperren würde diese Offline-Fähigkeit aufgeben) -
stattdessen merkt sich der Shop serverseitig, dass der alte Schlüssel
bereits eingetauscht wurde.

Dieser Zustand wird dem Modul NICHT laufend, sondern nur **beim tatsächlichen
Ändern** des eingetragenen Schlüssels mitgeteilt: die ohnehin schon an
`LICENSE_ACTIVATION_REPORT_URL` gemeldete Aktivierung (siehe oben) bekommt
dabei als Antwort `{"blocked": true}`, falls genau dieser Schlüssel bereits
upgegradet wurde. Ein nicht erreichbarer Meldeserver blockiert dabei nie
die Aktivierung selbst ("fail open", wie schon beim reinen Melden). Ein
Klick auf "Lizenz aktivieren" fragt für einen bereits als geblockt
bekannten Schlüssel jedes Mal erneut online nach (z. B. um ein
serverseitiges Entsperren zu bemerken, siehe Synergetix-Website-Repo,
`shop/admin/activations.php`) - alle anderen Aktivierungspfade (z. B.
"Übernehmen" mit unverändertem Schlüssel) fragen dagegen nur an, wenn sich
der eingetragene Schlüssel seit der letzten Prüfung geändert hat (Vergleich
gegen `attributeLastCheckedLicenseKeyHash`, NICHT gegen den kompletten
Aktivierungsverlauf) - das schließt insbesondere die Lücke, dass sich ein
bereits einmal aktivierter, inzwischen z. B. per Upgrade verbrauchter
Schlüssel durch simples Zurückwechseln beliebig oft wieder eintragen ließe,
ohne dass der Server je erneut gefragt würde, ob er inzwischen geblockt
wurde.

Ist ein Schlüssel als geblockt bekannt, wird er **nicht hart abgelehnt**,
sondern wie ein fehlender Lizenzschlüssel behandelt: die Testphase dieser
Instanz startet dabei automatisch neu (volle 30 Tage, voller
Funktionsumfang inkl. aller Pro-Features, unabhängig davon, was der
geblockte Schlüssel tatsächlich abdeckte) - genug Zeit, in Ruhe einen
gültigen Schlüssel einzutragen, ohne dass die Kachel sofort auf die
unbearbeiteten Original-Texte zurückfällt. Ein Hinweis-Popup informiert
beim Aktivieren entsprechend.

**Tägliche Statusprüfung, Widerruf und Ablaufverlängerung ohne neuen
Schlüssel:** Zusätzlich zur einmaligen Aktivierungsmeldung oben fragt das
Modul **einmal täglich** (unabhängig davon, ob sich der eingetragene
Schlüssel geändert hat) beim selben Meldeserver-Endpoint nach, ob die
Lizenz noch aktiv ist (`CheckLicenseStatus()`/`PerformDailyLicenseCheck()`).
Das schließt eine Lücke des reinen Aktivierungs-Checks: ohne diesen
täglichen Timer würde ein Widerruf/eine Rückerstattung (siehe
Synergetix-Website-Repo, `shop/admin/order.php`, Checkbox "Aktiv") nie bei
einer bereits laufenden Installation ankommen, solange der Admin dort
nichts am Konfigurationsformular ändert.

Zwei mögliche Antworten, beide **eigenständig** neben dem bestehenden
`{"blocked": true}`:

- `{"revoked": true}` - der Admin hat die Lizenz im Shop deaktiviert (z. B.
  Widerruf). Anders als "blocked" oben wird dabei **keine** frische
  Testphase gewährt - der Schlüssel bleibt einfach ungültig (`valid =>
  false, revoked => true` in `GetLicenseInfo()`), fällt aber auf dieselbe
  bereits bestehende "Testphase abgelaufen"-Darstellung zurück wie jeder
  andere ungültige Schlüssel auch (kein eigenes Gast-Popup nötig).
- `{"active": true, "expiresAt": <Unix-Timestamp>}` - Bestätigung plus das
  aktuell effektive Ablaufdatum laut Shop. Dieser Wert **überschreibt** das
  im Schlüssel selbst signierte `expiresAt` vollständig (siehe
  `attributeLicenseExpiresAtOverride`) - ein Admin kann ein Abo damit
  verlängern ODER verkürzen, ohne einen neuen signierten Schlüssel
  auszustellen und zuzusenden. Der Override gilt nur für genau den
  Schlüssel, für den er zuletzt gemeldet wurde (Hash-Vergleich) - ein
  später eingetragener anderer Schlüssel erbt ihn nicht.

**Fail open, wie überall bei diesem Meldeserver:** Ein nicht erreichbarer
oder fehlerhaft antwortender Server bei der täglichen Prüfung ändert
NICHTS am zuletzt bekannten Stand - weder wird eine Lizenz dadurch
fälschlich widerrufen, noch geht ein bereits gesetzter Ablauf-Override
verloren. Eine offline betriebene Installation bekommt einen Widerruf
oder eine Verlängerung entsprechend erst mit, sobald sie wieder online
ist und der tägliche Timer erfolgreich durchläuft - keine sofortige/
Push-basierte Sperrung möglich, siehe README/Plan zur bewusst offline
gehaltenen Signaturprüfung oben.

Für Entwickler: Ob ein Build die Testversion-Einschränkungen überhaupt
anwendet, steuert die Konstante `IS_TRIAL_BUILD` in `module.php` - für einen
Vollversion-Build (z. B. an zahlende Kunden nach Kauf) dort auf `false`
setzen, dann entfallen Sprach- und Zeitbeschränkung unabhängig vom
Lizenzschlüssel.

**Signatur der Lizenzschlüssel (Ed25519, asymmetrisch):** Geprüft wird mit
`sodium_crypto_sign_verify_detached()` gegen den öffentlichen Schlüssel in
der Konstante `LICENSE_PUBLIC_KEY` (`module.php`). Bewusst *kein* HMAC
(gemeinsames Geheimnis für Signieren und Prüfen) mehr: das Modul muss zum
Prüfen bei jedem Nutzer lokal laufen, ein HMAC-Geheimnis stünde also
zwangsläufig in jeder installierten Kopie von `module.php` - technisch
versierte Nutzer könnten es dort auslesen und sich selbst beliebig viele
gültige Lizenzen bauen. Mit Ed25519 lässt sich aus dem öffentlichen
Prüfschlüssel dagegen keine gültige Signatur erzeugen.

Vor jedem echten Release:
1. Neues Schlüsselpaar erzeugen: `sodium_crypto_sign_keypair()`.
2. Nur den **öffentlichen** Teil (`sodium_crypto_sign_publickey()`,
   base64-kodiert) in `LICENSE_PUBLIC_KEY` eintragen - dieser darf im Repo
   stehen.
3. Den **privaten** Teil (`sodium_crypto_sign_secretkey()`) NIEMALS committen
   - sicher außerhalb des Repos aufbewahren (z. B. Passwort-Manager), nur ein
   eigenes, privates Verkaufs-/Signier-Tool braucht ihn zum Ausstellen echter
   Lizenzschlüssel (`sodium_crypto_sign_detached($payloadJson, $privateKey)`,
   dieselbe base64url-Payload-plus-Signatur-Struktur wie oben beschrieben).
   Ein solches Tool ist bewusst nicht Teil dieses (öffentlichen) Repos.
4. Der aktuell eingetragene `LICENSE_PUBLIC_KEY` ist das echte, produktiv
   genutzte Schlüsselpaar (der private Gegenpart liegt in
   Synergetix-Website's `includes/secrets.php`/`secrets.prod.php`,
   `LICENSE_PRIVATE_KEY`) - damit signierte Schlüssel aus dem echten Shop
   validieren hier direkt.

### 9. PHP-Befehlsreferenz

`string IPSSL_TranslateText(integer $InstanzID, integer $ObjektID);`
Liefert den Inhalt der "Eigene Texte"-Zeile für die angegebene Objekt-ID
(die String-Variable im Root-Baum) in der aktuell aktiven Sprache
(Fallback: Quelltext), z. B. für Popup-Inhalte in eigenen HTMLBox-Skripten.

Beispiel:
`IPSSL_TranslateText(12345, 67890);`

`void IPSSL_Rescan(integer $InstanzID);`
Liest den konfigurierten Root der Visualisierung neu ein und übersetzt neu
gefundene oder noch unübersetzte Einträge. Entspricht dem Button
"Visualisierung neu einlesen" im Modul-Formular.

Beispiel:
`IPSSL_Rescan(12345);`

`string IPSSL_TranslateExternalText(integer $InstanzID, string $Text, string $Quellsprache = "");`
Übersetzt beliebigen Text live in die aktuell aktive Sprache dieser
Instanz - für Modulentwickler, deren eigenes Modul eine eigene HTML-Kachel
ausliefert (`GetVisualizationTile()`) statt Text in einer von Simple Locale
beobachtbaren Variable zu halten, siehe
[Abschnitt 10](#10-integration-für-modulentwickler). `$Quellsprache` ist
optional - weggelassen (oder `""`), greift die in dieser Instanz
konfigurierte Scan-Sprache; nur bei abweichender Fremdtext-Sprache explizit
angeben. Leerer Text, Quellsprache = aktive Sprache, oder eine wegen
abgelaufener Testphase gerade nicht kostenfreie Sprache liefern den Text
unverändert zurück - nie ein Fehler.

Beispiel (Scan-Sprache dieser Instanz, z. B. Deutsch):
`IPSSL_TranslateExternalText(12345, 'Guten Tag');`

Beispiel (abweichende, explizit angegebene Quellsprache):
`IPSSL_TranslateExternalText(12345, 'Good day', 'en');`

`string IPSSL_GetCurrentLanguageCode(integer $InstanzID);`
Liefert den aktuell aktiven Sprachcode dieser Instanz (z. B. `"en"`) - 
nützlich, um eigene Inhalte nur bei einem tatsächlichen
Sprachwechsel neu aufzubauen, statt bei jedem Rendern blind zu übersetzen.

Beispiel:
`IPSSL_GetCurrentLanguageCode(12345);`

`string IPSSL_GetAvailableLanguages(integer $InstanzID);`
Pro-Feature `custom_tile` (siehe [Abschnitt 8](#8-lizenz-und-testversion)) -
**wirft eine Exception ohne dieses Feature**, statt nur leer/wirkungslos zu
bleiben: liefert die aktuell wählbaren Sprachen als JSON-Array
`[{code, name, current}, ...]`, live in die aktuell aktive Sprache übersetzt
und alphabetisch sortiert - für eine komplett eigenständige, selbstgebaute
Sprachauswahl-Kachel (siehe [Abschnitt 7](#7-visualisierung); für die
naheliegendere Variante, nur das HTML/CSS der eingebauten Kachel
anzupassen, siehe dort den Button "Eigenen Kachel-HTML-Code bearbeiten" -
das braucht diesen Befehl nicht).

Beispiel:
`IPSSL_GetAvailableLanguages(12345);`

`void IPSSL_SetLanguage(integer $InstanzID, string $Sprachcode);`
Pro-Feature `custom_tile` - **wirft eine Exception ohne dieses Feature**:
wechselt die aktive Sprache von außen, mit derselben Logik wie ein Klick im
eingebauten Dropdown (Testphase-/Rate-Limit-Prüfung inklusive) - für eine
komplett eigenständige, selbstgebaute Sprachauswahl-Kachel.

Beispiel:
`IPSSL_SetLanguage(12345, 'en');`

### 10. Integration für Modulentwickler

Liefert dein eigenes Modul eine eigene HTML-Kachel aus
(via `GetVisualizationTile()`), lässt sich dessen Text-Inhalt live in die
gerade aktive Sprache einer Visualisierung mit Simple-Locale-Instanz übersetzen - ganz
ohne eigenen Google-Account, da `IPSSL_TranslateExternalText()` den
Google-API-Key der jeweiligen Simple-Locale-Instanz mitverwendet.

Da die meisten Nutzer (noch) keine Simple-Locale-Instanz installiert haben,
sollte der Aufruf immer defensiv erfolgen - mit `function_exists()` und
einer eigenen Suche nach einer passenden Instanz, statt die Instanz-ID fest
zu verdrahten:

```php
private function TranslateViaSimpleLocale(string $Text, string $SourceLanguage): string
{
    if (!function_exists('IPSSL_TranslateExternalText')) {
        // Simple Locale ist beim Nutzer nicht installiert - Text unverändert
        // anzeigen, kein Fehler.
        return $Text;
    }

    $instanceIDs = IPS_GetInstanceListByModuleID('{1A2E3892-FE35-9E4E-A3A8-B983B0C41F64}');
    if ($instanceIDs === []) {
        // Modul installiert, aber keine Instanz angelegt/konfiguriert.
        return $Text;
    }

    // Läuft eine einzelne SimpleLocale-Instanz beim Nutzer (üblicher Fall), reicht die
    // erste gefundene - bei mehreren Instanzen ggf. eine eigene Auswahl anbieten.
    return IPSSL_TranslateExternalText($instanceIDs[0], $Text, $SourceLanguage);
}
```

Am besten bei jedem Aufruf von `GetVisualizationTile()` aufgerufen - dort
gibt es (anders als bei Variablen-Werten) kein Caching-/Veraltungsproblem,
da die Kachel ohnehin bei jedem Aufruf neu gerendert wird.

### 11. Change-Log

Chronologische Historie aller Bugfixes, Features und Nachbesserungen aus
Build 53 bis Build 107 - ausgelagert aus Abschnitt 2, das dadurch als reine,
aktuelle Liste bestehen bleibt. Jeder Eintrag ist unverändert (verbatim) aus
der ursprünglichen Fassung übernommen.

* **Build 53** behebt einen internen Fehler, der diesen Live-Pfad zusätzlich
  bei JEDER Aktualisierung einer verfolgten Variable einen kompletten,
  eigentlich nur nach einer Quellsprachen-Änderung nötigen Zeilen-Abgleich
  (siehe "Quellsprache: pro Zeile individuell änderbar" in Abschnitt 7)
  erneut anstoßen und dabei in kurzer Zeit die Tageskontingente mehrerer
  Übersetzungsanbieter gleichzeitig aufbrauchen konnte - seit Build 53 läuft
  dieser Abgleich nur noch, wenn sich seit dem letzten Mal tatsächlich eine
  Quellsprache geändert hat (Fingerprint-Vergleich, kein API-Aufruf).
  Fehlerdetails zu jedem fehlgeschlagenen Übersetzungsversuch (welcher
  Anbieter, HTTP-Code, Antwort) landen seit Build 53 zusätzlich im normalen
  Symcon-Meldungen-Log der Instanz (nicht mehr nur im Debug-Panel).
* **Build 54**
  korrigiert dabei einen Fehler in Build 53 selbst: die von IPSModule geerbte
  `LogMessage()`-Methode löste, aus dem über `MessageSink()`/`VM_UPDATE`
  erreichbaren Übersetzungs-Fehlerpfad heraus aufgerufen, zuverlässig eine
  "InstanceInterface is not available"-Warnung aus (die Methode scheint eine im
  MessageSink-Ausführungskontext nicht existierende Interface-Instanz
  vorauszusetzen) - seit Build 54 wird stattdessen die kontextunabhängige globale
  `IPS_LogMessage()`-Funktion verwendet.
* **Build 55** führt die automatische Pause bei Rate-Limit/Tageskontingent ein. Meldet ein
  einzelner Übersetzungsanbieter ein Rate-Limit oder ein aufgebrauchtes
  Tageskontingent (erkannt an HTTP 429/456 bzw. HTTP 403 mit "rate limit" in
  der Antwort), wird genau dieser eine Anbieter für eine gewisse Zeit
  automatisch pausiert (kurze Sperre bei einem reinen Burst-Limit, 24h bei
  einem erkannten Tageskontingent) - er wird währenddessen nicht erneut
  angefragt, die übrigen konfigurierten Anbieter werden aber normal
  weiterversucht. Melden ALLE konfigurierten Anbieter gleichzeitig ein
  Limit (bei nur einem konfigurierten Anbieter genügt bereits dieser eine),
  lohnt sich kein weiterer Versuch mehr - die Instanz pausiert dann komplett
  bis zum frühesten Reset-Zeitpunkt: kein einziger weiterer API-Aufruf, bis
  mindestens ein Anbieter wieder verfügbar sein sollte. Sichtbar an drei
  Stellen: ein kleiner roter Hinweis "Übersetzung pausiert bis HH:MM" direkt
  unter dem Dropdown in der Kachel (live in die jeweils aktive Gast-Sprache
  übersetzt), der Instanz-Status "Aktiv, aber pausiert", und eine detaillierte
  Aufschlüsselung (welcher Anbieter pausiert bis wann) im Panel
  "Übersetzungsanbieter" des Konfigurationsformulars. Ein ungültiger/
  abgelaufener API-Key oder ein Netzwerkfehler lösen dagegen NIE eine Pause
  aus (die würde sich ja nie von selbst erledigen) - nur ein tatsächlich als
  Rate-Limit/Kontingent erkannter Fehler.
* **Build 57** korrigiert zwei live beobachtete Inkonsistenzen aus Build 55/56:
  (1) Googles "User Rate Limit Exceeded" enthält keines der Tageskontingent-
  Schlüsselwörter und bekam daher immer nur die kurze 15-Minuten-Basissperre -
  blieb der Fehler aber über Stunden bestehen, führte das zu einem
  "Flackern" (Google fiel nach jeder abgelaufenen Sperre wieder aus der
  Pause-Übersicht heraus, obwohl der Fehler weiter auftrat). Jeder ERNEUTE
  Fehlschlag ohne zwischenzeitlichen Erfolg verdoppelt die Sperrdauer jetzt
  automatisch (15min, 30min, 1h, ... gedeckelt auf 24h) - ein tatsächlich nur
  kurz blockierter Anbieter erholt sich weiterhin schnell, ein andauernd
  fehlschlagender wandert automatisch Richtung Tagessperre. (2) Der
  Instanz-Status "Aktiv, aber pausiert" wurde bisher nur GESETZT, wenn gerade
  tatsächlich ein Übersetzungsversuch lief - fand seitdem keiner mehr statt,
  blieb die Statuszeile veraltet stehen, obwohl die Panel-Übersicht (die
  immer frisch berechnet wird) bereits alle Anbieter als pausiert zeigte.
  Seit Build 57 wird der Status zusätzlich bei jedem Öffnen des
  Konfigurationsformulars und bei jedem "Übernehmen" neu bewertet.
* **Build 58 behebt den bisher schwerwiegendsten Fund dieser Reihe:** ein
  HTML-Widget (z. B. das Wetter-Beispiel aus Abschnitt 1) konnte bei
  pausierter/fehlgeschlagener Übersetzung komplett LEER erscheinen - Struktur
  (Rahmen, Icons, Tagesüberschriften) intakt, aber jeder einzelne dynamische
  Wert (Prozentzahlen, Windgeschwindigkeit, Temperaturen) leer, statt wie
  erwartet auf die unübersetzte Originalsprache zurückzufallen. Ursache: die
  Text-Knoten-Zerlegung für HTML-Inhalte (siehe Abschnitt 7, Build 47) setzte
  ein leeres Übersetzungsergebnis je Knoten direkt in die wiederzusammengesetzte
  HTML-Struktur ein, statt bei einem fehlgeschlagenen/pausierten
  Übersetzungsversuch auf den unübersetzten Original-Knoten zurückzufallen -
  betraf dadurch praktisch jedes mehrsprachige HTML-Widget, sobald auch nur
  EIN einzelner Übersetzungsversuch fehlschlug (nicht nur während einer
  Pause). Zusätzlich zeigte der kleine rote "Übersetzung pausiert bis"-Hinweis
  unter dem Dropdown teils nur die Uhrzeit ohne den Text davor (derselbe
  Grundfehler: ein leeres statt eines fehlenden Übersetzungsergebnisses wurde
  nicht als "fehlgeschlagen, bitte Original verwenden" erkannt).
* **Build 59 behebt dieselbe Fehlerklasse an zwei weiteren, deutlich
  schwerwiegenderen Stellen - dem eigentlichen DATENVERLUST-Bug dieser
  Serie:** sowohl `ReconcileRowSourceLanguageChanges()` (läuft für ALLE fünf
  Zeilen-Properties: Objektnamen, Eigene Texte, Beschriftungen, Automations,
  Begrüßung) als auch der `VM_UPDATE`-Live-Übersetzungspfad überschrieben eine
  bereits vorhandene, funktionierende Übersetzungsspalte bedingungslos mit dem
  Ergebnis eines erneuten Übersetzungsversuchs - auch dann, wenn dieser wegen
  einer pausierten/ausgefallenen Anbieter-Kette leer zurückkam. Live
  beobachtet: nach einer längeren Pause-Phase waren in "Objektnamen" und
  teilweise "Eigene Texte"/"Beschriftungen"/"Automations" sämtliche
  Zielsprachen-Spalten leer, obwohl vorher funktionierende Übersetzungen
  vorhanden waren - nur "Original-Import" blieb erhalten. Seit Build 59 wird
  eine bestehende Spalte bei einem fehlgeschlagenen Übersetzungsversuch NIE
  mehr überschrieben (die zuletzt bekannte gute Übersetzung bleibt stehen,
  bis ein neuer Versuch tatsächlich erfolgreich war), und die interne
  Buchführung wird nur bei VOLLSTÄNDIGEM Erfolg aller Zielsprachen
  fortgeschrieben - schlägt auch nur eine einzige fehl, bleibt die betroffene
  Zeile für einen späteren erneuten Versuch vorgemerkt, statt fälschlich als
  "erledigt" zu gelten. Zusätzlich überspringt `ApplyChanges()` den kompletten
  Quellsprachen-Abgleich jetzt schon im Vorfeld, solange die Anbieter-Kette
  komplett pausiert ist (siehe oben) - der zugehörige interne Fingerprint
  bleibt dabei bewusst unverändert, damit der Abgleich zuverlässig nachgeholt
  wird, sobald mindestens ein Anbieter wieder verfügbar ist. Der eigentliche
  Übersetzungs-Cache (`GetCachedTranslation`/`StoreCachedTranslation`, siehe
  Abschnitt 1) war von diesem Bug nie betroffen - ein fehlgeschlagenes/leeres
  Ergebnis wurde dort schon immer bewusst NICHT zwischengespeichert (siehe
  `TranslateBatch`: nur tatsächlich übersetzte, nicht-leere Ergebnisse landen
  im Cache), sodass ein erneuter Versuch für denselben Text nie an einem
  fälschlich gecachten leeren Ergebnis scheitert.
* **Build 60** ergänzt drei Wünsche und behebt einen weiteren, unabhängigen
  Datenverlust-Bug: (1) Ein neuer/geänderter API-Key (Google/DeepL) oder eine
  geänderte MyMemory-Kontakt-E-Mail beendet die Pause des betroffenen
  Anbieters jetzt sofort, statt auf den Ablauf der ggf. bereits eskalierten
  Sperrfrist zu warten - erkannt über einen SHA-256-Hash der zuletzt
  gesehenen Zugangsdaten (nie der Klartext-Wert selbst); beim allerersten
  `ApplyChanges()` einer Instanz (noch kein Vergleichswert vorhanden) wird
  das nie fälschlich als Änderung gewertet. (2) Der rote Testphase-/
  Pausiert-Hinweis unter dem Dropdown ist jetzt mittig statt linksbündig
  ausgerichtet. (3) Ein neuer Nutzungs-Zähler zeigt die durchschnittliche
  Anzahl an Übersetzungsanfragen und übersetzten Zeichen pro Stunde seit der
  allerersten Einrichtung der Instanz (nicht seit der ersten tatsächlichen
  Übersetzung) - als Satz direkt unter der Erläuterung des "Aktiv"-Schalters
  im Konfigurationsformular (nur bei natürlichem Öffnen/Neuaufbau des
  Formulars berechnet, löst NIE einen erzwungenen Refresh aus - siehe
  unten), und optional (Checkbox "Übersetzungsstatistik in der Kachel
  anzeigen", standardmäßig aus) auch als kleiner Hinweistext in der Kachel
  selbst, dort alle 10 Minuten aktualisiert über `PushVisualizationUpdate()`
  (nicht über einen Formular-Reload). Für eigene Kacheln stehen dafür zwei
  Platzhalter bereit, siehe [Abschnitt 7](#7-visualisierung).

  **Zusätzlich behebt Build 60 einen weiteren, unabhängigen und
  schwerwiegenden Bug:** Der automatische Hintergrund-Rescan (Timer, siehe
  "Automatischer Rescan (Minuten)") teilte sich bisher denselben internen
  Code-Pfad wie der manuelle "Baum neu einlesen"-Button - inklusive dessen
  abschließendem `ReloadForm()`. Das bedeutete: war das
  Konfigurationsformular gerade geöffnet und wurden dort z. B. Übersetzungen
  von Hand bearbeitet, konnte der Hintergrund-Timer JEDERZEIT dazwischen-
  funken und das gesamte Formular neu laden - alle noch nicht mit
  "Übernehmen" gespeicherten Änderungen gingen dabei kommentarlos verloren.
  Seit Build 60 lädt ausschließlich der manuelle Button das Formular neu;
  der automatische Hintergrund-Rescan aktualisiert die Objektliste weiterhin
  normal im Hintergrund, rührt ein gerade geöffnetes Formular aber nicht
  mehr an.
* **Build 61** ergänzt den Nutzungs-Zähler aus Build 60 um eine zweite
  Statistik und einen neuen Diagnose-Button: (1) Zusätzlich zu den
  tatsächlich gestellten Anfragen zählt die Instanz jetzt auch mit, wie viele
  Übersetzungsanfragen und Zeichen dank des Caches (siehe oben,
  `GetCachedTranslation`/`StoreCachedTranslation`) gar nicht erst an einen
  Anbieter geschickt werden mussten - als reine Gesamtsumme seit
  Inbetriebnahme (keine Pro-Stunde-Rate wie beim Hauptzähler), direkt
  angehängt an den Statistik-Satz unter der Erläuterung des "Aktiv"-Schalters
  im Konfigurationsformular. Für eigene Kacheln stehen dafür zwei weitere
  Platzhalter bereit, `<!--COUNT_CACHE_TRANSLATIONS-->` und
  `<!--COUNT_CACHE_SIGNES-->` (ebenfalls reine Ganzzahl ohne Einheit), siehe
  [Abschnitt 7](#7-visualisierung). (2) Ein neuer Button "Übersetzungsanbieter
  prüfen" ganz unten im Konfigurationsformular schickt eine einzelne
  Testanfrage ("Testabfrage" -> Englisch) DIREKT an jeden gerade
  eingerichteten Anbieter (Google/DeepL, falls ein API-Key eingetragen ist,
  sowie immer MyMemory) - bewusst am Cache vorbei (immer eine echte, frische
  Antwort) und unabhängig von einer eventuell laufenden Pause (die würde
  einen normalen Übersetzungsversuch sonst überspringen, hier soll aber
  gerade geprüft werden, ob der Anbieter TROTZ Pause inzwischen wieder
  funktioniert). Meldet für jeden Anbieter einzeln zurück, ob die Antwort
  angekommen ist, und beendet dabei automatisch eine noch laufende Pause,
  sobald ein Anbieter wieder erfolgreich antwortet - nützlich z. B. direkt
  nach einem Kontingent-/Abo-Upgrade beim Anbieter, ohne auf das
  automatische Ablaufen der (ggf. bereits mehrfach eskalierten, siehe Build
  57) Pause warten zu müssen.
* **Build 62 behebt zwei live gefundene Bugs:** (1) **Der eigentlich
  schwerwiegendere:** Der kostenfreie Anbieter (MyMemory) meldet ein
  erschöpftes Tageskontingent NICHT über einen HTTP-Fehlercode wie Google/
  DeepL, sondern ausschließlich über das JSON-Feld `quotaFinished` bei
  weiterhin HTTP 200 - `DetectRateLimitCooldown`/`RecordProviderPaused`
  (siehe oben) wurden dadurch für diesen Fall bisher NIE ausgelöst: 'free'
  blieb in der Panel-Übersicht dauerhaft als "nicht pausiert" sichtbar,
  obwohl JEDER weitere Versuch für den Rest des Tages ebenfalls scheiterte.
  Live beobachtet: Google/DeepL waren bereits (durch intensives Testen)
  pausiert, MyMemory zusätzlich fälschlich als verfügbar geführt - jeder
  weitere Rescan-Versuch schlug dadurch für ALLE drei Anbieter fehl, ohne
  dass die Instanz das als Pause/Fehler erkannte oder meldete: Rescan lief
  zwar technisch durch, aber ohne jede neue Übersetzung und ohne
  Statusänderung - wirkte dadurch nach außen wie "Rescan tut gar nichts".
  MyMemorys `quotaFinished` löst jetzt direkt die volle Tagessperre aus,
  genau wie ein per HTTP erkanntes Tageskontingent bei Google/DeepL. (2) Die
  in Build 60/61 eingeführte Pro-Stunde-Hochrechnung des Nutzungs-Zählers
  konnte kurz nach der Inbetriebnahme (oder nach einem kurzen Anfragen-
  Ansturm, z. B. über den "Übersetzungsanbieter prüfen"-Button) eine Rate
  zeigen, die HÖHER als der tatsächliche Gesamtzähler war (z. B. "1698
  Anfragen/h" bei nur "783 Anfragen insgesamt", da erst 28 Minuten seit
  Inbetriebnahme vergangen waren) - wirkte wie ein Rechenfehler, war aber
  nur eine Hochrechnung aus einem sehr kurzen Zeitfenster. Die Rate wird
  jetzt auf mindestens eine volle Stunde gedeckelt: innerhalb der ersten
  Stunde nach Inbetriebnahme zeigt sie exakt den bisherigen Gesamtwert (nie
  mehr), erst danach weicht sie als echte Rate vom Gesamtwert ab.
* **Build 63 korrigiert die Farbcodierung im Symcon-"Meldungen"-Log (englisch
  "Status Log"):** Übersetzungs-Fehler/-Warnungen (`LogTranslateMessage()`,
  siehe oben "MyMemorys quotaFinished" und die Anbieter-Fehlermeldungen)
  erschienen dort bisher grau als generischer "Custom"-Eintrag mit einem
  Text-Präfix "[FEHLER]"/"[WARNUNG]", statt in der eigentlich vorgesehenen
  roten/gelben Farbcodierung für "Error"/"Warning". Grund: die global
  aufgerufene `IPS_LogMessage()`-Funktion kennt gar keinen Schweregrad-
  Parameter - nur die von `IPSModule` geerbte, INSTANZ-gebundene
  `LogMessage($Message, $Type)`-Methode (mit `KL_ERROR`/`KL_WARNING`) liefert
  die echte Farbcodierung, wurde hier aber bewusst gemieden, weil sie
  nachweislich abstürzt, sobald sie aus dem `MessageSink()`/`VM_UPDATE`-
  Ausführungskontext heraus aufgerufen wird (siehe Build 54). Seit Build 63
  unterscheidet die Instanz beide Kontexte: außerhalb von `MessageSink()`
  (Rescan, "Übersetzungsanbieter prüfen", `ApplyChanges()`, ...) wird jetzt
  die typisierte `LogMessage()`-Methode verwendet - Fehler erscheinen dadurch
  korrekt rot als "Error", Warnungen gelb als "Warning". Nur innerhalb der
  einen bekannten Absturz-Situation (`MessageSink()`/`VM_UPDATE`) greift
  weiterhin die alte, sichere `IPS_LogMessage()`-Variante mit Text-Präfix,
  da dort echte Instabilität nachgewiesen wurde.
* **Build 64 behebt eine fehlende Ein-/Mehrzahl-Behandlung im
  Nutzungs-Zähler-Satz** (siehe oben, Build 60/61): "Anfragen"/"Zeichen" (und
  deren Übersetzungen) standen dort bisher IMMER in der Mehrzahl fest
  codiert, auch wenn der tatsächliche Wert 1 war (z. B. "1 Anfragen" statt
  korrekt "1 Anfrage", ebenso in den anderen 4 Sprachen). Behoben nach genau
  demselben, bereits bei der Testphasen-Anzeige bewährten Muster
  (`BuildTrialInfoText()`, "Tag(e)"/"day(s)"/"día(s)"/"giorno/i"/"jour(s)"):
  EIN einzelner, nicht konjugierender Anzeige-String pro Sprache, der für
  jede Anzahl passt - kein Laufzeit-Unterschied zwischen Einzahl/Mehrzahl
  nötig. Die betroffenen `locale.json`-Schlüssel heißen jetzt "Anfrage(n)"/
  "Anfrage(n)/h" (vorher "Anfragen"/"Anfragen/h") und liefern in jeder
  Sprache eine passende Kurzform (z. B. "request(s)/h" statt nur "requests/h";
  fürs unregelmäßige Spanisch "carácter"/"caracteres" wurde stattdessen
  bewusst auf das regelmäßig pluralisierende Synonym "signo(s)" ausgewichen,
  um die im Plural verschwindende Betonungs-Markierung "á" nicht falsch
  darzustellen; Italienisch nutzt statt eines Suffixes einen Klammer-Wechsel
  der letzten Buchstaben, z. B. "richiest(a/e)", "caratter(e/i)", passend zum
  bereits bestehenden "giorno/i").
* **Build 65 behebt den bisher schwerwiegendsten Fund dieser gesamten
  Serie:** Live beobachtet wurden "Automations" und "Begrüßung" nach einem
  Rescan während einer Anbieter-Pause komplett auf den unübersetzten
  deutschen Rohtext eingefroren - in JEDER Zielsprachen-Spalte, dauerhaft,
  auch nachdem die Pause längst vorbei war. Ursache: `TranslateBatchUncached()`
  fällt bei einem fehlgeschlagenen Übersetzungsversuch bewusst NIE auf einen
  leeren String zurück, sondern auf den unübersetzten Quelltext (siehe Build
  58 - richtig für die dortige Wiederzusammensetzung von HTML-Widgets, die
  sonst mit leeren dynamischen Werten dastünden). Das Problem: `TranslateBatch()`
  ist der EINE zentrale Durchgangspunkt, über den ALLE anderen Funktionen
  laufen (`FillLanguageColumn` beim Rescan, `ApplyTrackedVariableUpdate` bei
  der VM_UPDATE-Live-Nachübersetzung, `ReconcileRowFields` beim
  Quellsprachen-Abgleich) - und JEDE von ihnen entscheidet ausschließlich
  anhand eines LEEREN Strings, ob eine Zelle als "fertig übersetzt, nicht
  erneut versuchen" oder "fehlgeschlagen, bitte später erneut versuchen"
  gilt. Da dieses leere Signal wegen des Fallbacks nie ankam, wurde JEDE
  Zelle, deren allererster Übersetzungsversuch während einer Pause
  stattfand, fälschlich als "erledigt" verbucht - inklusive einer
  Cache-Vergiftung (der unübersetzte Rohtext wurde als "echte Übersetzung"
  zwischengespeichert und dadurch nie wieder neu versucht, selbst nach Ende
  der Pause). Behoben an der EINEN zentralen Stelle (`TranslateBatch()`):
  ein Ergebnis, das exakt dem unübersetzten Quelltext entspricht, wird dort
  wieder in einen echten Leerstring zurückverwandelt, bevor es an
  irgendeinen Aufrufer weitergereicht oder gecacht wird - `TranslateBatchUncached()`
  selbst (und damit die HTML-Wiederzusammensetzung) bleibt unverändert.
  **Bereits vorhandene, auf diese Weise eingefrorene Zellen werden davon
  nicht rückwirkend erkannt** (der gespeicherte deutsche Text ist nicht mehr
  von einer "zufällig identischen" echten Übersetzung unterscheidbar) -
  betroffene Zellen müssen einmalig manuell im Formular geleert werden,
  danach übersetzt sie der nächste Rescan/die nächste Anbieter-Erholung
  normal nach.
* **Build 66 schließt dieselbe Lücke zusätzlich im Übersetzungs-Cache:**
  Live beobachtet direkt nach Build 65 - Zellen leeren + Rescan füllte
  "Automations" trotzdem wieder mit Deutsch, obwohl "Begrüßung" im selben
  Test korrekt übersetzt wurde. Ursache: der interne Übersetzungs-Cache
  (siehe Abschnitt 1, `GetCachedTranslation`/`StoreCachedTranslation`) hatte
  denselben unübersetzten Rohtext bereits VOR Build 65 unter genau diesem
  Schlüssel als "echte Übersetzung" zwischengespeichert (z. B. "Gehen" für
  Deutsch->Englisch) - ein Cache-TREFFER läuft komplett an `TranslateBatch()`s
  frischem Übersetzungspfad (und damit am Build-65-Schutz) vorbei, liefert
  also weiterhin den vergifteten alten Eintrag. Im Debug-Log erkennbar an
  einem "..._Mapping"-Eintrag ganz ohne jeden nachfolgenden
  "..._Request"/"..._Response" - der sichere Hinweis auf einen Cache-Treffer
  statt eines echten (und damit geschützten) neuen Versuchs. Behoben wie
  beim strukturell identischen Vorfall vom 2026-08-15 (siehe
  `TRANSLATION_CACHE_SCHEMA_VERSION`): die Cache-Version wurde erneut erhöht
  (2 → 3) - macht JEDEN vor Build 66 gecachten Eintrag unerreichbar (die
  Version steckt im Cache-Schlüssel) und erzwingt für jeden betroffenen Text
  einmalig einen frischen, jetzt korrekt geschützten Übersetzungsversuch.
  **Mit Build 66 reicht das Leeren betroffener Zellen im Formular + Rescan
  wieder allein aus** - ein zusätzliches manuelles "Übersetzungs-Cache
  leeren" ist NICHT mehr nötig (macht aber ebenfalls nichts kaputt, falls
  bereits geklickt).
* **Build 67 behebt eine Konsolensprachen-Einschränkung, die zwei
  dynamische Textbereiche im Konfigurationsformular betraf** - den
  Nutzungs-Zähler-Satz unter "Aktiv" (siehe oben) und die
  Pause-Übersicht im Panel "Übersetzungsanbieter": beide blieben live
  beobachtet dauerhaft auf Deutsch stehen, selbst bei englischer
  (oder jeder anderen) Konsolensprache des Betrachters - obwohl fest
  eingebaute Formular-Beschriftungen ("Aktiv", "Notaus-Schalter: ...")
  im selben Formular korrekt übersetzt erschienen. Ursache: `$this->Translate()`
  ist an die Symcon-SYSTEMSPRACHE gebunden (eine einzelne, installationsweite
  Kernel-Einstellung), NICHT an die individuelle Konsolensprache der gerade
  betrachtenden Admin-Sitzung - die tatsächliche, per-Betrachter korrekte
  Übersetzung von `GetConfigurationForm()`-Beschriftungen übernimmt
  stattdessen der Konsolen-Client selbst, per exaktem Textabgleich einer
  KOMPLETTEN Beschriftung gegen `locale.json` - unabhängig davon, ob diese
  Beschriftung ursprünglich statisch in `form.json` stand oder von PHP
  gesetzt wurde. Eine zur Laufzeit aus mehreren `Translate()`-Fragmenten und
  eingefügten Werten (Datum, Uhrzeit, Zahlen) ZUSAMMENGESETZTE Zeichenkette
  matcht dadurch NIE einen `locale.json`-Eintrag als Ganzes und bleibt
  unabhängig von der tatsächlichen Konsolensprache stehen - exakt dieselbe,
  bereits beim Lizenz-Infobereich gefundene und dort erfolgreich behobene
  Einschränkung (siehe die vielen einzelnen `LicenseInfoXxx`-Formularelemente).
  Beide betroffenen Bereiche wurden nach demselben, bereits bewährten Muster
  umgebaut: viele einzelne, kleine `RowLayout`/Label-Formularelemente statt
  eines zusammengesetzten Fließtexts - jedes Element trägt entweder eine
  feste, unveränderte deutsche Zeichenkette (die der Konsolen-Client korrekt
  je nach Betrachter übersetzt) oder einen rohen, nicht zu übersetzenden Wert
  (Datum/Uhrzeit/Zahl), nie beides zusammengesetzt in einer Caption. Kein
  `$this->Translate()`-Aufruf mehr in `FormatTranslationStatsValue()`/
  `PopulateProviderPauseStatusElement()`/`FormatProviderPauseUntil()`.
  Zusätzlich zeigt die Pause-Übersicht im Panel "Übersetzungsanbieter"
  jetzt auch in der Kachel selbst (roter Hinweis unter dem Dropdown) das
  Datum zusätzlich zur Uhrzeit (z. B. "18.08. 21:34" statt nur "21:34") -
  eine reine Uhrzeit war bei einer über Mitternacht hinausreichenden Pause
  (durch die Eskalation, siehe Build 57, bis zu 24h) mehrdeutig.
* **Build 68 rundet die Build-67-Umstellung ab:** Live beobachtet blieben
  zwei einzelne Textbausteine trotz Build 67 weiterhin unübersetzt -
  "Tag(e):" und "Zeichen." -, obwohl das bloße "Tag(e)"/"Zeichen" an anderer
  Stelle im selben Formular korrekt übersetzte. Ursache: an genau diesen
  beiden Stellen war ein Satzzeichen DIREKT an das deutsche Wort angehängt
  ("Tag(e):" statt "Tag(e)", "Zeichen." statt "Zeichen") - eine Zeichenkette,
  die dadurch nicht mehr EXAKT dem registrierten `locale.json`-Schlüssel
  entspricht, bleibt beim Abgleich unübersetzt stehen (siehe Build 67).
  Jedes Satzzeichen, das zu keinem eigenen Textbaustein gehört, sitzt jetzt
  in einem eigenen, unbenannten Element (kein `locale.json`-Eintrag nötig -
  ein Satzzeichen ohne Übersetzungstreffer wird ohnehin unverändert
  angezeigt). Zusätzlich fehlte "Kostenfreier Anbieter (MyMemory)" bisher
  komplett als registrierter Übersetzungstext (nicht falsch zusammengesetzt,
  sondern schlicht nie übersetzbar gemacht) - jetzt in allen 4 Sprachen
  ergänzt und sowohl in der Pause-Übersicht als auch im
  "Übersetzungsanbieter prüfen"-Ergebnis verwendet. Der Nutzungs-Zähler
  wurde dabei gleich klarer strukturiert: statt der bisherigen ".../h"-Suffixe
  zeigt eine neue, eigene Zeile "Stündlich" (übersetzbar) die
  Pro-Stunde-Werte, "Insgesamt" (vormals klein geschrieben "insgesamt")
  startet jetzt ebenfalls eine eigene Zeile - vier klar getrennte,
  vollständig lokalisierte Zeilen (Seit Inbetriebnahme / Stündlich /
  Insgesamt / Durch den Cache eingespart) statt eines einzigen, dichten
  Absatzes. Nach demselben Muster wurden außerdem der Testphasen-Hinweis
  (`TrialInfoFreshLabel`/`TrialInfoRunningRow`/`TrialInfoExpiredRow`, je
  nach Testphasen-Status genau eine sichtbare Variante) und das Ergebnis-
  Popup von "Übersetzungsanbieter prüfen" umgebaut (ein `RowLayout` je
  geprüftem Anbieter, per `UpdateFormField()` einzeln befüllt statt eines
  einzigen zusammengesetzten Texts) - beide hatten dieselbe Systemsprache-
  statt-Konsolensprache-Einschränkung, waren bisher aber nur noch nicht
  gemeldet worden.
* **Build 69 behebt einen unsichtbaren Zeichen-Artefakt aus MyMemory:** Live
  im Debug-Log beobachtet lieferte MyMemory bei einem Treffer aus seiner
  Übersetzungsspeicher-Datenbank ein zusätzliches, unsichtbares Zeichen direkt
  am Ende der Übersetzung mit ("Position" wurde tatsächlich als
  "Position " - mit einem geschützten Leerzeichen (U+00A0) dahinter -
  zurückgegeben). PHPs `trim()` entfernt nur ASCII-Leerraum (Leerzeichen, Tab,
  Zeilenumbruch), niemals Unicode-Zeichen wie ein geschütztes Leerzeichen oder
  ein Zero-Width-Space (U+200B) - ein solches Ergebnis wurde daher unverändert
  gespeichert/gecacht und sah in den allermeisten Ansichten optisch identisch
  zum sauberen Text aus, obwohl es ihm nicht exakt entsprach. Behoben an der
  einen zentralen Stelle, durch die die Ergebnisse aller drei Anbieter
  (Google/DeepL/MyMemory) laufen (`TranslateChunk()`): am Anfang und Ende
  werden jetzt gezielt nur geschützte Leerzeichen und Zero-Width-Spaces
  entfernt - bewusst NIE ein normales ASCII-Leerzeichen, da ein einzelner
  HTML-Textknoten (siehe Build 63/`SplitHtmlIntoTextNodes`) am Rand
  absichtlich ein Leerzeichen tragen kann, das für den korrekten Abstand
  zwischen zwei benachbarten Inline-Elementen gebraucht wird.
* **Build 70 übersetzt live nur noch die aktuell aktive Gast-Sprache, holt
  alle anderen bei Bedarf nach, und filtert reine Zahlen/Symbole komplett
  heraus:** Live beobachtet lief ein täglich verfügbares Übersetzungs-
  Kontingent innerhalb weniger Stunden vollständig leer (77.000 Zeichen an
  einem einzigen Tag), obwohl die Übersetzungs-Anbieter zwischenzeitlich
  sogar pausiert waren. Ursache: eine häufig extern aktualisierte "Eigene
  Texte"-Variable (z. B. ein Wetter-/Sensor-Widget, mehrmals pro Minute
  über `VM_UPDATE` aktualisiert) hat bei JEDER Änderung sofort ALLE
  konfigurierten Zielsprachen neu übersetzt, obwohl zu keinem Zeitpunkt
  mehr als eine Sprache gleichzeitig angezeigt wurde. Ab Build 70
  übersetzen der Rescan, die `VM_UPDATE`-Live-Nachübersetzung und der
  Quellsprachen-Abgleich (siehe Build 57) sofort nur noch die AKTUELL
  aktive Gast-Sprache - alle anderen Zielsprachen-Zellen bleiben dabei
  bewusst auf ihrem letzten bekannten (ggf. jetzt veralteten) Stand stehen,
  statt geleert zu werden. Ein neuer Zeitstempel-Abgleich je Zeile (wann
  wurde der Rohtext zuletzt geändert, wann wurde jede Sprache zuletzt
  tatsächlich übersetzt) erkennt zuverlässig, welche Zelle veraltet ist,
  ohne den bisherigen Fallback-Wert zu löschen. Wechselt ein Gast
  tatsächlich auf eine bisher nur lazy behandelte Sprache, holt ein neuer
  Nachhol-Mechanismus GENAU die betroffenen Zeilen gebündelt (ein
  API-Aufruf je Zeilen-Property statt einzeln je Zeile) nach, bevor die
  Sprache angezeigt wird - danach ist sie normal gecacht. Bereits vor
  diesem Build gespeicherte Zeilen ohne die neue Zeitstempel-Buchführung
  gelten dabei bewusst als "aktuell" (keine Massen-Neuübersetzung des
  kompletten Bestands nach dem Update). Zusätzlich geht ein Text-Fragment
  ganz ohne jeden Buchstaben (reine Zahlen, "%", "°", Uhrzeiten,
  Satzzeichen - besonders häufig bei der feingranularen HTML-Text-Knoten-
  Zerlegung eines Live-Widgets, siehe Build 63) gar nicht mehr an eine
  Übersetzungs-API: eine erkannte einzelne Zahl wird stattdessen über
  PHPs eingebaute `NumberFormatter`-Klasse (Intl-Erweiterung) rein lokal
  in die landesübliche Schreibweise der Zielsprache umgerechnet (z. B.
  deutsches "1.234,56" → englisches "1,234.56"), ohne dabei eine
  ungruppierte Zahl (z. B. eine Jahreszahl oder Zimmernummer wie "2026")
  fälschlich mit einem künstlich eingefügten Tausendertrennzeichen zu
  versehen. Fehlt die Intl-Erweiterung auf einer Installation, wird der
  Text stattdessen unverändert durchgereicht (kein Fehler). Einziger,
  bewusst in Kauf genommener Nebeneffekt: eine reine Zahl wird nie mehr
  durch eine Übersetzungs-API-Anfrage geschickt und kann deshalb auch
  keine darüber hinausgehende, kontextabhängige Umformatierung mehr
  erhalten, die Google/DeepL gelegentlich mitgeliefert haben.
* **Build 71 entkoppelt die Live-Übersetzung von der Formular-Persistierung
  einer häufig aktualisierten Variable:** Live gemeldet - trotz Build 70
  konnte ein Admin praktisch keine eigene Änderung im Konfigurationsformular
  mehr speichern, wenn eine "Eigene Texte"-Variable mehrmals pro Minute von
  außen aktualisiert wurde (z. B. ein Wetter-/Sensor-Widget). Ursache: jede
  externe Änderung hat weiterhin sofort per `IPS_SetProperty()` + `IPS_
  ApplyChanges()` genau die Property umgeschrieben, die im offenen Formular
  als bearbeitbare "Eigene Texte"-Liste angezeigt wird - kein `ReloadForm()`
  nötig, das reine Überschreiben der zugrunde liegenden Property unter einer
  live gebundenen Formularliste reichte bereits, um eine laufende Bearbeitung
  zu stören. Ab Build 71 sind zwei Schreibvorgänge, die bislang immer
  gemeinsam sofort passierten, sauber entkoppelt: die **Live-Variable**
  (das, was der Gast in der Kachel sieht) wird weiterhin komplett
  unverändert/unverzögert geschrieben; nur die **Property-Persistierung**
  (die Buchführung, die ausschließlich für einen späteren, seltenen
  Sprachwechsel gebraucht wird) wird jetzt gepuffert und erst nach 12
  Minuten Ruhe auf der jeweiligen Variable tatsächlich committet (Debounce -
  jede neue Änderung schiebt den Zeitpunkt weiter nach hinten). Speichert
  der Admin währenddessen im Formular ("Übernehmen"), wird der noch
  wartende Puffer automatisch VORHER eingespielt, bevor die eigene Änderung
  verarbeitet wird - die zuletzt gepufferte externe Änderung geht dabei
  nicht verloren, unabhängig vom Timing. Ein tatsächlicher Sprachwechsel
  (selten, aber jederzeit möglich) leert den Puffer ebenfalls sofort, statt
  auf das Ende der Ruhephase zu warten - ein Gast bekommt dadurch immer den
  aktuellsten Stand zu sehen. Das Konfigurationsformular zeigt zusätzlich
  oben einen Hinweis mit der voraussichtlichen Uhrzeit der nächsten
  Persistierung an, solange etwas ansteht.
* **Build 72 macht den Übersetzungs-Cache treffsicherer und größer:** Bisher
  war der lokale Cache (bis zu 500 Einträge) rein nach Einfügereihenfolge
  organisiert (FIFO) - wurde er voll, flog immer der ZUERST gespeicherte
  Eintrag zuerst raus, unabhängig davon, wie oft er seitdem tatsächlich
  wiederverwendet wurde. Ein Schwung einmaliger, nie wieder vorkommender
  Texte konnte dadurch theoretisch einen häufig wiederverwendeten Kern-
  Eintrag (z. B. einen festen Objektnamen) verdrängen, nur weil dieser
  zufällig zuerst im Cache landete. Jeder Eintrag führt jetzt zusätzlich
  einen Hit-Zähler und den Zeitpunkt seines letzten Zugriffs - wird der
  Cache voll, fliegt der Eintrag mit dem NIEDRIGSTEN Hit-Zähler zuerst raus
  (bei Gleichstand der am längsten nicht mehr genutzte). Ein Eintrag, der
  seit über 24 Stunden nicht mehr gelesen wurde, gilt beim nächsten Zugriff
  als "neu wieder aufgewärmt" (Zähler-Reset auf 1) statt seinen alten
  Zähler für immer fortzuschreiben - verhindert, dass ein früher einmal
  populärer, inzwischen längst nicht mehr gebrauchter Eintrag einen frisch
  aktiven verdrängt. Die Kapazität wurde gleichzeitig von 500 auf 1000
  Einträge angehoben. Da sich dabei die gespeicherte FORM eines Eintrags
  ändert (von einem reinen String zu einem kleinen Objekt mit Hit-Zähler/
  Zeitstempel), wurde `TRANSLATION_CACHE_SCHEMA_VERSION` erhöht (4) - macht
  den kompletten, bis dahin aufgewärmten Cache einmalig unerreichbar (jeder
  Text wird beim nächsten Bedarf einmal frisch übersetzt, alte Einträge
  bleiben als toter Ballast stehen, bis die neue Verdrängungslogik sie -
  mangels jedes Hit-Zählers - als Erstes wieder herausdrängt).
* **Build 73 stellt klar, dass "nur aktive Sprache" ausschließlich für den
  automatischen Live-Trigger gilt, nicht für Rescan, und macht den
  Persistierungs-Hinweis live sichtbar:** Zwei Nachbesserungen nach dem
  ersten Praxistest von Build 70/71. Erstens: Rescan (manuell wie
  Auto-Rescan) und der Quellsprachen-Abgleich (siehe Build 57) übersetzen
  ab sofort wieder ALLE konfigurierten Zielsprachen in einem Durchgang,
  nicht mehr nur die aktuell aktive - live gemeldet, nachdem gelöschte
  Objekte per Rescan zurückkehrten (bzw. eine Zelle manuell geleert wurde),
  aber keine ihrer Zielsprachen nachübersetzt wurde, solange sie nicht
  gerade aktiv war. Ein Nutzer, der "Baum neu einlesen" klickt oder eine
  Zelle absichtlich leert, erwartet zu Recht, dass JEDE fehlende
  Übersetzung nachgeholt wird, nicht nur die gerade angezeigte Sprache -
  das war ursprünglich zu weit gefasst: die eigentliche
  Kontingent-Ursache (siehe Build 70) war ausschließlich die automatische
  Live-Nachübersetzung bei externen Variablenänderungen
  (`ApplyTrackedVariableUpdate`, mehrmals pro Minute bei einer aktiven
  Wetter-/Sensor-Variable), NICHT ein einmaliger Rescan. Diese eine Stelle
  bleibt weiterhin bewusst auf die aktive Sprache beschränkt - der
  Nachhol-Mechanismus beim Sprachwechsel bleibt für sie zusätzlich als
  Backstop bestehen. Zweitens: der in Build 71 eingeführte
  Persistierungs-Hinweis im Formular wurde bislang nur beim (Neu-)Öffnen
  des Formulars berechnet - ein bereits offenes Formular zeigte ihn
  deshalb nie an, egal wie lange man wartete (folgerichtig, da Build 71
  bewusst kein `ReloadForm()` mehr bei externen Schreibvorgängen auslöst).
  Der Hinweis wird jetzt zusätzlich per `UpdateFormField()` direkt aus dem
  Puffer-Mechanismus heraus live in ein bereits offenes Formular
  eingeblendet bzw. wieder ausgeblendet - ohne jede Störung der laufenden
  Bearbeitung, exakt wie die bereits bestehenden `UpdateFormField()`-
  Aufrufe in anderen Formular-Popups.
* **Build 74 behebt eingeschleuste Platzhalter-Tags bei DeepL-Übersetzungen
  reiner Objektnamen:** Live gemeldet (Screenshot, zweimal beobachtet): ein
  völlig einfacher Objektname ohne jedes HTML ("N-JOY") kam auf Spanisch als
  `<g id="1">N-JOY</g>                    <g id="2"><g id="3"/></g>` zurück
  - sichtbare, kaputte Auszeichnungs-Reste mitten im Klartext. Ursache:
  `TranslateChunkDeepL()` hat bei JEDER Anfrage unterschiedslos
  `"tag_handling": "html"` an DeepL geschickt, unabhängig davon, ob der Text
  tatsächlich HTML enthielt (kopiert vom analogen, aber harmloseren Muster
  bei Google, siehe unten). Anders als Googles `format`-Parameter (der nur
  steuert, ob Sonderzeichen als HTML-Entity zurückkommen) schaltet DeepLs
  `tag_handling` seine komplette Markup-Verarbeitung ein - und kann dabei
  auch bei komplett taglosem Eingabetext eigene, synthetische
  Platzhalter-Tags in die Ausgabe einschleusen. `$IsHtml` wird jetzt bis zu
  `TranslateChunkGoogle()`/`TranslateChunkDeepL()` durchgereicht:
  `tag_handling` wird bei DeepL nur noch für echte "Eigene Texte"-HTML-
  Inhalte überhaupt gesetzt (sonst fehlt der Schlüssel im Request komplett -
  DeepLs Standardmodus ohne jede Markup-Erkennung, strukturell
  ausgeschlossen, dass so ein Platzhalter-Tag je entstehen kann). Bei dieser
  Gelegenheit auch Google angepasst: `format` steht jetzt nur noch bei
  echtem HTML auf `"html"`, sonst auf `"text"` - vermeidet nicht nur den
  bisher nötigen `html_entity_decode()`-Umweg für reinen Text, sondern
  schließt auch aus, dass ein wörtliches "&"/"<" in einem Objektnamen (z. B.
  "Bad & WC") im html-Modus fälschlich als Beginn einer HTML-Entity/eines
  Tags interpretiert wird.
* **Build 75 fasst inhaltlich identische Beschriftungen ohne geteiltes
  Profil/Template zu einer Zeile zusammen:** Live gemeldet (Screenshot):
  mehrere Variablen mit exakt identischem Beschriftungs-Inhalt (z. B. eine
  ganze Reihe "Ja"/"Nein"-Variablen) erschienen als komplett getrennte
  Zeilen in "Captions", obwohl über ein geteiltes Profil oder Template
  verknüpfte Variablen bereits korrekt zu einer Zeile zusammengefasst
  wurden. Ursache: `GetPresentationSourceKey()` fiel auf einen rein
  variablenspezifischen Schlüssel zurück, sobald eine Variable ihre
  `VariableCustomPresentation` INLINE trägt (kein Profilname, keine
  Template-GUID) - ein sehr verbreitetes Muster, da viele Symcon-
  Gerätetreiber dieselbe JSON-Struktur direkt in jede einzelne Variable
  schreiben, statt ein gemeinsames Template-Objekt zu referenzieren. Fällt
  jetzt zusätzlich auf einen Hash über den tatsächlich extrahierten,
  übersetzbaren Inhalt (Feldpfad + Text) zurück, wenn weder Profil noch
  Template vorliegen - zwei Variablen mit identischem Inhalt landen jetzt
  automatisch in derselben Zeile, auch ganz ohne eine geteilte Symcon-
  Objektidentität dahinter. Profil-/Template-basierte Gruppierung (bereits
  korrekt) bleibt davon komplett unberührt und hat weiterhin Vorrang - das
  ist bewusst so: verweisen zwei Variablen auf ein ECHTES, aber (durch ein
  fremdes Modul, z. B. Echo/Alexa) je Geräteinstanz eigenes Profil, bleiben
  sie zurecht getrennte Zeilen, selbst wenn ihr Inhalt gerade zufällig
  übereinstimmt - sonst würde eine spätere, unabhängige Änderung an EINEM
  der beiden Profile fälschlich beide Zeilen gemeinsam betreffen. Hinweis:
  nach dem ersten Rescan mit diesem Build bleiben die alten,
  variablenspezifischen Zeilen als verwaiste Duplikate bestehen (wie bei
  jedem entfernten/veränderten Objekt, siehe Abschnitt "Bekannte
  Einschränkungen") - können nach Prüfung der neuen, zusammengeführten
  Zeile manuell gelöscht werden.
* **Build 76 ergänzt "Aufräumen": verwaiste Zeilen künftig per Klick statt
  einzeln von Hand entfernbar.** Nutzer-Wunsch nach einem Feature-Vergleich
  mit Symcons eigener, konkurrierender Lösung - die hat eine entsprechende
  Funktion, Simple Locale bislang nicht. Bereits mit Build 51 (Root-Baum-
  Merge) bewusst als Designentscheidung eingeführt: Rescan/Auto-Rescan
  lassen Zeilen, deren Objekt inzwischen gelöscht oder aus der
  Visualisierung entfernt wurde, absichtlich unangetastet stehen (siehe
  `MergeRows`/`MergeEnumerationOptions`/`MergeAutomationRows`) - ein
  Sicherheitsnetz gegen eine versehentlich falsche/unvollständige Root-
  Kategorie, das schon mehrfach in dieser Historie vor Datenverlust
  geschützt hat (u. a. Build 75s eigene Migrationsnotiz direkt darüber).
  Der neue Button "Übersetzungen gelöschter Elemente in der Visualisierung
  entfernen" (siehe [Abschnitt 5](#5-einrichten-der-instanzen-in-symcon),
  Absatz "Aufräumen: verwaiste Zeilen endgültig entfernen") macht diese
  bislang nur manuelle, zeilenweise Aufräumarbeit zu einer bewussten,
  expliziten Ein-Klick-Aktion - mit ausdrücklicher Warnung im Formular,
  dass (anders als ein normaler Rescan) dabei tatsächlich unwiederbringlich
  gelöscht wird, falls die Root-Kategorie gerade falsch/unvollständig
  gewählt ist. "Begrüßung" bleibt bewusst ausgenommen (keine gescannte
  Liste, sondern eine einzelne direkt konfigurierte Einstellung).
* **Build 77 behebt eingefrorene deutsche Gast-Hinweise nach einer
  Anbieter-Pause und erweitert das Info-Popup der Kachel.** Live gemeldet:
  bei aktiv gewählter englischer Gast-Sprache blieben der
  Pausiert-Hinweis ("Übersetzung pausiert bis...") und der
  Statistik-Hinweis unter dem Dropdown trotzdem auf Deutsch stehen.
  Ursache: `EnsureGuestLanguageNamesFresh()` (der Cache für alle
  live in die Gast-Sprache übersetzten UI-Texte, max. 1x/Tag
  aktualisiert) lief während einer AKTIVEN Anbieter-Pause, der
  Übersetzungsversuch schlug dadurch für JEDEN Text zwangsläufig fehl
  und fiel korrekt auf den rohen deutschen Text zurück - wurde
  anschließend aber trotzdem als "heute schon erfolgreich aktualisiert"
  verbucht und dadurch bis zu 24 Stunden lang nicht erneut versucht,
  selbst nachdem die Pause längst vorbei war. Behoben durch dieselbe
  Kurzschluss-Prüfung wie in `TranslateChunk()` (während einer aktiven
  Pause lohnt sich gar kein Versuch, der bestehende - zuletzt ECHT
  erfolgreich übersetzte - Cache bleibt unangetastet) plus eine zweite
  Absicherung: der Cache gilt nur dann als "heute schon frisch", wenn
  wirklich mindestens ein Text erfolgreich übersetzt wurde, nicht bei
  einem kompletten (auch anbieter-pause-unabhängigen) Fehlschlag.
  Zusätzlich, auf Nutzer-Wunsch, zeigt das Info-Popup der Kachel (ⓘ-Symbol)
  jetzt zusätzlich dieselbe Übersetzungsstatistik (Seit Inbetriebnahme/
  Stündlich/Insgesamt/Durch den Cache eingespart, wie im
  Konfigurationsformular) sowie - falls gerade aktiv - einen Kurzhinweis
  zur laufenden Anbieter-Pause, beides live in die jeweils aktive
  Gast-Sprache übersetzt. Die bisherige Überschrift "Hinweise" wurde durch
  App-Name + Lizenz-Edition ersetzt (z. B. "Simple Locale - Pro Edition",
  ohne Lizenz schlicht "Simple Locale") - bewusst NICHT übersetzt, eine
  Marken-/Editionsbezeichnung ist sprachunabhängig, genau wie
  `$licenseInfo['edition']` im Konfigurationsformular selbst bereits als
  roher Wert behandelt wird.

  Außerdem, ebenfalls auf Nutzer-Wunsch: das 🌐-Emoji links neben dem
  Sprach-Dropdown wurde durch das eigentliche Simple-Locale-Symbol ersetzt
  (`libs/assets/module_icon_48.png`, als Base64-Grafik eingebettet - kein
  öffentlicher Pfad/Webhook nötig, funktioniert dadurch überall
  identisch). Fällt auf die alte 🌐-Glyphe zurück, falls die Bilddatei aus
  irgendeinem Grund nicht lesbar ist. Die zugehörige Einstellung heißt
  jetzt "Simple-Locale-Symbol in der Kachel anzeigen" (Property/Attribut-
  Name `ShowGlobeIcon` und CSS-Klasse `ipssl-globe` bleiben aus
  Kompatibilitätsgründen unverändert - siehe Abschnitt 7 für eigene,
  darauf aufbauende Kachel-Anpassungen).
* **Build 78 macht die festen Gast-Oberflächentexte komplett unabhängig von
  Anbieter-Pausen, ergänzt den Pause-Grund und weitere kleinere
  Verbesserungen.** Der eigentliche Kern dieses Builds, direkte Folge des
  in Build 77 gefundenen Bugs: die festen Gast-Oberflächentexte
  ("Übersetzung pausiert bis", die Statistik-Beschriftungen, der
  Info-Popup-Hinweistext, ...) laufen ab sofort NICHT mehr über einen
  24h-Live-Übersetzungs-Cache (`EnsureGuestLanguageNamesFresh`), der -
  genau dann, wenn er ausgerechnet während einer Anbieter-Pause aktualisiert
  wird - für den Rest des Tages auf Deutsch hängen bleiben konnte. Diese
  Texte werden jetzt genau wie Objektnamen/Automations beim Rescan EINMALIG
  in alle konfigurierten Zielsprachen übersetzt und dauerhaft in einer
  eigenen, neuen Property (`OwnUiTexts`) gespeichert - da sie fest im
  PHP-Code stehen und sich nur mit einem künftigen Modul-Update überhaupt
  ändern können, liegt die Übersetzung dadurch strukturell IMMER schon vor,
  bevor eine Pause je eine Rolle spielen könnte. Bewusst OHNE eigene Liste
  im Konfigurationsformular und ausdrücklich NICHT von "Aufräumen" (Build
  76) betroffen - der Admin kann diese Zeilen weder versehentlich löschen
  noch verändern, sie gehören zu keinem Symcon-Objekt und sollen dauerhaft,
  unabhängig von jeder Admin-Aktion, vorhanden bleiben. Ändert ein
  künftiges Modul-Update den deutschen Wortlaut eines dieser Texte, wird
  das beim nächsten Rescan automatisch erkannt und neu übersetzt (die alte
  Übersetzung bleibt bis dahin als Fallback sichtbar, statt sofort zu
  verschwinden).

  Zusätzlich, alles auf Nutzer-Wunsch: das Info-Popup nennt jetzt auch den
  GRUND einer laufenden Anbieter-Pause ("Grund: Alle konfigurierten
  Übersetzungsanbieter melden aktuell ihr Limit erreicht."), nicht mehr
  nur "bis wann". Die Überschrift des Info-Popups wird jetzt fett
  dargestellt - technisch über die "Mathematical Sans-Serif Bold"-Zeichen
  aus dem Unicode-Block "Mathematical Alphanumeric Symbols" (U+1D5D4 ff.),
  da `alert()` reiner Text ist und keine HTML-/Markdown-Formatierung
  kennt; sehen in praktisch jedem modernen Browser/Betriebssystem
  fettgedruckt aus, sind aber technisch eigene Zeichen statt eines
  Formatierungsattributs (deckt nur A-Z/a-z/0-9 ab, Leerzeichen/
  Sonderzeichen bleiben unverändert). Und: der graue Kreis-Hintergrund
  hinter dem Simple-Locale-Symbol in der Kachel (Build 77) wurde entfernt -
  nur noch das reine Symbol, ohne umschließende Form.
* **Build 79 behebt eine Lücke bei unterschiedlichen Quellsprachen: die
  Basissprache verschwindet nicht mehr aus der Gast-Auswahl, wenn die
  Scan-Sprache später geändert wird.** Bisher gab es neben den echten
  Zielsprachen eine separate Pseudo-Sprache "Original" (`ORIGINAL_IMPORT`),
  die sich immer auf die AKTUELL eingestellte Scan-Sprache
  (`SourceLanguage`) bezog. Das führte zu einer Lücke: wurde z. B. zuerst
  mit Deutsch gescannt und die Scan-Sprache danach auf Englisch
  umgestellt, verschwand Deutsch komplett aus der Gast-Auswahl - obwohl
  bereits gescannte Objekte weiterhin ihre deutsche Zeilen-Quellsprache
  trugen und für sie gar keine Übersetzung "zurück" nach Deutsch existierte
  (siehe `fieldRowSourceLanguage`, eingeführt in Build 57). "Original" gibt
  es ab jetzt nicht mehr als eigene wählbare Sprache: stattdessen sorgt
  eine neue Methode (`EnsureSourceLanguageIsTarget()`, aufgerufen am Anfang
  jedes `ApplyChanges()`-Laufs) dafür, dass die jeweils AKTUELLE
  Quellsprache immer als ganz normaler, dauerhafter Eintrag in den
  Zielsprachen (`TargetLanguages`) steht - ändert sich die Scan-Sprache
  später erneut, bleibt der alte Eintrag als normale Zielsprache stehen,
  statt zu verschwinden.

  **Wichtig für Lizenzen mit begrenzter Sprachanzahl** (z. B. die
  "Spezialversion"): der automatisch ergänzte Quellsprachen-Eintrag
  unterliegt exakt derselben Lizenz-Sprachobergrenze wie jede manuell
  hinzugefügte Zielsprache (`EnforceLicensedLanguageLimit()`, läuft direkt
  im Anschluss). Das ist bewusst so: ohne diese Kopplung könnte man durch
  wiederholtes Umstellen der Scan-Sprache beliebig viele "kostenlose"
  Zielsprachen an einer lizenzierten Obergrenze vorbei ansammeln. In der
  Praxis bedeutet das: bei einer Lizenz mit z. B. Sprachlimit 1, die
  bereits eine Zielsprache konfiguriert hat, verbraucht ein Wechsel der
  Scan-Sprache selbst einen Platz in dieser Obergrenze - im Zweifel wird
  dabei sogar eine zuvor konfigurierte Zielsprache automatisch verdrängt
  (dieselbe Kappungs-Logik wie bisher schon bei einem Lizenz-Downgrade).
  Unlimitierte Lizenzen sind davon nicht betroffen.

  Bereits bestehende Installationen: eine Instanz, deren aktive
  Gast-Sprache (`CurrentLanguage`) noch auf der alten Pseudo-Sprache
  "Original" stand, wird beim ersten `ApplyChanges()` nach diesem Update
  automatisch, einmalig auf die tatsächliche Quellsprache umgeschrieben -
  keine manuelle Nacharbeit nötig.
* **Build 80 behebt zwei Nachbesserungen an Build 79, die erst beim
  direkten Live-Test auffielen.** Erstens: ein reiner Rescan-Klick zeigte
  die Quellsprache weder in "Zielsprachen" noch als neue Spalte bei
  "Objektnamen"/"Eigene Texte" - `ScanRootTree()` liest die aktuelle
  Zielsprachenliste ganz am Anfang eines Durchlaufs, WEIT BEVOR
  `EnsureSourceLanguageIsTarget()` (das bisher nur in `ApplyChanges()`
  hing) überhaupt zum Zug kam - der neu ergänzte Eintrag kam dadurch immer
  einen ganzen Rescan-Durchlauf zu spät. `ScanRootTree()` ruft
  `EnsureSourceLanguageIsTarget()` jetzt selbst, ganz am Anfang, auf - ein
  einzelner Rescan reicht ab jetzt aus.

  Zweitens, unabhängig vom ersten Fund: bei einer Lizenz mit einer
  eingeschränkten `allowedLanguages`-Liste (nur bei gezielten
  Promo-Lizenzen wie "Finnisch zu Nikolaus" oder der
  "Nachbarländer"-Aktion, siehe `GetLicensedAllowedLanguages`) wurde der
  gerade erst ergänzte Quellsprachen-Eintrag von genau dieser Prüfung in
  JEDEM `ApplyChanges()`-Durchlauf sofort wieder entfernt, da die eigene
  Basissprache so gut wie nie in einer thematisch engen Promo-Sprachliste
  auftaucht - für diese Lizenzen hätte Build 79 dauerhaft überhaupt keine
  Wirkung gezeigt, egal wie oft man "Übernehmen" klickt. Die aktuelle
  Quellsprache ist jetzt explizit von der `allowedLanguages`-Einschränkung
  ausgenommen (das numerische Sprachlimit selbst bleibt davon unberührt -
  siehe oben, das ist weiterhin bewusst gewollt).
* **Build 81 behebt zwei weitere Anzeige-Lücken, die erst nach Build 80 im
  Live-Test auffielen - die Daten selbst waren zu diesem Zeitpunkt bereits
  korrekt gespeichert (per Debug-Meldung bestätigt), es fehlte nur die
  passende Darstellung.** Erstens übersprang `BuildLanguageColumnSet()`
  (baut die Sprachspalten für "Objektnamen"/"Eigene Texte"/"Aufzählungs-
  optionen"/"Automations"/"Begrüßung" auf) bislang grundsätzlich die Spalte
  für die instanzweite Quellsprache - korrekt VOR Build 79, als ihr Inhalt
  immer 1:1 identisch mit "Original import" war. Seitdem kann aber eine
  EINZELNE Zeile eine abweichende eigene Quellsprache tragen (z. B. eine
  ursprünglich englischsprachig gescannte Zeile in einem sonst deutschen
  Baum) - für so eine Zeile zeigt "Original import" weiterhin den
  englischen Rohtext, während die (bisher fehlende) "Deutsch"-Spalte die
  tatsächliche deutsche Übersetzung zeigen sollte, also einen eigenen,
  nicht-redundanten Wert. Die Spalte fehlte dadurch für den kompletten
  Baum, unabhängig davon, ob überhaupt eine Zeile abweichende Quellsprachen
  hatte. Für Zeilen mit einheitlicher Quellsprache bleibt der Spalteninhalt
  weiterhin redundant zu "Original import" - bewusst in Kauf genommen,
  keine Sonderlogik dafür.

  Zweitens zeigte "Zielsprachen" nach dem automatischen Ergänzen der
  Quellsprache (siehe Build 79) eine LEERE Zeile ohne sichtbaren
  Sprachnamen: `BuildTargetLanguageOptions()` liefert nicht nur die Auswahl
  für "Hinzufügen", sondern auch die Beschriftung, mit der die Liste jede
  bereits gespeicherte Zeile anzeigt - und schloss die Quellsprache
  ebenfalls grundsätzlich aus (plus, unter Testphase/`allowedLanguages`-
  Einschränkung, ein zweites Mal). Beide Ausschlüsse sind jetzt entfernt
  bzw. die Quellsprache explizit ausgenommen - dieselbe Ausnahme wie in
  Build 80 für `EnforceLicensedLanguageLimit()`, konsistent an beiden
  Stellen angewendet.
* **Build 82, auf Nutzer-Wunsch: die Spalte der Quellsprache bleibt beim
  Rescan nicht mehr leer, sondern übernimmt direkt den Rohtext.** Trifft
  eine Zeile beim Rescan auf eine Zielsprache, die genau ihrer eigenen
  Quellsprache entspricht (siehe Build 79/81), gibt es nichts zu
  übersetzen - der Rohtext IST bereits der korrekte Inhalt. Bisher blieb
  die Zelle in diesem Fall trotzdem leer (kein API-Aufruf, aber auch kein
  Kopiervorgang), was in der Admin-Ansicht wie eine fehlende Übersetzung
  aussah. Die Zelle wird jetzt direkt mit dem Rohtext befüllt - ohne
  API-Aufruf, ohne Übersetzungs-Kontingent zu verbrauchen - unter der
  Annahme, dass dieser Text bereits gut genug ist; der Admin kann ihn wie
  jede andere Zelle jederzeit manuell korrigieren, eine bereits gefüllte
  oder korrigierte Zelle wird dabei nie überschrieben.

  Außerdem, ebenfalls auf Nutzer-Wunsch: das Simple-Locale-Symbol links
  neben dem Sprach-Dropdown skaliert jetzt in der Höhe exakt auf die Höhe
  der Dropdown-Box, statt einer festen Pixelgröße - passt sich dadurch
  automatisch an, falls Schriftgröße/Innenabstand des Dropdowns sich
  künftig ändern (z. B. durch eigenes Kachel-HTML, siehe Abschnitt 7).
* **Build 83, auf Nutzer-Wunsch: das Panel "Übersetzungsanbieter" spiegelt
  jetzt wider, wie zuverlässig die angezeigte Pause-Zeit je Anbieter
  tatsächlich ist, plus dieselbe Formatierungs-Korrektur wie bei der
  Statistik.** Bisher zeigten alle drei Anbieter (Google, DeepL,
  kostenfreier Anbieter/MyMemory) dieselbe generische, EXPONENTIELL
  eskalierende Schätzung ("jetzt + 15min/30min/1h/2h/... bis maximal 24h",
  siehe `RecordProviderPaused`) als "pausiert bis" an - unabhängig davon,
  ob diese Schätzung für den jeweiligen Anbieter überhaupt etwas mit der
  Realität zu tun hat:
  - **MyMemory** setzt sein kostenfreies Tageskontingent nachweislich
    zuverlässig um Mitternacht UTC zurück (fest, bekannt) - die generische
    "jetzt + 24h"-Schätzung konnte dadurch je nach Tageszeit des
    Fehlschlags um bis zu fast 24 Stunden danebenliegen. Wird jetzt exakt
    auf die nächste UTC-Mitternacht berechnet (`GetNextUtcMidnightTimestamp`) -
    sowohl beim eindeutigen `quotaFinished`-JSON-Signal als auch beim
    generischen HTTP-429-Pfad, sofern dort ein Tageskontingent (nicht nur
    ein kurzes Burst-Limit) erkannt wurde.
  - **Google** liefert dagegen keine verlässliche Reset-Zeit (siehe
    `RecordProviderPaused`: live beobachtet, dass ein erkanntes Rate-Limit
    trotzdem über Stunden bestehen blieb) - die Zeile heißt jetzt
    "voraussichtlich pausiert bis" statt schlicht "pausiert bis", um die
    angezeigte Zeit klar als Schätzung statt als Zusage zu kennzeichnen.
  - **DeepL** bekommt dieselbe "voraussichtlich"-Formulierung PLUS einen
    zusätzlichen Hinweis darunter: bei DeepL ist ein aufgebrauchtes
    Kontingent nicht garantiert automatisch zurückgesetzt - bloßes Warten
    hilft dann nicht, nur ein Kauf bei DeepL oder ein neuer API-Key.

  Außerdem, wie von der Statistik-Sektion bekannt: die bisher separat
  schwebende ":" -Beschriftung (eigenes Label-Element, dadurch je nach
  Länge des Anbieternamens unterschiedlich weit vom Text entfernt) ist
  jetzt direkt an den Anbieternamen angehängt ("Google Cloud Translate:"
  statt "Google Cloud Translate" + ":" als getrennte Elemente) - dieselbe
  Technik wie bei "Stündlich:"/"Insgesamt:" im Statistik-Panel.
* **Build 84 behebt zwei weitere, live gefundene Probleme.** Erstens war
  das Simple-Locale-Symbol nach Build 82 auf manchen Kacheln sichtbar
  GRÖSSER als das Dropdown, nicht exakt gleich hoch: die Höhenanpassung
  lief über `align-self: stretch`, was das Icon auf die Höhe der GESAMTEN
  Zeile (`.ipssl-select-row`) skalierte - in der echten Kachel-Darstellung
  bekommt diese Zeile aber offenbar mehr Höhe zugewiesen, als das Dropdown
  selbst braucht, wodurch auch das Icon zu groß wurde. Gelöst über eine
  gemeinsame, feste CSS-Variable (`--ipssl-control-height`), die Dropdown
  UND Icon jetzt beide explizit auf denselben Wert setzt - unabhängig
  davon, wie viel Höhe die umgebende Zeile tatsächlich bekommt.

  Zweitens, deutlich wichtiger: eine String-Variable im gescannten Baum
  kann statt echtem Gast-Anzeigetext auch Konfigurations-/Steuerdaten für
  ein GANZ ANDERES Modul enthalten (live beobachtet: eine
  Favoriten-/Playlist-Liste mit Inhalt wie
  `{"musicProvider":"CLOUDPLAYER","searchPhrase":"Mein Discovery Mix"}`).
  Simple Locale übersetzte diesen Rohtext bisher wie gewöhnlichen
  Fließtext - Google/DeepL lieferten dabei u. a. HTML-kodierte
  Anführungszeichen (`&quot;` statt `"`) zurück, was die JSON-Struktur für
  das eigentlich konsumierende Skript zerstörte (z. B. erwartete dieses
  Skript für `musicProvider` einen bestimmten, festen Wert). Eine gezielte
  "nur die Anzeigetexte innerhalb des JSON übersetzen, Struktur/Schlüssel
  erhalten"-Lösung wäre technisch nicht zuverlässig umsetzbar: strukturelle
  Schlüssel/Enum-Werte (z. B. `"CLOUDPLAYER"`) lassen sich innerhalb
  desselben JSON nicht verlässlich von echtem, übersetzbarem Anzeigetext
  unterscheiden - eine "intelligente" JSON-Teilübersetzung würde ebenso oft
  falsch raten und stattdessen ein trügerisches Sicherheitsgefühl erzeugen.
  Erkennt Simple Locale jetzt, dass ein Rohtext gültiges JSON ist (beginnt
  mit `{` oder `[` UND lässt sich vollständig parsen - ein einzelnes Wort
  oder eine Zahl wie "42"/"true" zählt bewusst NICHT als JSON, da das
  weiterhin ganz normaler übersetzbarer Text sein kann), wird dieser
  Rohtext von der Übersetzung komplett ausgenommen - für JEDE Gast-Sprache
  bleibt automatisch der unveränderte Rohtext sichtbar (bestehender
  Rohtext-Fallback, keine neue Logik nötig). Bereits VOR diesem Update
  fehlerhaft übersetzte JSON-Zellen bleiben bestehen (wie bei jeder anderen
  Fehlübersetzung, siehe unten) - einmalig die betroffene Zelle leeren,
  "Übernehmen" klicken, dann erneut Rescan ausführen, danach bleibt sie
  dauerhaft leer/unverändert (kein erneuter Übersetzungsversuch mehr).
* **Build 85, auf Nutzer-Wunsch: die eigenen Gast-Oberflächentexte (siehe
  Build 78) bringen jetzt feste Standard-Übersetzungen fürs Ausliefern mit
  - für de/en/es/it/fr/nl sowie alle `TRIAL_LANGUAGE_CODES` (isländisch,
  walisisch, zulu, māori, latein) steht die Übersetzung dieser Texte sofort
  bereit, ganz ohne einen einzigen API-Aufruf bei irgendeinem Provider zu
  verbrauchen - selbst direkt nach einer frischen Installation.** Neue
  Konstante `OWN_UI_TEXT_BUNDLED_TRANSLATIONS`, eingebunden in
  `MergeOwnUiTextRows()`: füllt beim Rescan JEDE noch leere Sprachspalte
  einer dieser mitgelieferten Sprachen direkt mit dem fest hinterlegten
  Text - unabhängig von den aktuell konfigurierten Zielsprachen, damit die
  Übersetzung sofort bereitsteht, sobald eine dieser Sprachen jemals als
  Zielsprache gewählt wird. Eine bereits vorhandene (echte, per Provider
  erzeugte) Übersetzung wird dabei nie überschrieben. en/es/it/fr
  übernehmen bewusst denselben Wortlaut wie die längst vorhandenen
  Konsolensprachen-Übersetzungen derselben deutschen Texte (z. B.
  "Stündlich:" → "Hourly:"), damit Konsolen- und Gast-Oberfläche
  konsistent klingen.

  Außerdem, ebenfalls auf Nutzer-Wunsch: das Panel "Übersetzungsanbieter"
  nutzt für MyMemory jetzt den WIRKLICH genauen Reset-Zeitpunkt statt der
  Build-83-Annahme "nächste UTC-Mitternacht" - MyMemorys Fehlermeldung
  nennt die verbleibende Wartezeit meist direkt und exakt (z. B. "NEXT
  AVAILABLE IN 02 HOURS 51 MINUTES 23 SECONDS"), live beobachtet und
  spürbar präziser als die reine Mitternachts-Annahme (das Kontingentfenster
  scheint nicht zwingend exakt auf UTC-Mitternacht zu fallen, sondern eher
  rollierend ab dem ersten Verbrauch zu laufen). Neue
  `ParseMyMemoryNextAvailableTimestamp()` extrahiert diesen Countdown direkt
  aus MyMemorys Antworttext; die UTC-Mitternacht-Berechnung aus Build 83
  bleibt als Rückfallwert bestehen, falls das Muster einmal nicht gefunden
  wird (z. B. bei einer künftig geänderten Formulierung).
* **Build 86 behebt einen Bug in Build 85, live gefunden: eine bereits als
  Gast-Sprache aktive, mitgelieferte Sprache (z. B. Englisch) zeigte
  trotzdem den deutschen Rohtext.** Ursache: die neuen mitgelieferten
  Übersetzungen (`OWN_UI_TEXT_BUNDLED_TRANSLATIONS`) landeten bisher NUR
  über `MergeOwnUiTextRows()` in der jeweiligen Zeile - und diese Funktion
  läuft ausschließlich innerhalb `ScanRootTree()`, also nur bei einem
  tatsächlichen Rescan. Vor dem allerersten Rescan (oder direkt nach dem
  Update auf Build 85, bevor ein neuer Rescan lief) blieb
  `propertyOwnUiTexts` für diese Sprache leer, und `GetOwnUiText()` fiel
  auf den deutschen Rohtext zurück - genau das Gegenteil des in Build 85
  versprochenen "sofort bereit, kein Rescan nötig". `GetOwnUiText()` (der
  eigentliche Lesepfad, den jeder Gast-Text-Baustein nutzt) greift jetzt
  zusätzlich direkt auf die mitgelieferte Übersetzungstabelle zurück, wenn
  weder eine persistierte Zeile noch eine Zelle darin etwas liefert -
  unabhängig davon, ob/wann je ein Rescan lief. Eine bereits persistierte
  Zeile (echte Provider-Übersetzung, manuelle Korrektur oder ein durch
  einen späteren Rescan eingetragener Bundled-Wert) hat weiterhin Vorrang
  vor diesem Fallback.
* **Build 87 behebt zwei weitere, live gefundene Probleme und dokumentiert
  eine strukturelle Einschränkung.**

  Erstens: eine korrekte, mit hoher Konfidenz bestätigte Übersetzung, die
  zufällig identisch zum Ausgangstext blieb (z. B. "Cover" bleibt auch auf
  Spanisch "Cover" - ein echtes Lehnwort, von MyMemory selbst mit
  `match: 1` bestätigt), wurde bisher fälschlich als gescheiterter
  Übersetzungsversuch gewertet und dauerhaft verworfen - die Zelle blieb
  leer UND wurde bei JEDEM weiteren Rescan erneut angefragt, ohne je
  "fertig" zu werden (treffend als drohender Deadlock beschrieben: Zellen,
  deren korrekte Übersetzung zufällig gleich dem Rohtext bleibt, wie auch
  technische, gar nicht übersetzbare Bezeichner wie `SetVisibilityOff`
  innerhalb von Automatisierungs-Aktionsnamen, konnten so nie einen
  stabilen, gecachten Endzustand erreichen). Ursache war eine
  Unterscheidungslücke: `TranslateBatchUncached()` fällt bei einem
  ECHTEN Anbieter-Fehlschlag bewusst auf den unübersetzten Rohtext zurück
  (verhindert eine leere/kaputte HTML-Struktur, siehe Build-70-Historie),
  aber das Ergebnis sah für die aufrufende Stelle identisch aus wie eine
  ECHTE, zufällig gleichbleibende Übersetzung - beide wurden bisher über
  einen reinen Textvergleich ("Ergebnis == Rohtext?") auseinandergehalten,
  einer strukturell unzuverlässigen Heuristik. `TranslateBatchUncached()`
  liefert jetzt zusätzlich ein echtes, aus `TranslateChunk()` stammendes
  `failed`-Flag pro Text mit - kein Rätselraten mehr nötig: eine echte
  Übersetzung (auch wenn zufällig identisch) gilt jetzt korrekt als
  erfolgreich und wird gecacht, nur ein wirklicher Fehlschlag bleibt leer
  und offen für den nächsten Versuch.

  Zweitens: der rote "Übersetzung pausiert bis..."-Hinweis auf der Kachel
  blieb nach Ende einer Anbieter-Pause teils MINUTENLANG stehen, obwohl
  `GetGlobalPauseUntil()` selbst jederzeit korrekt (nicht
  zwischengespeichert) den aktuellen Zustand lieferte und ein manueller
  Anbieter-Test im Formular längst wieder Erfolg meldete - reines
  Anzeigeproblem, keine fehlerhafte Übersetzungs-Entscheidung dahinter.
  `ClearProviderPause()` (läuft bei jedem echten Übersetzungserfolg)
  aktualisiert zwar sofort den gespeicherten Pause-Zustand, pusht aber nie
  von sich aus eine aktualisierte Anzeige an bereits geöffnete
  Gast-Kacheln - und auch ein rein zeitliches Ablaufen der Pause (ganz
  ohne neuen Übersetzungsversuch) hat dafür ohnehin keinen eigenen
  Auslöser. Der bisher nur für die Statistik-Zeile gedachte periodische
  Kachel-Refresh (`RefreshTranslationStatsTile`, zuvor an
  `propertyShowTranslationStats` gekoppelt und alle 10 Minuten) läuft ab
  jetzt IMMER (unabhängig von dieser Einstellung) und alle 2 statt 10
  Minuten - er aktualisiert ohnehin die komplette Gast-Anzeige in einem
  Rutsch (Statistik UND Pause-/Testphase-Hinweis), verursacht dabei
  weiterhin keinen einzigen API-Aufruf (reine `PushVisualizationUpdate()`-
  Neuberechnung).
* **Build 88, auf Nutzer-Wunsch: "Baum neu einlesen" zeigt jetzt einen
  Fortschrittsbalken im Konfigurationsformular, solange der Rescan
  läuft.** Bisher war ein länger laufender Rescan (viele neue
  Übersetzungen, mehrere API-Aufrufe) im Formular selbst nicht von einem
  eingefrorenen/nicht reagierenden Modul zu unterscheiden - erkennbar war
  das bislang nur über die Debug-Meldungen-Konsole, die kaum ein
  gewöhnlicher Nutzer je öffnet. Neues `ProgressBar`-Formularelement
  (`indeterminate`, also eine laufende Animation statt eines exakten
  Prozentwerts - eine echte Prozentanzeige würde eine deutlich tiefere
  Umstrukturierung der Übersetzungs-Batches erfordern, für vergleichsweise
  wenig zusätzlichen Nutzen), dessen Beschriftung während `ScanRootTree()`
  bei jedem Verarbeitungsschritt per `UpdateFormField()` live aktualisiert
  wird ("Baum wird eingelesen…" → "Objektnamen und Texte werden
  übersetzt…" → "Weitere Inhalte werden übersetzt…" → "Ergebnis wird
  gespeichert…") - nach demselben Prinzip, nach dem auch die
  Debug-Konsole schon während eines laufenden Skripts live neue Einträge
  zeigt, nicht erst nach Skriptende. Wird sowohl beim Abbruch (unbenannte
  Objekte gefunden) als auch beim regulären Abschluss zuverlässig wieder
  ausgeblendet - unabhängig davon, ob es sich um einen manuellen oder
  einen automatischen Hintergrund-Rescan handelt (letzterer ruft nie
  `ReloadForm()` auf, das Ausblenden darf deshalb nicht daran hängen -
  sonst genau dieselbe Art von hängenbleibender Anzeige wie der in
  Build 87 behobene Pause-Hinweis).
* **Build 89, auf Nutzer-Wunsch: neue Liste "Eigene Übersetzungstabelle" -
  ein admin-gepflegtes Glossar, das jeder automatischen Übersetzung
  (Google/DeepL/MyMemory) vorgezogen wird.** Aufbau wie "Objektnamen"
  (eine Quellsprachen-Spalte + eine Spalte je Zielsprache), aber komplett
  eigenständig admin-editierbar statt aus dem Objektbaum gescannt - über
  den "Hinzufügen"-Button der Liste selbst legt der Admin eine neue Zeile
  an, wählt die Quellsprache, trägt den Quelltext ein und füllt beliebig
  viele Zielsprachen-Zellen aus. Trifft der Quelltext einer Zeile hier
  (zeichengenau, kein Fuzzy-Matching) auf den Rohtext IRGENDEINER anderen
  Zeile in dieser Instanz zu (Objektnamen, Eigene Texte,
  Aufzählungsoptionen, Automatisierungen, Begrüßung), wird für jede
  ausgefüllte Zielsprachen-Zelle diese Übersetzung verwendet statt eines
  Anbieter-Aufrufs - und zwar mit höherer Priorität als sogar der interne
  Übersetzungs-Cache. Bewusst zellenweise: eine ansonsten passende Zeile
  mit einer noch LEEREN Zelle für eine bestimmte Zielsprache blockiert die
  automatische Übersetzung NUR für andere, bereits ausgefüllte Sprachen
  nicht - für diese eine Sprache läuft die automatische Übersetzung ganz
  normal weiter. Neues Lizenz-Feature `manual_translations` (ab
  Standard-Lizenz, unabhängig vom bestehenden `edit_translations` für das
  nachträgliche Korrigieren einzelner Auto-Übersetzungszellen) - ohne
  dieses Feature bleibt die Property zwar erhalten (kein Datenverlust bei
  einem Lizenz-Downgrade), wirkt sich aber gar nicht mehr aus, weder beim
  Bearbeiten noch bei der Anwendung bereits gespeicherter Zeilen.
* **Build 90 behebt einen strukturellen Bug, live gefunden: ein
  Sprachwechsel konnte in "Aufzählungsoptionen" ("Captions") Dopplungs-
  Zeilen erzeugen, deren "Original import" tatsächlich die zuletzt
  angezeigte ÜBERSETZUNG war, fälschlich als Quellsprache markiert.**
  Zeigt eine Variable ihre Beschriftung über ein geteiltes Profil/Template
  (z. B. "An"/"Aus"), kann Simple Locale beim Live-Anzeigen einer anderen
  Sprache keine einzelnen Felder an einer WEITERHIN referenzierten
  Quelle überschreiben - die Variable wird deshalb bewusst "geforkt"
  (die Profil-/Template-Referenz entfernt, die Übersetzung stattdessen
  direkt inline in die Variable geschrieben, siehe
  `ApplyEnumerationOptionsToVariable`). Bis Build 90 verlor die Variable
  dadurch aber auch dauerhaft die einzige Spur ihrer ursprünglichen,
  geteilten Identität: ohne erkennbare Profil-/Template-Referenz fiel die
  Zeilenerkennung beim nächsten Rescan auf einen Hash über den GERADE
  angezeigten Text zurück (Build 75, für Variablen, die von Haus aus kein
  geteiltes Profil/Template nutzen) - und dieser Hash änderte sich mit
  JEDEM Sprachwechsel, weil sich der angezeigte Text änderte. Der nächste
  Rescan erkannte die Zeile dadurch nicht wieder, hielt den gerade
  angezeigten (oft längst übersetzten) Text für frischen Quelltext und
  legte eine neue, falsch beschriftete Dopplungs-Zeile an - reproduzierbar
  bei jedem automatischen Rescan, solange die Kachel auf einer anderen
  als der Quellsprache stand. Der bereits vor dem allerersten Fork
  gesicherte Rücksprung-Zustand
  (`attributeEnumerationPresentationBackup`, ursprünglich nur fürs
  Zurückschalten auf "Original" gedacht) kennt die echte, stabile
  Profil-/Template-Referenz aber weiterhin - `GetPresentationSourceKey()`
  leitet den Zeilen-Schlüssel jetzt bevorzugt daraus ab, sobald ein
  Backup existiert, unabhängig davon, welche Sprache die Variable gerade
  live anzeigt.

  Zweiter, tieferer Teil desselben Bugs: Variablen OHNE jedes geteilte
  Profil/Template (eigenständige, direkt vom Gerätetreiber gesetzte
  Inline-Präsentation, siehe Build 75) verlieren durch den Fork nicht nur
  eine Referenz, sondern ihren GESAMTEN stabilen Rohtext - für sie reicht
  die stabile Referenz allein nicht, da der Rohtext selbst (nicht nur ein
  Verweis darauf) in den Content-Hash einfließt. `ReadTranslatablePresentation()`
  liest den zu extrahierenden Inhalt jetzt ebenfalls bevorzugt aus dem
  Backup, sobald dieses selbst keine Profil-/Template-Referenz enthält -
  hat der Backup dagegen eine Referenz (der häufigere Fall), bleibt es bei
  der live aufgelösten Präsentation für den Inhalt (der Backup ist dort
  nur eine dünne Referenz ohne eigene Beschriftungen), die
  Zeilenerkennung ist für diesen Fall bereits über die Schlüssel-Ableitung
  oben abgesichert.

  **Bereits bestehende Installationen:** durch den Bug bereits entstandene
  Dopplungs-Zeilen verschwinden nicht automatisch rückwirkend - einmal
  "Baum neu einlesen" (damit die betroffenen Variablen wieder unter ihrem
  stabilen Schlüssel erkannt werden) und danach einmal "Aufräumen"
  (entfernt die jetzt nicht mehr auffindbaren alten Dopplungs-Zeilen,
  siehe Build 76) klicken.
* **Build 91, auf Nutzer-Wunsch: der "Hinzufügen"-Button der "Eigenen
  Übersetzungstabelle" ist jetzt ebenfalls deaktiviert, wenn die Lizenz
  das Feature `manual_translations` (Build 89) nicht enthält.** Die
  Zellen selbst waren dafür bereits schreibgeschützt (siehe
  `BuildListColumns`), der Button blieb bisher aber unabhängig davon
  klickbar - ein Klick legte eine neue Zeile an, die sich anschließend
  gar nicht mehr bearbeiten ließ, ein verwirrender Sackgassen-Zustand.
  Außerdem ist `manual_translations` jetzt ab der Standard-Lizenz Teil
  des offiziellen Feature-Sets (Standard UND Pro, siehe
  `includes/products.php` im Shop-Repository) - bereits VOR diesem Update
  ausgestellte Lizenzschlüssel enthalten dieses Feature naturgemäß noch
  nicht (ein Lizenzschlüssel ist beim Ausstellen kryptografisch signiert
  und lässt sich nicht nachträglich ändern) und benötigen einen neu
  ausgestellten Schlüssel, um die "Eigene Übersetzungstabelle"
  tatsächlich nutzen zu können.
* **Build 92:** die Überschrift der Liste selbst ("Eigene
  Übersetzungstabelle", live gemeldet) fehlte noch in `locale.json` und
  blieb dadurch bei jeder Konsolensprache auf Deutsch stehen, obwohl die
  Spalten-/Beschreibungstexte bereits korrekt übersetzt wurden - Build 89
  hatte nur Letztere ergänzt, den kurzen Listentitel selbst aber
  übersehen. Ergänzt für en/es/it/fr.
* **Build 93 behebt einen wichtigen Bug in der "Eigenen
  Übersetzungstabelle" (Build 89): ein Glossar-Eintrag wirkte bisher nur
  auf noch LEERE Zellen, nicht auf bereits (ggf. falsch) automatisch
  übersetzte.** Live gefunden: "SSW" (Windrichtung Süd-Südwest) wurde von
  Google fälschlich als Abkürzung für "Schwangerschaftswoche" erkannt und
  mit "week of pregnancy" "übersetzt" - ein extra dafür angelegter
  Glossar-Eintrag ("SSW" → "SSW") blieb trotzdem komplett wirkungslos,
  weil die betroffene Zielsprachen-Zelle bereits (falsch) befüllt war und
  die Glossar-Prüfung bisher nur innerhalb `TranslateBatch()` lief - dort
  aber nur für noch unübersetzte ("pending") Zellen erreichbar ist, exakt
  wie bei jeder anderen Zelle auch (schützt normalerweise echte manuelle
  Korrekturen vor versehentlichem Überschreiben). Das widersprach aber der
  ursprünglichen Anfrage wörtlich: ein Glossar-Eintrag soll "immer
  Vorrang vor Online-Übersetzungen" haben - was nur funktionieren kann,
  wenn er auch eine bereits gefüllte Zelle überschreiben darf. Neue
  `ApplyManualTranslationOverrides()` läuft jetzt bei JEDEM Rescan VOR der
  normalen Fülllogik und prüft JEDE Zelle jeder Zeile gegen das Glossar -
  unabhängig davon, ob sie bereits befüllt ist -, und überschreibt sie,
  sobald ein passender Eintrag mit abweichendem Wert existiert. Eine
  bereits korrekte Zelle (Wert entspricht bereits dem Glossar-Eintrag)
  bleibt dabei unverändert (kein unnötiges Neu-Markieren). Betrifft
  bewusst auch die Quellsprachen-Spalte selbst (z. B. um einen Tippfehler
  im gescannten Rohtext gezielt zu korrigieren, ohne den eigentlichen
  Objektnamen anzufassen). Die bisherige Prüfung innerhalb
  `TranslateBatch()` bleibt zusätzlich bestehen - notwendig für die live
  nachübersetzten "Eigenen Texte" (siehe `ApplyTrackedVariableUpdate`),
  die nicht über den normalen Rescan-Pfad laufen.
* **Build 94, rein diagnostisch: temporäres `SendDebug`-Logging (Kategorie
  `IPSSL_GreetingDiag`) rund um die "Begrüßung" (Modus "Variable").** Live
  gemeldet: nach einem Sprachwechsel in der Gäste-Visu (de → en) und einem
  anschließenden Rescan stand `ORIGINAL_IMPORT` der Begrüßungs-Zeile
  fälschlich auf dem englischen Text, obwohl die Quellsprache weiterhin
  `de` ist - trotz des seit Build (siehe Commit `6ae1bd3`) bestehenden
  `IsSourceLanguageActive`-Schutzes in `MergeGreetingRows()`, der genau
  das verhindern soll. Reine Code-Analyse konnte den genauen Leck-Punkt
  nicht zweifelsfrei bestimmen - zwei Kandidaten: (a) der Schutz in
  `MergeGreetingRows()` selbst greift in diesem konkreten Fall nicht wie
  erwartet, oder (b) der eigentliche Schreib-/Übersetzungsvorgang von
  `ApplyGreetingLanguage()` (beim Sprachwechsel selbst, nicht erst beim
  Rescan) über `HandleTrackedVariableUpdate()`/`ApplyTrackedVariableUpdate()`
  läuft - letztere Funktion hat KEINEN `IsSourceLanguageActive`-Schutz und
  würde jeden als "extern" erkannten Schreibvorgang ungeprüft als frischen
  Rohtext übernehmen. Das Logging deckt beide Kandidaten ab: Reihenfolge
  von `SetValueString()` und dem Self-Write-Guard-Attribut in
  `WriteTrackedValueString()`, den tatsächlich ausgewerteten
  `IsSourceLanguageActive`-Wert in `ScanRootTree()`, sowie jeden Schreibpfad
  in `ApplyGreetingLanguage()`/`ApplyTrackedVariableUpdate()` mit den
  jeweils beteiligten Rohwerten. Rein additiv, keine Verhaltensänderung
  (volle Regressionssuite unverändert grün) - wird entfernt bzw. durch die
  eigentliche Korrektur ersetzt, sobald die Logs den Mechanismus bestätigt
  haben.
* **Build 95 behebt den in Build 94 diagnostizierten Bug und entfernt das
  dortige temporäre Logging wieder.** Der Log-Dump des Nutzers hat den
  Mechanismus zweifelsfrei belegt (Kandidat b aus Build 94): `existingGreeting`
  und `mergedGreeting` beim Rescan waren korrekt deutsch -
  `IsSourceLanguageActive` in `MergeGreetingRows()` funktioniert also wie
  vorgesehen. Trotzdem zeigte die Zeile direkt nach `IPS_SetProperty()` +
  `IPS_ApplyChanges()` wieder den englischen Text, während `de`/`en`/`es`
  unverändert blieben - ein klares Zeichen für einen gezielten Feld-Patch,
  keinen vollständigen Zeilenaustausch. Ursache: irgendwann zuvor (Zeitpunkt
  unklar, vermutlich ein seltenes Timing-Fenster im Selbst-Schreib-Schutz
  `attributeLastSelfWrittenValues`) hatte `WriteTrackedValueString()`s
  eigener Übersetzungs-Schreibvorgang für die Begrüßungsvariable
  `HandleTrackedVariableUpdate()` ausgelöst, ohne vom Selbst-Schreib-Schutz
  erkannt zu werden - `ApplyTrackedVariableUpdate()` hat den englischen Text
  daraufhin (ohne jeden `IsSourceLanguageActive`-Schutz, den nur
  `MergeGreetingRows()` kennt) als vermeintlich frischen deutschen Rohtext
  übernommen und über `BufferPendingTrackedRowUpdate()` gepuffert. Dieser
  längst veraltete Puffer-Eintrag blieb liegen, bis der o. g. Rescan lief:
  dessen abschließendes `IPS_ApplyChanges()` reentert in `ApplyChanges()`,
  das als einen seiner ersten Schritte `FlushPendingTrackedRowUpdates()`
  aufruft - und DAS hat den falschen Puffer-Eintrag über das gerade eben
  korrekt geschriebene Ergebnis geschrieben. Ein zeitpunktunabhängiger Fix
  direkt am Symptom statt am (schwer greifbaren) Timing-Fenster:
  `ApplyTrackedVariableUpdate()` bricht jetzt sofort ab, wenn der neu
  beobachtete Wert exakt der bereits gespeicherten Übersetzung dieser Zeile
  für die AKTUELL aktive Sprache entspricht - das ist so gut wie sicher ein
  Echo des eigenen Schreibvorgangs, kein echter externer Inhaltswechsel (ein
  Fremdmodul/Zeitplan-Skript, das z. B. eine Tageszeit-Begrüßung schreibt,
  träfe kaum je zufällig exakt den Text einer vorhandenen Übersetzung). Der
  legitime externe-Update-Anwendungsfall aus Build 70 (z. B. ein häufig
  aktualisiertes Wetter-Widget) bleibt davon unberührt, da ein echter neuer
  Messwert praktisch nie mit einer gespeicherten Übersetzung übereinstimmt.
* **Build 96, auf Nutzer-Wunsch: sichtbare Rückmeldung für alle Buttons im
  Konfigurationsformular.** "Lizenz aktivieren" hatte bereits ein passendes
  Popup und "Übersetzungs-Cache leeren" stellte sich bei der Durchsicht als
  bereits vollständig umgesetzt heraus (Popup `CacheClearedPopup` mit
  Erfolgsmeldung, in allen vier Sprachen lokalisiert, nach demselben Muster
  wie das Ergebnis-Popup der Anbieter-Prüfung) - keine Änderung nötig. Neu
  bekommen "Übersetzungen gelöschter Elemente entfernen" (Aufräumen) und
  "Übersetzungsanbieter prüfen" je einen eigenen Fortschrittsbalken
  (`CleanupProgressBar`/`ProviderCheckProgressBar`, dieselbe `ProgressBar`-
  Anzeige wie beim Rescan seit Build 88), sichtbar ab Klick bis kurz vor dem
  jeweiligen Ergebnis (Popup bzw. Formular-Neuladen) - selbst wenn "Aufräumen"
  in der Praxis meist nur einen kurzen Moment dauert, bestätigt das kurze
  Aufblitzen dem Nutzer, dass der Klick etwas ausgelöst hat, statt scheinbar
  wirkungslos zu bleiben. Neue gemeinsame Hilfsfunktion `SetButtonProgress()`
  - dieselbe Live-Push-Logik wie `SetRescanProgress()`, aber ohne dessen
  persistierten Attribut-Zustand (der nur für einen ggf. minutenlangen Rescan
  gebraucht wird, den ein wieder geöffnetes Formular nachträglich anzeigen
  können muss - "Aufräumen"/"Anbieter prüfen" laufen synchron innerhalb eines
  einzigen `RequestAction()`-Aufrufs und sind dafür zu kurzlebig). Das
  bestehende Ergebnis-Popup der Anbieter-Prüfung bleibt unverändert - der
  Fortschrittsbalken blendet sich unmittelbar davor aus.
* **Build 97, auf Nutzer-Nachfrage geprüft: die in `library.json` deklarierte
  Mindestversion Symcon 7.1 stimmt weiterhin, ein realer Lücke in der
  Absicherung von `ApplyEnumerationOptionsToVariable()` wurde dabei aber
  gefunden und geschlossen.** `IPS_SetVariableCustomPresentation()` und
  `IPS_GetVariablePresentation()` - Symcons Presentation-System, seit Build 90
  Grundlage für die Übersetzung von Enum-/Profil-Beschriftungen (Dropdowns,
  Status-Variablen usw.) - sind laut offizieller Symcon-Dokumentation erst
  "seit 8.0" verfügbar. Die Lese-Seite (`ReadTranslatablePresentation()`,
  Quelle für `propertyEnumerationOptions`) hat dafür bereits seit jeher einen
  `function_exists('IPS_GetVariablePresentation')`-Schutz (liefert auf
  Symcon < 8.0 einfach `null`, siehe auch [Abschnitt
  3](#3-voraussetzungen): "bleibt komplett inaktiv, kein Fehler") -
  `ApplyEnumerationOptionsToVariable()` (die Schreib-Seite, angewendet bei
  jedem Sprachwechsel) hatte denselben Schutz aber NIE bekommen (nur `@`,
  unterdrückt bloß Warnungen, keinen Fatal Error bei einer unbekannten
  Funktion). Auf einer frisch auf Symcon < 8.0 gescannten Instanz blieb das
  bisher folgenlos, weil `propertyEnumerationOptions` dort dank der
  Lese-Seiten-Sperre ohnehin nie Zeilen enthält (die Schleife, die
  `ApplyEnumerationOptionsToVariable()` aufruft, läuft dann schlicht nie an) -
  betroffen wäre aber eine Instanz gewesen, deren Konfiguration ursprünglich
  auf Symcon ≥ 8.0 gescannt wurde (also bereits reale Zeilen in
  `propertyEnumerationOptions` stehen) und die anschließend auf eine Version
  < 8.0 zurückgestuft bzw. deren Konfiguration dorthin übertragen wird - dort
  hätte der nächste Sprachwechsel einen Fatal Error ausgelöst. Jetzt trägt
  `ApplyEnumerationOptionsToVariable()` denselben Schutz wie die Lese-Seite.
  Der ursprünglich für Symcon 7.1 dokumentierte Kompatibilitätsanspruch bleibt
  damit korrekt, ohne Einschränkung.
* **Build 98 behebt einen live gemeldeten Bug: das Ergebnis-Popup von
  "Aufräumen" verschwand direkt wieder, weil der dafür nötige
  Formular-Reload (`ReloadForm()`) im selben Aufruf lief.** `ReloadForm()`
  ist bei "Aufräumen" nicht verzichtbar - ein bereits offenes
  Konfigurationsformular hätte sonst weiterhin den alten (längeren)
  Listen-Stand im "Übernehmen"-Puffer und würde ihn beim nächsten Speichern
  über das gerade bereinigte Ergebnis zurückschreiben (dieselbe Begründung
  gilt für den manuellen Rescan, siehe dortiger Kommentar) - aber genau
  dieser komplette Formular-Neuaufbau riss auch das gerade erst über
  `UpdateFormField()` gezeigte `CleanupResultPopup` sofort wieder mit aus dem
  DOM, bevor der Nutzer die Meldung lesen konnte. Fix: `CleanupOrphanedRows()`
  ruft `ReloadForm()` nicht mehr synchron im selben Durchlauf auf, sondern
  blendet das Ergebnis-Popup zuerst live auf dem noch offenen Formular ein
  (derselbe bereits bewährte Mechanismus wie bei `CacheClearedPopup`/
  `ProviderCheckResultPopup`, die beide ganz ohne Reload auskommen) und
  startet dann einen einmaligen, um `CLEANUP_RELOAD_DELAY_SECONDS` (5s)
  verzögerten Timer (`ProcessDeferredCleanupReload()`), der den eigentlichen
  Reload erst danach nachholt - genug Zeit, die Meldung zu lesen, bevor die
  Liste im Hintergrund aktualisiert wird. Das Popup bleibt dabei nahtlos
  sichtbar: der zugrunde liegende Zähler-Wert wird weiterhin erst beim
  tatsächlichen Reload (in `PopulateFormElements()`) einmalig verbraucht,
  nicht schon beim Live-Einblenden.
* **Build 99, auf Nutzer-Wunsch: Tausendertrennzeichen in den
  Übersetzungsstatistiken.** Live gemeldet anhand eines Cache-Ersparnis-Werts
  von über 1,6 Millionen Zeichen, der als reine Ziffernfolge kaum lesbar war.
  Neue Funktion `FormatStatsCountForDisplay()` (Format "1.622.345", dieselbe
  feste, nicht konsolensprachenabhängige Konvention wie das bereits
  bestehende `date('d.m.Y', ...)` an anderer Stelle) wird jetzt in den
  Konfigurationsformular-Statistikzeilen, im Gast-Info-Popup der Kachel und
  im kleinen Hinweistext unter dem Sprach-Dropdown verwendet. Bewusst NICHT
  in die bestehende `FormatStatsCount()` eingebaut, sondern als eigene
  Funktion daneben: `FormatStatsCount()` liefert auch die Werte für die
  `<!--COUNT_TRANSLATIONS-->`/`<!--COUNT_SIGNES-->`-Platzhalter in eigenen
  Kacheln (siehe Abschnitt 7) - dort laut Dokumentation bewusst "nur die
  reine Zahl", da Nutzer sich daraus eigenen Text/JS/CSS bauen; ein
  Trennzeichen hätte dort z. B. ein eigenes `parseInt()` stillschweigend
  brechen können.
* **Build 100, rein diagnostisch: temporäres `SendDebug`-Logging (Kategorie
  `IPSSL_TranslateGapDiag`) in `FillLanguageColumn()`.** Live gemeldet: nach
  einem vollständigen Rescan blieben viele "Eigene Texte"-Zellen für eine
  einzelne Zielsprache (hier: Spanisch) leer, obwohl weder eine Anbieter-Pause
  aktiv war noch der Rohtext als JSON erkannt wurde (das wäre erwartetes
  Verhalten, siehe Build 84) - ein per Debug-Log bestätigter kompletter
  Rescan-Durchlauf zeigte für die betroffenen Zeilen ueberhaupt KEINEN
  `FreeTranslate_Request`/`GoogleTranslate_Mapping`-Eintrag für
  `Text_es`, obwohl die Original-Zelle sichtbar nicht-leeren, echten Text
  enthielt. Reine Code-Analyse von `IsRowLanguageTranslationCurrent()` (der
  einzige Ort, an dem eine Zeile hier übersprungen werden kann, wenn
  `$fromText` nicht leer und kein JSON ist) zeigt keinen offensichtlichen
  Fehler - der Kernverdacht ist daher, dass die betroffene Zielsprachen-Zelle
  entgegen dem Anschein in der Formular-Ansicht in Wahrheit NICHT den leeren
  String `''` enthält (z. B. ein einzelnes Leerzeichen, übrig von einer
  manuellen Lösch-Aktion im Formular) - der strikte `===''`-Vergleich würde
  das fälschlich als "bereits übersetzt" werten. Das neue Logging macht den
  tatsächlichen Zellenwert per `json_encode()` eindeutig sichtbar (deckt auch
  unsichtbare Whitespace-/Sonderzeichen auf) und protokolliert für jede nicht
  als JSON erkannte Zeile mit nicht-leerem Rohtext die vollständige
  Entscheidungsgrundlage (aktueller Zellenwert, JSON-Erkennung,
  "bereits aktuell"-Ergebnis, Zeitstempel). Rein additiv, keine
  Verhaltensänderung (volle Regressionssuite unverändert grün) - wird
  entfernt bzw. durch die eigentliche Korrektur ersetzt, sobald die Logs den
  Mechanismus bestätigt haben.
* **Build 101 behebt einen per direkter Property-Abfrage bestätigten Bug:
  wird ein Rohtext leer, bleiben veraltete Übersetzungen in den
  Zielsprachen-Spalten unverändert stehen, statt mit-geleert zu werden.**
  Live gefunden (Nutzer-Diagnose über ein kleines Inspektions-Skript, siehe
  unten): eine "Eigene Texte"-Zeile mit dynamischem Inhalt (springt je nach
  Bedingung zwischen echtem Text und `""`) zeigte `ORIGINAL_IMPORT_Text` aktuell
  leer, `Text_en` aber weiterhin eine längst nicht mehr zutreffende alte
  Übersetzung. Ursache: `ApplyTrackedVariableUpdate()` übernimmt einen leeren
  Wert korrekt als frischen Rohtext und markiert per `MarkRowSourceChanged()`
  bewusst alle Zielsprachen-Zellen als veraltet, OHNE ihren bisherigen
  (Fallback-)Wert zu löschen (siehe dortiger Kommentar) - die eigentliche
  Auffrischung sollte der nächste Rescan übernehmen. `FillLanguageColumn()`/
  `FillLanguageColumnFromRawSource()` übersprangen eine Zeile mit leerem
  Rohtext bisher aber komplett ("nichts zu übersetzen") - dabei blieb die
  laengst veraltete Zielsprachen-Zelle als Karteileiche stehen, statt
  wenigstens geleert zu werden. Trifft ein Rescan die Zeile wiederholt in
  ihrem leeren Zustand (z. B. weil der Inhalt öfter leer als gefüllt ist),
  konnte das faktisch dauerhaft so bleiben. Fix: eine bereits befüllte
  Zielsprachen-Zelle wird jetzt aktiv mit-geleert, sobald der Rohtext selbst
  leer ist - konsistent mit `ResolveRowValue()`, das bei leerem Rohtext
  ohnehin nichts anzuzeigen hätte. Entfernt außerdem das temporäre Build-100-
  Logging wieder. **Der zweite, separate Verdacht aus derselben Live-Diagnose
  ist inzwischen aufgeklärt** (siehe Build 102 direkt im Anschluss) - zwei
  statische "Eigene Texte"-Zeilen mit langen Rohtexten zeigten dauerhaft
  leeres Spanisch bei gefülltem Englisch; der Meldungen-Log des Nutzers zeigte
  den tatsächlichen Grund: "alle Anbieter der Kette (deepl [pausiert], google
  [pausiert], free) haben 'de' -> 'es' abgelehnt" - kein Logikfehler, sondern
  eine echte Anbieter-Erschöpfung (siehe Build 102).
* **Build 102, auf Nutzer-Hinweis: DeepLs kostenfreie Stufe ist inzwischen
  KEIN wiederkehrendes Monats-/Tageskontingent mehr, sondern ein EINMALIGES
  Frei-Kontingent (aktuell 1 Mio. Zeichen), danach bleibt der Key dauerhaft
  gesperrt.** `DetectRateLimitCooldown()` behandelte DeepLs dediziertes HTTP
  456 ("Quota Exceeded") bisher wie jedes andere erkannte
  Tageskontingent-Signal (`DAILY_QUOTA_COOLDOWN_SECONDS`, 24h) - das Modul
  hätte einen einmalig aufgebrauchten DeepL-Key dadurch jeden einzigen Tag
  aufs Neue (erfolglos) angefragt, obwohl das Kontingent nie zurückkehrt.
  HTTP 456 bekommt jetzt die deutlich längere, neue
  `DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS` (30 Tage) - stoppt die
  automatischen Wiederholungsversuche faktisch, ohne den Anbieter für immer
  zu deaktivieren; ein Klick auf "Übersetzungsanbieter prüfen" nach einem
  Key-Wechsel/Upgrade beendet die Sperre wie gewohnt sofort bei Erfolg. Dabei
  einen zweiten, unabhängigen Bug in `RecordProviderPaused()` gefunden und
  mitbehoben: die Eskalations-Deckelung rundete JEDEN übergebenen
  Basis-Cooldown ab `DAILY_QUOTA_COOLDOWN_SECONDS` bedingungslos auf genau
  24h herunter - das hätte die neue, längere DeepL-Sperre stillschweigend
  wirkungslos gemacht. Ein bereits als "langfristig bekannt" erkannter
  Fehlschlag (Tageskontingent ODER jetzt auch DeepLs Einmalkontingent) startet
  jetzt direkt beim tatsächlich übergebenen Wert; nur die generische
  Kurzsperren-Eskalation (Streak-Verdopplung für ein nicht näher erkanntes
  Rate-Limit) bleibt weiterhin auf 24h gedeckelt.
* **Build 103 behebt einen direkten Nebeneffekt dieser Längengrenze, live
  gefunden anhand einer irreführenden Meldungen-Log-Zeile.** Ein Eintrag
  "alle Anbieter der Kette (deepl [pausiert], google [pausiert], free) haben
  'de' -> 'es' abgelehnt (77 Text(e), erster Text: 'Echo Info')" sah aus, als
  hätte selbst ein triviales 9-Zeichen-Wort abgelehnt - der zugehörige Debug-
  Log-Eintrag zeigte für "Echo Info" aber eine echte, erfolgreiche MyMemory-
  Antwort (HTTP 200, `quotaFinished: false`). Der Text im Log ist nur der
  ERSTE von 77 angefragten Texten (`$Texts[0]` in der Fehler-Zusammenfassung),
  nicht zwangsläufig der eigentliche Übeltäter. Ursache: MyMemory hat keinen
  echten Batch-Endpunkt (ein HTTP-Request pro Text, siehe oben) -
  `TranslateChunkFree()` brach bei EINEM einzelnen `null`-Ergebnis (egal an
  welcher Position) bislang sofort für den GESAMTEN Aufruf ab und verwarf
  dabei alle bereits erfolgreich übersetzten Texte desselben Aufrufs. Ein
  einzelner über 500 Byte langer Text irgendwo unter den 77 angefragten
  reichte also aus, um alle 76 übrigen, problemlos übersetzbaren Texte mit
  sich zu reißen. `TranslateSingleFree()` liefert für diesen Fall jetzt `''`
  (Leerstring) statt `null` - dasselbe Signal wie beim bereits bestehenden
  Leerstring-Fall für einen leeren Rohtext direkt darüber -
  `TranslateChunkFree()` fährt dadurch mit den restlichen Texten fort, statt
  abzubrechen. Die zu lange Zelle selbst bleibt weiterhin leer (wird beim
  nächsten Rescan erneut versucht, dann ggf. über einen zwischenzeitlich
  wieder verfügbaren bezahlten Anbieter ohne diese Längenbegrenzung) - alle
  anderen Texte desselben Aufrufs werden jetzt aber sofort korrekt gefüllt,
  statt unnötig auf den nächsten Rescan warten zu müssen.
* **Build 104, auf Nutzer-Nachfrage: eine manuell im Formular korrigierte
  Übersetzungszelle wurde bislang nicht sofort in der Visualisierung
  sichtbar.** Nicht wie zunächst vermutet die für externe VM_UPDATE-
  Schreibvorgänge gedachte 12-Minuten-Debounce
  (`PENDING_ROW_UPDATE_DEBOUNCE_SECONDS`, siehe `BufferPendingTrackedRowUpdate`
  weiter unten) - die betrifft ausschließlich fremde Schreibzugriffe, nicht
  eine manuelle Bearbeitung im Konfigurationsformular. Der tatsächliche Grund:
  `ApplyLanguage()` (die Funktion, die Namen/Wert tatsächlich ans lebende
  Objekt schreibt) lief in `ApplyChanges()` bisher NUR erneut, wenn sich
  entweder die aktuell aktive Gast-Sprache selbst geändert hatte oder eine
  Zeilen-Quellsprache reconciled wurde - eine reine Korrektur einer
  Übersetzungszelle löst keines von beidem aus. Die Korrektur landete zwar
  sofort gespeichert in der Property, wurde aber erst beim nächsten
  tatsächlichen Sprachwechsel ans Objekt gepusht. Neue, güns­tige
  Fingerprint-Prüfung (`ComputeActiveLanguageContentFingerprint()`, kein
  API-Aufruf, reiner `md5()`-Vergleich über die für die aktuell aktive
  Sprache aufgelösten Zellwerte, analog zur bereits bestehenden
  `ComputeRowSourceLanguageFingerprint()` für Zeilen-Quellsprachen) stößt
  `ApplyLanguage()` jetzt zusätzlich an, sobald sich irgendein für die aktuell
  aktive Sprache relevanter Zellinhalt seit dem letzten Durchlauf geändert
  hat - eine Korrektur an einer GERADE NICHT aktiven Zielsprache löst dabei
  bewusst nichts aus (für den aktuellen Gast ändert sich ja nichts sichtbar).
* **Build 105 behebt einen direkten Nebeneffekt von Build 104, live gefunden:
  eine manuelle Korrektur wurde kurz angezeigt, dann aber "einen Augenblick
  später" wieder auf den alten Wert zurückgesetzt - in der Tabelle stand
  weiterhin die Korrektur, in der Visualisierung wieder der alte Wert.**
  Ursache: `StagePendingTrackedRowUpdates()` (der verzögerte, gepufferte
  Flush externer VM_UPDATE-Änderungen, siehe `BufferPendingTrackedRowUpdate`/
  `PENDING_ROW_UPDATE_DEBOUNCE_SECONDS` oben) überschrieb Zeilenfelder beim
  tatsächlichen Schreiben bislang bedingungslos mit dem gepufferten Wert -
  völlig unabhängig davon, ob die Zelle inzwischen längst anderweitig
  geändert wurde. `FlushPendingTrackedRowUpdates()` läuft am Anfang JEDES
  `ApplyChanges()`-Durchlaufs, auch genau desjenigen, den das eigene
  "Übernehmen" der manuellen Korrektur gerade selbst auslöst - ein zu diesem
  Zeitpunkt noch ausstehender, längst veralteter Puffer-Eintrag (aus einem
  früheren externen Schreibvorgang oder einem Selbst-Schreib-Echo, siehe
  Build 95) konnte die frische Korrektur damit im selben oder einem knapp
  späteren `ApplyChanges()`-Durchlauf kommentarlos wieder überschreiben -
  ein Bug, der schon vor Build 104 bestand, aber unsichtbar blieb, weil
  `ApplyLanguage()` bis dahin nur bei einem echten Sprachwechsel erneut
  lief. Build 104s neue, häufigere `ApplyLanguage()`-Aufrufe machten genau
  diesen alten Bug jetzt sichtbar. Fix: `BufferPendingTrackedRowUpdate()`
  sichert jetzt zusätzlich eine Baseline (den Feldwert UNMITTELBAR VOR der
  externen Änderung) für die beiden eigentlichen Inhaltsfelder (Rohtext und
  ggf. die live nachübersetzte Zielsprachen-Zelle - reine
  Zeitstempel-Buchführung bleibt unverändert bedingungslos). Beim
  tatsächlichen Schreiben wendet `StagePendingTrackedRowUpdates()` ein
  gepuffertes Feld nur noch an, wenn der aktuelle Zeilenwert noch exakt der
  Baseline entspricht (also seither NICHTS anderes - typischerweise eine
  manuelle Korrektur - die Zelle verändert hat) - andernfalls wird gezielt
  NUR dieses eine Feld übersprungen, alle anderen gepufferten Felder
  derselben Zeile sowie alle anderen Zeilen werden weiterhin normal
  angewendet. Das ursprüngliche Ziel von Build 71 (ein gepufferter externer
  Schreibvorgang darf durch ein unabhängiges "Übernehmen" nicht verloren
  gehen) bleibt für jedes nicht betroffene Feld unverändert bestehen.
* **Build 106, rein diagnostisch: Build 105 hat das live gemeldete Problem
  NICHT behoben - live gefunden, dass es sich um "Objektnamen" handelt (nicht
  "Eigene Texte"), für die aktuell aktive Sprache.** Ein frischer Debug-Export
  zeigte statt eines Puffer-Flushes einen kompletten RESCAN rund 2 Sekunden
  nach der manuellen Korrektur (zwei `EnsureSourceLanguageIsTarget`-Zeilen im
  Abstand von 2s, vermutlich der Auto-Rescan-Timer) - `MergeRows()` (per
  Code-Analyse bestätigt korrekt: friert Rohtext/Übersetzungen für bereits
  bekannte `ObjectID`s ein) und ein Abgleich mit der "Eigenen
  Übersetzungstabelle" (Build 93 - Glossareintrag würde jede Rescan-gestützte
  Korrektur zurücksetzen) wurden beide geprüft und ausgeschlossen (keine
  passende Glossar-Zeile vorhanden). Bleibt als Verdacht:
  `FillLanguageColumn()`s "bereits aktuell"-Prüfung (`IsRowLanguageTranslationCurrent()`)
  erkennt die frisch manuell bearbeitete Zelle fälschlich als "veraltet" und
  übersetzt sie beim Rescan neu, wodurch die manuelle Korrektur überschrieben
  wird - dieselbe Funktion, bei der Build 100/101 bereits einmal eine
  verwandte Lücke fand (dort: Rohtext wurde leer). Neues
  `SendDebug('IPSSL_NameRevertDiag', ...)` in `FillLanguageColumn()` (ersetzt
  das in Build 101 entfernte `IPSSL_TranslateGapDiag`) protokolliert für jede
  nicht-leere, nicht als JSON erkannte Zeile die vollständige
  Pending/Aktuell-Entscheidung. Rein additiv, keine Verhaltensänderung (volle
  Regressionssuite unverändert grün) - wird entfernt bzw. durch die
  eigentliche Korrektur ersetzt, sobald die Logs den Mechanismus bestätigt
  haben.
* **Build 107 fand die eigentliche Ursache - nicht in `FillLanguageColumn()`,
  sondern in `ApplyLanguage()` selbst.** Der Build-106-Diagnose-Export zeigte
  bei allen 99 protokollierten Zeilen `isCurrent=true` (korrekt nicht
  pending), und die tatsächlich bearbeitete `ObjectID` tauchte im gesamten
  Log kein einziges Mal auf - der Verdacht aus Build 106 war damit widerlegt.
  Ein direkt per Skript in der Symcon-Konsole ausgelesener Property-Snapshot
  bewies stattdessen, dass die manuelle Korrektur den Rücksprung binnen des
  GLEICHEN `ApplyLanguage()`-Laufs erlitt, nicht erst Sekunden später durch
  einen Rescan. Grund: `WalkTree()` legt für JEDES Objekt mit gesetztem Ident
  eine Zeile in "Objektnamen" an - unabhängig davon, ob es sich zusätzlich um
  eine getrackte "Eigene Texte"-Variable handelt. Eine solche Variable landet
  dadurch zwangsläufig gleichzeitig in BEIDEN Listen, und "Eigene Texte"
  pflegt für ihre eigenen Zeilen eine komplett unabhängige, eigene
  Namens-Übersetzung (`ORIGINAL_IMPORT_Name`/`Name_<lang>`). `ApplyLanguage()`
  rief für dasselbe Objekt daher zwei voneinander unabhängige `IPS_SetName()`
  auf - zuerst aus "Objektnamen" (die gerade korrigierte, richtige Zeile),
  direkt darauf aus "Eigene Texte" (der eigene, unveränderte, damit
  zwangsläufig veraltete Stand) -, wobei der zweite Aufruf den ersten
  kommentarlos wieder überschrieb. Ein Schutz gegen doppeltes Schreiben
  existierte bereits für Werte (`$writtenValueObjectIDs`, gegen zwei
  "Eigene Texte"-Zeilen mit derselben `ValueObjectID`), aber nicht für Namen
  über beide Listen hinweg. Fix: neues `$writtenNameObjectIDs`, von der
  "Objektnamen"-Schleife befüllt - die "Eigene Texte"-Schleife überspringt
  ihren eigenen `IPS_SetName()`-Aufruf jetzt für jedes Objekt, das bereits
  über "Objektnamen" benannt wurde. Betraf nur Objekte, die in beiden Listen
  gleichzeitig auftauchen (jede sinnvoll benannte "Eigene Texte"-Variable mit
  gesetztem Ident, ein häufiger Fall) - reiner Namens-Bug, Werte
  (`SetValueString`) waren nie betroffen. Kein Rescan/Sprachwechsel nötig,
  damit der Fix greift: er wirkt bereits beim nächsten `ApplyLanguage()`-Lauf
  nach der jeweiligen Korrektur. Für "Beschriftungen", "Automations" und
  "Begrüßung" besteht dasselbe Risiko nicht - sie schreiben auf strukturell
  andere Ziele (Custom Presentation bzw. die `Automations`-/`GreetingName`-
  Property der Kachel-Visualisierungs-Instanz), nie über `IPS_SetName()`; der
  einzige denkbare Überschneidungsfall (Begrüßung im Variable-Modus teilt
  sich eine `ValueObjectID` mit einer "Eigene Texte"-Zeile) war bereits vor
  Build 107 über denselben `$writtenValueObjectIDs`-Mechanismus abgesichert.
* **Build 108 (Nutzer-Wunsch): übersetzt zusätzlich die Legenden-Titel von
  Symcons eingebautem Chart-Element.** Ein per "Add Chart" in der
  Kachel-Visualisierung angelegtes Diagramm zeigt pro Datenreihe einen
  eigenen Titel (z. B. "Außentemperatur", "Wohnzimmer") - diese Titel lagen
  bisher außerhalb jeder Übersetzung und blieben bei jedem Sprachwechsel
  fest Deutsch, während alles andere um sie herum (inkl. des Objektnamens
  des Charts selbst, der bereits über "Objektnamen" lief) korrekt
  wechselte. Konfiguriert ist ein Chart NICHT über eine normale Property,
  sondern als eigenes Symcon-Medienobjekt (`ObjectType` 5, `MediaType` 4 =
  `MEDIATYPE_CHART`) mit dem gesamten Aufbau (Datenreihen, Farben, Titel)
  als base64-kodiertes JSON im Medien-Inhalt selbst
  (`IPS_GetMediaContent()`/`IPS_SetMediaContent()`, kein `IPS_ApplyChanges()`
  nötig - Medienobjekte kennen diesen Mechanismus nicht). Ein Chart sitzt
  (anders als "Automations", die separat über die Kachel-Visualisierungs-
  Instanz gescannt werden) als ganz normales Objekt im Root-Baum und wird
  daher direkt von `WalkTree()` mit erfasst - neue Property
  `ObjectCharts`/Liste "Charts", eindeutiger Zeilen-Schlüssel ist
  ChartID+VariableID (ein Chart kann mehrere Datenreihen gleichzeitig
  zeigen). Schreiben passiert über die neue `ApplyChartsLanguage()`, nach
  demselben Muster wie `ApplyAutomationsLanguage()`.

  **Live gefundener Nachtrag, noch während derselben Anfrage:** ein zweiter
  vom Nutzer getesteter Chart übersetzte seine Legenden-Titel bereits VOR
  diesem Fix scheinbar von selbst korrekt - ohne dass Simple Locale je
  dessen Medien-Inhalt angefasst hätte. Ursache, per Screenshot des
  "Configure Graph"-Dialogs bestätigt: Symcon hält den Titel einer
  Datenreihe automatisch synchron mit dem LIVE-Namen ihrer zugrunde
  liegenden Variable. Ist diese Variable zusätzlich an anderer Stelle im
  Root-Baum als eigenes Objekt platziert (z. B. eine eigene
  Anzeige-Kachel), wird sie ohnehin schon über "Objektnamen" umbenannt -
  und Symcon zieht diesen neuen Namen automatisch auch in die
  Chart-Legende nach, eine eigene Übersetzung wäre hier doppelte
  (und potenziell mit Symcons eigener Synchronisierung konkurrierende)
  Arbeit. Als erster Ansatz verglich `WalkTree()` dafür jeden
  Datenreihen-Titel beim Scan direkt gegen `IPS_GetName()` der zugehörigen
  Variable - **dieser Ansatz erwies sich kurz darauf als falsch, siehe
  Build 109.**

* **Build 109 (live gefunden, direkt im Anschluss an Build 108): der dortige
  Live-Namens-Vergleich schloss ein zweites, unabhängiges Chart komplett
  aus der neuen "Charts"-Liste aus.** Ein vom Nutzer getesteter
  Luftfeuchtigkeits-Chart mit drei Datenreihen tauchte nach Build 108 gar
  nicht mehr in "Charts" auf - keine der drei Zeilen wurde angelegt.
  Ursache: Symcon füllt das `title`-Feld beim erstmaligen Anlegen einer
  Datenreihe standardmäßig mit dem DAMALIGEN Namen der gewählten Variable -
  unabhängig davon, ob diese Variable jemals sonst irgendwo im Baum
  eigenständig auftaucht. Build 108s Vergleich "Titel == aktueller
  Live-Name" traf auf alle drei Datenreihen zu (der Titel war seit dem
  Anlegen nie geändert worden), obwohl keine der drei Variablen anderswo im
  Baum stand und sie folglich NIE von irgendetwas übersetzt worden wären -
  der Live-Namens-Vergleich ist also kein zuverlässiges Signal dafür, ob
  Symcon tatsächlich synct, sondern trifft schlicht auf den unveränderten
  Symcon-Standardzustand IMMER zu, auch ohne jede Synchronisierung. Die
  tatsächlich zuverlässige Bedingung ist stattdessen exakt die aus dem
  Build-108-Nachtrag beschriebene Ursache selbst: nicht der Titel-Vergleich,
  sondern ob die Variable TATSÄCHLICH als eigenständiges Objekt im selben
  Root-Baum-Scan gefunden wurde. Neue Funktion
  `ExcludeChartRowsForIndependentlyNamedVariables()` prüft das jetzt direkt
  gegen die bereits gescannten Objektnamen (`$ScannedNames`/`$ScannedCharts`
  aus demselben `WalkTree()`-Durchlauf) - bewusst ERST NACH dessen
  vollständigem Abschluss aufgerufen (sowohl in `ScanRootTree()` vor dem
  Merge als auch in `CleanupOrphanedRows()`), da die referenzierte Variable
  an einer beliebigen anderen Stelle im Baum liegen kann, vor oder nach dem
  Chart selbst - erst ein vollständiges `$ScannedNames` beantwortet
  zuverlässig, ob eine Variable eigenständig vorkommt. Der fehlerhafte
  `IPS_GetName()`-Vergleich wurde komplett entfernt. Regressionstest um den
  genau gegenteiligen Fall ergänzt (keine der Variablen eigenständig im
  Baum → alle Zeilen müssen erhalten bleiben), damit dieser konkrete Fehler
  nicht erneut einschleicht.
* **Build 110 (live gefunden, direkt im Anschluss an Build 109): Build 109
  behob den Ausschluss-Fehler, aber "Humedad del aire" tauchte danach
  IMMER NOCH nicht in "Charts" auf.** Ein direkter `IPS_GetMediaContent()`-
  Rohdump dieses Charts brachte die eigentliche Ursache zutage: alle drei
  Datenreihen hatten `"title":""` - einen komplett LEEREN Titel, nicht
  einen mit dem Variablennamen übereinstimmenden (die Annahme aus Build
  109, "Symcon füllt title beim Anlegen standardmäßig", war selbst
  ebenfalls falsch - Symcon lässt das Feld schlicht leer). Der
  ursprüngliche Scan-Code (`if ($datasetVariableID === 0 || $datasetTitle
  === '') continue;`, unverändert seit Build 108) übersprang jede Zeile mit
  leerem Titel von Anfang an - unabhängig von Build 109s Filterung kam für
  "Humedad del aire" also nie auch nur eine Zeile zustande. Die Kachel
  zeigte dabei nachweislich (Screenshot) weiterhin unübersetzt
  "Luftfeuchtigkeit" in der Legende: Symcon rendert bei leerem Titel live
  den AKTUELLEN Namen der Variable - bei "Irradiación luminosa" (ebenfalls
  vermutlich leere Titel, nie direkt geprüft) funktionierte das nur, WEIL
  die Variablen zusätzlich eigenständig im Baum standen und dadurch
  bereits übersetzt waren; bei "Humedad del aire" standen sie das nicht,
  weshalb ihr roher, nie übersetzter Name für immer stehen geblieben wäre.
  Fix: der Quelltext einer Datenreihe ist jetzt der explizite Titel, falls
  gesetzt, sonst ersatzweise der aktuelle Name der Variable
  (`$sourceText = $datasetTitle !== '' ? $datasetTitle : (string)
  @IPS_GetName($datasetVariableID);`) - genau das, was Symcon selbst in
  diesem Fall in der Legende anzeigen würde. Der bereits in Build 109
  eingeführte `ExcludeChartRowsForIndependentlyNamedVariables()`-Filter
  bleibt unverändert bestehen und greift weiterhin zuverlässig für
  eigenständig im Baum stehende Variablen wie bei "Irradiación luminosa".
* **Build 111, rein diagnostisch: live gemeldet, dass "Aufräumen" zwar
  tatsächlich verwaiste Zeilen entfernt (nach Löschen einer kompletten
  Instanz samt vier Kind-Variablen aus dem Root-Baum), das Ergebnis-Popup
  aber "Removed: " mit LEEREM statt dem tatsächlichen Zähler anzeigt.**
  Betrifft "Objektnamen", nicht die neue "Charts"-Liste. Der Code-Pfad
  selbst (`CleanupOrphanedRows()`/`GetConfigurationForm()`, Build 76/98)
  sieht bei genauer Durchsicht strukturell korrekt aus - Verdacht: der
  "einmal lesen, dann auf -1 zurücksetzen"-Mechanismus für
  `attributeLastCleanupRemovedCount` wird zwischen dem sofortigen
  Live-Push (`UpdateFormField('CleanupResultCountLabel', ...)`) und dem um
  `CLEANUP_RELOAD_DELAY_SECONDS` (5s) verzögerten `ReloadForm()`
  (`ProcessDeferredCleanupReload()`) durch einen ZUSÄTZLICHEN,
  nicht selbst ausgelösten `GetConfigurationForm()`-Aufruf vorzeitig
  konsumiert - noch nicht bestätigt. Neues `SendDebug('IPSSL_CleanupCountDiag',
  ...)` protokolliert mit `microtime(true)`-Zeitstempeln jeden
  `GetConfigurationForm()`-Aufruf (gelesener Zählerwert, ob zurückgesetzt),
  das Ende von `CleanupOrphanedRows()` (geschriebener Zählerwert) und den
  Zeitpunkt, an dem `ProcessDeferredCleanupReload()` tatsächlich feuert -
  damit sich die genaue Reihenfolge/das Timing zwischen diesen Ereignissen
  rekonstruieren lässt. Rein additiv, keine Verhaltensänderung (volle
  Regressionssuite unverändert grün) - wird entfernt bzw. durch die
  eigentliche Korrektur ersetzt, sobald die Logs den Mechanismus bestätigt
  haben.
* **Build 112 (live gefunden, direkt im Anschluss an Build 110):
  "Aufräumen" löschte fälschlich eine Chart-Zeile mit einem echten, im
  Chart selbst gesetzten Titel.** Nach dem Build-110-Fix funktionierte
  "Humedad del aire" korrekt, aber bei "Temperaturas" verschwand
  "Außentemperatur" aus der "Charts"-Liste - im Chart selbst blieb die
  Datenreihe (samt bereits übersetztem Titel) unverändert bestehen, nur die
  Admin-Tabelle zeigte die Zeile nicht mehr. Ursache:
  `ExcludeChartRowsForIndependentlyNamedVariables()` (Build 109) prüfte
  ausschließlich, ob die zugrunde liegende Variable zusätzlich eigenständig
  im Baum steht - unabhängig davon, ob der Chart-Titel überhaupt aus dem
  Leer-Titel-Fallback (Build 110) stammte oder ein echter, eigener Text
  war. "Außentemperatur" hatte einen expliziten Titel, dessen Variable
  ZUFÄLLIG zusätzlich eigenständig im Baum stand (genau die Konstellation,
  vor der schon beim Feature-Wunsch selbst gewarnt wurde: "Vermutlich
  können wir aber auch den Titel separat überschreiben") - die Regel
  "Symcon synct das schon selbst" gilt aber ausschließlich für den
  Leer-Titel-Fall; ein gesetzter Titel wird von Symcon immer unverändert
  angezeigt, unabhängig vom Variablennamen. Fix: `WalkTree()` markiert jede
  gescannte Zeile jetzt mit einem rein transienten, nie persistierten
  Merkmal `_EmptyTitleFallback` (true nur, wenn der ORIGINALE Chart-Titel
  leer war) - `ExcludeChartRowsForIndependentlyNamedVariables()` wendet die
  Ausschluss-Regel nur noch an, wenn dieses Merkmal gesetzt ist.
  `MergeChartRows()` entfernt das Merkmal wieder, bevor eine neue Zeile
  persistiert wird (kein Ballast in der gespeicherten Property/Tabelle).
  Regressionstest um beide Fälle nebeneinander ergänzt (echter Titel bleibt
  erhalten, Leer-Titel-Fallback wird weiterhin korrekt ausgeschlossen).
* **Build 113 (live gemeldet, schwerwiegend): nach "Aufräumen" fehlten
  plötzlich viele manuell korrigierte "Objektnamen"-Zeilen, deren Objekte in
  der Visualisierung nachweislich noch existierten - u. a. eine seit
  Stunden stabile eigene Korrektur ("Idioma") wurde durch eine frische
  Maschinenübersetzung ersetzt.** Auslöser laut Nutzer: ein einmaliger
  Testklick auf "Aufräumen". Root Cause noch nicht abschließend bestätigt
  (Diagnose läuft), aber starker, plausibler Verdacht: `@IPS_GetMedia()`/
  `@IPS_GetMediaContent()` (neu seit Build 108, siehe dort) wirft für ein
  ungewöhnlich konfiguriertes oder defektes Medienobjekt eine echte
  PHP-Exception - der `@`-Operator unterdrückt nur Warnungen/Notices,
  NIEMALS eine tatsächlich geworfene Exception. Eine solche Exception würde
  mitten im `WalkTree()`-Durchlauf den KOMPLETTEN restlichen Baum-Scan
  abbrechen: jedes ab diesem Punkt noch nicht besuchte, in Wahrheit
  weiterhin existierende Objekt fehlt dann in `$ScannedNames` - "Aufräumen"
  hält es fälschlich für verwaist und löscht seine Zeile unwiederbringlich;
  ein nachfolgender Rescan legt sie als "neu" an und übersetzt sie komplett
  frisch, jede manuelle Korrektur ist damit verloren. Fix: der gesamte
  Chart-Scan-Block in `WalkTree()` läuft jetzt in einem eigenen
  `try`/`catch (\Throwable $e)` - ein Fehler bei einem einzelnen Chart wird
  geloggt (`SendDebug('IPSSL_ChartScanError', ...)`) und übersprungen, statt
  den kompletten restlichen Baum-Scan (und damit potenziell zahllose andere,
  völlig unbeteiligte Objekte) zu gefährden. Zusätzlich neues
  `SendDebug('IPSSL_CleanupCountDiag', ...)` in `CleanupOrphanedRows()`:
  protokolliert vor jedem Löschen die Größe des frischen Live-Scans gegen
  die bestehende Property sowie die exakten ObjectIDs jeder tatsächlich zu
  entfernenden "Objektnamen"-Zeile - damit sich ein unvollständiger Scan
  (deutlich kleinerer `liveNames`-Count als erwartet) im Debug-Log sofort
  erkennen lässt, auch falls die eigentliche Ursache doch woanders liegt.
  Regressionstest ergänzt (Chart-Scan-Block läuft nachweislich in
  try/catch), volle Suite grün.
* **Build 114 löst den in Build 111 diagnostizierten leeren Zähler im
  "Aufräumen"-Ergebnis-Popup - ein echter Debug-Log-Export bewies die
  exakte Ursache mit Zeitstempeln.** Ablauf laut Log: `CleanupOrphanedRows()`
  schreibt den Zähler (T+0,00s) - rund 30 Millisekunden später ruft die
  Symcon-Konsole SELBSTSTÄNDIG (nicht vom Modul ausgelöst) erneut
  `GetConfigurationForm()` auf und liest den Zähler korrekt, setzte ihn
  bislang aber SOFORT wieder auf -1 zurück (Build 76) - lange bevor der
  bewusst um `CLEANUP_RELOAD_DELAY_SECONDS` (5s) verzögerte, eigentlich für
  die Anzeige vorgesehene `ProcessDeferredCleanupReload()`-Reload überhaupt
  feuert (T+5,00s). Dessen `GetConfigurationForm()`-Aufruf sah den Zähler
  dadurch IMMER schon verbraucht (-1) und zeigte das Popup folgerichtig mit
  leerem statt dem tatsächlichen Wert - unabhängig vom tatsächlichen
  `removedCount` und reproduzierbar bei jedem einzelnen "Aufräumen"-Lauf,
  kein seltenes Race. Fix: `GetConfigurationForm()` liest den Zähler jetzt
  nur noch (beliebig oft wiederholbar, kein Zustand wird dabei verändert) -
  das tatsächliche Zurücksetzen übernimmt `ProcessDeferredCleanupReload()`
  selbst, erst NACHDEM sein eigener `ReloadForm()`-Aufruf (der letzte
  beabsichtigte Aufruf für diesen Cleanup-Lauf) abgeschlossen ist. Der in
  Build 113 geäußerte Verdacht auf massenhaften Datenverlust durch einen
  abgebrochenen `WalkTree()`-Durchlauf ist durch denselben Log NICHT
  bestätigt (in diesem Lauf war `removedCount=0`, kein
  `IPSSL_ChartScanError` aufgetreten) - bleibt aber vorsorglich abgesichert,
  bis ein Lauf mit tatsächlich zu entfernenden Zeilen das endgültig klärt.
  Regressionstest ergänzt, volle Suite grün.
* **Build 115 klärt den "N+1"-Zähler bei "Aufräumen" endgültig auf (kein
  Bug) und entfernt als direkte Folge davon eine echte, vom Nutzer
  identifizierte strukturelle Redundanz.** Ein exakter Vorher/Nachher-
  Vergleich aller Zeilen-Properties (eigens dafür geschriebenes
  Diagnose-Skript, dreimal ausgeführt: vor dem Anlegen einer Test-Instanz,
  danach, nach deren Löschen + "Aufräumen") zeigte: 5 gelöschte Objekte →
  6 gemeldete Zeilen, exakt weil eine der 5 Variablen ("Aktive Szene") eine
  String-Variable war und dadurch sowohl in "Objektnamen" (1 Zeile) als
  auch in "Eigene Texte" (1 weitere Zeile) stand - macht 4×1 + 1×2 = 6.
  Derselbe Mechanismus erklärte rückwirkend auch einen früheren Test (7
  Objekte → 8 gemeldet). "Aufräumen" zählt schlicht Zeilen über mehrere
  Listen hinweg, nicht "Objekte" - korrektes, erwartbares Verhalten.

  **Die zugrunde liegende Redundanz selbst wurde vom Nutzer zu Recht als
  eigenständiges Problem erkannt:** da "Objektnamen" ausnahmslos JEDES
  Objekt im Baum abdeckt (siehe `WalkTree()`), war die zusätzliche, eigene
  Namens-Übersetzung, die "Eigene Texte" bislang für ihre Zeilen führte
  (`ORIGINAL_IMPORT_Name`/`Name_<lang>`, siehe Build 107), für JEDES
  Objekt strukturell redundant - nie eine eigenständige Datenquelle,
  sondern immer eine zweite, unabhängig editierbare und unabhängig
  übersetzte Kopie desselben Namens. Build 107 hatte nur den daraus
  entstehenden SCHREIB-Konflikt entschärft (`$writtenNameObjectIDs` ließ
  "Objektnamen" beim Sprachwechsel gewinnen) - der Name ließ sich in der
  Admin-Tabelle aber weiterhin an ZWEI Stellen bearbeiten, mit dem Risiko,
  dass beide auseinanderlaufen, und wurde weiterhin zweimal (unnötig)
  übersetzt.

  Build 115 entfernt die zweite Datenquelle komplett statt nur ihren
  Schreibkonflikt zu entschärfen: "Eigene Texte" trackt jetzt
  ausschließlich noch den WERT einer String-Variable, keinen Namen mehr -
  der Name kommt ausschließlich aus "Objektnamen". Entfernt: die Felder/
  Konstanten `fieldOriginalImportName`/`fieldNamePrefix`, die komplette
  `$writtenNameObjectIDs`-Logik in `ApplyLanguage()` (die "Eigene
  Texte"-Schleife ruft jetzt nie mehr `IPS_SetName()` auf, nur noch
  `WriteTrackedValueString()`), die Spalte "Original-Import (Name)" samt
  ihrem Sprachspalten-Satz in `BuildListColumns()`/`form.json`, und das
  Namensfeld beim Scan in `WalkTree()`. Bestehende Installationen behalten
  die alten `ORIGINAL_IMPORT_Name`/`Name_<lang>`-Schlüssel als harmlosen,
  nie wieder gelesenen/geschriebenen Ballast in ihrer gespeicherten
  `ObjectTexts`-Property - keine Migration nötig.

  Bei dieser Gelegenheit umbenannt, da explizit nachgefragt und bestätigt
  (`ResolveStringVariableID()` filtert ausschließlich auf
  `VARIABLETYPE_STRING`): "Eigene Texte" heißt jetzt "Eigene Texte
  (String-Variablen)", in allen vier Sprachen.

  Als Nebenaufräumung außerdem entfernt: das komplette temporäre
  Diagnose-Logging aus Build 111/113 (`IPSSL_CleanupCountDiag`/
  `IPSSL_ChartScanError`) - beide damit untersuchten Verdachtsfälle sind
  jetzt aufgeklärt bzw. abgesichert, die zugehörigen `try`/`catch`-Schutz-
  mechanismen selbst bleiben unverändert bestehen, nur ihr Logging wurde
  entfernt. Regressionstest komplett auf die neue Architektur umgeschrieben
  (kein Name-Feld mehr in "Eigene Texte", kein Schreibkonflikt mehr
  möglich, da strukturell ausgeschlossen statt nur verhindert), volle
  Suite grün.
* **Build 116 (Nutzer-Wunsch): sowohl "Rescan" als auch "Aufräumen" luden das
  Konfigurationsformular bislang sichtbar ZWEIMAL neu pro Klick.** Der
  Build-115-Debug-Log (siehe dort) hatte es bereits nebenbei aufgedeckt:
  Symcons Konsole ruft `GetConfigurationForm()` nachweislich SELBSTSTÄNDIG
  ein weiteres Mal auf, kurz (~30ms) nach jedem `RequestAction()` - dieses
  automatische Verhalten war die ganze Zeit schon da, unabhängig davon, ob
  das Modul selbst zusätzlich einen eigenen `ReloadForm()`-Aufruf machte.
  Sowohl `ScanRootTree()` (nach einem manuellen Rescan-Klick) als auch
  `CleanupOrphanedRows()` (über den in Build 98 eingeführten, um
  `CLEANUP_RELOAD_DELAY_SECONDS` verzögerten `ProcessDeferredCleanupReload()`)
  lösten zusätzlich zu diesem automatischen Reload noch einen EIGENEN aus -
  macht zwei komplette Formular-Neuaufbauten kurz nacheinander pro Klick,
  sichtbar als spürbares doppeltes Neuladen.

  Fix: beide eigenen, jetzt nachweislich redundanten `ReloadForm()`-Aufrufe
  wurden ersatzlos entfernt - Symcons automatischer Reload übernimmt beide
  ursprünglichen Aufgaben bereits vollständig (frische Listendaten anzeigen
  UND den "Übernehmen"-Puffer aktualisieren, siehe Build 60/88 für den Grund,
  warum Letzteres überhaupt nötig ist - ein expliziter `ReloadForm()` und der
  automatische Konsolen-Reload lösen strukturell denselben
  `GetConfigurationForm()`-Neuaufbau aus, es gibt keinen funktionalen
  Unterschied). Damit vollständig entfernt: der komplette Build-98-
  Verzögerungsmechanismus (`ProcessDeferredCleanupReload()`,
  `GetCleanupReloadTimerIdent()`, `CLEANUP_RELOAD_DELAY_SECONDS`, der
  zugehörige `RegisterTimer()`-Aufruf in `Create()` samt einmaliger
  Alt-Instanzen-Bereinigung des dadurch verwaisten Timer-Objekts) sowie der
  komplette `$ReloadFormAfterward`-Parameter von `ScanRootTree()` (war seit
  dieser Entfernung in beiden Aufrufern `Rescan()`/`AutoRescan()` ohne jede
  Wirkung mehr). `GetConfigurationForm()` setzt den "Aufräumen"-Zähler
  dadurch auch wieder wie ursprünglich in Build 76 sofort beim ersten Lesen
  zurück (die Build-114-Sonderbehandlung, die einen konkurrierenden ZWEITEN
  Aufruf überleben musste, ist mit dessen Wegfall hinfällig - es gibt jetzt
  wieder nur den einen automatischen Konsolen-Aufruf). Ein automatischer
  Hintergrund-Rescan (`AutoRescan()`, kein `RequestAction()`, daher auch kein
  automatischer Konsolen-Reload) bleibt davon unberührt und lädt weiterhin
  nie ein gerade offenes Formular neu (Build 60), wie schon zuvor.
  Regressionstests aktualisiert, volle Suite grün.
* **Build 117 (live gefunden): dieselbe Wetter-Beschreibung wurde acht Mal
  hintereinander identisch beim kostenfreien Anbieter angefragt, statt nur
  einmal.** Ein Debug-Log-Export zeigte acht exakt identische
  MyMemory-Requests für denselben Text ("Überwiegend bewölkt") innerhalb
  weniger Sekunden während einer Wetter-Kachel-Aktualisierung. Ursache:
  eine Wettervorhersage-HTMLBox (z. B. "8-Tage Vorhersage") wird per
  `SplitHtmlIntoTextNodes()` in einzelne Text-Knoten zerlegt und alle
  zusammen in einem einzigen `TranslateBatch()`-Aufruf übersetzt - teilen
  sich mehrere Tage zufällig dieselbe Beschreibung, landete `TranslateBatch()`
  bislang jedes weitere Vorkommen genauso im "braucht frische Übersetzung"-
  Topf wie das erste, weil der persistente Cache
  (`GetCachedTranslation`/`StoreCachedTranslation`) für diesen Text zu
  diesem Zeitpunkt noch leer war - er wird erst NACH Abschluss des
  GESAMTEN Batches befüllt, zu spät für weitere Vorkommen IM SELBEN Batch.
  Für den kostenfreien Anbieter (kein echter Batch-Endpunkt, ein
  HTTP-Request pro Text, siehe `TranslateChunkFree`) bedeutete das: derselbe
  Text wurde so oft angefragt, wie er im Batch vorkam, statt nur einmal -
  unnötiger Verbrauch des ohnehin knappen Tageskontingents.

  Fix: `TranslateBatch()` merkt sich jetzt zusätzlich zum bestehenden
  persistenten Cache eine reine Batch-interne Text→Position-Zuordnung -
  jedes weitere Vorkommen desselben Rohtexts im selben Batch wird direkt als
  Duplikat erkannt und übernimmt nach Abschluss des Batches das bereits für
  sein erstes Vorkommen aufgelöste Ergebnis, ganz ohne eigenen Anbieter-
  Aufruf. Zählt dabei genauso in die Statistik "Durch den Cache eingespart"
  wie ein echter Cache-Treffer - aus Nutzersicht ist es exakt dieselbe Art
  von vermiedener Anfrage. Betrifft strukturell jeden Aufrufer von
  `TranslateBatch()` mit mehreren, potenziell identischen Texten im selben
  Aufruf (v. a. HTML-Widgets mit mehreren, inhaltlich manchmal
  übereinstimmenden Text-Knoten), nicht nur Wetter-Widgets.

  **Separat dazu, kein Modul-Bug:** derselbe Nutzer beobachtete außerdem,
  dass MyMemorys Web-Oberfläche für denselben Suchbegriff (z. B.
  "Sprache"→"Idioma") ein ANDERES Ergebnis liefert als die von diesem Modul
  genutzte API (z. B. "Español"). Das ist eine Eigenheit des kostenfreien
  Anbieters selbst - MyMemory ist eine reine Übersetzungsspeicher-Datenbank
  (Translation Memory) aus unzähligen, unterschiedlich zuverlässigen
  Quellen; Web-Oberfläche und API können für sehr kurze, mehrdeutige Texte
  (einzelne Wörter ohne Satzkontext, z. B. "Sprache" oder "Shuffle") einen
  unterschiedlichen Top-Treffer aus dieser Datenbank ausliefern. Simple
  Locale übernimmt exakt das, was die API liefert - dieselbe bereits
  dokumentierte Einschränkung wie "Die automatische Übersetzung kann
  trotzdem Fehler machen" ([Abschnitt 2](#2-bekannte-einschränkungen)): bei
  kurzen, kontextlosen Einzelwörtern lohnt sich eine manuelle Prüfung/
  Korrektur besonders.

  Regressionstest ergänzt, volle Suite grün.
* **Build 118 (live gefunden, Build 117 reichte nicht aus): dieselbe
  Wetter-Beschreibung wurde weiterhin bis zu zwölf Mal identisch beim
  Anbieter angefragt, trotz des Build-117-Fixes.** Ein neuer Debug-Log-
  Export bestätigte, dass das Problem unverändert bestand. Ursache: Build
  117 deduplizierte identische Texte nur auf der Ebene ganzer
  Zeilen-Rohtexte (dem `$Texts`-Array, das `TranslateBatch()` von seinem
  Aufrufer bekommt) - für HTML-Inhalte (z. B. ein Wetter-Widget als einzelne
  "Eigene Texte"-Zeile) zerlegt `TranslateBatchUncached()` aber JEDE Zeile
  intern NOCHMAL in einzelne Text-Knoten (`SplitHtmlIntoTextNodes`) und
  sammelt ALLE Knoten über alle Zeilen hinweg in einer eigenen, flachen
  Liste (`$translatable`), die erst DANACH an den Anbieter geschickt wird -
  diese Knoten-Ebene liegt UNTERHALB der Ebene, auf der Build 117
  dedupliziert. Teilen sich mehrere Vorhersage-Tage INNERHALB EINER
  EINZIGEN Wetter-Widget-Zeile zufällig dieselbe Beschreibung, blieben die
  daraus resultierenden identischen Text-Knoten von Build 117 komplett
  unberührt und wurden weiterhin einzeln angefragt.

  Fix: `TranslateBatchUncached()` dedupliziert jetzt zusätzlich auf dieser
  tieferen Knotenebene, direkt bevor die Liste an den Anbieter geschickt
  wird (`array_unique($translatable)`, Übersetzung nur für die eindeutigen
  Werte, danach per `array_combine()` auf die volle, ursprüngliche
  Knotenliste zurückgemappt). Die nachgelagerte Cursor-basierte
  Rekonstruktion (die exakt dieselbe Länge/Reihenfolge wie die Eingabe
  erwartet, siehe Kommentar bei `SplitHtmlIntoTextNodes`) bleibt dadurch
  unverändert kompatibel - nur die tatsächliche Anzahl der
  Anbieter-Anfragen sinkt auf die Anzahl eindeutiger Knoten. Bonus-Effekt:
  da Duplikate jetzt schon vor dem Chunking (`translateMaxTextsPerRequest`)
  herausfallen, geht das Chunk-Größenlimit nicht mehr unnötig durch
  wiederholte identische Knoten verloren.

  Regressionstest ergänzt (bildet exakt den gemeldeten Fall nach - fünf
  Text-Knoten einer einzigen HTML-Zeile mit drei eindeutigen Werten), volle
  Suite grün.
* **Build 119 (Nutzer-Wunsch, direkt im Anschluss an Build 118): warum
  profitieren Wochentags-Kürzel und wiederkehrende Wetterbeschreibungen
  nicht vom persistenten Cache, obwohl sie bei jeder Aktualisierung
  identisch bleiben?** Berechtigter Einwand. Sowohl der persistente
  Übersetzungs-Cache (`GetCachedTranslation`/`StoreCachedTranslation`) als
  auch die "Eigene Übersetzungstabelle" (`FindManualTranslation`) wurden
  bislang nur in `TranslateBatch()` geprüft - auf der Ebene ganzer
  Zeilen-Rohtexte. Ein Wetter-Widget als HTML-Zeile ändert aber bei JEDER
  Aktualisierung seinen GESAMTEN Roh-Inhalt (neue Messwerte), daher trifft
  dieser Zeilen-Cache so gut wie nie - obwohl viele einzelne Text-Knoten
  darin (Wochentags-Kürzel, Beschreibungen wie "Überwiegend bewölkt")
  Aktualisierung für Aktualisierung identisch bleiben und daher eine hohe
  Trefferquote haben müssten. Genau die Knotenebene, auf der Build 118 die
  Innerhalb-eines-Aufrufs-Deduplizierung eingeführt hat, kannte weder den
  Cache noch die manuelle Tabelle.

  Fix: `TranslateBatchUncached()` prüft jetzt jeden eindeutigen Knoten
  zusätzlich einzeln gegen die manuelle Übersetzungstabelle und den
  persistenten Cache, BEVOR er überhaupt an den Anbieter geschickt wird -
  nur tatsächlich unbekannte Knoten lösen noch einen echten
  Anbieter-Aufruf aus, dessen Ergebnis anschließend selbst wieder gecacht
  wird. Damit werden Wochentags-Kürzel und wiederkehrende Beschreibungen
  spätestens ab der zweiten Aktualisierung nie wieder angefragt, egal wie
  oft sich der umgebende HTML-Rohtext (Messwerte) ändert. Nebenbei
  bestätigt: die "Aktiv, aber pausiert"-Sperre (`GetGlobalPauseUntil()`,
  siehe `TranslateChunk()`) sitzt bereits strukturell UNTERHALB von
  Cache-/Tabellen-Prüfung - ein Cache- oder Tabellentreffer wurde und wird
  nie durch eine laufende Anbieter-Pause blockiert.

  Regressionstest ergänzt (ein bereits gecachtes Wochentags-Kürzel/eine
  bereits gecachte Beschreibung dürfen bei einem neuen Update mit
  komplett anderem umgebendem Rohtext nicht erneut angefragt werden;
  ein manueller Tabelleneintrag greift ebenfalls auf Knotenebene), volle
  Suite grün.

* **Build 120 (Nutzer-Wunsch): Lizenz-Widerruf braucht eine Möglichkeit,
  eine bereits ausgestellte Lizenz zu deaktivieren.** Bisher gab es nur den
  einmaligen Aktivierungs-Check (siehe Abschnitt 8, "Upgrade-Lizenzen und
  der Blockier-Mechanismus") - der greift aber nur bei einer ÄNDERUNG des
  eingetragenen Schlüssels, nicht laufend. Für einen Widerruf/eine
  Rückerstattung reicht das nicht: die Installation läuft dabei ja
  unverändert weiter.
  Neuer täglicher Timer (`CheckLicenseStatus()`/`PerformDailyLicenseCheck()`,
  Ident `timerIdentLicenseCheck`, 24h-Intervall wie von Kai gewünscht) fragt
  denselben Meldeserver-Endpoint unabhängig von einer Schlüssel-Änderung ab.
  Zwei neue Antwort-Formen, ausgewertet über eine mit `RecordLicenseActivation()`
  geteilte `ApplyActivationReportResponse()`: `{"revoked": true}` (Admin hat
  im Shop deaktiviert - anders als "blocked" OHNE Testphasen-Reset, siehe
  `attributeRevokedLicenseKeyHash`) und `{"active": true, "expiresAt": ...}`
  (bestätigt aktiv, liefert das aktuell effektive Ablaufdatum). Letzteres
  ermöglicht als Nebeneffekt eine Abo-Verlängerung/-Verkürzung rein
  serverseitig, ohne neuen signierten Schlüssel (`GetLicenseInfo()`
  überschreibt das signierte `expiresAt` mit einem passenden, per Hash an
  den aktuellen Schlüssel gebundenen Override). Fail-open wie beim
  bestehenden Meldeserver-Aufruf: eine nicht erreichbare/fehlerhafte
  Antwort ändert nichts am zuletzt bekannten Stand.
  Standalone-Simulationstest (7 Szenarien: Widerruf ohne Testphasen-Reset,
  serverseitiges Zurücknehmen, Verlängerung/Verkürzung per Override,
  Fail-open, "blocked"-Regression) gegen einen echt signierten Testschlüssel
  (reales Ed25519-Testpaar) grün. Entsprechende Shop-Seite (Checkbox
  "Aktiv" + Ablaufdatum-Override, Synergetix-Website-Repo,
  `shop/admin/order.php`) ist ein separater Commit in diesem Repo.

* **Build 121 (Nutzer-Report, live per Debug-Log gefunden): ein Medienplayer-
  Widget ("Echo Info", Alexa/Echo-Kachel) mit eingebettetem Cover-Bild
  (`<img src="data:image/png;base64,...">`, mehrere zehntausend Zeichen ganz
  ohne `<`/`>`) verbrauchte bei JEDER Aktualisierung einen zweistelligen
  Kilozeichen-Betrag Übersetzungs-Kontingent, obwohl das Widget gar keinen
  echten Text enthielt.** Ursache: `SplitHtmlIntoTextNodes()`s Tag-
  Aufteilungs-Regex (`preg_split('/(<[^>]*>)/s', ...)`) scheiterte an so
  einem großen, zusammenhängenden Block an PHPs PCRE-Backtrack-Grenze -
  `preg_split()` lieferte `false`, und der dafür bereits vorhandene Fallback
  griff: der KOMPLETTE Rohinhalt (inklusive Bilddaten) wurde unverändert als
  EIN einziger "Textknoten" an den Übersetzer geschickt, statt (wie
  beabsichtigt) in einzelne kurze Textstücke zerlegt zu werden. Kein Crash,
  keine kaputte Rekonstruktion - aber live per Debug-Log bestätigt: über
  22.000 Zeichen pro Anfrage, wiederholt bei praktisch jeder
  Medienplayer-Aktualisierung (VM_UPDATE), nicht nur bei einem Rescan.
  Fix: Data-URIs (`data:...;base64,...`) werden jetzt VOR jeder weiteren
  Verarbeitung durch kurze Platzhalter ersetzt (macht die Regex wieder
  unproblematisch kurz) und beim Zusammenbau des übersetzten Ergebnisses
  exakt wieder eingesetzt - unabhängig davon, ob am Ende doch noch der
  Fallback greift. Betrifft jedes HTML-Widget mit eingebettetem Bild
  (Cover-Art, Icons, ...) als "Eigene Texte"-Zeile oder Begrüßung, nicht nur
  Echo-Kacheln. Neuer Regressionstest (großes eingebettetes Bild taucht in
  keinem Textknoten mehr auf, Rekonstruktion bleibt exakt, kein
  Platzhalter-Rest im Ergebnis, normales HTML ohne Data-URI unverändert),
  volle Suite grün.

* **Build 122, rein diagnostisch: temporäres Debug-Logging in
  `ReconcileRowSourceLanguageChanges()`.** Läuft der Untersuchung eines
  gemeldeten Falls nach, bei dem manuell korrigierte Übersetzungen in
  "Automations" und "Objektnamen" ohne erkennbaren Auslöser (kein
  "Aufräumen"-Klick, keine manuelle Löschung) durch frische
  Maschinenübersetzungen ersetzt wurden - ein Property-Dump zeigte dabei
  alle betroffenen Zielsprachen-Zellen mit nahezu identischem Zeitstempel
  neu befüllt, ein Muster, das zu `ReconcileRowFields()`s
  "Quellsprache hat sich geändert"-Erkennung passt. Loggt vor jeder
  Mutation Property, Zeilen-Schlüssel sowie die alten
  `Quellsprache`/`UebersetztGegen`-Werte einer Zeile, für die dieser Pfad
  auslösen wird - wird nach Abschluss der Untersuchung wieder entfernt.

* **Build 123, rein diagnostisch: `AutoRescan()` loggt jetzt seinen eigenen
  Start.** Bisher waren ein manueller Rescan (Button) und ein
  Timer-ausgelöster Auto-Rescan im Debug-Log nicht unterscheidbar (beide
  riefen `ScanRootTree()` identisch auf). Neue Zeile
  `AutoRescan: Timer-ausgeloester Rescan startet jetzt` direkt beim
  Timer-Callback - hilft, im Rahmen derselben Automations-Korruptions-
  Untersuchung (siehe Build 122) zu bestätigen oder auszuschließen, ob ein
  gemeldeter Vorfall zeitlich mit einem automatischen Hintergrund-Rescan
  zusammenfällt. Wird zusammen mit dem Build-122-Logging wieder entfernt.

* **Build 124 (direkter Nachbericht zu Build 121, live per Debug-Log
  bestätigt): ein HTML-Segment ganz ohne echten Text landete weiterhin im
  "ganzer Block als ein Knoten"-Fallback.** Build 121 verhinderte, dass ein
  eingebettetes Base64-Bild selbst zum Übersetzungsgegenstand wird - aber
  der umgebende Rest (z.B. leere `<div>`s einer Medienplayer-Kachel ohne
  aktuell laufenden Titel) hatte danach zwar keine Bilddaten mehr, aber
  eben auch KEINEN echten Text - und wurde trotzdem komplett an den
  Übersetzer geschickt, live bestätigt als wiederholte identische Anfrage
  bei jedem Update (derselbe leere Block ändert sich ja nie). `SplitHtmlIntoTextNodes()`
  unterscheidet jetzt sauber zwischen einem echten Parse-Fehler (weiterhin
  konservativ: ganzer Block als ein Knoten, da unbekannter Inhalt) und
  einem erfolgreich geparsten, aber ausschließlich aus Tags/Leerraum
  bestehenden Segment (liefert jetzt gar keinen Übersetzungs-Knoten mehr -
  nichts zu tun, keine Anfrage nötig). Regressionstest erweitert (leeres
  Segment mit eingebettetem Bild liefert null Knoten, Bild bleibt trotzdem
  exakt erhalten; echter Text neben einem Bild liefert weiterhin genau
  diesen einen Knoten), volle Suite grün.

* **Build 125 (Nutzer-Wunsch, direkter Nachbericht der Automations/
  Objektnamen-Korruptions-Untersuchung): der persistente Übersetzungs-Cache
  war nie mit manuellen Korrekturen synchronisiert.** Eine im Formular von
  Hand korrigierte Zielsprachen-Zelle (z.B. "Salir" statt der ursprünglich
  maschinell übersetzten "Andar") landete bisher ausschließlich in der
  jeweiligen Zeilen-Property - der persistente Cache
  (`StoreCachedTranslation`) wird ausschließlich nach einem frischen
  Anbieter-Aufruf befüllt, nie bei einer manuellen Eingabe. Wurde eine
  solche Zeile später aus irgendeinem Grund erneut als "veraltet" erkannt
  (z.B. durch `ReconcileRowFields`, siehe die noch laufende Untersuchung in
  Build 122/123), lieferte ein Cache-Treffer für denselben Rohtext die
  ALTE, vor der Korrektur gecachte Maschinenübersetzung zurück - und die
  landete dann ganz normal wieder in der Property: die manuelle Korrektur
  wurde nicht nur angezeigt-überschrieben, sondern dauerhaft persistiert
  verloren, unabhängig davon, was genau die Neuübersetzung ursprünglich
  ausgelöst hat.
  `ApplyLanguage()` synct jetzt bei jedem tatsächlichen Lauf (läuft dank
  des bestehenden Fingerprint-Kurzschlusses nicht bei jedem VM_UPDATE) den
  aktuell aufgelösten Zellwert jeder Zeile für die gerade aktive Sprache in
  den Cache zurück - ob der Wert ursprünglich von einem Anbieter oder von
  Hand kam, spielt für den Cache ab sofort keine Rolle mehr. Behebt damit
  nicht zwingend die noch unbekannte URSACHE der Automations/Objektnamen-
  Korruption, verhindert aber wirksam, dass eine erneute Übersetzung
  überhaupt noch auf einen veralteten, vor-korrigierten Cache-Eintrag
  zurückgreifen kann. Ein Lese-/Schreibvorgang für die gesamte
  Cache-Property statt je Zeile einzeln, schreibt nur bei tatsächlicher
  Änderung. Neuer Regressionstest (manuelle Korrektur überschreibt den
  alten Cache-Eintrag, bereits synchroner Eintrag verursacht keinen
  Schreibvorgang, Quellsprache und leere Zellen werden nie gesynct), volle
  Suite grün.

* **Build 126 (dringende Korrektur eines mit Build 125 live ausgelösten
  Fatal Error, sofort nach Meldung behoben): `ApplyChanges()` schlug für
  jede Instanz mit mindestens einer rein numerischen Original-Import-Zeile
  (z.B. ein rein aus Ziffern bestehender Automations-/Objektname) komplett
  fehl.** Ursache: `SyncCurrentLanguageIntoCache()` (Build 125) verwendet
  den Rohtext als Array-Schlüssel (`$updates[$sprache][$rohtext] = ...`) -
  PHP wandelt einen rein numerisch aussehenden String-Schlüssel dabei
  automatisch in einen echten Integer um (bekanntes PHP-Verhalten). Der so
  wieder ausgelesene Integer wurde ungeprüft an `BuildTranslationCacheKey()`
  weitergereicht, deren dritter Parameter zwingend einen String erwartet -
  Ergebnis: `TypeError: ... must be of type string, int given` bei JEDEM
  `ApplyChanges()`-Lauf, live bestätigt als "Error while applying changes".
  Erneutes `(string)`-Casting direkt vor der Verwendung behebt es. Zusätzlich
  in diesem Build:
  - Die "GoogleTranslate_Mapping"-Debug-Zeile (Diagnosehilfe, zeigt Rohtext
    je Batch-Position) kürzte lange Rohtexte nicht - ein umfangreiches
    HTML-Widget erzeugte dadurch eine einzelne Debug-Zeile von über 60.000
    Zeichen, obwohl die tatsächlich gesendeten Anfragen dank Knoten-
    Aufteilung längst klein waren. Auf 200 Zeichen pro Zeile gekürzt
    (Gesamtlänge bleibt sichtbar, ObjectID-Zuordnung bleibt vollständig
    erhalten).
  - Der persistente Übersetzungs-Cache (`GetCachedTranslation`/
    `StoreCachedTranslation`/`SyncCurrentLanguageIntoCache`) hatte keinerlei
    Schutz gegen überlappende Zugriffe. Nutzer-Report + eigene Bestätigung
    ("Vorhersage und Aktuelle Bedingungen werden vom gleichen Script
    aktualisiert"): Symcon dispatcht VM_UPDATE-Nachrichten nicht blockierend
    im auslösenden Skript, sondern als eigene, potenziell überlappende
    Skriptausführungen - setzt ein externes Skript kurz hintereinander
    mehrere Variablen, können zwei Übersetzungsläufe für denselben Rohtext
    (z.B. "Überwiegend Klar", das sowohl in "Aktuelle Bedingungen" als auch
    in der "Vorhersage" vorkommt) einander überholen: beide lesen den Cache,
    bevor der jeweils andere geschrieben hat - live bestätigt als zwei
    identische Anbieter-Anfragen im Sekundenabstand, bei einem alle 180
    Sekunden aktualisierenden Echo-Widget sogar wiederholt. Ein knapper,
    instanzweiter `IPS_SemaphoreEnter()`/`IPS_SemaphoreLeave()`-Sperrbereich
    um die jeweilige Lese-/Schreibsequenz auf `attributeTranslationCache`
    schließt die Lücke - best-effort (gelingt der Sperrerwerb binnen 1s
    nicht, wird ohne Sperre weitergemacht statt die Übersetzung ganz zu
    verwerfen). Zusätzlich temporäres Diagnose-Logging bei jedem Cache-Miss/
    -Schreibvorgang, um eine über die reine Gleichzeitigkeit hinausgehende
    Cache-Lücke (derselbe Text blieb auch nach 10 Minuten Abstand
    ungecacht) weiter einzugrenzen - wird nach Abschluss der Untersuchung
    wieder entfernt.
  Neuer Regressionstest für den numerischen-Schlüssel-Fix (löst keinen
  TypeError mehr aus, Symmetrie-Check gegen den realen `(string)`-Cast) und
  für die Mapping-Kürzung (lange Zeilen werden gekürzt, ObjectID-Zuordnung
  bleibt erhalten, kurze Texte unverändert), volle Suite grün.

* **Build 127 (direkter Nachbericht zu Build 126, live per Debug-Log
  aufgeklärt): der persistente Übersetzungs-Cache war bei jedem Zugriff
  exakt bei seiner Obergrenze von 1000 Einträgen - ständige Verdrängung,
  selbst ein bei jedem Update exakt gleichbleibender Text ("Echo Info" in
  einer Alexa-Medienplayer-Kachel) landete zehn Minuten in Folge wiederholt
  beim Anbieter statt aus dem Cache bedient zu werden.** Ursache
  gemeinsam mit dem Nutzer aufgeklärt: `TranslateBatch()` cachte bisher
  zusätzlich zum (eigentlich wertvollen) Knoten-Cache aus
  `TranslateBatchUncached()` auch den KOMPLETTEN Zeilen-Rohtext. Für ein
  HTML-Widget, dessen Gesamtinhalt sich durch neue Messwerte/Songtitel bei
  praktisch jedem Update ändert (live bestätigt: der Rohtext enthielt den
  aktuell auf dem Echo-Gerät laufenden Songtitel/Interpreten, z.B. "Más
  Cara [Explicit]" von "Bad Gyal"), ist so ein Eintrag so gut wie nie
  wiederverwendbar - belegt aber dauerhaft einen der 1000 begrenzten
  Cache-Plätze und verdrängt dadurch echte, oft wiederverwendete
  Knoten-Einträge wie den festen HTML-`<title>`-Text "Echo Info", noch
  bevor die überhaupt einen zweiten Treffer landen konnten. Für
  `$IsHtml`-Inhalte wird die ganze Zeile jetzt nicht mehr zusätzlich
  gecacht - nur noch die (bereits vorhandene, feingranulare) Knotenebene,
  wie vom Nutzer bestätigt ("im Cache sollten nur die API-gefeuerten
  Übersetzungen landen, nicht der HTML... Zerlegte HTML im Cache ist ok").
  Nicht-HTML-Zeilen (Objektnamen, Automations, ...) sind unverändert
  weiterhin auf Zeilenebene gecacht, da die typischerweise kurz und exakt
  wiederkehrend sind.
  Zusätzlich (Nutzer-Wunsch): die Diagnose-Log-Zeilen aus Build 126
  (Cache-Hit/Miss, Schreibvorgang) zeigen jetzt zusätzlich den mitgegebenen
  Kontext (z.B. `ValueObjectID=46091`) statt nur den Text - macht einen
  Log-Eintrag im Konfigurationsformular eindeutig einem konkreten Objekt
  zuordenbar.
  Neuer Regressionstest (HTML-Inhalt landet nicht mehr als ganze Zeile im
  Cache, Nicht-HTML-Zeilen bleiben unverändert gecacht, Symmetrie-Check
  gegen den realen `$IsHtml`-Schutz), volle Suite grün.

* **Build 128 (direkter Nachbericht zu Build 127, live per Debug-Log
  lückenlos bestätigt): der Cache stand bei jedem einzelnen Zugriff exakt
  auf seiner Obergrenze - "Überwiegend Klar" verschwand innerhalb
  desselben Wetter-Widget-Updates wieder und war eine Sekunde später für
  eine ANDERE Zeile bereits erneut weg, obwohl es gerade erst gecacht
  wurde.** Ursache: ein einzelnes HTML-Widget übersetzt oft mehrere
  brandneue Knoten auf einmal (z.B. "Überwiegend Klar" + Sonnenauf-/
  -untergang + Windgeschwindigkeit + Windrichtung) - `TranslateBatchUncached()`
  rief dafür `StoreCachedTranslation()` bisher einzeln je Knoten auf, jeder
  einzelne Aufruf sperrte/las/schrieb den Cache für sich.
  Präzisierung (per Nachrechnen bestätigt, siehe Test in
  `test_batch_cache_write_no_self_eviction.php`): rein rechnerisch, ohne
  Nebenläufigkeit, liefern Einzeln- und gesammeltes Schreiben bei
  gleichem Ausgangszustand dasselbe Endergebnis - das gesammelte Schreiben
  "rettet" für sich allein keinen Knoten vor echter Reservoir-Knappheit
  (zu wenige längst etablierte, gering genutzte Alteinträge, die die
  Verdrängung stattdessen treffen könnte). Der tatsächliche, belegbare
  Vorteil des gesammelten Schreibens (neue `StoreCachedTranslationsBatch()`,
  ersetzt den bisherigen Aufruf pro Knoten) ist ein anderer: nur noch EIN
  Sperr-/Lese-/Schreibzyklus pro Übersetzungs-Batch statt N einzelner -
  verkleinert das Zeitfenster für die in Build 126 bereits bestätigte
  Ursache (überlappende, gleichzeitig laufende Skriptausführungen bei
  einem Script, das mehrere Variablen kurz hintereinander setzt) und senkt
  den Gesamt-Overhead.
  Die eigentliche Abhilfe gegen das beobachtete "ständig voll"-Symptom ist
  die gleichzeitig erhöhte Cache-Kapazität: `TRANSLATION_CACHE_MAX_ENTRIES`
  1000 → 3000. Mehrere Echo-/Alexa-Geräte mit ständig wechselndem
  Songtitel/Interpret pro Update erzeugen einen konstanten Strom echt
  einmaliger Knoten-Einträge, die zwar korrekt via Hit-Zähler verdrängt
  werden, dafür bei nur 1000 Plätzen aber zu wenig Puffer ließen, bis
  häufig wiederverwendete Kerntexte (der feste "Echo Info"-Titel,
  wiederkehrende Wetterbeschreibungen) einen rettenden zweiten Treffer
  verzeichnen konnten.
  Neuer Regressionstest: bestätigt per Nachrechnen, dass beide
  Schreibvarianten bei knappem UND bei ausreichendem Reservoir dasselbe
  Ergebnis liefern (keine falsche "Wunder-Fix"-Erwartung), verifiziert den
  tatsächlichen Vorteil (ein einziger Sperr-/Lese-/Schreibzyklus statt N),
  und bestätigt die erhöhte Kapazität, volle Suite grün.

* **Build 129, zwei gemeinsam hergeleitete Verbesserungen des
  Übersetzungs-Caches (Nutzer-Wunsch/-Report):**
  - **Cache-Kapazität weiter erhöht, 3000 → 10.000.** Gemeinsame Herleitung
    mit dem Nutzer: der "harte Kern" tatsächlich dauerhaft wertvoller
    Cache-Einträge ist klein (nur Zeilen, deren Rohtext sich bei JEDER
    Aktualisierung ändert, durchlaufen überhaupt `TranslateBatch()` -
    bereits gefüllte statische Zeilen wie Objektnamen/Automations werden
    über `ResolveRowValue()` DIREKT aus der Property gelesen, nie über den
    Cache). Grob geschätzt 50-150 wirklich wiederkehrende Rohtexte
    (Wochentags-Kürzel, gängige Wetterbeschreibungen, feste Widget-Label) ×
    2-3 Zielsprachen ergeben etwa 100-450 Einträge harten Kern. Da der
    Cache (ein lokaler JSON-Attribut-Zugriff, keine Netzwerklatenz) selbst
    bei deutlich größeren Werten um Größenordnungen schneller bleibt als
    jeder Anbieter-Aufruf (spürbare Verlangsamung realistisch erst im
    Bereich mehrerer Zehntausend Einträge/mehrerer MB JSON), gibt es keinen
    Nachteil darin, die Kapazität komfortabel über den harten Kern zu
    setzen - schützt insbesondere deutlich größere Installationen als
    diese vor dem in Build 126-128 behobenen Verdrängungseffekt und spart
    Übersetzungskontingent.
  - **Der äußere, zeilen-weite Cache-Check in `TranslateBatch()` wird für
    `$IsHtml`-Inhalte komplett übersprungen.** Live per Debug-Log gefunden
    (Nutzer-Beobachtung): seit Build 127 landet der komplette
    HTML-Zeilen-Rohtext nie mehr im Cache (nur noch seine Knoten, siehe
    `TranslateBatchUncached`/`StoreCachedTranslationsBatch`) - ein
    `GetCachedTranslation()`-Aufruf dafür ist bei `$IsHtml=true` also
    strukturell IMMER ein Fehlschlag, kostete aber trotzdem
    Semaphor-Erwerb, volles Lesen/Dekodieren des gesamten (jetzt bis zu
    10.000 Einträge großen) Caches und eine Hash-Berechnung über ein
    komplettes HTML-Dokument - live bestätigt als wiederholte garantierte
    Leerläufe für ganze `<!doctype html>`/`<style>`/`<table>`-Blöcke.
    Nicht-HTML-Inhalte (Objektnamen, Automations, ...) sind unverändert
    weiterhin auf Zeilenebene gecacht, da dort ein Treffer tatsächlich
    möglich ist.
  Neuer Regressionstest für den übersprungenen Zeilen-Cache-Check bei
  HTML-Inhalten (kein Aufruf mehr, Text läuft trotzdem normal in die
  Knoten-Aufteilung weiter; Nicht-HTML-Zeilen unverändert), volle Suite
  grün.

* **Build 130 (Nutzer-Wunsch): Überarbeitung der Nutzerführung im
  Konfigurationsformular, mehrere Verbesserungen für mehr Übersicht.**
  - Alle Kachel-bezogenen Optionen (Symbole ein-/ausblenden,
    Übersetzungsstatistik in der Kachel, eigene Sprachauswahl-Kachel inkl.
    HTML-Editor) sind jetzt in einem eigenen, standardmäßig eingeklappten
    ExpansionPanel "Kachel-Einstellungen" zusammengefasst, statt einzeln
    im Konfiguration-Panel zu stehen.
  - Die Statistiken sind jetzt zweispaltig/eingerückt dargestellt: links
    steht einmalig "Statistiken" als Überschrift, rechts die eigentlichen
    Werte (Laufzeit, stündlich, insgesamt, durch Cache eingespart).
  - "Automatischer Rescan" steht jetzt direkt über dem neuen
    "Kachel-Einstellungen"-Panel statt am Ende des Konfiguration-Panels.
  - Neue kurze Erklärung direkt unter "Scan-Sprache": weist auf das in
    Abschnitt 7 dokumentierte Verhalten hin, dass bereits erfasste Zeilen
    ihre eigene, eingefrorene Quellsprache behalten, und dass ein
    laufender Automatischer Rescan ein neues Objekt fälschlich in der
    falschen Sprache einfrieren kann, wenn dabei nicht "Aktuell aktive
    Sprache" = Scan-Sprache aktiv ist.
  - Alle neun Übersetzungstabellen (Zielsprachen, Objektnamen, Eigene
    Texte, Beschriftungen, Automations, Charts, Begrüßung, Unbenannte
    Objekte, Eigene Übersetzungstabelle) sind jetzt einheitlich auf 100%
    Bildschirmbreite gestreckt.
  - Das Lizenz-Panel klappt nicht mehr allein deshalb automatisch auf,
    weil irgendein Lizenzschlüssel eingetragen ist (betraf praktisch jede
    aktiv genutzte Instanz dauerhaft) - nur noch, wenn die Testphase
    tatsächlich abgelaufen ist.
  - Die langen Erläuterungstexte unter den vier Aktions-Buttons ("Rescan",
    "Aufräumen", "Cache leeren", "Anbieter prüfen") stehen nicht mehr als
    Dauertext im Formular, sondern sind in ein Info-Popup ausgelagert,
    erreichbar über ein ℹ️-Symbol direkt neben dem jeweiligen Button.
  Zwei neue Sprachdatei-Einträge (Panel-Beschriftung "Kachel-
  Einstellungen", neue Scan-Sprachen-Erklärung) in allen vier
  Konsolensprachen ergänzt. Neuer Regressionstest (neues Panel + Inhalt,
  Positionierung von Automatischem Rescan, zweispaltige Statistiken,
  Scan-Sprachen-Erklärung, 100%-Breite aller Tabellen, Info-Popups statt
  Dauertext, Lizenz-Panel-Bedingung, generische Formular-Rekursion), volle
  Suite grün.

* **Build 131 (direkter Nachbericht zu Build 130, Nutzer-Feedback): mehrere
  Korrekturen und Ergänzungen an der Formular-Überarbeitung.**
  - **"Statistiken" fehlte komplett in `locale.json`** - live gefunden,
    blieb in einer nicht-deutschen Konsolensprache unübersetzt stehen.
    Ergänzt in allen vier Sprachen. Bei der Gelegenheit auch zwei weitere,
    schon länger bestehende Lücken geschlossen ("- Google Cloud
    Translate:", "- DeepL:" in den Anbieter-Pausenzeilen, sowie
    "Quelltext" in der Eigenen Übersetzungstabelle).
  - Neuer, genereller Regressionstest: gleicht jetzt JEDE buchstabenhaltige
    Formular-Beschriftung (inkl. Popups, Spalten-Überschriften) gegen
    `locale.json` ab, mit expliziten Ausnahmen für reine Marken-/
    Produktnamen und das Info-Symbol - hätte den "Statistiken"-Bug von
    Anfang an gefangen.
  - Neue Erklärung direkt nach "Bevorzugter Anbieter" (Nutzer-Nachfrage,
    gemeinsam verifiziert): DeepL verlangt für Englisch/Portugiesisch
    zwingend eine großgeschriebene Regionsvariante (EN-GB/EN-US,
    PT-PT/PT-BR) statt eines einfachen "EN"/"PT" wie bei Google - ein
    Anbieterwechsel kann daher bereits gewählte Zielsprachen ungültig
    machen. Verweist auf Abschnitt 7 der Dokumentation.
  - Die vier Info-Popup-Buttons aus Build 130 sind jetzt schmal (40px)
    statt in voller Button-Breite - sollte optisch eher wie ein
    Icon-Knopf statt wie ein zweiter vollwertiger Button wirken. Zusätzlich
    ein experimenteller `"icon": "information"`-Versuch, um Symcons
    Standard-Zahnradsymbol auf PopupButton-Elementen zu ersetzen -
    ungewiss, ob Symcons Formular-Renderer das tatsächlich unterstützt,
    Rückmeldung nach Installation nötig.
  Neuer Regressionstest (locale.json-Vollständigkeit für das gesamte
  Formular, DeepL-Erklärung vorhanden und korrekt platziert, schmale
  Info-Buttons), volle Suite grün.

* **Build 132 (Nutzer-Frage, gemeinsam hergeleitet): die vier
  Übersetzungs-Statistik-Zähler liefen als klassische 32-Bit-Integer-
  Attribute, ganz ohne Überlaufschutz.** IP-Symcons `Integer`-Attributtyp
  ist laut SDK ein 32-Bit-Integer (Bereich bis 2.147.483.647) - unabhängig
  davon, dass PHP selbst auf jedem 64-Bit-System einen 64-Bit-Integer
  verwendet. Bei sehr langer Laufzeit (mehrere Jahre) hätte das
  theoretisch zu einem stillen Überlauf/Wraparound beim Schreiben in
  Symcons 32-Bit-Speicher führen können, obwohl PHPs eigene
  Rechenoperation (`+1`/`+N`) selbst nie überläuft - eine Instanz, die
  jahrelang unbeaufsichtigt läuft, ist genau das erwartete
  Einsatzszenario dieses Moduls.
  `attributeStatsRequestCount`/`CharacterCount`/`CacheSavedRequestCount`/
  `CacheSavedCharacterCount` sind jetzt `RegisterAttributeString` (praktisch
  unbegrenzt, Rechnen weiterhin über normale PHP-Ints, nur die
  Persistierung ändert sich) statt `RegisterAttributeInteger`, unter neuen
  Attributnamen ("V2"-Suffix) statt denselben Namen mit geändertem Typ
  wiederzuverwenden (unklares/riskantes Symcon-Verhalten). `Create()`
  migriert dabei einmalig die bereits real angesammelten alten
  Integer-Zählerstände einer laufenden Installation in die neuen
  String-Attribute, bevor die alten Namen als reine, fortan nie mehr
  aktualisierte Altlast liegen bleiben (harmlos, da Attribute nicht im
  sichtbaren Objektbaum stehen).
  Neuer Regressionstest (reale historische Zählerstände werden korrekt
  migriert statt verloren zu gehen, eine frische Installation bleibt bei
  0, die Migration greift garantiert nur einmal und überschreibt keinen
  bereits weitergezählten Wert, Symmetrie-Check gegen die reale
  Umstellung), volle Suite grün.

* **Build 133 (Nutzer-Wunsch, gemeinsam hergeleitet): "Eigene
  Übersetzungstabelle" wird jetzt ab Standard-Lizenz mit Vorschlagszeilen
  für gängige Maßeinheiten und Kompassrichtungen vorbefüllt - erspart
  unnötige (und teils sogar fehleranfällige) API-Aufrufe für Zahl-plus-
  Einheit-Texte.** Auslöser war ein Dump-Fund: kurze Texte wie "0.82 m/s"
  gingen bisher immer an den Übersetzungsanbieter, obwohl praktisch jede
  gängige SI-/Alltagseinheit (°C, kg, km/h, hPa, kWh, ...) in jeder
  unterstützten Sprache identisch geschrieben wird - reine Verschwendung
  von Kontingent. Schlimmer: dabei beobachtet wurde eine tatsächlich
  FALSCHE automatische Übersetzung von "°C" nach "°F" (Zahlenwert blieb
  gleich, Anzeige wäre dadurch falsch gewesen).
  Kompassrichtungen sind dagegen das Gegenteil von universell -
  dieselbe Buchstabenfolge kann in verschiedenen Sprachen Gegenteiliges
  bedeuten: deutsch "O" = Ost/East, spanisch "O" = Oeste/**West** -,
  Russisch und Türkisch verwenden zusätzlich völlig andere Buchstaben statt
  N/O/S/W. Ein reines 1:1-Durchreichen (wie bei den Einheiten) wäre hier
  also grundsätzlich falsch.
  Bewusst NICHT als unsichtbare interne Tabelle umgesetzt (erste Idee),
  sondern als vorbefüllte Zeilen der bereits vorhandenen, sichtbaren
  "Eigenen Übersetzungstabelle" (`MergeBundledManualTranslations()`,
  analog zu `MergeOwnUiTextRows()` aus Build 78/85) - der Admin sieht die
  Vorschläge direkt im Formular und kann jeden einzelnen jederzeit löschen
  (Beispiel: "SSW" kollidiert in einer Installation zufällig mit einem
  Personen-Kürzel statt einer Windrichtung). Eine einmal gelöschte
  Vorbelegung kehrt dank eines neuen Merkzettel-Attributs
  (`attributeSeededManualTranslationKeys`) bei einem späteren Rescan nicht
  zurück - anders als bei `propertyOwnUiTexts` gibt es hier also KEINE
  Zwangs-Neuerzeugung.
  9 Zielsprachen (Englisch, Spanisch, Französisch, Italienisch,
  Portugiesisch, Niederländisch, Polnisch, Russisch, Türkisch) - bewusst
  nicht weiter ausgebaut: jede zusätzliche Sprache müsste die
  Kompass-Kürzel und ihre sprachspezifische Bedeutung einzeln verifizieren,
  sonst droht genau die Art Fehler, die diese Tabelle eigentlich vermeiden
  soll. Wie beim restlichen Glossar naturgemäß nur ab Standard-Lizenz
  (`manual_translations`) - die Light-Edition ruft für diese Fälle
  weiterhin ganz normal die API auf.
  Neuer Regressionstest (universelle Einheiten identisch über alle
  Sprachen, Kompassrichtungen genuin sprachspezifisch übersetzt statt naiv
  durchgereicht inkl. des deutsch/spanisch-"O"-Bedeutungswechsels,
  gelöschte Vorschläge bleiben dauerhaft gelöscht, bestehende Zeilen werden
  nie dupliziert/überschrieben, Light-Edition bekommt nichts,
  Symmetrie-Check gegen die reale Umsetzung), volle Suite grün.

* **Build 134 (direkter Nachbericht zu Build 133, Nutzer-Wunsch: "prüfe alle
  Übersetzungen der Abkürzungen mal explizit auf Korrektheit"): nicht jede
  Einheit aus Build 133 ist tatsächlich in jeder der 9 Sprachen identisch.**
  Konkreter Auslöser: "km/h" wird im Spanischen umgangssprachlich als "kph"
  abgekürzt, nicht mit dem lateinischen SI-Kürzel "h" für Stunde. Bei der
  daraufhin angeforderten vollständigen Prüfung aller ~68 Einheiten- und
  aller 16×9 Kompass-Kürzel gegen die jeweilige Sprachlogik (Wortmuster,
  Zwischenrichtungs-Systematik) bestätigten sich zwei weitere,
  strukturell identische Fälle: Niederländisch schreibt Geschwindigkeit
  ("uur" = Stunde) als "km/u", Türkisch ("saat" = Stunde) als "km/sa". Neue
  Konstante `UNIT_BUNDLED_LANGUAGE_OVERRIDES` (Einheit => Sprache =>
  abweichendes Kürzel), angewendet NACH dem universellen Durchreichen aus
  Build 133 - reine Energie-Einheiten mit "h" (Wh/kWh/Ah/mAh) sind davon
  bewusst NICHT betroffen, da Stromrechnungen/Batteriepackungen dort auch in
  diesen drei Sprachen weiterhin unverändert das international übernommene
  SI-Kürzel verwenden, nur die Geschwindigkeitsangabe weicht ab.
  Größerer Fund bei derselben Prüfung: Russisch schreibt in der Praxis
  (Konsumgeräte, Windows-Lokalisierung, GOST-Normschreibweise) fast
  durchgehend KYRILLISCHE Kürzel statt lateinischer SI-Symbole - "kg" wäre
  dort als reines Durchreichen schlicht falsch, korrekt ist "кг". Rund 60
  der 68 Einheiten-Einträge bekommen daher jetzt eine eigene russische
  Übersetzung (u. a. "км/ч", "кВт·ч", "Гц", "Дж", "об/мин") - bewusst
  ausgenommen bleiben "%"/"‰" (universelle Symbole), "°F"/"psi" (in Russland
  praktisch nie genutzte nicht-metrische Einheiten) sowie "ppm"/"ppb" (auch
  im Russischen überwiegend als lateinisches Fachkürzel übernommen).
  Bei der gleichen Gelegenheit wurde die komplette Kompass-Tabelle aus Build
  133 nochmal Zeile für Zeile gegen die jeweilige Richtungs-Systematik
  durchgerechnet (Niederländisch: Z für Zuid statt S, O für Oost statt E;
  Polnisch: bewusst identisch zu Englisch, da "Północ"/"Południe" beide mit
  P beginnen und eigene Initialen dort unbrauchbar wären; Russisch und
  Türkisch: vollständig gegen die systematische
  "nähere-Haupt-plus-Zwischenrichtung"-Logik verifiziert) - keine weiteren
  Fehler gefunden. Eine echte Unsicherheit blieb: europäisches Portugiesisch
  nutzt "E" (Este) für Ost, brasilianisches Portugiesisch dagegen häufig "L"
  (Leste) - da das Modul nur ein einziges "pt" ohne BR/PT-Unterscheidung
  kennt und beide Varianten sprachlich korrekt sind, wurde dies dem Nutzer
  explizit zur Entscheidung vorgelegt statt einseitig geraten; Ergebnis:
  bleibt beim bisherigen "E" (europäisch).
  Neuer Regressionstest (km/h bekommt die bestätigten es/nl/tr/ru-Ausnahmen
  statt naivem Durchreichen des deutschen Kürzels, Einheiten ohne eigene
  Ausnahme fallen weiterhin auf das universelle Kürzel zurück, Russisch
  bekommt für "kg" & Co. tatsächlich die kyrillische Form, Symmetrie-Check
  gegen die reale Konstante inkl. mehrerer Stichproben-Einträge), volle
  Suite grün.

* **Build 135 (Nutzer-Wunsch): neue Checkbox "Übersetzung aktiv" in
  "Objektnamen", "Automations" und "Begrüßung" - deaktiviert für genau eine
  Zeile dauerhaft jede Übersetzung.** Gedacht für Einträge, die bewusst NIE
  übersetzt werden sollen (Eigennamen, Marken, technische Kürzel) - z. B. ein
  Mitbewohner-Name in einer Präsenz-Anzeige, der sonst je nach Zielsprache
  unerwünscht "übersetzt" würde. Eine deaktivierte Zeile zeigt ab sofort immer
  ihren Rohtext, unabhängig von der aktuell aktiven Gast-Sprache - technisch
  über eine neue, zentrale Weiche `GetEffectiveSelectedLanguage()`, die für
  eine deaktivierte Zeile an allen drei Schreibstellen
  (`ApplyLanguage`/`ApplyAutomationsLanguage`/`ApplyGreetingLanguage`)
  konsequent die Pseudo-Sprache `ORIGINAL_IMPORT` statt der echten Zielsprache
  an `ResolveRowValue()` übergibt.
  Bewusst mit "true" (Übersetzung aktiv) vorbelegt - der Admin schaltet
  gezielt einzelne Ausnahmen ab, nicht umgekehrt. Ein Rescan fragt für eine
  deaktivierte Zeile erst gar keine Übersetzung mehr an (spart API-Kontingent
  für Inhalte, die ohnehin nie in übersetzter Form gezeigt würden).
  Sorgfältig gegen bereits VOR Build 135 gespeicherte Installationen
  abgesichert: eine alte Zeile ohne das neue Feld wird beim nächsten Rescan
  aktiv mit `true` nachgetragen (neue Funktion
  `BackfillTranslationActiveFlag()`, analog zu `BackfillRowSourceLanguage()`
  aus Build 70) - ohne diesen Schritt hätte die Checkbox in der Konsole für
  JEDEN bestehenden Eintrag fälschlich "nicht abgehakt" angezeigt (Symcons
  List-Element zeigt eine fehlende Checkbox-Vorbelegung als unchecked an),
  obwohl die Zeile weiterhin ganz normal übersetzt worden wäre - ein rein
  kosmetischer Unterschied hätte bei einer versehentlichen Bearbeitung eines
  ANDEREN Felds derselben Zeile sofort zu echtem, unbeabsichtigtem
  Übersetzungsausfall geführt. Der Backfill überschreibt dabei nie eine
  bereits bewusst deaktivierte Zeile (`array_key_exists()`-Prüfung, nicht nur
  "ist das Feld leer").
  Zunächst nur für "Objektnamen", "Automations" und "Begrüßung" umgesetzt -
  siehe Build 136 für die direkt im Anschluss erfolgte Ausweitung auf alle
  übrigen gescannten Zeilen-Tabellen.
  Neuer Regressionstest (eine deaktivierte Zeile löst immer zum Rohtext auf,
  unabhängig von der aktiven Sprache; eine aktive Zeile übersetzt weiterhin
  ganz normal; eine Zeile ohne das Feld gilt als aktiv UND wird korrekt mit
  `true` nachgetragen; eine bewusst deaktivierte Zeile wird vom Backfill nie
  zurückgesetzt; Symmetrie-Check, dass die Checkbox exakt in den drei
  angefragten Listen verdrahtet ist, in keiner weiteren), volle Suite grün.

* **Build 136 (direkter Nachbericht zu Build 135, Nutzer-Korrektur): die
  "Übersetzung aktiv"-Checkbox war als "jede einzelne Zeile in DEN
  Übersetzungstabellen" gemeint, nicht nur für Objektnamen/Automations/
  Begrüßung.** Konkret gewünscht: bei "Eigene Texte" soll sich damit z. B.
  gezielt eine einzelne Stringvariable von der Übersetzung ausschließen
  lassen, deren Inhalt in Wahrheit JSON-Steuerdaten für ein anderes Modul ist
  - "implizit, als ob die Übersetzungszellen gelöscht wurden, nur mit einer
  Checkbox". Die Checkbox ist jetzt zusätzlich in "Eigene Texte",
  "Aufzählungen" und "Charts" verfügbar - zusammen mit den bereits
  vorhandenen drei Tabellen aus Build 135 damit in allen sechs gescannten
  Zeilen-Tabellen. Bewusst weiterhin NICHT in der "Eigenen
  Übersetzungstabelle" (ManualTranslations) - dort wird strukturell nie
  automatisch übersetzt, ein "nie übersetzen"-Schalter wäre dort wirkungslos.
  Wichtig zur Abgrenzung (vom Nutzer selbst angesprochen): die bereits
  bestehende automatische JSON-Erkennung (`LooksLikeJson`, Build 84) bleibt
  von dieser Checkbox unberührt - sie ist ein zusätzlicher, admin-
  gesteuerter Mechanismus, kein Ersatz dafür. Bei echtem JSON-Inhalt bleibt
  die Checkbox typischerweise angehakt/aktiv, die automatische Erkennung
  verhindert eine Übersetzung unabhängig davon bereits von selbst.
  Technisch etwas anspruchsvoller als die ursprünglichen drei Tabellen, da
  zwei Fälle eine echte Zeilen-für-Zeilen-Granularität statt einer einzigen
  Sprachweiche pro Aufruf brauchen: `ApplyEnumerationOptionsToVariable()`
  baut eine EINZIGE Variablen-Präsentation aus MEHREREN Zeilen zusammen (ein
  Feld je Caption/Prefix/Suffix) - hier wird die Weiche pro Feld einzeln
  angewendet, sodass ein deaktiviertes Feld seinen Rohtext zeigt, während ein
  anderes, weiterhin aktives Feld DERSELBEN Variable normal übersetzt wird
  (kein Alles-oder-nichts-Schalter für die ganze Variable). `ApplyChartsLanguage()`
  bekam dieselbe Behandlung pro Datenreihen-Titel. Zusätzlich musste die
  LIVE-Nachübersetzung bei externen Schreibvorgängen
  (`ApplyTrackedVariableUpdate`, gemeinsam genutzt von "Eigene Texte" und
  "Begrüßung" im Modus "Variable") um dieselbe Prüfung ergänzt werden - sonst
  hätte ein deaktiviertes "Eigene Texte"-Feld bei jedem externen
  Schreibvorgang trotzdem unnötig API-Kontingent verbraucht, auch wenn das
  Ergebnis nie geschrieben worden wäre.
  Neuer Regressionstest (eine deaktivierte "Eigene Texte"-Zeile löst exakt
  wie vom Nutzer gefordert zum Rohtext auf; innerhalb einer geteilten
  Variablen-Präsentation bleibt ein deaktiviertes Feld roh, während ein
  aktives Geschwisterfeld weiterhin übersetzt; eine deaktivierte Zeile wird
  bei einem live ankommenden externen Wert nicht mehr an die API geschickt;
  Symmetrie-Check, dass Checkbox und Auflösungslogik jetzt tatsächlich in
  allen sechs Tabellen verdrahtet sind, weiterhin NICHT in der Eigenen
  Übersetzungstabelle), volle Suite grün.

* **Build 137 (direkter Nachbericht zu Build 135/136, Nutzer-Wunsch): die
  "Übersetzung aktiv"-Checkbox wird jetzt automatisch auf "inaktiv" gesetzt,
  sobald der Rohtext einer Zeile gültiges JSON ist.** Hintergrund: JSON-
  Rohtext (siehe `LooksLikeJson()`, Build 84 - erkennt Konfigurations-/
  Steuerdaten für ein anderes Modul statt echten Anzeigetext) wurde in
  `FillLanguageColumn()` schon immer UNBEDINGT von jeder Übersetzung
  ausgenommen, komplett unabhängig vom Stand der Checkbox - für so eine Zeile
  hatte die Checkbox also faktisch nie eine Wirkung, konnte der Konsole aber
  trotzdem fälschlich "wird übersetzt" (angehakt) anzeigen. Ein Rescan setzt
  die Checkbox jetzt aktiv auf "inaktiv", sobald das zutrifft - neue Funktion
  `AutoDeactivateTranslationForJsonContent()`, läuft direkt nach
  `BackfillTranslationActiveFlag()` an denselben sechs Stellen in
  `ScanRootTree()`, mit dem jeweils passenden Rohtext-Feld je Zeilenform
  (`ORIGINAL_IMPORT_Text` bei "Eigene Texte", sonst überall `ORIGINAL_IMPORT`).
  Bewusst nur EINSEITIG wirksam (niemals automatisch wieder auf "aktiv"
  zurückgesetzt): hört ein Rohtext auf, JSON zu sein, bleibt eine
  zwischenzeitlich vom Admin aus einem völlig anderen Grund manuell
  deaktivierte Zeile (z. B. ein Eigenname) unangetastet deaktiviert, statt
  stillschweigend reaktiviert zu werden.
  Neuer Regressionstest (eine Zeile mit JSON-Rohtext und noch aktivem
  Standardwert wird beim nächsten Rescan automatisch deaktiviert; eine Zeile
  mit normalem Text bleibt von der Automatik komplett unberührt; eine bereits
  aus anderem Grund deaktivierte, nicht-JSON-Zeile wird NIE automatisch
  wieder aktiviert; Symmetrie-Check, dass die neue Funktion tatsächlich mit
  dem jeweils korrekten Rohtext-Feld je Tabelle in alle sechs Stellen
  verdrahtet ist), volle Suite grün.

* **Build 138 (zwei Nutzer-Wünsche): die vier Info-Buttons unten im
  Konfigformular sehen jetzt aus wie das Info-Symbol der Kachel, UND die
  "Übersetzung aktiv"-Checkbox aus Build 135-137 ist ab sofort ein
  Pro-Feature.** Erster Punkt, direkter Nachbericht zu Build 130/131: die vier
  Info-`PopupButton`s trugen bisher WIDERSPRÜCHLICH gleichzeitig ein
  Symcon-eigenes Icon (`"icon": "information"`) UND das Emoji "ℹ️" als
  Caption - vermutlich der eigentliche Grund für den schon damals gemeldeten
  "sieht komisch aus"-Eindruck. Die Kachel selbst verwendet für ihr eigenes
  Info-Symbol gar kein Symcon-Icon, sondern schlicht das Zeichen "ⓘ" als
  reinen Text-Span (siehe `$infoIconHtml` in `GetVisualizationTile()`) - die
  vier Buttons wurden exakt darauf angeglichen (Caption jetzt "ⓘ", das
  zusätzliche Symcon-Icon entfernt). Nicht änderbar bleibt dagegen das
  "Zahnradsymbol" im Kopf des sich öffnenden Popup-Dialogs selbst - das ist
  Teil von Symcons eigener Konsolen-Darstellung für `PopupButton`-Popups und
  wird nicht über form.json konfiguriert.
  Zweiter Punkt: die "Übersetzung aktiv"-Checkbox ist jetzt ein Pro-Feature
  (`edit_translations`, dasselbe bereits bestehende Feature, das auch die
  manuelle Korrektur einzelner Übersetzungszellen freischaltet) - ohne Pro
  wird die Spalte komplett WEGGELASSEN, nicht nur schreibgeschützt (anders als
  z. B. bei der "Quellsprache"-Spalte). Bewusst NUR die Formular-Spalte
  selbst ist lizenzabhängig - `GetEffectiveSelectedLanguage()`,
  `BackfillTranslationActiveFlag()` und die neue automatische
  JSON-Erkennung aus Build 137 laufen unverändert für JEDE Lizenz: eine
  bereits gespeicherte Deaktivierung bleibt so auch nach einem Downgrade von
  Pro konsistent wirksam, und die schon lange vor dieser Checkbox bestehende
  automatische JSON-Ausnahme (Build 84) funktioniert unabhängig von der
  Lizenz weiterhin in jeder Edition. Wie vom Nutzer selbst angemerkt: dieselbe
  Wirkung ließe sich in jeder Edition ohnehin schon manuell nachbilden (die
  jeweilige Zielsprachen-Zelle einzeln leeren) - Pro schaltet also nur den
  Komfort ("einmal ankreuzen, gilt für alle Sprachen gleichzeitig") frei,
  keine grundsätzlich neue technische Möglichkeit.
  Neuer Regressionstest (die Info-Buttons verwenden jetzt exakt dasselbe
  "ⓘ"-Zeichen wie die Kachel, ohne redundantes Symcon-Icon; die Checkbox-
  Spalte fehlt komplett ohne die Pro-Lizenz statt nur schreibgeschützt zu
  sein; die Auflösungslogik bleibt nachweislich frei von jedem eigenen
  Lizenz-Check), volle Suite grün.

* **Build 139 (direkter Nachbericht zu Build 138, Nutzer-Feedback per
  Screenshot): das gerade erst auf "ⓘ" umgestellte Info-Zeichen sah in der
  echten Konsole aus wie ein "Aus"-/Standby-Symbol, nicht wie ein
  Info-Zeichen.** Ursache: die Kachel selbst rendert "ⓘ" über ihr eigenes,
  freies HTML/CSS (siehe `module.html`), während die vier Info-`PopupButton`s
  im Konfigformular von der nativen Symcon-Konsole mit deren eigener
  Systemschrift dargestellt werden - dort erschien exakt dasselbe
  Unicode-Zeichen missverständlich. Auf Vorschlag des Nutzers ("das ist am
  sichersten") auf reinen, eindeutigen Klartext "Information" umgestellt
  statt eines erneuten Icon-Versuchs - Breite von 40px (Icon-Größe) auf 110px
  (Textbreite) angepasst, neue Übersetzung in allen vier Sprachen ergänzt.
  Der vom Nutzer eigentlich bevorzugte Ansatz - ein rahmenloses Icon wie in
  der Kachel, das dennoch beim Klick das Popup öffnet - ist über form.json
  technisch nicht erreichbar: jeder Button-/PopupButton-Typ der Symcon-
  Konsole rendert zwingend mit eigenem Rahmen/Chrome; nur die Kachel selbst
  kann als freies HTML/CSS komplett ohne Button-Optik auskommen. Klartext
  bleibt damit die zuverlässigste Lösung innerhalb der Konsole.
  Bestehender Regressionstest angepasst (Buttons zeigen jetzt "Information"
  statt eines Icon-Zeichens, entsprechend breiter statt Icon-schmal), volle
  Suite grün.

* **Build 140 (Nutzer-Wunsch, im Vorfeld der IPS-Store-Einreichung): alle
  während der Live-Fehlersuche dieser Session eingebauten, als "temporaer"
  markierten Diagnose-Ausgaben sowie weitere inzwischen überflüssige
  Debug-Zeilen entfernt.** Betroffen: der einmalige Marker in `AutoRescan()`
  (Build 122, unterschied den Auto-Timer vom manuellen Rescan-Klick), der
  komplette Vorher-Zustand-Mitschnitt in `ReconcileRowSourceLanguageChanges()`
  (Build 121, ursprünglich für den Automations/ObjectNames/Begrüßung-
  Korruptionsverdacht gedacht), die beiden "Cache-Miss"-Zeilen in
  `GetCachedTranslation()` sowie die Cache-Größen-Mitschnitte in
  `StoreCachedTranslation()`/`StoreCachedTranslationsBatch()` (Build 126-128,
  Cache-Selbstverdrängungs-Untersuchung). Der dadurch nutzlos gewordene
  `$DebugContext`-Parameter dieser drei Cache-Funktionen wurde ebenfalls
  entfernt (inkl. aller Aufrufstellen) statt als toter Parameter stehen zu
  bleiben.
  Zusätzlich (Nutzer: "ggf. auch nun überflüssige Debugs") eigenständig
  identifiziert und entfernt: die vier Zeilen-für-Zeilen-Mitschnitte der
  kompletten Begrüßungs-Zeile bei jedem einzelnen Rescan-Durchlauf in
  `ScanRootTree()` (existingGreeting/scannedGreeting, mergedGreeting,
  filledGreeting, persisted - allesamt Teil derselben, inzwischen
  abgeschlossenen Untersuchung, aber nie explizit als "temporaer" markiert),
  die beiden reinen Ablauf-Meldungen zu unbenannten Objekten (der eigentliche
  Zustand steht ohnehin bereits in `attributeUnnamedObjects`/`SetStatus`),
  sowie alle vier Zeilen in `EnsureSourceLanguageIsTarget()` (Build 79,
  reine Ablaufverfolgung einer inzwischen längst stabilen, einfachen
  Funktion, keine erkennbare weitere Diagnose nötig).
  Bewusst NICHT angetastet: die permanenten Diagnose-Kategorien
  `GoogleTranslate_Request`/`_Response`, `DeepLTranslate_Request`/`_Response`,
  `FreeTranslate_Request`/`_Response`, `GoogleTranslate_Mapping` sowie deren
  Fehler-Gegenstücke - das sind die eigentlichen, dauerhaft nützlichen
  Diagnose-Werkzeuge dieses Moduls (in dieser Session wiederholt zur echten
  Fehlersuche anhand von Nutzer-Dumps verwendet), ebenso `ClearTranslationCache`
  (seltene, admin-ausgelöste Aktion) und die Meldung in
  `DeduplicateTextRowsByValueObjectID()` (seltenes, potenziell
  datenverlust-relevantes Ereignis).
  `php -l` sauber, volle Test-Suite grün, keine funktionale Änderung -
  reines Aufräumen vor der Einreichung im IP-Symcon Module Store.

* **Build 141 (zwei live gemeldete Bugs auf einer frischen Instanz, beide rund
  um "unbenannte Objekte"): die Liste der unbenannten Objekte blieb nach einem
  Rescan-Abbruch unsichtbar, und ein späteres "Übernehmen" setzte den Status
  fälschlich zurück auf "Aktiv".**
  Bug 1: Der erste Klick auf "Rescan" meldete korrekt "Unnamed objects found -
  see list in form." - die Liste war im Formular aber nirgends zu sehen. Sie
  tauchte erst später auf, als der Nutzer aus einem völlig anderen Grund (eine
  Zielsprache hinzugefügt) auf "Übernehmen" klickte. Ursache: der
  Abbruch-Zweig in `ScanRootTree()` kehrt VOR dem abschließenden
  `IPS_ApplyChanges()` zurück. Die Annahme aus Build 116 ("die Konsole lädt das
  Formular nach jedem RequestAction ohnehin selbst neu") stimmt aber nur, WEIL
  der normale Durchlauf dieses `IPS_ApplyChanges()` erreicht - DAS löst den
  Reload aus, nicht die RequestAction an sich. Der Abbruch-Zweig bekam dadurch
  nie einen Reload, und das gerade frisch geschriebene Attribut wurde nie
  gerendert. `ScanRootTree()` bekommt dafür einen neuen Parameter
  `$IsInteractive`, den ausschließlich der manuelle Weg (`Rescan()`/
  `IPSSL_Rescan()`) setzt - der Auto-Rescan-Timer läuft bewusst weiterhin ohne,
  damit der bereits in Build 60 behobene Bug (ein Hintergrund-Rescan reißt dem
  Admin das offene Formular mitten in der Bearbeitung weg und verwirft unsavte
  Änderungen) nicht wieder eingeschleppt wird.
  Bug 2: Nach jenem "Übernehmen" zeigte die Statuszeile "Aktiv" (102), obwohl
  die Liste der unbenannten Objekte im selben Formular unverändert sichtbar
  darunter stand und weiterhin jeden Rescan blockiert - Formular und
  Statuszeile widersprachen sich offen. Ursache: `ApplyChanges()` hat den von
  `ScanRootTree()` gesetzten `STATUS_UNNAMED_OBJECTS` kommentarlos
  überschrieben, da es die anstehenden unbenannten Objekte gar nicht kannte.
  Die Status-Kaskade berücksichtigt sie jetzt - eingeordnet nach den
  fundamentaleren Blockern (fehlender Visualisierungs-Root, abgelaufene
  Testphase), aber vor der sich selbst auflösenden Anbieter-Pause.
  Strukturelle Absicherung gegen ein erneutes Auseinanderlaufen: das Attribut
  `attributeUnnamedObjects` wird jetzt nur noch an EINER einzigen Stelle
  gelesen (neuer gemeinsamer Helfer `GetPendingUnnamedObjects()`/
  `HasPendingUnnamedObjects()`), aus der sich sowohl die Statuszeile als auch
  die Sichtbarkeit der Liste im Formular speisen.
  Neuer Regressionstest (manueller Rescan-Abbruch lädt das Formular selbst
  neu; der Hintergrund-Rescan tut das weiterhin NIE; der normale Durchlauf
  bleibt unberührt; ein späteres "Übernehmen" meldet weiterhin
  STATUS_UNNAMED_OBJECTS statt "Aktiv"; nach behobenen Benennungen meldet der
  Status wieder normal "Aktiv"; die Status-Rangfolge stimmt; Symmetrie-Check
  inkl. der einzigen verbliebenen Lesestelle des Attributs).
  Enthält außerdem eine bereits zuvor vorbereitete, noch nicht eingecheckte
  Textkorrektur: die beiden Begrüßungs-Modushinweise sagten "wird unten
  übersetzt", obwohl die Begrüßungstabelle im umgebauten Formular (siehe Build
  130) nicht mehr zwingend direkt darunter steht - das "unten" ist jetzt in
  allen vier Sprachen entfernt.

* **Build 142 (live gemeldeter Bug): ein Klick in der eigenen
  Sprachauswahl-Kachel konnte die Instanz dauerhaft unspeicherbar machen.**
  Szenario: frische Instanz, Testversion, eigene Sprachauswahl-Kachel
  (Pro-Feature `custom_tile`) aktiviert. Deren mitgeliefertes **Beispiel** zeigt
  zwei feste Flaggen mit fest eingetragenen Sprachcodes - ein Klick auf die
  englische Flagge schickte `en`, obwohl `en` auf dieser Instanz gar keine
  konfigurierte Zielsprache war. Der Code landete ungeprüft in
  `propertyCurrentLanguage`; Symcons Konfigurationsformular baut daraus ein
  Select, das nur die tatsächlich konfigurierten Sprachen kennt, und verweigerte
  daraufhin **jedes weitere Speichern der Instanz** ("Invalid configuration /
  Current value "en" is not available"). Da praktisch jeder Nutzer das
  mitgelieferte Beispiel einmal ausprobiert - das ist ja sein Zweck -, hätte das
  potenziell sehr viele Neuinstallationen getroffen, jeweils mit einem Modul,
  das sich plötzlich "nicht mehr bedienen lässt". Zusätzlich kann ein Nutzer in
  seiner eigenen Kachel jederzeit beliebige weitere ungültige Codes eintragen.
  Zwei Ebenen dagegen:
  1. **Vorbeugend:** `RequestAction()` prüft den gewünschten Sprachcode jetzt
     zuerst gegen die tatsächlich wählbaren Sprachen (neuer Helfer
     `IsSelectableGuestLanguage()` - dieselben Codes, die auch das Formular-Select
     anbietet, zusätzlich die Quellsprache und der interne Rückfall
     `ORIGINAL_IMPORT`). Ein unbekannter Code wird abgelehnt, die aktive Sprache
     bleibt unverändert stehen, und die Ablehnung landet mit Angabe der
     konfigurierten Sprachen in der neuen Debug-Kategorie `IPSSL_Language`. Die
     Prüfung läuft bewusst **vor** der Testphasen- und Rate-Limit-Behandlung,
     sonst könnte ein ungültiger Code an ihr vorbei in die Property gelangen.
  2. **Heilend:** eine Instanz, die bereits in diesem Zustand feststeckt, wäre
     davon allein nicht gerettet - ihr Formular lässt sich ja gerade nicht mehr
     übernehmen, um den Wert von Hand zu korrigieren. `ApplyChanges()` prüft die
     gespeicherte aktive Sprache daher ebenfalls und setzt sie bei Bedarf auf die
     Quellsprache zurück. Das greift auch in einem Fall ganz ohne eigene Kachel:
     wenn der Admin die gerade aktive Zielsprache aus der Liste entfernt.
  Das mitgelieferte Kachel-Beispiel trägt jetzt außerdem die Überschrift
  "Custom tile example:" sowie einen deutlichen Kommentar, dass die Sprachcodes
  darin fest eingetragen sind und zu den eigenen Zielsprachen passen müssen.
  Neuer Regressionstest (9 Prüfungen: nicht konfigurierter Code wird abgelehnt
  ohne die aktive Sprache anzutasten; konfigurierte Codes funktionieren normal
  weiter; Quellsprache und `ORIGINAL_IMPORT` bleiben immer zulässig; leerer Code
  wird abgelehnt; eine bereits blockierte Instanz heilt sich selbst; gültige
  Sprachen werden von der Heilung nie angefasst; das Entfernen der aktiven
  Zielsprache führt sauber zurück; Symmetrie-Check inkl. der Reihenfolge der
  Prüfung).

* **Build 143 (Nutzer-Wunsch mit Screenshot): die eingebaute Kachel zeigte bei
  Visualisierungs-Höhe "1" einen Scrollbalken, weil sie nur wenige Pixel zu hoch
  war.** Höhe "1" ist für eine reine Sprachauswahl die naheliegende Einstellung,
  entsprechend viele Nutzer werden sie wählen - ein Scrollbalken für ein paar
  ungenutzte Pixel sieht dort schlicht unfertig aus.
  Der Platz unter dem Dropdown ist genau dann ungenutzt, wenn keine der drei
  optionalen Hinweiszeilen (Testphase / Anbieter-Pause / Statistik) angezeigt
  wird. Nur in diesem Fall bekommt die Zeile jetzt die zusätzliche CSS-Klasse
  `ipssl-compact` und holt sich den Platz per negativem unteren Rand zurück.
  Sind Hinweise sichtbar, braucht die Kachel die Höhe ohnehin - dann bleibt
  alles unverändert (genau der vom Nutzer benannte Kompromiss: "wenn der User
  die Statistiken sehen will, lässt sich das nicht ändern").
  Bewusst **nur nach unten**: oben reserviert Symcon den Platz für Titel und
  Vergrößern-Symbol der Kachel (siehe den langjährigen Kommentar am Anfang von
  `module.html`) - ein negativer Rand dort würde das Dropdown unter die
  Titelzeile schieben. Die Höhe der Bedienelemente selbst (`--ipssl-control-height`,
  38px) bleibt ebenfalls unangetastet; verkleinert wird ausschließlich
  ungenutzter Leerraum. Reicht der zurückgewonnene Platz auf einer bestimmten
  Installation nicht ganz, ist der `margin-bottom`-Wert in dieser einen
  CSS-Regel der vorgesehene Stellwert.
  Hinweis für bestehende Instanzen mit **eigener** Kachel (Pro-Feature
  `custom_tile`): deren HTML ist eine zum Anlegezeitpunkt gezogene Kopie von
  `module.html` und enthält die neue Regel daher nicht. Die Klasse wird dort
  gesetzt, bleibt aber wirkungslos - kein Fehler, und die Regel lässt sich bei
  Bedarf von Hand nachtragen.
  Neuer Regressionstest (kompakt genau dann, wenn keine Hinweiszeile vorliegt;
  jede der drei Hinweisarten verhindert den Kompaktmodus einzeln; die
  Basisklasse bleibt immer erhalten, da Layout und ggf. eigenes Nutzer-CSS daran
  hängen; die Hinweise werden nur noch an einer Stelle gebaut, damit sie nicht
  doppelt gerendert werden; die CSS-Regel wirkt nachweislich ausschließlich nach
  unten, nie in den Titelbereich).

* **Build 144 (Nutzer-Wunsch: auch die alte WebFront-Visualisierung
  unterstützen): die Startkategorie wird jetzt über mehrere bekannte
  Property-Namen erkannt - und, deutlich wichtiger, der Zugriff auf die fremde
  Visualisierungs-Instanz stürzt nicht mehr ab.**
  Gemeldet war zunächst nur, dass die Root-Kategorie einer WebFront-Instanz
  nicht erkannt wird ("vermutlich nutzt Symcon einen anderen Namen"). Die
  Ursachensuche förderte aber ein zweites, ernsteres Problem zutage:
  `IPS_GetProperty()` wirft bei einem **unbekannten** Property-Namen eine
  Exception - und `@` unterdrückt in PHP nur Warnungen, **niemals** Exceptions.
  Der Code las die fremde Instanz an zehn Stellen direkt per
  `@IPS_GetProperty($visu, 'Automations'/'GreetingName'/'ShowGreeting'/…)` aus.
  Bei einer Instanz, die diese Properties nicht kennt - genau der Fall bei der
  alten WebFront-Visualisierung - riss damit nicht nur die Root-Erkennung ab,
  sondern der komplette Rescan mit einer unbehandelten Exception.
  Alle zehn Lesezugriffe laufen jetzt über `IPS_GetConfiguration()` (liefert das
  JSON **aller** vorhandenen Properties; ein fehlender Schlüssel ist damit ein
  ganz normaler Array-Miss statt eines Abbruchs), gekapselt in
  `GetVisuInstanceProperties()`/`GetVisuInstanceProperty()`. Die beiden
  **schreibenden** Zugriffe (`GreetingName`, `Automations`) prüfen über
  `VisuInstanceHasProperty()` vorher, ob es die Property dort überhaupt gibt -
  `IPS_SetProperty()` wirft bei unbekanntem Namen genauso. Das ist nicht
  theoretisch: wer seine Instanz zuerst mit der Kachel-Visualisierung betreibt
  (dabei entstehen Begrüßungs-/Automations-Zeilen) und sie danach auf die alte
  WebFront-Visualisierung umstellt, behält diese Zeilen.
  Die Startkategorie löst `ResolveVisuRootCategoryID()` über eine feste
  Kandidatenliste auf (`BaseID` zuerst, damit die Kachel-Visualisierung immer
  Vorrang behält). Bewusst **keine** blinde Suche nach "irgendeiner
  ID-Property": die könnte eine Verweis-Property auf eine ganz andere Kategorie
  erwischen und stillschweigend den falschen Baum übersetzen - deutlich
  schlimmer als ein sauberes `STATUS_ROOT_CATEGORY_MISSING`. Passt kein
  Kandidat, werden die tatsächlich vorhandenen Property-Namen einmal in die neue
  Debug-Kategorie `IPSSL_Visu` geschrieben, damit sich ein bislang unbekanntes
  Visualisierungs-Modul ohne Raterei nachtragen lässt.
  Funktionsumfang bei der alten WebFront-Visualisierung: Objektnamen, Eigene
  Texte, Aufzählungen und Charts werden normal übersetzt. Automations,
  Begrüßung und Favoriten sind Eigenschaften der Kachel-Visualisierung und
  bleiben dort naturgemäß leer - jetzt aber sauber leer statt mit einem
  Abbruch.
  Neuer Regressionstest (9 Prüfungen: `BaseID` funktioniert unverändert;
  abweichende Namen werden erkannt; die Reihenfolge entscheidet; ein Kandidat
  mit gelöschtem Objekt wird übersprungen; eine unbekannte Visualisierung
  liefert sauber 0 statt blind irgendeiner ID; fehlende Properties ergeben leere
  Werte statt einer Exception; kaputtes JSON/unerreichbare Instanz ebenso;
  Symmetrie-Check, dass kein ungeschützter Zugriff auf die fremde Instanz mehr
  existiert).

* **Build 145 (Nachtrag zu Build 144): Unterstützung der alten
  WebFront-Visualisierung nach Prüfung einer echten Instanz bewusst verworfen -
  die dabei geratenen Property-Namen wieder entfernt.**
  Build 144 hatte die Startkategorie über eine Liste möglicher Property-Namen
  gesucht, weil unklar war, wie die alte WebFront ihre Wurzel benennt. Die
  Konfiguration einer laufenden WebFront-Instanz zeigte dann: sie hat
  **überhaupt keine** Startkategorie-Property auf oberster Ebene. Ihr Aufbau
  steckt in `Items` - einem JSON-String mit Widgets, deren Kategorie-Verweise
  erst in einem **zweiten**, darin verschachtelten JSON liegen
  (`ClassName: "Category"` → `Configuration` → `baseID`). Zusätzlich kann eine
  WebFront **mehrere gleichrangige Wurzeln** haben (mehrere Category-Widgets),
  während das Modul strukturell von genau einer ausgeht.
  Nach Abwägung des Aufwands gegen den erwarteten Nutzen wurde die
  Unterstützung verworfen. Damit sind die in Build 144 aufgenommenen, nur
  geratenen Namen (`RootID`, `BaseCategory`, …) nicht bloß nutzlos, sondern ein
  Risiko: träfe so ein Name zufällig eine gleichnamige Property eines fremden
  Moduls, würde stillschweigend der **falsche** Baum übersetzt - deutlich
  schlimmer als ein sauberes `STATUS_ROOT_CATEGORY_MISSING`. Die Liste ist
  daher wieder auf den einzigen verifizierten Namen (`BaseID`,
  Kachel-Visualisierung) zurückgestutzt; ein Regressionstest hält sie dort.
  **Ausdrücklich erhalten bleibt der eigentliche Fund aus Build 144**: der
  Absturzschutz beim Zugriff auf die fremde Visualisierungs-Instanz. Der ist
  vom WebFront-Thema unabhängig und greift für **jede** dort ausgewählte
  Instanz, die eine erwartete Property nicht kennt - statt eines abgebrochenen
  Rescans gibt es jetzt eine saubere Statusmeldung.
  Bekannte Grenze, damit sie dokumentiert ist: die alte
  WebFront-Visualisierung wird nicht unterstützt. Sie lässt sich zwar auswählen,
  liefert dann aber `STATUS_ROOT_CATEGORY_MISSING` - bewusst, statt zu raten.

* **Build 146 (Nutzer-Wunsch): auswählbares Kachel-Symbol und auswählbare
  Kachel-Vorlage, als Wiedererkennungswert für Sonder-Editionen.** Ein
  Xmas-Special kann damit eine eigene Optik mitbringen, ohne dass jemand HTML
  anfassen muss.
  Zwei Kataloge im Code (`TILE_ICON_CATALOG`, `TILE_TEMPLATE_CATALOG`) führen die
  auslieferbaren Symbole bzw. Vorlagen. Jeder Eintrag trägt einen Anzeigenamen
  und optional ein benötigtes Lizenz-Feature; die Auswahlfelder im Formular
  werden daraus dynamisch gefüllt und dabei nach Berechtigung gefiltert - ein
  Saison-Design taucht also nur bei den Editionen überhaupt auf, die es erworben
  haben (Xmas 2026 sieht kein Nikolaus-Design und umgekehrt).
  **Die Berechtigung hängt an `features[]` im Lizenzschlüssel** - derselben
  Spalte, die es in `slips_orders` und `slips_promo_licenses` bereits gibt. Ein
  neues Saison-Design braucht damit keinerlei Schema-Änderung, sondern nur einen
  weiteren Eintrag in der Feature-Liste des Produkts (z. B. `theme_xmas2026`).
  Bewusst **nicht** am `edition`-Feld: das ist laut Schema ausdrücklich ein
  Werbename mit Fallback, ein Marketing-Umbenennen würde sonst still eine
  Berechtigung entziehen.
  **Zwei Entwurfsentscheidungen, die den Anforderungen "zurücksetzbar" und
  "geht bei Updates nicht verloren" zugrunde liegen:**
  1. Gespeichert wird **nur die ID**, nie der Inhalt. Der Inhalt kommt bei jedem
     Rendern frisch aus dem Code bzw. der Vorlagendatei. `propertyCustomTileHtml`
     zeigt das Gegenbeispiel: dort steht der Inhalt in der Instanz, und weil
     Properties bei einem Update zu Recht nicht überschrieben werden, erreichen
     spätere Korrekturen sie nie - real passiert, der Scrollbalken-Fix aus Build
     143 kam bei keiner Instanz an, die die eigene Kachel bereits aktiviert
     hatte. Mit einer ID bleibt die Auswahl stabil **und** die Vorlage
     wartbar. Zurücksetzen heißt schlicht: ID wieder auf `default`.
  2. Die Berechtigung läuft **nicht** über `HasLicenseFeature()`. Das gibt
     während der Testphase absichtlich alles frei, damit sich der Mechanismus
     vor dem Kauf ausprobieren lässt - für Saison-Designs würde genau das den
     Wiedererkennungswert aushebeln. Eigene Prüfung `HasThemeEntitlement()`:
     Einträge ohne Feature-Anforderung sind immer wählbar (die reinen
     Auslieferungszustände), alles Weitere ausschließlich mit einer **gültigen**
     Lizenz, die das Feature auch führt.
  Bei Downgrade/Ablauf greift die Auswahl nicht mehr, der gespeicherte Wert
  bleibt aber erhalten und lebt nach erneuter Lizenzierung sofort wieder auf -
  dasselbe Muster wie `custom_tile`/`auto_rescan`, kein Datenverlust.
  Ausgeliefert werden zunächst die beiden Auslieferungszustände: das
  Simple-Locale-Symbol (Standard) und die Weltkugel, die damit von einem reinen
  Notbehelf zu einer echten Auswahl wird. Der Katalog unterstützt sowohl
  Bilddateien als auch reine Zeichen - ein Saison-Symbol lässt sich dadurch auch
  ganz ohne neue Grafik ausliefern (z. B. 🎄), was den Modul-Download nicht
  vergrößert. Wer echte Grafik einsetzt, legt bewusst nur eine 48px-Variante
  dazu, nicht die 1024px-Datei (`module_icon.png` ist allein 1,1 MB).
  Der Haken heißt jetzt neutral "Symbol in der Kachel anzeigen" statt fest
  "Simple-Locale-Symbol …" - der Property-Name (`ShowGlobeIcon`) bleibt
  unverändert, damit bestehendes, an die CSS-Klasse gebundenes Kachel-HTML nicht
  ohne Not bricht.
  Neuer Regressionstest (jede Sonder-Edition sieht ausschließlich ihr eigenes
  Design; Auslieferungszustände sind überall wählbar; die Testphase bekommt
  trotz "alle Features frei" kein Saison-Design; Zurücksetzen und unbekannte IDs
  fallen sauber auf den Standard; Downgrade deaktiviert ohne zu verwerfen;
  Symmetrie-Checks, dass die Berechtigung `HasLicenseFeature()` umgeht und die
  Vorlage beim Rendern aus der Datei statt aus der Property kommt).

* **Build 147 (Nutzer-Vorgabe zur offenen Frage aus Build 146): eine
  Sonder-Edition mit eigenem Design zeigt dieses jetzt von sich aus, statt es
  erst auswählen zu lassen.** Genau darum geht es beim Wiedererkennungswert -
  der Käufer soll sein Design nicht suchen müssen.
  Beide Auswahlfelder stehen im Auslieferungszustand auf **"Automatisch"** (ein
  reservierter Wert, bewusst keine Katalog-ID, damit er nie mit einem echten
  Design kollidiert). Automatisch bedeutet: das neueste Saison-Design, für das
  eine Berechtigung vorliegt - sonst der neutrale Auslieferungszustand. Die
  Auswahlliste zeigt dabei an, was das gerade konkret heißt
  ("Automatisch (Weihnachten 2026)"), damit die Einstellung nicht undurchsichtig
  wirkt.
  Ein statischer Property-Default hätte das **nicht** leisten können: die Lizenz
  wird typischerweise erst nach dem Anlegen der Instanz eingetragen, der Default
  steht zu diesem Zeitpunkt längst fest. Die Auswertung passiert deshalb bei
  jedem Auflösen, nicht einmalig beim Registrieren.
  Drei Feinheiten, die den Unterschied zwischen "funktioniert" und "nervt"
  ausmachen:
  1. Eine **ausdrückliche** Wahl schlägt die Automatik und bleibt bestehen. Wer
     trotz Saison-Lizenz bewusst das neutrale Symbol will, behält es - sonst
     wäre die Auswahl wertlos. Nur der Wert "Automatisch" wird neu bewertet.
  2. Besitzt jemand **mehrere** Sonder-Editionen, gewinnt deterministisch die
     zuletzt erschienene (der letzte passende Katalogeintrag; neue Designs
     werden angehängt).
  3. Verliert eine ausdrückliche Wahl ihre Berechtigung, fällt sie auf den
     **neutralen** Standard zurück - nicht auf ein anderes Saison-Design, das
     zufällig ebenfalls vorliegt. Sonst spränge die Optik beim Ablaufen einer
     Lizenz überraschend auf etwas völlig anderes.
  Bewusst **keine** Datumslogik ("nur im Dezember"): die Berechtigung selbst ist
  der Punkt, und ein erworbenes Design soll nicht ungefragt wieder verschwinden.
  Wer den neutralen Zustand will, wählt ihn ausdrücklich.
  Regressionstest um fünf Prüfungen erweitert (Sonder-Edition zeigt ihr Design
  ohne Zutun; ausdrückliche Wahl schlägt die Automatik; bei mehreren
  Berechtigungen gewinnt deterministisch die neueste; eine ungültig gewordene
  Wahl fällt neutral zurück statt auf ein fremdes Design; Symmetrie-Check des
  Auslieferungszustands).

* **Build 148 (Nutzer-Vorgaben zum Abo-Modell): App-Seite für Abos
  fertiggestellt - die Abo-Verwaltung ist damit reine Website-Arbeit.**
  Ausgangspunkt war die Frage, welche Informationen die App künftig braucht,
  damit später *nichts* mehr am Modul geändert werden muss. Die Bestandsaufnahme
  ergab, dass der **Verlängerungsmechanismus app-seitig bereits fertig war**:
  die tägliche Statusprüfung übernimmt ein vom Server geliefertes `expiresAt`
  als Override über das im Schlüssel signierte Datum (siehe Build 120). Eine
  Abo-Verlängerung ist damit ein reiner Schreibvorgang auf
  `slips_licenses.expires_at_override` - kein neuer Schlüssel, kein Versand,
  keine App-Änderung. Eine Kündigung ebenso: der Server hört einfach auf zu
  verlängern.
  Ergänzt wurden daher nur die fehlenden Anzeige- und Schutzteile:
  - **`interval` im signierten Schlüssel** (`month`/`year`), angezeigt als neue
    Zeile "Abozeitraum: monatlich/jährlich" im Lizenz-Panel. Gehört bewusst in
    den Schlüssel und nicht in die tägliche Prüfung: der Wert ist statisch und
    lässt sich nicht aus `expiresAt` ableiten (ein Jahresabo kurz vor Ablauf
    sieht aus wie ein Monatsabo). Streng normalisiert - alles Unerwartete wird
    zu `''` und blendet die Zeile aus. Ältere Schlüssel ohne das Feld
    funktionieren unverändert weiter.
  - **Ablaufhinweis in der Kachel**, im selben roten Stil wie der Pause-Hinweis:
    ab 7 Tagen vor Ablauf "Deine Lizenz läuft ab am TT.MM.JJJJ. Verlängern:
    <Link>", danach "Deine Lizenz ist abgelaufen. Verlängern: <Link>". Als
    Gast-Oberflächentexte umgesetzt, erscheinen also in der Gastsprache. Ein
    unbefristeter Einmalkauf (`expiresAt` = 0) bekommt nie einen solchen
    Hinweis.
  - **Zielsprachen gesperrt bei abgelaufener Lizenz.** Eine neue Zielsprache
    würde eine Übersetzung auslösen, die nicht mehr erworben ist. Das übrige
    Formular bleibt ausdrücklich bedienbar - insbesondere das Lizenzfeld selbst,
    denn ein neuer Schlüssel ist der einzige Weg zurück. Ein Regressionstest
    sichert genau das ab (Sackgassen-Schutz).
  Das Zurückfallen der Übersetzungen auf die Quellsprache bei Ablauf war bereits
  vorhanden (`IsTrialLocked()`/`ResetToOriginalLanguageIfNeeded()`) - eine
  abgelaufene Lizenz landet automatisch in derselben Mechanik, ebenso der
  blockierte Rescan.
  **Zwei bewusste Nicht-Entscheidungen:** Kulanzfristen rechnet der **Server**
  in `expiresAt` ein, die App kennt gar keine Grace-Logik - dadurch bleibt die
  Kulanzpolitik jederzeit serverseitig änderbar, ohne je ein Modul-Update. Und
  es gibt **kein** frei belegbares Hinweisfeld vom Server: die beiden festen
  Zustände decken den Bedarf ab, und ein Servertext hätte Sprach- und
  Escaping-Fragen aufgeworfen, die er nicht wert ist.
  Neuer Regressionstest (Vorwarnung exakt ab 7 Tagen und nicht früher;
  Abgelaufen-Zustand; ein unbefristeter Einmalkauf wird nie zur Verlängerung
  aufgefordert; eine serverseitige Verlängerung lässt den Hinweis von selbst
  verschwinden; serverseitige Kulanz wirkt ohne Grace-Logik; `interval` streng
  normalisiert; alte Schlüssel ohne das Feld funktionieren weiter;
  Symmetrie-Checks inkl. Sackgassen-Schutz fürs Lizenzfeld).

* **Build 149 (Nutzer-Wunsch beim Testen): neuer Knopf "Verknüpfungen
  automatisch nach ihrem Ziel benennen" - spart beim Einrichten viel
  Handarbeit.**
  Ein Rescan bricht ab, solange irgendein Objekt im Baum keinen Namen hat (ein
  leerer Name lässt sich nicht übersetzen und würde als leere Beschriftung in
  der Gäste-Visualisierung landen). Beim Einrichten eines größeren Baums sind
  das schnell Dutzende Objekte - und der Großteil davon sind **Verknüpfungen**:
  Symcon zeigt für eine namenlose Verknüpfung automatisch den Namen ihres Ziels
  an. In der Visualisierung sieht also alles richtig aus, während `IPS_GetName()`
  leer bleibt. Der Admin müsste von Hand exakt den Namen abtippen, den Symcon
  ohnehin schon anzeigt.
  Der Knopf erscheint direkt unter der Liste der unbenannten Objekte (und nur
  dann, wenn es welche gibt) und übernimmt für jede betroffene Verknüpfung den
  Namen ihres Ziels. **Optisch ändert sich dadurch nichts** - der vergebene Name
  ist genau der, den Symcon vorher automatisch eingeblendet hat. Deshalb bewusst
  ohne Rückfrage: es gibt nichts zu überschreiben.
  Bewusst **nur** Verknüpfungen: eine unbenannte Kategorie oder Variable hat kein
  Ziel, aus dem sich ein sinnvoller Name ableiten ließe. Solche Objekte bleiben
  stehen und werden in der Rückmeldung getrennt ausgewiesen.
  Drei Fälle, die bewusst abgefangen werden:
  - Zeigt die Verknüpfung auf ein **selbst unbenanntes** Ziel, wäre der
    übernommene Name genauso wertlos - dann bleibt sie stehen, damit der Admin
    die eigentliche Ursache sieht statt einer Platzhalter-Kette.
  - Fehlendes/gelöschtes Ziel oder ein inzwischen entferntes Objekt brechen den
    Durchlauf nicht ab.
  - Ein **gesperrtes** Objekt lehnt das Umbenennen ab. Nach dem Schreiben wird
    der Name deshalb noch einmal gelesen und nur gezählt, was nachweislich
    angekommen ist - sonst würde Erfolg gemeldet und der nächste Rescan meckerte
    trotzdem weiter.
  Die Rückmeldung nennt die Zahl der benannten Verknüpfungen, weist übrige
  Objekte getrennt aus und bittet um einen erneuten Rescan.
  Neuer Regressionstest (Hauptfall; Platzhalter-Name "(ID: n)" wird ersetzt;
  Nicht-Verknüpfungen bleiben unangetastet; selbst unbenanntes Ziel erzeugt keine
  Platzhalter-Kette; fehlendes Ziel/gelöschtes Objekt brechen nicht ab;
  fehlgeschlagene Umbenennung wird ehrlich als übersprungen gezählt; gemischter
  Bestand; Symmetrie-Check der Verdrahtung).

* **Build 150 (live gemeldet, per Debug-Dump nachgewiesen): ein einzelnes
  `&nbsp;` konnte bis zu 127 völlig unbeteiligte Texte unübersetzt lassen.**
  Gemeldetes Symptom: Nach einem Rescan blieben in "Eigene Texte" praktisch alle
  Zielsprachen-Zellen leer - auch triviale wie "Bernd" oder "Wohnbereich".
  Gefüllt waren nur Zahlen/Daten, die der lokale Filter ohne API bedient.
  Gleichzeitig war kein Kontingent erschöpft, keine Pause aktiv, und die
  Objektnamen-Tabelle übersetzte einwandfrei.
  Der Dump zeigte die Ursache: MyMemory antwortet für manche Eingaben mit
  **HTTP 200 und `"translatedText": null`** - live für `&nbsp;` aus einem
  HTML-Widget. Das ist kein Anbieter-Fehler, sondern schlicht "dafür habe ich
  nichts". Der Code machte daraus aber ein `null`, und `TranslateChunkFree()`
  bricht bei einem `null` den **kompletten Chunk** ab. `TranslateChunk()` wertet
  das als Anbieter-Fehlschlag, findet keinen weiteren Anbieter und füllt **alle**
  Texte des Chunks mit Leerstrings. Da ein Chunk bis zu 128 Texte fasst, riss ein
  einziges `&nbsp;` bis zu 127 unbeteiligte Texte mit - deren eigene Anfragen
  waren im Dump nachweislich erfolgreich.
  Exakt dieselbe Fehlerklasse wie beim zu langen Text, die dort bereits behoben
  war (siehe `test_free_provider_oversized_text_no_longer_blocks_batch`) - dieser
  Pfad wurde damals übersehen.
  `TranslateSingleFree()` unterscheidet jetzt sauber: ein echter Fehlschlag
  (Transport, Kontingent) liefert weiterhin `null`, damit die Anbieter-Kette auf
  den nächsten Anbieter ausweichen kann; eine gültige Antwort ohne Übersetzung
  liefert den **Originaltext** zurück. Bewusst nicht den Leerstring: bei
  HTML-Knoten würde der den Knoten beim Zusammensetzen löschen (aus `&nbsp;`
  würde nichts) und damit das Dokument beschädigen.
  **Zwei Diagnose-Mängel, die die Fehlersuche aktiv in die Irre geführt hatten,
  sind mitbehoben:**
  - Die Anbieter-Prüfung testete fest verdrahtet `de → en` und rief die
    Anbieter-Funktion direkt auf, also am Notaus-Schalter und an der
    Pausen-Prüfung vorbei. Sie konnte damit "funktioniert" melden, während die
    tatsächlich konfigurierte Sprachrichtung scheiterte. Geprüft wird jetzt die
    echte Scan-Sprache gegen die erste abweichende Zielsprache, und der
    Ergebnis-Dialog weist die geprüfte Richtung aus. Ist noch keine abweichende
    Zielsprache konfiguriert, wird das als Ersatz-Paarung gekennzeichnet, mit
    dem Hinweis, dass ein Erfolg dann nichts über die späteren Zielsprachen
    aussagt.
  - Ein Text über der 500-**Byte**-Grenze von MyMemory wurde wortlos
    übersprungen: leere Zelle, kein Log-Eintrag. Jetzt mit Klartext-Meldung
    inklusive tatsächlicher Bytezahl (Umlaute zählen doppelt, die sichtbare
    Textlänge führt in die Irre), Kontext und Textanfang.
  Außerdem aufgeräumt: die einmalige Zähler-Migration aus Build 132 wurde
  entfernt. Sie war für jede künftige Installation strukturell unerreichbar (die
  alten Attribute werden von keinem Codepfad mehr beschrieben, stehen also
  dauerhaft auf 0, und die Migration sprang nur bei einem Wert ungleich 0 an) -
  und ihr Lesevorgang lag ohnehin an der falschen Stelle: in `Create()` werden
  Attribute erst **deklariert**, ein `ReadAttribute*` liefert dort nicht
  zuverlässig den persistierten Wert. Das erklärt rückblickend den beim Testen
  beobachteten Zähler-Reset. Die Lehre steht jetzt als Kommentar genau an der
  Stelle, an der man sie wieder falsch machen würde: wertlesende Migrationen
  gehören nach `ApplyChanges()`.
  Neuer Regressionstest (ein einzelnes `&nbsp;` reißt die übrigen Texte nicht
  mehr mit; der untranslatierbare Knoten bleibt unverändert erhalten statt
  geleert zu werden; ein echter Fehlschlag liefert weiterhin `null`, damit die
  Kette greift; alle 128 Texte eines Durchlaufs überleben einen einzelnen
  untranslatierbaren; mehrere gleichzeitig sind ebenso unschädlich;
  Symmetrie-Check der Unterscheidung). Bestehende Tests um die Log-Pflicht bei
  der Byte-Grenze erweitert.

* **Build 151 (live gemeldet, per `dump21` nachgewiesen): ein Serverfehler
  mitten im Durchlauf warf alle bereits fertigen Übersetzungen desselben Laufs
  weg.**
  Gemeldetes Symptom: Ein Rescan lief minutenlang, übersetzte nachweislich
  erfolgreich - und trug trotzdem nichts in die Tabelle ein. Im Log stand die
  letzte erfolgreiche Abfrage direkt vor einem Serverfehler: "erfolgreich
  abgefragt, aber nicht in der Liste eingetragen".
  Der Dump zeigte den Ablauf: MyMemory lieferte **21 Übersetzungen sauber aus**
  (darunter die im Screenshot leer gebliebene Zeile sowie die beiden langen
  Texte, die also *nicht* an der 500-Byte-Grenze scheiterten), dann kam ein
  **HTTP 504 Gateway Time-out** - der Anbieter war schlicht überlastet.
  `TranslateChunkFree()` brach daraufhin mit `return null` ab und verwarf dabei
  **alle 21 fertigen Übersetzungen**; `TranslateChunk()` wertete das als
  Anbieter-Ausfall und füllte den gesamten Chunk mit Leerstrings.
  Das Kontingent war für diese 21 Anfragen längst verbraucht - beim nächsten
  Rescan begann alles von vorn, inklusive erneutem Verbrauch. Bei einem
  zeitweise überlasteten Anbieter konnte ein größerer Baum dadurch **nie fertig
  übersetzt werden**.
  Kern des Problems: MyMemory hat keinen Batch-Endpunkt und ruft pro Text
  einzeln auf - ein **Teilerfolg ist dort der Normalfall**, anders als bei
  Google/DeepL, wo ein Aufruf den ganzen Chunk abdeckt. Der Code behandelte
  beide gleich.
  - `TranslateChunkFree()` arbeitet jetzt weiter: erfolgreiche Texte behalten
    ihr Ergebnis, nur der tatsächlich fehlgeschlagene bleibt offen. `null` ist
    dem **Totalausfall** vorbehalten (kein einziger Text durchgekommen) - nur
    dann gilt der Anbieter als gescheitert, was Kettenwechsel und
    Pausen-Eskalation weiterhin korrekt auslöst.
  - `TranslateChunk()` sammelt Teilergebnisse jetzt **über die Anbieter-Kette
    hinweg** und reicht an den nächsten Anbieter nur die noch offenen Texte
    weiter - der verbraucht damit auch kein Kontingent für bereits Übersetztes,
    und ein bereits gutes Ergebnis wird nie überschrieben.
  Nach der ganzen Kette noch offene Texte bleiben bewusst **leer** statt mit
  dem Originaltext gefüllt: Eine leere Zelle gilt als "nicht aktuell" und wird
  beim nächsten Rescan erneut versucht, ein eingetragener Originaltext würde
  fälschlich als fertige Übersetzung gelten und nie wieder angefasst.
  Beim Erkennen leerer Ergebnisse bewusst eine explizite Prüfung auf `!== ''`
  statt `array_filter()` - letzteres wertet auch eine Übersetzung, die wörtlich
  `"0"` lautet, als leer und würde sie verwerfen.
  **Außerdem (Nutzer-Wunsch): der Laufbalken nennt jetzt die zu erwartende
  Dauer.** Gerade der erste Scan läuft bei vielen Objekten minutenlang, weil
  jeder noch nicht gecachte Text einzeln an den Anbieter geht - ohne Zeitangabe
  wirkt das wie ein Hänger. Die beiden Übersetzungs-Stufen tragen den Hinweis
  "(je nach Anzahl der Objekte kann das einige Minuten dauern)" in allen vier
  Sprachen. Die schnellen Stufen ("Baum wird eingelesen", "Ergebnis wird
  gespeichert") bewusst **nicht** - dort wäre die Ankündigung einer
  minutenlangen Wartezeit falsch und würde den Hinweis überall entwerten; ein
  Test sichert beide Richtungen ab.
  Neuer Regressionstest (Erfolge überleben einen Serverfehler im selben
  Durchlauf; alle 21 Erfolge aus dem Report bleiben erhalten; ein Totalausfall
  liefert weiterhin `null`; die Kette holt offene Texte beim nächsten Anbieter
  nach; bereits Übersetztes wird nicht überschrieben; durchgehend
  fehlgeschlagene Texte bleiben leer für den nächsten Rescan; Symmetrie-Checks
  inklusive des `array_filter()`-Fallstricks).

* **Build 152 (Nutzer-Frage: "Wie bekommt der User vom Ausfall eines Anbieters
  mit? Wenn es keine Information darüber gibt, könnte er denken, dass unsere App
  nicht richtig funktioniert."): Ausfälle sind jetzt im Formular sichtbar - mit
  konkreter Handlungsanweisung.**
  Berechtigter Einwand, und Build 151 hatte das Problem sogar **verschärft**:
  Vorher scheiterte bei einem Anbieterfehler der ganze Durchlauf und setzte
  wenigstens einen Fehlerstatus. Seit Build 151 bleiben Teilerfolge erhalten
  (richtig so), der Lauf gilt damit als gelungen - und der Ausfall wurde
  unsichtbar. Der Nutzer sah nur leere Zellen und musste annehmen, das Modul
  funktioniere nicht.
  Ein neues Attribut hält die Bilanz des **letzten** Durchlaufs (wird zu dessen
  Beginn zurückgesetzt) und speist zwei Hinweiszeilen direkt unter dem
  Rescan-Button, jeweils nur sichtbar, wenn die zugehörige Zahl > 0 ist.
  **Bewusst zwei getrennte Zähler**, weil sie zu gegensätzlichen Ratschlägen
  führen:
  - *Anbieter nicht erreichbar* (jeder HTTP >= 400, cURL-Fehler, Timeout - also
    nicht nur der beobachtete 504, sondern auch 500/502/503, DNS-Ausfälle,
    Verbindungsabbrüche): ein erneuter Scan kann helfen, also wird
    **ausdrücklich dazu aufgefordert** ("Bitte führe einen erneuten Scan durch -
    ggf. nach ein paar Minuten Wartezeit"). Eine Formulierung wie "wird
    nachgeholt" wäre missverständlich - sie klänge, als geschähe das von selbst.
  - *Zu lang für den kostenfreien Anbieter* (500-Byte-Grenze): ein erneuter Scan
    ändert daran **nichts**, die Grenze bleibt exakt dieselbe. Hier lautet der
    Rat stattdessen, einen Google- oder DeepL-Schlüssel zu hinterlegen. Wären
    beide Ursachen zusammengeworfen, bekäme jemand mit zu langen Texten die
    Aufforderung zum erneuten Scan - und der zweite Lauf endete exakt gleich.
  **Statuszeile und Kachel bleiben bewusst unberührt** (Nutzer-Vorgabe): Das
  Modul selbst ist in Ordnung, ein vorübergehend überlasteter Fremdserver ist
  kein Instanzfehler; und der Gast kann daran ohnehin nichts ändern. Beides ist
  per Test festgenagelt, damit es nicht später "hilfreich" ergänzt wird.
  Da Teilerfolge seit Build 151 erhalten bleiben, **sinkt die Zahl von Lauf zu
  Lauf** - der Nutzer sieht unmittelbar Fortschritt statt immer derselben
  Meldung, und am Ende verschwindet der Hinweis von allein.
  **Fehler aus Build 151 dabei mitkorrigiert:** Ein Text über der Byte-Grenze
  liefert einen Leerstring, nicht `null` - der wurde dort fälschlich als Erfolg
  gewertet. Ein Chunk aus lauter zu langen Texten hätte damit "Anbieter hat
  funktioniert" gemeldet, und die Kette hätte Google/DeepL **nicht mehr
  gefragt** - obwohl genau die diese Grenze nicht kennen. Jetzt zählt nur ein
  nicht-leeres Ergebnis als Erfolg.
  Neuer Regressionstest (Ausfall wird sichtbar, mit Anzahl; ein sauberer Lauf
  zeigt nichts; beide Ursachen werden getrennt gezählt und gemeldet; zu lange
  Texte fordern nicht zum sinnlosen erneuten Scan auf; die Bilanz gilt je
  Durchlauf; die Zahl sinkt von Lauf zu Lauf und verschwindet am Ende;
  Symmetrie-Checks inklusive der bewussten Nicht-Änderung von Statuszeile und
  Kachel).

* **Build 160 (Nutzer-Wunsch): ohne das Feature `manual_translations`
  verschwindet die "Eigene Übersetzungstabelle" jetzt ganz.**
  Bis Build 159 stand sie auch ohne das Feature im Formular: leer, nicht
  vorbefüllt, aber mit funktionierendem Papierkorb. Der Nutzer sah also eine
  Tabelle, erwartete dass sie etwas tut, und bekam nichts.

  Überschrift und Beschreibung bleiben bewusst stehen - er soll lesen können,
  was ihm entgeht -, darunter erscheint "Die eigene Übersetzungstabelle steht in
  dieser Edition nicht zur Verfügung." Nichts zum Klicken, keine falsche
  Erwartung. Der mitgelieferte Katalog für Einheiten und Kompassrichtungen wirkt
  davon unberührt weiter im Hintergrund (siehe Build 158/159).

  Regressionstest `test_manual_table_hidden_without_feature.php` (5 Fälle:
  Tabelle weg ohne Feature; Überschrift und Absage erscheinen stattdessen; mit
  Feature genau umgekehrt, damit die Überschrift nicht doppelt steht;
  Symmetrie-Check inklusive der Reihenfolge, dass für eine unsichtbare Tabelle
  keine Spalten mehr aufgebaut werden; beide Texte in allen vier Sprachen
  registriert).

* **Build 159 (live gemeldet): Build 158 hatte nur die halbe Strecke gebaut -
  bereits falsch gespeicherte Zellen wurden nicht korrigiert.**
  Der mitgelieferte Katalog griff seit Build 158 bei **neuen** Übersetzungen,
  aber eine einmal falsch gespeicherte Zelle wurde nie wieder angefasst: befüllte
  Zellen werden nicht neu übersetzt. Der einzige Durchlauf, der gespeicherte
  Zellen nachträglich korrigiert, ist `ApplyManualTranslationOverrides()` - und
  der stieg ohne das Feature `manual_translations` sofort aus. Live sichtbar
  daran, dass ein einmal als `°F` gespeichertes `°C`-Suffix auch nach dem Update
  stehenblieb.

  *Fix:* der Durchlauf läuft jetzt auch ohne das Feature, nur eben gegen den
  mitgelieferten Katalog statt gegen die gespeicherte Tabelle. Die Abkürzung
  "nichts zu prüfen" greift nur noch, wenn das Feature vorhanden **und** die
  Tabelle leer ist - ohne Feature ist die leere Liste der Normalfall und darf
  nicht zum Aussteigen führen. Genau diese Abkürzung war der Fehler.

  Regressionstest `test_bundled_glossary_corrects_stored_cells.php` (6 Fälle:
  der gemeldete Fall; der Fix; die Abkürzung bleibt mit Feature erhalten; ein
  Symmetrie-Check auf genau diese Bedingung; eine bereits korrekte Zelle bleibt
  unangetastet; normale Übersetzungen werden nicht überschrieben).

* **Build 158 (Nutzer-Entscheidung nach dem Befund aus Build 157): die
  mitgelieferte Nachschlagetabelle für Einheiten und Kompassrichtungen greift
  jetzt in JEDER Edition.**
  Bis Build 157 hingen zwei Dinge an einem einzigen Lizenz-Flag
  (`manual_translations`): die editierbare "Eigene Übersetzungstabelle" **und**
  der mitgelieferte Katalog. Eine Edition ohne das Feature schickte damit auch
  `°C` ganz normal an die API - und bekam auf Englisch `°F` zurück. Eine
  Einheitenumrechnung als Übersetzung ist in jeder Edition falsch, und dem
  Kunden ist nicht vermittelbar, dass das an seiner Edition liegt.

  Verkauft wird die **editierbare** Tabelle, nicht die korrekte Behandlung von
  Einheiten. Ohne das Feature bleibt sie unsichtbar und unbearbeitbar, der
  interne Lookup greift trotzdem - das kostet nichts und spart sogar Kontingent.

  Die Gegenrichtung ist bewusst unverändert: **mit** dem Feature gibt es keinen
  Fallback. Dort ist die Tabelle maßgeblich, und eine bewusst gelöschte Zeile
  (z. B. weil "SSW" in dieser Installation ein Personen-Kürzel ist, keine
  Windrichtung) muss gelöscht bleiben - ein Fallback würde genau diese Löschung
  wirkungslos machen.

  Regressionstest `test_bundled_glossary_all_editions.php` (6 Fälle: der
  gemeldete Fall; die Gegenrichtung mit Feature; ein eingetragener Wert gewinnt
  immer; der Katalog greift nur bei deutscher Quellsprache; unbekannte Texte
  gehen weiterhin an die API; Symmetrie-Check inklusive der Pufferung der Karte,
  da die Suche je Text läuft).

* **Build 157 (live gemeldet, per Screenshot belegt): die mitgelieferten
  Vorschlagszeilen der "Eigenen Übersetzungstabelle" blieben leer - und dadurch
  wurde `°C` nach Englisch zu `°F`.**
  In der Tabelle standen die Einheiten-Zeilen zwar da, ihre Sprachspalten waren
  aber leer. `MergeBundledManualTranslations()` übersprang eine Zeile
  vollständig, sobald es sie schon gab - kam eine Zielsprache erst **später**
  dazu, wurde ihre Spalte nie mehr befüllt. Eine leere Zelle gilt in
  `FindManualTranslation()` als "kein Treffer", der Text lief also ganz normal in
  die API. Sichtbar an einem Einheiten-Suffix: aus `°C` wurde `°F` - eine
  Einheitenumrechnung, keine Übersetzung.

  *Fix:* bestehende Vorschlagszeilen werden nachbefüllt, aber **nur leere
  Zellen** - ein vom Admin eingetragener Wert gewinnt immer. Der bestehende
  Schutz bleibt unangetastet: eine bewusst gelöschte Vorschlagszeile kehrt
  weiterhin nicht zurück (`attributeSeededManualTranslationKeys`). Anlegen und
  Nachbefüllen speisen jetzt aus einer gemeinsamen Quelle
  (`BuildBundledManualTranslationMap()`), damit sie nicht auseinanderlaufen
  können.

  Regressionstest `test_bundled_glossary_backfill.php` (6 Fälle: der gemeldete
  Fall inklusive des ausbleibenden Glossartreffers; der Fix; ein Admin-Wert wird
  nie überschrieben; gelöschte Vorschläge kehren nicht zurück; eigene Zeilen
  bleiben unangetastet; Symmetrie-Check inklusive der Gegenseite, dass eine
  leere Zelle weiterhin als "kein Treffer" gilt).

* **Build 156 (live gemeldet, per Screenshot belegt): die Auswahltexte der
  Kachel-Kataloge folgten der Symcon-Systemsprache statt der Konsolensprache.**
  Bei englischer Konsole standen die Feldbeschriftungen korrekt auf Englisch
  ("Tile template", "Icon in the tile"), die Auswahleinträge daneben aber weiter
  auf "Automatisch (Standard)".

  `BuildCatalogOptions()` setzte die Beschriftung zur Laufzeit aus mehreren
  `Translate()`-Fragmenten zusammen. Eine so zusammengebaute Zeichenkette matcht
  nie einen `locale.json`-Eintrag und bleibt dadurch an die Systemsprache
  gebunden - derselbe Fehler wie zuvor bei den Anbieter-Pausen-Zeilen und bei
  `propertyAutoRescanInterval`, und dort genauso behoben: fester,
  vollständig vorregistrierter deutscher Gesamttext ohne `Translate()`, damit
  die Konsole exakt matchen und selbst übersetzen kann.

  Preis dafür: jeder neue Katalogeintrag braucht zwei `locale.json`-Zeilen je
  Sprache - sein Label und die "Automatisch (Label)"-Kombination. Damit das bei
  künftigen Editions-Symbolen und -Vorlagen nicht vergessen wird, prüft
  `test_tile_catalog_captions_localized.php` beides und nennt fehlende Strings
  einzeln pro Sprache (5 Fälle, inklusive einer Kontrollprobe, dass die Prüfung
  bei einem neuen Eintrag tatsächlich anschlägt).

  Ebenfalls in diesem Build (Nutzerwunsch): im Kachel-Panel steht die
  Kachel-Vorlage jetzt an erster Stelle, und das Symbol-Dropdown sitzt in einem
  `RowLayout` direkt neben seiner "Symbol anzeigen"-Checkbox.

* **Build 155 (live gemeldet): das Ergebnis-Popup von "Aufräumen" ging nach dem
  automatischen Neuladen des Formulars ein zweites Mal auf - diesmal ohne Zahl.**
  `PopulateFormElements()` stieg nur in `$element['items']` ab. Die Inhalte
  eines `PopupAlert` liegen aber eine Ebene tiefer, in
  `$element['popup']['items']`, und wurden deshalb nie befüllt. Das Popup selbst
  ist ein Element oberster Ebene und wurde korrekt sichtbar gesetzt - daher
  "Popup ja, Zahl nein". Dass das ERSTE Popup die Zahl noch zeigte, liegt an
  `UpdateFormField()`: das adressiert ein Feld über seinen Namen und erreicht es
  unabhängig von der Verschachtelung.

  Geprüft, bevor die Rekursion erweitert wurde: von den 24 Feldern unterhalb
  eines `popup`-Knotens hat genau eines (`CleanupResultCountLabel`) überhaupt
  einen Zweig in `PopulateFormElements()` - der Eingriff ändert also an keinem
  anderen Popup etwas.

  Im selben Build auf Nutzerwunsch die **doppelte Anzeige** abgestellt: das
  Popup ging sichtbar zweimal auf, weil es zwei unabhängige Wege gab - das
  sofortige Live-Einblenden per `UpdateFormField()` (Build 98) und das Attribut
  `attributeLastCleanupRemovedCount`, das `PopulateFormElements()` beim
  automatischen Neuaufbau liest. Der Neuaufbau ist der verlässlichere Weg (er
  überlebt den Reload, der Live-Push nicht), deshalb entfällt das
  Live-Einblenden ersatzlos. `test_cleanup_deferred_reload.php` wurde dabei
  durch `test_cleanup_result_popup_shown_once.php` ersetzt: der alte Test
  beschrieb noch den Build-98-Stand samt verzögertem `CleanupReloadTimer`, den
  Build 116 längst entfernt hatte, und prüfte nur noch seine eigene Replik.

  Regressionstest `test_popup_items_populated.php` (6 Fälle: der gemeldete Fall
  mit der lückenhaften Rekursion; der Fix; kein Popup ohne vorangegangenes
  Aufräumen; ein Ergebnis von 0 erscheint als "0" statt als Leerfeld; beliebig
  tief verschachtelte Popup-Inhalte; Symmetrie-Check inklusive des Nachweises,
  dass das Feld real unterhalb eines `popup`-Knotens liegt).

* **Build 154 (live gemeldet, per `dump23` nachgewiesen): Datenverlust - die
  Spalte `ORIGINAL_IMPORT_Text` in "Eigene Texte" wurde durch die eigene
  Übersetzung ersetzt.**
  Der Nutzer änderte nur die Begrüßung in der Visu-Instanz und sah daraufhin 88
  Übersetzungsanfragen. 67 davon trugen als Quelltext exakt das, was ein früherer
  Lauf als *Ergebnis* geliefert hatte - bei `langpair=de|la`. Die Mapping-Zeile
  zeigte den Rohtext von sechs Objekten in Latein, eines bereits mit arabischen
  Fragmenten: der Text war mehrfach im Kreis gelaufen (Deutsch → Latein →
  Latein-von-Latein). Auf der Live-Instanz war praktisch jede Zeile betroffen.

  *Ursache 1 (die eigentliche):* `WriteTrackedValueString()` setzte den
  Selbst-Schreib-Marker (`attributeLastSelfWrittenValues`) **nach**
  `SetValueString()`. Symcon stellt `VM_UPDATE` synchron zu -
  `HandleTrackedVariableUpdate()` lief also bereits, während der Marker noch den
  alten Stand trug, und hielt den eigenen Schreibvorgang für eine externe
  Änderung. Das ist das "seltene Timing-Fenster", das der Build-95-Kommentar
  nicht auflösen konnte: kein Zufall, sondern schlicht die Reihenfolge.

  *Ursache 2 (warum das Netz darunter riss):* der Build-95-Schutz verglich den
  externen Wert nur mit der Zelle der **aktuell aktiven** Sprache. Genau die war
  nach dem Kontingent-Abbruch aus `dump22` leer bzw. nur teilweise gefüllt - der
  Vergleich griff nicht.

  *Fix:* Marker vor dem Schreibvorgang persistieren; zusätzlich eine
  Rückübersetzungs-Sperre, die den externen Wert gegen **alle** gespeicherten
  Zielsprachen-Zellen der Zeile prüft (Quellsprache bewusst ausgenommen, dort ist
  Gleichheit der Normalfall) und im Debug als
  `TrackedValue_BackTranslationBlocked` sichtbar wird.

  Ebenfalls in diesem Build: die Debug-Kategorie `GoogleTranslate_Mapping` heißt
  jetzt `Translate_Mapping`. Sie wird anbieterunabhängig geschrieben und hatte
  den Nutzer zweimal glauben lassen, es gehe ein Aufruf an Google, obwohl gar
  kein Google-Key konfiguriert war.

  Für die Reparatur der betroffenen Live-Installation entstand ein
  eigenständiges Skript (`tools/repair_original_import.php`, Modi `diagnose`,
  `freeze`, `copy` aus einer intakten zweiten Instanz, `backup` aus einer
  `settings.json`, `restore` aus dem Symcon-Archiv und `reset_texts`). Es wird
  bewusst **nicht** mit dem Modul ausgeliefert - IP-Symcon liest jede PHP-Datei
  im Modulverzeichnis ein und meldet bei einem eigenständigen Skript mit Code
  auf oberster Ebene Fehler. Bei Bedarf lässt es sich aus der Historie holen
  (Commits `f342aa6` und `e4cc37c`).

  Regressionstest `test_back_translation_cycle.php` (8 Fälle: die
  Marker-Reihenfolge; der gemeldete Fall mit leerer Zelle der aktiven Sprache;
  der bestehende Build-95-Schutz bleibt wirksam; echte externe Änderungen kommen
  weiterhin durch; die Quellsprache ist ausgenommen; ein leerer Wert löst die
  Sperre nicht aus; zwei Sprachwechsel-Runden lassen den Rohtext unverändert;
  Symmetrie-Checks gegen die reale Umsetzung).

* **Build 153 (live gemeldet, per `dump22` nachgewiesen): zwei Regressionen aus
  Build 151 behoben - Weiterfragen trotz erschöpftem Kontingent, und eine
  Anbieter-Sperre, die sich selbst sofort wieder aufhob.**
  Der Nutzer fuhr bewusst gegen MyMemorys Tageslimit, um das Verhalten zu sehen,
  und meldete zwei Auffälligkeiten. Beide gehen auf denselben Umbau zurück: Seit
  Build 151 kann ein Anbieter **teilweise** liefern - und an dieser neuen
  Möglichkeit hingen zwei Stellen, die ich damals nicht mitgedacht hatte.
  - **Weiterfragen nach dem Rate-Limit.** Bis Build 151 stoppte der erste
    Fehlschlag den Durchlauf (mit dem Nebeneffekt, alle Teilerfolge zu
    verwerfen - deshalb der Umbau). Seitdem lief die Schleife stur weiter:
    laut Dump 90 Texte erfolgreich, dann HTTP 429 mit
    "YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY" - und danach **35
    weitere, völlig aussichtslose Aufrufe**, die nur Zeit kosteten und einen
    ohnehin überlasteten Fremdserver zusätzlich belasteten.
    `TranslateChunkFree()` prüft jetzt vor jedem Aufruf, ob der Anbieter
    inzwischen gesperrt wurde. Die restlichen Texte bleiben offen und werden
    beim nächsten Rescan erneut versucht - genau wie ein einzelner Fehlschlag.
  - **Die Sperre hob sich selbst auf.** MyMemorys 429 enthält das Wort "TODAY",
    die Erkennung greift also korrekt und setzt die volle Tagessperre mit exakt
    geparstem Reset (im Dump: 7 h 15 min). Unmittelbar danach lief aber
    `ClearProviderPause()` - denn ein Teilerfolg galt als Anbieter-Erfolg. Die
    gerade gesetzte Sperre war damit sofort wieder weg. Folgen: Die Statuszeile
    blieb fälschlich auf "Aktiv" statt "pausiert" (genau die Beobachtung des
    Nutzers), und der nächste Chunk rannte ungebremst in dieselbe Wand.
    Die Sperre wird jetzt nur noch bei **vollständiger** Lieferung aufgehoben -
    ein unvollständiger Lauf ist kein Gesundheitsnachweis. Ein vollständig
    gelieferter Lauf hebt sie weiterhin auf, damit ein längst wieder gesunder
    Anbieter nicht unnötig gesperrt bleibt.
  **Drittens gemeldet: erneutes Abfragen bereits übersetzter Texte** - das war
  kein neuer Fehler, sondern die Nachwirkung des in Build 151 behobenen. Der
  betreffende Lauf im Dump lief noch auf dem alten Stand: Der HTTP 504 verwarf
  dort alle 21 Erfolge, und da `TranslateBatchUncached()` nur nicht-leere
  Ergebnisse cacht, landete auch **nichts im Cache**. Der spätere Lauf musste
  sie deshalb neu holen. Seit Build 151 werden Teilerfolge gespeichert *und*
  gecacht; im aktuellen Lauf des Dumps sind die 90 Erfolge nachweislich beides.
  Neuer Regressionstest (nach dem Rate-Limit wird kein weiterer Aufruf mehr
  abgesetzt; die Erfolge davor bleiben trotzdem erhalten; ein unvollständiger
  Lauf hebt die Sperre nicht auf; ein vollständiger sehr wohl; schlägt schon der
  erste Text fehl, kommt genau ein Aufruf und danach `null` für den
  Kettenwechsel; Symmetrie-Checks für Prüf-Reihenfolge und Aufhebe-Bedingung).
