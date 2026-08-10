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

### 1. Funktionsumfang

* Übersetzt live die Namen aller Objekte (Kategorien, Variablen, Kacheln, Links)
  innerhalb einer frei wählbaren Root-Kategorie, per `IPS_SetName`.
* Übersetzt zusätzlich den Wert aller String-Variablen innerhalb dieser
  Root-Kategorie (z. B. Hinweis-/Popup-Texte, auch komplette HTMLBox-Widgets),
  per `SetValueString`. `<style>`- und `<script>`-Blöcke innerhalb solcher
  Werte werden dabei nie an Google geschickt und bleiben beim Übersetzen
  unverändert (verhindert kaputtes CSS/JS durch mitübersetzte Eigenschaften).
* Automatische Übersetzung über die Google Cloud Translate API, inkl.
  persistentem Cache – Google wird nur für neue oder noch unübersetzte Einträge
  aufgerufen, nie für bereits vorhandene (auch manuell korrigierte) Werte.
* Übersetzungen sind direkt im Modul-Formular überprüf- und korrigierbar.
* Der Objektbaum wird manuell (Button) oder automatisch (Timer-Intervall)
  erneut eingelesen; dabei werden nur neue Objekte/Sprachen ergänzt, nichts
  wird überschrieben oder gelöscht. Objekte werden über ihre Objekt-ID
  identifiziert - ein gesetzter Ident ist nicht erforderlich.
