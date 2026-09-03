# Simple Locale
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Bekannte Einschränkungen](#2-bekannte-einschränkungen)
3. [Voraussetzungen](#3-voraussetzungen)
4. [Software-Installation](#4-software-installation)
5. [Einrichten der Instanzen in Symcon](#5-einrichten-der-instanzen-in-symcon)
6. [Statusvariablen und Profile](#6-statusvariablen-und-profile)
7. [Visualisierung](#7-visualisierung)
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
  welche Nutzersprache gerade aktiv ist. Wirkt wie ein dauerhaftes Leeren aller
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
  nachübersetzt (nicht nur die gerade aktive) - schaltet ein Nutzer danach in
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
  Wie viele Anfragen/Zeichen dadurch konkret eingespart wurden, zeigt der Nutzungs-Zähler im Konfigurationsformular (siehe
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
* **Automatische Pause bei Rate-Limit/Tageskontingent.** Meldet ein
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
  unter dem Dropdown in der Kachel (live in die jeweils aktive Nutzersprache
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
  Schreibvorgang sofort an verbundene Nutzer-Browser - inklusive des
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
  Original-Rohtext, kein Absturz). Das sieht auf den ersten Blick wie ein
  stiller Fehler ohne erkennbaren Grund aus, ist aber genau diese
  Längenbegrenzung. Der Symcon-Meldungen-Log der Instanz
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
  unabhängig von der in Simple Locale aktiven Sprache. Wählt ein Nutzer über
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
Simple-Locale-Symbol in der Kachel anzeigen | Blendet das Simple-Locale-Symbol links neben dem Dropdown aus, falls nicht gewünscht (z. B. bei eigenem Kachel-Design). Das Symbol ist als Base64-Grafik eingebettet (siehe Abschnitt 7). Standardmäßig an.
Info-Symbol in der Kachel anzeigen | Blendet das ⓘ-Symbol (Erklärung der Einschränkungen, siehe Abschnitt 2) aus. Standardmäßig an.
Eigene Sprachauswahl-Kachel verwenden | Unterdrückt die eingebaute Dropdown-Kachel zugunsten einer selbstgebauten (siehe Abschnitt 7). **Pro-Feature** (`custom_tile`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und die eingebaute Kachel aktiv.
Übersetzungsanbieter (Panel)    | Siehe eigenen Abschnitt unten ("Übersetzungsanbieter: Google/DeepL/kostenfrei") - funktioniert ab Werk ohne jede Eingabe.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen der Visualisierung, 0 = nur manuell über den Button "Visualisierung neu einlesen" (siehe unten). **Pro-Feature** (`auto_rescan`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und der Timer aus, der manuelle Rescan-Button bleibt aber in jeder Edition nutzbar.
Übersetzungen gelöschter Elemente in der Visualisierung entfernen | "Aufräumen" (siehe eigenen Absatz unten "Aufräumen: verwaiste Zeilen endgültig entfernen") - entfernt dauerhaft Zeilen, die keinem Objekt in der aktuellen Visualisierung mehr zugeordnet werden können. In jeder Edition nutzbar.

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
Scan-Sprache zwischen zwei Scans ändert?" Es genügt, die "Quellsprache"
EINER Zeile im Formular zu
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
- **Anbieter gezielt prüfen:** Der Button "Übersetzungsanbieter prüfen" ganz unten im Konfigurationsformular schickt eine einzelne Testanfrage direkt an jeden eingerichteten Anbieter (Google/DeepL, falls konfiguriert, sowie immer MyMemory) - am Cache vorbei und unabhängig von einer eventuell laufenden Pause, meldet also auch, ob ein eigentlich pausierter Anbieter inzwischen wieder geht. Eine noch laufende Pause wird dabei automatisch beendet, sobald ein Anbieter wieder erfolgreich antwortet - praktisch z. B. direkt nach einem Kontingent-/Abo-Upgrade beim Anbieter.

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

**Aufräumen: verwaiste Zeilen endgültig entfernen**

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
automatisch, ein eigenes, manuell wählbares Root-Feld gibt es nicht. Das verhindert
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
Objekte liefert `SLOC_TranslateText()` den Text in der aktuell aktiven
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

   **Welches HTML die Kachel überhaupt verwendet** (`GetVisualizationTile()`) -
   das entscheidet sich VOR jedem Platzhalter, und zwar in dieser Reihenfolge:

   1. "Eigene Sprachauswahl-Kachel verwenden" ist an **und** das Pro-Feature
      `custom_tile` liegt vor **und** das Feld "HTML-Code" ist nicht leer
      → dein HTML.
   2. Sonst die **ausgewählte mitgelieferte Vorlage** (Feld "Kachel-Vorlage",
      auch serverseitig ausgelieferte, siehe Abschnitt 8).
   3. Deren Standard ist `module.html`.

   `module.html` ist also der Rückfall, nicht der Träger: es wird nicht
   "ausgeliefert und ergänzt", sondern ist einer von mehreren möglichen
   Ausgangstexten. Erst auf den Gewinner laufen die Platzhalter.

   **Es sind zwei unabhängige Ebenen.** Die Hülle (Feld "HTML-Code") und die
   Sprachauswahl darin (Feld "Sprachauswahl-HTML-Code") lassen sich einzeln
   austauschen: eigene Hülle mit eingebauter Auswahl, eingebaute Hülle mit
   eigener Auswahl, oder beides eigen.

   > **Für Autoren einer gelieferten Vorlage** (siehe Abschnitt 8): eine Vorlage
   > ist die **komplette Hülle** und ersetzt `module.html` vollständig - sie wird
   > *nicht* an `<!--LANGUAGE_SELECT-->` eingesetzt. Der einfachste Weg ist,
   > `module.html` zu nehmen und anzupassen. Drei Dinge dabei:
   >
   > 1. **Kein `margin`/`padding` auf `body`.** Symcon legt Titel und
   >    Vergrößern-Symbol als Overlay über den Inhalt und reserviert oben Platz
   >    dafür. Wer den Rand entfernt, schiebt seinen Inhalt unter die Titelzeile.
   >    Muss das Standardpadding doch angefasst werden, dann nur links/rechts.
   > 2. **Wenn du die eingebaute Auswahl nutzt:** `<!--WRAPPER_ID-->` gehört in
   >    das `id`-Attribut, `<!--LANGUAGE_SELECT-->` als **Inhalt** desselben
   >    Elements - und dieses Element enthält sonst nichts, denn beim Neuzeichnen
   >    wird sein kompletter Inhalt ersetzt:
   >    ```html
   >    <div id="<!--WRAPPER_ID-->"><!--LANGUAGE_SELECT--></div>
   >    ```
   >    Beide in dasselbe Attribut zu schreiben ist ein naheliegender, aber
   >    folgenschwerer Fehler: die komplette Sprachauswahl landet dann als Text im
   >    ID-Wert.
   > 3. **Eigene Auswahl statt der eingebauten?** Dann `<!--LANGUAGE_SELECT-->`
   >    weglassen. Das Modul zeichnet die Kachel dann nicht mehr nach (es gäbe ja
   >    nichts nachzuzeichnen), die Statistik-Zähler stehen still, und die
   >    Hinweise an den Nutzer kommen unverändert an.
   >
   > Den `handleMessage`-Block musst du nicht mitliefern - fehlt er, ergänzt ihn
   > das Modul.

   **Alle Platzhalter auf einen Blick** (`ApplyTilePlaceholders()`):

   | Platzhalter | wird ersetzt durch |
   |---|---|
   | `<!--LANGUAGE_SELECT-->` | die Sprachauswahl - eigene, falls hinterlegt, sonst die generierte |
   | `<!--WRAPPER_ID-->` | eine pro Instanz eindeutige DOM-ID |
   | `<!--TILE_ICON-->` | das gewählte Symbol, einzeln platzierbar |
   | `<!--AVAILABLE_LANGUAGES-->` | JSON: alle konfigurierten Sprachen |
   | `<!--ACTIVE_LANGUAGE-->` | JSON: der Code der aktiven Sprache |
   | `<!--COUNT_TRANSLATIONS-->` | reine Zahl: Übersetzungen/h |
   | `<!--COUNT_SIGNES-->` | reine Zahl: Zeichen/h |
   | `<!--COUNT_CACHE_TRANSLATIONS-->` | Gesamtzahl der durch den Cache gesparten Anfragen |
   | `<!--COUNT_CACHE_SIGNES-->` | dieselbe Ersparnis in Zeichen |

   Die vier Zähler liefern **nur die Zahl**, keinen Einheitstext - den Satz
   drumherum baust du selbst. Kein Platzhalter ist Pflicht: fehlt einer, wird
   an dieser Stelle schlicht nichts eingesetzt.

   Das Bearbeiten-Fenster enthält zwei getrennte Felder:

   - **"HTML-Code"** - der äußere Rahmen (Layout/CSS), vorbefüllt mit einer
     1:1-Kopie der eingebauten `module.html`. Diese beiden Platzhalter braucht
     eine funktionierende Kachel - technisch erzwungen ist keiner, ohne sie
     bleibt die Stelle aber schlicht leer:
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
     <div class="sloc-select-row">
       <span class="sloc-globe" aria-hidden="true"><img src="data:image/png;base64,..." alt=""></span>
       <select onchange="requestAction('Language', this.value);">
         <option value="de">Deutsch</option>
         <option value="en" selected>English</option>
       </select>
       <span class="sloc-info-icon" aria-hidden="true" onclick="alert('...');">ⓘ</span>
     </div>
     <!-- + roter Testphase-Hinweis, nur solange ungelizenziert und Testphase läuft -->
     ```
     Das `<img>` ist das Simple-Locale-Symbol
     (`libs/assets/module_icon_48.png`), als Base64-Data-URI eingebettet -
     kein öffentlicher Pfad/Webhook nötig. Für eine eigene Kachel kann hier
     stattdessen jedes beliebige eigene Icon/Emoji stehen, die CSS-Klasse
     `sloc-globe` (Name aus historischen Gründen unverändert) liefert
     bereits einen passenden 32×32px-Kreis als Container.

     **Das gewählte Symbol einzeln einsetzen: `<!--TILE_ICON-->`.**
     Oben steckt das Symbol INNERHALB der generierten Sprachauswahl - wer eine
     eigene Sprachauswahl hinterlegt, ersetzt damit den ganzen Block und verliert
     es. Mit diesem Platzhalter setzt du es an eine beliebige Stelle deines
     Templates, und die Auswahl im Feld "Symbol in der Kachel" bleibt wirksam -
     einschließlich der serverseitig ausgelieferten
     Editions-Symbole. Ist die Checkbox "Symbol in der Kachel anzeigen"
     abgeschaltet, bleibt der Platzhalter leer.

     Das eingesetzte `<img>` trägt die Klasse `sloc-tile-icon` und eine
     Größenangabe, die **nicht kollabiert** (`max-width`/`max-height` statt
     `height:100%`): dein Container braucht also keine feste Höhe zu haben.
     Willst du es anders skalieren, sprich die Klasse in deinem CSS an.

     **Das Layout an der Konfiguration ausrichten:
     `<!--AVAILABLE_LANGUAGES-->` und `<!--ACTIVE_LANGUAGE-->`.**
     Ohne sie müsste ein eigenes Template die Sprachcodes fest eintippen - und
     träfe damit leicht neben die tatsächlich konfigurierten Zielsprachen. Diese
     beiden Platzhalter geben dir stattdessen die Konfiguration selbst:

     | Platzhalter | liefert |
     |---|---|
     | `<!--AVAILABLE_LANGUAGES-->` | JSON-Liste aller konfigurierten Sprachen: `[{"code":"de","name":"Deutsch","current":true}, …]` - dieselbe Struktur wie `SLOC_GetAvailableLanguages()` |
     | `<!--ACTIVE_LANGUAGE-->` | der Code der aktiven Sprache, als JSON-String: `"de"` |

     Beide liefern **immer gültiges JSON**, auch wenn etwas fehlt oder gesperrt
     ist. Du kannst sie deshalb direkt in eine Zuweisung setzen, ohne
     Anführungszeichen drumherum:

     ```html
     <script>
         const languages = <!--AVAILABLE_LANGUAGES-->;
         let active = <!--ACTIVE_LANGUAGE-->;

         function render() {
             document.getElementById("flags").innerHTML = languages.map(l =>
                 `<span onclick="requestAction('Language', '${l.code}')"
                        class="${l.code === active ? 'is-active' : ''}"
                        title="${l.name}">${l.code.toUpperCase()}</span>`
             ).join("");
         }
         render();
     </script>
     ```

     `<!--ACTIVE_LANGUAGE-->` ist immer ein **echter Sprachcode**. `ORIGINAL_IMPORT`
     ist modulintern und erscheint hier nie - ist die Ursprungssprache aktiv,
     steht deren Code drin.

     Anders als die gleichnamige Funktion `SLOC_GetAvailableLanguages()` sind
     **beide Platzhalter an kein Feature gebunden**. Sie brauchen es nicht: an
     dieser Stelle ist die Sperre bereits gefallen. Eigenes Kachel-HTML wirkt
     sich überhaupt nur mit `custom_tile` aus - ohne das Feature lässt sich ein
     Platzhalter also gar nicht erst einschleusen. Der einzige andere Weg in die
     Kachel ist ein mitgeliefertes Editions-Design, und die schreiben nicht die
     Anwender. Ein solches Design darf den Platzhalter deshalb ohne Rücksicht
     auf die Edition des Empfängers benutzen.

     Die Funktion selbst bleibt gesperrt - sie ist der Weg, eine eigene Auswahl
     per Skript **an der Kachel vorbei** zu bauen, und dort greift keine
     vorgelagerte Prüfung.

     > **Wichtig - beide frieren beim Laden ein.** Sie werden einmal eingesetzt,
     > wenn die Kachel gerendert wird. Wechselt der Nutzer danach die Sprache, wird
     > die Kachel *nicht* neu gebaut, und dein `active` zeigt weiterhin den alten
     > Stand. Damit deine Hervorhebung mitwandert, definiere
     > `window.slocOnLanguageChange` - das Modul ruft sie bei **jedem**
     > Sprachwechsel auf, auch bei einem abgelehnten:
     >
     > ```js
     > window.slocOnLanguageChange = function (activeLanguage, availableLanguages) {
     >     active = activeLanguage;
     >     render();
     > };
     > ```
     >
     > Die Funktion ist optional - definierst du sie nicht, ändert sich nichts.
     > Du musst dafür nichts weiter einbauen: die Verdrahtung bringt das Modul
     > mit, auch wenn dein Template einen eigenen `handleMessage`-Block hat, den
     > es aus `module.html` übernommen hat.

     Dazu kommen **vier Zähler-Platzhalter** für die in
     [Abschnitt 2](#2-bekannte-einschränkungen) beschriebene Nutzungsstatistik.
     Alle vier liefern eine reine, gerundete Ganzzahl ohne Einheit (z. B. "30"
     oder "500") - die passende Beschriftung ("Übersetzungen/h", "Zeichen/h",
     "gesparte Anfragen" o. ä.) ergänzt man selbst im umgebenden HTML:

     | Platzhalter | liefert |
     |---|---|
     | `<!--COUNT_TRANSLATIONS-->` | Übersetzungsanfragen pro Stunde (Durchschnitt) |
     | `<!--COUNT_SIGNES-->` | übersetzte Zeichen pro Stunde (Durchschnitt) |
     | `<!--COUNT_CACHE_TRANSLATIONS-->` | seit Inbetriebnahme durch den Cache gesparte Anfragen (Gesamtsumme) |
     | `<!--COUNT_CACHE_SIGNES-->` | dieselbe Ersparnis in Zeichen (Gesamtsumme) |

     Die ersten beiden sind eine **Rate pro Stunde**, die beiden Cache-Zähler
     eine **Gesamtsumme** - das ist der einzige inhaltliche Unterschied.

     Für alle vier gilt dasselbe: unabhängig vom eingebauten Toggle
     "Übersetzungsstatistik in der Kachel anzeigen" (der betrifft nur die
     eingebaute Standard-Kachel), nutzbar sowohl im "HTML-Code"-Feld als auch im
     "Sprachauswahl-HTML-Code"-Feld weiter unten, und ohne zusätzlichen Aufwand,
     falls keiner davon im HTML vorkommt. Aktualisiert wird alle 10 Minuten über
     denselben `PushVisualizationUpdate()`-Mechanismus, der auch den
     `REFRESH`-Payload weiter unten auslöst - nie über einen Formular-Reload.

   - **"Sprachauswahl-HTML-Code"** - ersetzt `<!--LANGUAGE_SELECT-->`.
     Standardmäßig **vorbefüllt mit einem funktionierenden Beispiel** (zwei
     Flaggen statt Dropdown für Deutsch/Englisch), damit direkt nach dem
     Aktivieren etwas Sichtbares/Funktionierendes in der Kachel steht:
     ```html
     <div style="display:flex; align-items:center; gap:10px;">
         <span onclick="requestAction('Language', 'de');" style="cursor:pointer; font-size:24px;" title="Deutsch">🇩🇪</span>
         <span onclick="requestAction('Language', 'en');" style="cursor:pointer; font-size:24px;" title="English">🇬🇧</span>
     </div>
     ```
     `requestAction('Language', '<Code>')` ist der eigentliche Mechanismus -
     die von Symcon in jede Kachel injizierte JS-Funktion, die einen
     Sprachwechsel auslöst; sie ist an keine bestimmte HTML-Struktur
     gebunden (kein `<select>` nötig - jedes klickbare Element reicht).
     `<Code>` muss ein **konfigurierter** Sprachcode sein (z. B. `en`, `fr`) -
     die Scan-Sprache eingeschlossen, die immer als Zielsprache mitgeführt wird
     (siehe `EnsureSourceLanguageIsTarget()`). Über sie kommt man zum
     unbearbeiteten Originaltext zurück, also z. B. `de` statt eines
     Sonderwerts.

     > Ein Code, den die Instanz nicht kennt, wird abgelehnt: die aktive Sprache
     > bleibt stehen, und der Nutzer bekommt ein Popup, das genau
     > das sagt. Genau dieser Fall tritt auf, wenn eine eigene Sprachauswahl
     > feste Codes trägt und später die Zielsprachen geändert werden.

     `ORIGINAL_IMPORT` ist **keine wählbare Nutzersprache**
     und rein modulintern (Rückfall bei abgelaufener Testphase). In einer
     eigenen Kachel hat der Wert nichts zu suchen.

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
   - `SLOC_GetAvailableLanguages(int $InstanzID): string` - liefert die
     wählbaren Sprachen als JSON-Array `[{code, name, current}, ...]`, live
     in die aktuell aktive Sprache übersetzt und alphabetisch sortiert -
     exakt dieselbe Liste wie im eingebauten Dropdown - also ausschließlich
     konfigurierte Sprachcodes, die Scan-Sprache eingeschlossen. Genau diese
     Werte akzeptiert auch `SLOC_SetLanguage()`.
   - `SLOC_SetLanguage(int $InstanzID, string $Sprachcode): void` -
     wechselt die aktive Sprache, mit derselben Logik wie ein Klick im
     eingebauten Dropdown (Testphase-/Rate-Limit-Prüfung inklusive).
   - `SLOC_GetCurrentLanguageCode(int $InstanzID): string` - der Code der
     gerade aktiven Sprache (z. B. `"en"`), um die eigene Anzeige darauf
     einzustellen: welcher Eintrag hervorgehoben wird, ob überhaupt neu
     aufgebaut werden muss. Liefert immer einen echten Sprachcode - ist die
     Ursprungssprache aktiv, steht deren Code drin, nie der modulinterne
     Wert `ORIGINAL_IMPORT`. **Nicht** an `custom_tile` gebunden, im
     Gegensatz zu den beiden anderen: der Befehl liest nur, er baut nichts
     an der Kachel vorbei. Denselben Wert liefert innerhalb einer Vorlage
     der Platzhalter `<!--ACTIVE_LANGUAGE-->` (siehe oben).

Ohne das Feature `custom_tile` bleiben die Formularfelder aus Weg 1
ausgegraut UND die eingebaute Kachel aktiv (unabhängig vom gespeicherten
Wert), UND die beiden **erstgenannten** Befehle aus Weg 2 werfen bei jedem
Aufruf eine Exception, statt einfach nichts zu tun - eine selbstgebaute
Kachel ließe sich sonst komplett kostenlos an der Lizenzprüfung vorbei
realisieren, siehe [Abschnitt 8](#8-lizenz-und-testversion).

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
  `SLOC_GetAvailableLanguages`/`SLOC_SetLanguage` in
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
mehreren unterschiedlichen Licensee-Adressen auf, wurde die Lizenz entweder
weitergegeben oder sie läuft gleichzeitig in mehreren Installationen. Eine
Weitergabe ist erlaubt (verkauft oder verschenkt, siehe LICENSE und
Abschnitt 7 der AGB) und wird nach Meldung einfach umgeschrieben; das Signal
dient dazu, die NICHT gemeldeten Fälle zu erkennen - etwa einen Schlüssel,
der als "gebraucht" mehrfach bei Ebay verkauft wird. Die Konstante `LICENSE_ACTIVATION_REPORT_URL` in
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
  andere ungültige Schlüssel auch (kein eigenes Popup für den Nutzer nötig).
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

**Kachel-Designs je Edition (Symbol und Vorlage):** Eine Edition kann ein
eigenes Design mitbringen - ein Symbol für die Kachel, eine komplette
Kachel-Vorlage, oder beides. Gepflegt wird das nicht im Modul, sondern beim
Anbieter; ausgeliefert wird es bei der **Lizenz-Aktivierung**, denn erst dort
steht fest, zu welcher Edition eine Installation gehört. Ein ausdrücklicher
Klick auf "Lizenz aktivieren/aktualisieren" holt es ebenfalls, ohne dabei eine
weitere Aktivierung zu melden.

Zwei Bindungen: Ein Design **mit** Edition geht nur an deren Käufer und wird von
der Einstellung "Automatisch" von selbst ausgewählt - das ist der
Wiedererkennungswert einer Sonder-Edition. Ein Design **ohne** Edition geht an
alle und verhält sich wie der Auslieferungszustand: immer wählbar, nie
automatisch.

Beim **ersten** Eintreffen wird ein editionsgebundenes Design gleich aktiv
gesetzt, damit der Käufer es nicht suchen muss. Kommt dasselbe Design bei einer
späteren Aktivierung erneut mit, bleibt die Auswahl unangetastet - was einmal
abgewählt wurde, bleibt abgewählt. Ein einmal geliefertes Design bleibt
dauerhaft auswählbar, auch ohne Internetverbindung und auch dann, wenn der
Anbieter es später zurückzieht.

Die eingebauten Einträge (Standard-Vorlage, Simple-Locale-Symbol, Weltkugel)
werden dabei nie überschrieben - die Kachel lässt sich also immer auf den
Auslieferungszustand zurücksetzen. Wie eine solche Vorlage aufgebaut sein muss,
steht in [Abschnitt 7](#7-visualisierung).

**Warum das sicher ist:** Das Paket trägt dieselbe Ed25519-Signatur wie ein
Lizenzschlüssel und wird gegen denselben einkompilierten öffentlichen Schlüssel
geprüft; ohne gültige Signatur wird nichts übernommen. Das Modul lädt also
Inhalte aus dem Netz, akzeptiert aber ausschließlich, was mit dem privaten
Offline-Schlüssel des Anbieters signiert wurde - ein manipulierter DNS, ein
übernommener Webserver oder ein Man-in-the-Middle können nichts einschleusen.
Gerendert wird das Ergebnis über dieselbe Strecke, die auch selbst editiertes
Kachel-HTML seit jeher ausliefert (siehe `custom_tile` in
[Abschnitt 7](#7-visualisierung)) - nur mit einer strengeren Herkunftsprüfung.

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

`string SLOC_TranslateText(integer $InstanzID, integer $ObjektID);`
Liefert den Inhalt der "Eigene Texte"-Zeile für die angegebene Objekt-ID
(die String-Variable im Root-Baum) in der aktuell aktiven Sprache
(Fallback: Quelltext), z. B. für Popup-Inhalte in eigenen HTMLBox-Skripten.

Beispiel:
`SLOC_TranslateText(12345, 67890);`

`void SLOC_Rescan(integer $InstanzID);`
Liest den konfigurierten Root der Visualisierung neu ein und übersetzt neu
gefundene oder noch unübersetzte Einträge. Entspricht dem Button
"Visualisierung neu einlesen" im Modul-Formular.

Beispiel:
`SLOC_Rescan(12345);`

`string SLOC_TranslateExternalText(integer $InstanzID, string $Text, string $Quellsprache = "");`
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
`SLOC_TranslateExternalText(12345, 'Guten Tag');`

Beispiel (abweichende, explizit angegebene Quellsprache):
`SLOC_TranslateExternalText(12345, 'Good day', 'en');`

`string SLOC_GetCurrentLanguageCode(integer $InstanzID);`
Liefert den aktuell aktiven Sprachcode dieser Instanz (z. B. `"en"`) - 
nützlich, um eigene Inhalte nur bei einem tatsächlichen
Sprachwechsel neu aufzubauen, statt bei jedem Rendern blind zu übersetzen.

Beispiel:
`SLOC_GetCurrentLanguageCode(12345);`

`string SLOC_GetAvailableLanguages(integer $InstanzID);`
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
`SLOC_GetAvailableLanguages(12345);`

`void SLOC_SetLanguage(integer $InstanzID, string $Sprachcode);`
Pro-Feature `custom_tile` - **wirft eine Exception ohne dieses Feature**:
wechselt die aktive Sprache von außen, mit derselben Logik wie ein Klick im
eingebauten Dropdown (Testphase-/Rate-Limit-Prüfung inklusive) - für eine
komplett eigenständige, selbstgebaute Sprachauswahl-Kachel.

Beispiel:
`SLOC_SetLanguage(12345, 'en');`

### 10. Integration für Modulentwickler

Liefert dein eigenes Modul eine eigene HTML-Kachel aus
(via `GetVisualizationTile()`), lässt sich dessen Text-Inhalt live in die
gerade aktive Sprache einer Visualisierung mit Simple-Locale-Instanz übersetzen - ganz
ohne eigenen Google-Account, da `SLOC_TranslateExternalText()` den
Google-API-Key der jeweiligen Simple-Locale-Instanz mitverwendet.

Da die meisten Nutzer (noch) keine Simple-Locale-Instanz installiert haben,
sollte der Aufruf immer defensiv erfolgen - mit `function_exists()` und
einer eigenen Suche nach einer passenden Instanz, statt die Instanz-ID fest
zu verdrahten:

```php
private function TranslateViaSimpleLocale(string $Text, string $SourceLanguage): string
{
    if (!function_exists('SLOC_TranslateExternalText')) {
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
    return SLOC_TranslateExternalText($instanceIDs[0], $Text, $SourceLanguage);
}
```

Am besten bei jedem Aufruf von `GetVisualizationTile()` aufgerufen - dort
gibt es (anders als bei Variablen-Werten) kein Caching-/Veraltungsproblem,
da die Kachel ohnehin bei jedem Aufruf neu gerendert wird.

### 11. Change-Log

Der vollstaendige Change-Log steht in einer eigenen Datei:
**[CHANGELOG.md](CHANGELOG.md)** - alle Builds, neueste zuerst.

Er ist bewusst ausgelagert: mit inzwischen 145 Eintraegen war er laenger
als die eigentliche Dokumentation und hat sie in dieser Datei erdrueckt.