* Ein Rescan prüft vor jeder Übersetzung, ob alle Objekte im Root-Baum einen
  echten Namen haben, und bricht andernfalls komplett ab (siehe
  [Abschnitt 2](#2-bekannte-einschränkungen)) - verhindert leere
  Beschriftungen in der Gäste-Visualisierung.
* Die Instanz selbst bietet eine eigene, schlanke Kachel für die Visualisierung
  (natives HTML-`<select>`-Dropdown statt Symcons Standard-Buttonliste) - dazu
  einfach die Instanz per Drag & Drop in WebFront platzieren, keine zusätzliche
  Variable nötig. Die Sprachnamen im Dropdown (inkl. kleiner Flagge und Google-
  Sprachcode, z. B. "🇬🇧 English - en") werden live in die gerade aktive Sprache
  übersetzt, damit dort nie mehrere Sprachen gemischt angezeigt werden. Ein
  Info-Symbol (ⓘ) neben dem Dropdown erklärt Gästen auf Klick (ebenfalls live
  übersetzt) die wichtigsten Einschränkungen, siehe
  [Abschnitt 2](#2-bekannte-einschränkungen).
* Die aktuell aktive Sprache ist eine reine Instanz-Property (kein Symcon-
  Variablenprofil, das wäre global über alle Instanzen hinweg geteilt) - sie
  ist direkt im Konfigurationsformular sicht- und änderbar.
* Reagiert live auf Wertänderungen, die *andere* Module/Skripte an den in
  "Eigene Texte" verfolgten String-Variablen vornehmen (z. B. ein Wetter-
  oder Messwert-Modul, das seinen Text bei jeder Aktualisierung selbst neu
  schreibt): der neue Wert wird automatisch als frischer Rohtext übernommen
  und sofort in die aktuell aktive Gast-Sprache nachübersetzt - ganz ohne
  Zutun des anderen Modulentwicklers. Technisch über Symcons
  VariableManager-Update-Nachrichten (`VM_UPDATE`), siehe
  [Abschnitt 2](#2-bekannte-einschränkungen) für die eine Voraussetzung
  dabei.
* Übersetzt zusätzlich die Beschriftungen von Variablen mit einer
  Wert-Aufzählung (z. B. Integer-Variablen mit klassischem Profil oder
  moderner Enumeration-Presentation, etwa "Abwesend/Anwesend" oder
  "Aktiv/Inaktiv") - unabhängig davon, ob diese Beschriftungen aus einem
  ggf. installationsweit **geteilten** Profil/Template stammen. Das
  zugrunde liegende Profil/Template selbst wird dabei **nie** verändert,
  siehe den Fork-Mechanismus in
  [Abschnitt 2](#2-bekannte-einschränkungen).

### 2. Bekannte Einschränkungen

* **Eine Sprache pro Instanz, nicht pro Besucher.** Die aktuell aktive Sprache
  ist ein Zustand der Instanz, kein Zustand der einzelnen Browser-Sitzung.
  Zwei Gäste, die gleichzeitig dieselbe Visualisierung öffnen, sehen daher
  immer dieselbe Sprache - es gibt keine getrennte Sprache je Person. Werden
  wirklich gleichzeitig unterschiedliche Sprachen für unterschiedliche
  Zielgruppen benötigt, braucht es mehrere Instanzen mit jeweils eigener
  Root-Kategorie/Kachel.
* **Dynamisch aktualisierte Inhalte werden automatisch nachübersetzt -
  vorausgesetzt, sie werden in der konfigurierten Basissprache geschrieben.**
  Schreibt ein *anderes* Modul oder Skript den Wert einer verfolgten
  String-Variable neu (z. B. ein Wetter-Skript bei seinem nächsten
  Aktualisierungsintervall), übersetzt Simple Locale live nach (siehe
  [Abschnitt 1](#1-funktionsumfang)) - dabei wird angenommen, dass der neue
  Wert in der Basissprache verfasst ist, genau wie beim ursprünglichen Scan.
  Schreibt das fremde Modul tatsächlich in einer anderen Sprache, fällt die
  automatische Übersetzung entsprechend falsch aus (wie bei jeder
  automatischen Übersetzung, siehe unten) - Basissprache in der
  Modul-Konfiguration also passend zur tatsächlich verwendeten Sprache des
  überwachten Moduls wählen. Beobachtet werden ausschließlich die Variablen
  unter "Eigene Texte" - Objektnamen ändern sich durch Fremdzugriffe
  praktisch nie und werden daher nicht überwacht.

  Dieser Punkt ist auch direkt in der Kachel über das Info-Symbol (ⓘ)
  neben dem Dropdown einsehbar, live in der jeweils aktiven Gast-Sprache.
* **Die automatische Übersetzung kann trotzdem Fehler machen.** Google
  Translate liefert nicht immer eine passende Übersetzung. (Ein früherer,
  strukturell inzwischen ausgeschlossener Fall: Google erkannte bei der
  kurzen Basissprachen-Bereinigung ohne feste Quellsprache "Haus" fälschlich
  als Hmong und lieferte "Trinken" - deshalb wird die Quellsprache inzwischen
  immer fest vorgegeben, siehe [Abschnitt 7](#7-visualisierung).) **Alle
  Übersetzungen in "Objektnamen" und "Eigene Texte" daher nach dem ersten
  Rescan einmal durchsehen** und falsch übersetzte Zellen manuell
  korrigieren - eigene Korrekturen werden nie automatisch überschrieben
  (siehe Abschnitt 5). Soll eine bereits gefüllte Zelle stattdessen neu von
  Google übersetzt werden: Zelleninhalt löschen, "Übernehmen" klicken, dann
  erneut Rescan ausführen - nur leere Zellen werden dabei (neu) übersetzt.
* **Alle Objekte im Root-Baum brauchen einen echten Namen.** Ein Rescan
  bricht komplett ab (keine Übersetzung, kein Speichern), sobald er ein
  Objekt ohne Namen findet (leerer Name, oder von Symcon selbst vergebener
  Platzhalter wie "Unnamed Object (ID: ...)"/"Unbenanntes Objekt (ID: ...)").
  Die betroffenen Objekt-IDs samt Pfad erscheinen dann als Liste im
  Konfigurationsformular - erst benennen, dann erneut "Baum neu einlesen"
  klicken.
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
  ist geteilt). Beim Zurückwechseln auf die Basissprache wird der Fork **pro
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

### 3. Voraussetzungen

- Symcon ab Version 7.1
- Für die Übersetzung von Beschriftungen (Abschnitt 1/2): Symcon ab Version
  8.0 (Variable Presentations). Auf älteren Versionen bleibt dieser
  Teilbereich einfach komplett inaktiv (kein Fehler) - Objektnamen und
  Eigene Texte funktionieren unabhängig davon bereits ab 7.1.
- Alle Objekte innerhalb der konfigurierten Root-Kategorie müssen einen
  echten Namen haben (kein leerer Name, kein von Symcon selbst vergebener
  Platzhalter wie "Unnamed Object (ID: ...)") - siehe
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
Root-Kategorie                  | Kategorie im Objektbaum, deren Inhalt (Namen + Werte von String-Variablen) übersetzt wird. Sollte nur die Gäste-Sichtbereich-Kacheln enthalten, nicht die Admin-Oberfläche.
Basissprache                    | Sprache, in der die Objektnamen/-werte ursprünglich gepflegt sind. Dient Google Translate als feste Quellsprache für alle Zielsprachen-Übersetzungen; die Basissprache selbst hat keine eigene Übersetzungsspalte, siehe [Abschnitt 7](#7-visualisierung).
Aktuell aktive Sprache          | Welche Sprache gerade angezeigt wird - normalerweise über die Kachel vom Gast selbst gesteuert (siehe Abschnitt 7), lässt sich hier aber auch manuell zu Testzwecken umschalten.
Weltkugel-Symbol in der Kachel anzeigen | Blendet das 🌐-Symbol links neben dem Dropdown aus, falls nicht gewünscht (z. B. bei eigenem Kachel-Design). Standardmäßig an.
Info-Symbol in der Kachel anzeigen | Blendet das ⓘ-Symbol (Erklärung der Einschränkungen, siehe Abschnitt 2) aus. Standardmäßig an.
Google Cloud Translate API-Key  | API-Key für die Cloud Translation API v2. **Muss zuerst eingetragen und über "Übernehmen" gespeichert werden**, bevor irgendetwas anderes funktioniert - insbesondere ist der "Hinzufügen"-Button bei den Zielsprachen bis dahin ausgegraut (nicht versteckt, sondern deaktiviert), da ohne gültigen Key keine echte Sprachliste von Google geladen werden kann.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen des Baums, 0 = nur manuell über den Button "Baum neu einlesen" (siehe unten).

**Übersetzung:**

Name                            | Beschreibung
-------------------------------- | ------------------
Zielsprachen                    | Sprachen, in die übersetzt werden soll. Auswahl-Optionen kommen von Google, sobald ein gültiger API-Key gespeichert ist. Ausgegraut, sobald entweder noch kein API-Key gespeichert ist oder das Sprachlimit einer "Spezialversion"-Lizenz erreicht ist (siehe Abschnitt 8). Wichtig: Nach dem Klick auf "Sprachliste aktualisieren" die Instanzkonfiguration einmal schließen und neu öffnen, bevor Häkchen gesetzt werden - sonst kann die Konsole falsche Sprachen speichern.
Objektnamen / Eigene Texte / Beschriftungen | Listen der gefundenen Objekte mit Quelltext und je einer Spalte pro Zielsprache. Übersetzungen sind hier direkt editierbar; leere Zellen werden beim nächsten Rescan automatisch übersetzt. "Beschriftungen" siehe [Abschnitt 2](#2-bekannte-einschränkungen) (Fork-Mechanismus).

**Wann sollte ein Rescan ausgeführt werden?**

Inhaltstyp        | Neue/verschobene Objekte | Inhaltliche Änderungen
------------------ | ------------------------- | ------------------------
Objektnamen        | Nur per Rescan (manuell/Timer) erkannt. | Ändert sich ein Name selten spontan; falls doch, Zelle im Formular leeren + Rescan.
Eigene Texte (Werte) | Nur per Rescan erkannt. | Automatisch (siehe Abschnitt 1, `VM_UPDATE`) - **kein** Rescan nötig, solange die Basissprache stimmt.
Beschriftungen      | Nur per Rescan erkannt. | **Kein** automatisches Erkennen von Änderungen am zugrunde liegenden Profil/Template - Symcon liefert dafür keine Update-Benachrichtigung. Ändert ein anderes Modul/der Admin die Beschriftungen eines Profils, das eine bereits geforkte Variable nutzt, wird das erst nach manuellem Löschen der betroffenen Original-Import-Zelle + Rescan übernommen.

**Lizenz:**

Name                            | Beschreibung
-------------------------------- | ------------------
Lizenzschlüssel                 | Nur in der Testversion sichtbar/relevant, siehe [Abschnitt 8](#8-lizenz-und-testversion). Nach Eingabe über den Button "Lizenz aktivieren" (siehe unten) prüfen lassen.

Unterhalb der drei Bereiche liegen die Aktions-Buttons "Lizenz aktivieren" und
"Baum neu einlesen und fehlende Übersetzungen ergänzen" sowie, ganz unten,
der aufklappbare Bereich "Produktinformationen" mit einem Link zum
GitHub-Repository und kurzen Lizenzhinweisen.

### 6. Statusvariablen und Profile

Die Statuskategorien werden automatisch angelegt. Das Löschen kann zu
Fehlfunktionen führen. Simple Locale legt bewusst keine eigenen Symcon-
Variablen oder -Profile für die Sprachsteuerung an (siehe Abschnitt 7) - der
gesamte Zustand steckt in Instanz-Properties.

### 7. Visualisierung

Die Simple-Locale-Instanz selbst per Drag & Drop in WebFront platzieren (bei
der Kachel-Auswahl nicht eine Variable, sondern die Instanz selbst auswählen)
- sie liefert eine eigene, kompakte Kachel mit `<select>`-Dropdown
(Weltkugel-Symbol statt Text-Label "Sprache", damit keine Sprachen gemischt
angezeigt werden) und löst beim Auswählen direkt den Sprachwechsel aus. Die
aktuell aktive Sprache wird als Instanz-Property gespeichert (kein
Symcon-Variablenprofil - das wäre global über alle Instanzen der Installation
hinweg geteilt und würde sich bei mehreren Instanzen gegenseitig überschreiben).

Das Dropdown bietet immer folgende Sprachen zur Auswahl: die Basissprache und
alle konfigurierten Zielsprachen (Flagge, live übersetzter Name, Google-Code,
z. B. "🇬🇧 English - en"). Intern gibt es zusätzlich die Pseudo-Sprache
"Original" (liefert den rohen, unangetasteten Text, exakt so wie er im
Objektbaum vorgefunden wurde, Tippfehler inklusive) - im Dropdown erscheint
dafür aber **kein separater Eintrag** "Original (unbearbeitet)": Google Cloud
Translate lehnt eine Übersetzung von einer Sprache in sich selbst ohnehin ab
(HTTP 400 "Bad language pair"), es gibt für die Basissprache also gar keine
eigene, separat übersetzte Spalte - ihr Inhalt ist identisch mit dem
Rohtext. Der Basissprache-Eintrag im Dropdown liefert deshalb technisch
"Original", zeigt aber ganz normal die Basissprache selbst an (z. B.
"🇩🇪 Deutsch - de") statt einer eigenen "Original"-Beschriftung.

Die Einträge sind alphabetisch nach dem angezeigten Namen sortiert (nicht
nach Sprachcode), und zwar nach den Sortierregeln der jeweils aktiven
Gast-Sprache (z. B. korrekte Einordnung von Umlauten/Akzenten) - dafür wird,
falls auf dem Symcon-Server installiert, PHPs `intl`-Erweiterung (Klasse
`Collator`) genutzt; ist sie nicht verfügbar, greift eine einfache
alphabetische Sortierung ohne sprachspezifische Sonderregeln.

Ein Info-Symbol (ⓘ) neben dem Dropdown öffnet auf Klick einen nativen
Browser-Dialog (`alert()`) mit den in [Abschnitt 2](#2-bekannte-einschränkungen)
beschriebenen Einschränkungen, ebenfalls live in der jeweils aktiven
Gast-Sprache. Bewusst kein eigenes HTML-Popup: die Kachel läuft in einem
eigenen iframe und eigene Overlays können dessen Grenzen nicht überschreiten -
ein Browser-Dialog dagegen schon.

Für eigene HTMLBox-Popups oder Hinweise außerhalb der live umbenannten
Objekte liefert `IPSSL_TranslateText()` den Text in der aktuell aktiven
Sprache.

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
  Gast trotzdem, über das Dropdown die Sprache zu wechseln, bleibt es beim
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

**Erkennung von Lizenzmissbrauch:** Bei jeder Aktivierung (Button "Lizenz
aktivieren" oder einfach nur "Übernehmen" mit einem neuen gültigen Schlüssel)
wird lokal protokolliert, welcher `IPS_GetLicensee()` (die beim Kauf von
Symcon selbst hinterlegte E-Mail-Adresse, eindeutig pro Symcon-Installation)
mit welchem Lizenzschlüssel aktiviert wurde. Taucht derselbe Schlüssel mit
mehreren unterschiedlichen Licensee-Adressen auf, ist das ein Hinweis auf
Weiterverkauf/Weitergabe (z. B. ein Schlüssel, der als "gebraucht" mehrfach
bei Ebay verkauft wird). Die Konstante `LICENSE_ACTIVATION_REPORT_URL` in
`module.php` ist aktuell ein leerer Platzhalter - ohne echte URL bleibt es
bei der rein lokalen Protokollierung (Debug-Log der Instanz); erst mit einer
echten Meldeserver-URL wird jede Aktivierung zusätzlich dorthin gemeldet
(Lizenzschlüssel-Hash statt Klartext-Schlüssel, plus Licensee und Zeitpunkt).
**Wichtig:** `IPS_GetLicensee()` liefert eine echte, personenbezogene
E-Mail-Adresse - diese Erhebung/Übermittlung gehört vor dem Eintragen einer
echten Meldeserver-URL in die eigenen Lizenzbedingungen/Datenschutzhinweise.

Für Entwickler: Ob ein Build die Testversion-Einschränkungen überhaupt
anwendet, steuert die Konstante `IS_TRIAL_BUILD` in `module.php` - für einen
Vollversion-Build (z. B. an zahlende Kunden nach Kauf) dort auf `false`
setzen, dann entfallen Sprach- und Zeitbeschränkung unabhängig vom
Lizenzschlüssel. Die Konstante `LICENSE_SIGNING_SECRET` in derselben Datei
ist aktuell ein Platzhalter und muss vor jedem echten Release durch ein
eigenes, geheimes Signatur-Secret ersetzt werden - mit dem Platzhalter lässt
sich kein echter, gültig signierter Lizenzschlüssel erzeugen.

### 9. PHP-Befehlsreferenz

`string IPSSL_TranslateText(integer $InstanzID, integer $ObjektID);`
Liefert den Inhalt der "Eigene Texte"-Zeile für die angegebene Objekt-ID
(die String-Variable im Root-Baum) in der aktuell aktiven Sprache
(Fallback: Quelltext), z. B. für Popup-Inhalte in eigenen HTMLBox-Skripten.

Beispiel:
`IPSSL_TranslateText(12345, 67890);`

`void IPSSL_Rescan(integer $InstanzID);`
Liest die konfigurierte Root-Kategorie neu ein und übersetzt neu gefundene
oder noch unübersetzte Einträge. Entspricht dem Button "Baum jetzt neu
einlesen" im Modul-Formular.

Beispiel:
`IPSSL_Rescan(12345);`

`string IPSSL_TranslateExternalText(integer $InstanzID, string $Text, string $Quellsprache);`
Übersetzt beliebigen Text live in die aktuell aktive Gast-Sprache dieser
Instanz - für Modulentwickler, deren eigenes Modul eine eigene HTML-Kachel
ausliefert (`GetVisualizationTile()`) statt Text in einer von Simple Locale
beobachtbaren Variable zu halten, siehe
[Abschnitt 10](#10-integration-für-modulentwickler). Leerer Text,
Quellsprache = aktive Sprache, oder eine wegen abgelaufener Testphase
gerade nicht kostenfreie Sprache liefern den Text unverändert zurück -
nie ein Fehler.

Beispiel:
`IPSSL_TranslateExternalText(12345, 'Guten Tag', 'de');`

`string IPSSL_GetCurrentLanguageCode(integer $InstanzID);`
Liefert den aktuell aktiven Gast-Sprachcode dieser Instanz (z. B. `"en"`) -
immer ein echter Sprachcode, nie die interne Pseudo-Sprache
`"ORIGINAL_IMPORT"`. Nützlich, um eigene Inhalte nur bei einem tatsächlichen
Sprachwechsel neu aufzubauen, statt bei jedem Rendern blind zu übersetzen.

Beispiel:
`IPSSL_GetCurrentLanguageCode(12345);`
### 10. Integration für Modulentwickler

Liefert dein eigenes Modul eine eigene HTML-Kachel aus
(`GetVisualizationTile()`), lässt sich dessen Text-Inhalt live in die
gerade aktive Gast-Sprache einer Simple-Locale-Instanz übersetzen - ganz
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
