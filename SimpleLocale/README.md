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
  innerhalb des Root der Visualisierung, per `IPS_SetName`.
* Übersetzt zusätzlich den Wert aller String-Variablen innerhalb dieses
  Root der Visualisierung (z. B. Hinweis-/Popup-Texte, auch komplette HTMLBox-Widgets),
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
  "Eigene Texte" verfolgten String-Variablen vornehmen (z. B. ein Wetter-
  oder Messwert-Modul, das seinen Text bei jeder Aktualisierung selbst neu
  schreibt): der neue Wert wird automatisch als frischer Rohtext übernommen
  und sofort in die aktuell aktive Sprache nachübersetzt - ganz ohne
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
  Zwei Anwender, die gleichzeitig dieselbe Visualisierung öffnen, sehen daher
  immer dieselbe Sprache - es gibt keine getrennte Sprache je Person. Werden
  wirklich gleichzeitig unterschiedliche Sprachen für unterschiedliche
  Zielgruppen benötigt, braucht es mehrere Instanzen mit jeweils eigenem
  Root der Visualisierung/eigener Kachel.
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
  neben dem Dropdown einsehbar, live in der jeweils aktiven Sprache.
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
Kachel-Visualisierung           | Instanz der eingebauten Kachel-Visualisierung (WebFront-Kernmodul, nicht Teil von Simple Locale). **Pflichtfeld** - ihre eigene "Startkategorie" wird automatisch als Root der Visualisierung übernommen (Kategorie im Objektbaum, deren Inhalt - Namen + Werte von String-Variablen - übersetzt wird; sollte nur die Sichtbereich-Kacheln enthalten, nicht die Admin-Oberfläche) und sie liefert zusätzlich die Automations/Favoriten-Übersetzung, siehe eigenen Absatz unten. Ohne Auswahl (oder ohne gültige Startkategorie) bleibt die Instanz im Status "Root der Visualisierung fehlt".
Basissprache                    | Sprache, in der die Objektnamen/-werte ursprünglich gepflegt sind. Dient Google Translate als feste Quellsprache für alle Zielsprachen-Übersetzungen; die Basissprache selbst hat keine eigene Übersetzungsspalte, siehe [Abschnitt 7](#7-visualisierung).
Aktuell aktive Sprache          | Welche Sprache gerade angezeigt wird - normalerweise über die Kachel vom Anwender selbst gesteuert (siehe Abschnitt 7), lässt sich hier aber auch manuell zu Testzwecken umschalten.
Weltkugel-Symbol in der Kachel anzeigen | Blendet das 🌐-Symbol links neben dem Dropdown aus, falls nicht gewünscht (z. B. bei eigenem Kachel-Design). Standardmäßig an.
Info-Symbol in der Kachel anzeigen | Blendet das ⓘ-Symbol (Erklärung der Einschränkungen, siehe Abschnitt 2) aus. Standardmäßig an.
Eigene Sprachauswahl-Kachel verwenden | Unterdrückt die eingebaute Dropdown-Kachel zugunsten einer selbstgebauten (siehe Abschnitt 7). **Pro-Feature** (`custom_tile`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und die eingebaute Kachel aktiv.
Übersetzungsanbieter (Panel)    | Siehe eigenen Abschnitt unten ("Übersetzungsanbieter: Google/DeepL/kostenfrei") - funktioniert ab Werk ohne jede Eingabe.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen der Visualisierung, 0 = nur manuell über den Button "Visualisierung neu einlesen" (siehe unten). **Pro-Feature** (`auto_rescan`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und der Timer aus, der manuelle Rescan-Button bleibt aber in jeder Edition nutzbar.

**Übersetzungsanbieter: Google/DeepL/kostenfrei**

Simple Locale funktioniert **ab Werk ohne jede Konfiguration**: Ganz ohne eingetragenen API-Key läuft die Übersetzung sofort über einen kostenfreien, account-losen Anbieter ([MyMemory](https://mymemory.translated.net)). Google Cloud Translate und DeepL sind beide **optional** und lassen sich unabhängig voneinander zusätzlich eintragen (eigene Panels "Übersetzungsanbieter" im Konfigurationsformular):

- **Nur kostenfrei** (Auslieferungszustand): 5.000 Zeichen/Tag anonym, mit hinterlegter Kontakt-E-Mail (Feld "Kontakt-E-Mail für den kostenfreien Anbieter") 50.000 Zeichen/Tag. Kein Konto, kein Key.
- **Google und/oder DeepL zusätzlich eingetragen, volle Verkettung** (Pro-Feature `paid_providers`, siehe [Abschnitt 8](#8-lizenz-und-testversion)): bezahlte Anbieter werden VOR dem kostenfreien versucht (Reihenfolge über "Bevorzugter Anbieter" wählbar, falls beide eingetragen sind), beide bezahlten Anbieter kombiniert, falls beide konfiguriert - deutlich großzügigere Freikontingente/Qualität, aber jeweils ein eigenes Konto samt API-Key nötig.
- **Google und/oder DeepL eingetragen, ohne `paid_providers`** (z. B. "Light"-Edition): der kostenfreie Anbieter bleibt immer die primäre Grundausstattung, zusätzlich darf höchstens EIN einzelner bezahlter Anbieter als Rückfall danach greifen (nie beide gleichzeitig verkettet) - welcher, entscheidet "Bevorzugter Anbieter", falls beide eingetragen sind.
- **Ausfallsicher durch Verkettung**: Schlägt ein Anbieter fehl (Tageskontingent erschöpft, Preismodell geändert, Key abgelaufen, Netzwerkfehler), übernimmt automatisch der nächste in der jeweiligen Kette - der kostenfreie Anbieter steht dabei immer als garantiert verfügbares Glied bereit (mit `paid_providers` am Ende, ohne dieses Feature am Anfang). Die Übersetzung funktioniert dadurch strukturell auch dann noch, wenn Google/DeepL komplett ausfallen oder ihr Preismodell ändern sollten.

Ein Anbieterwechsel (z. B. Google zu DeepL) macht bereits gewählte Zielsprachen ungültig, wenn die neue Sprachliste andere Codes verwendet (Google: klein geschrieben "de"/"en", DeepL: teils groß geschrieben mit Regionsvariante "DE"/"EN-GB") - betroffene Zielsprachen müssen dann neu ausgewählt werden. Der kostenfreie Anbieter hat keinen eigenen Sprachlisten-Endpunkt und nutzt eine eingebaute, rund 25 Sprachen umfassende statische Liste.

Bewusst außerhalb von v1: Google und DeepL gleichzeitig für dieselbe Übersetzung anzufragen, um ihre Freikontingente zu kombinieren - die Kette probiert Anbieter nacheinander, nicht parallel. Der kostenfreie Anbieter (MyMemory) unterstützt zudem keinen Batch-Aufruf (ein Text pro Request, siehe `TranslateChunkFree`) und lehnt einzelne Texte über 500 Byte ab (z. B. vollständige HTMLBox-Widgets als "Eigene Texte") - solche Texte scheitern über diesen Anbieter bewusst früh und werden von der Kette an einen bezahlten Anbieter ohne diese Begrenzung weitergereicht, sofern einer konfiguriert ist.

**Übersetzung:**

Name                            | Beschreibung
-------------------------------- | ------------------
Zielsprachen                    | Sprachen, in die übersetzt werden soll. Auswahl-Optionen kommen ab Werk aus der eingebauten Liste, mit konfiguriertem Google-/DeepL-Key aus deren dynamisch geladener Liste (siehe oben). Ausgegraut, wenn ein bezahlter Anbieter konfiguriert ist, aber noch keine Liste laden konnte, oder wenn das Sprachlimit einer "Spezialversion"-Lizenz erreicht ist (siehe Abschnitt 8). Wichtig: Nach dem Klick auf "Sprachliste aktualisieren" die Instanzkonfiguration einmal schließen und neu öffnen, bevor Häkchen gesetzt werden - sonst kann die Konsole falsche Sprachen speichern.
Objektnamen / Eigene Texte / Beschriftungen | Listen der gefundenen Objekte mit Quelltext und je einer Spalte pro Zielsprache. Übersetzungen sind hier direkt editierbar; leere Zellen werden beim nächsten Rescan automatisch übersetzt. "Beschriftungen" siehe [Abschnitt 2](#2-bekannte-einschränkungen) (Fork-Mechanismus).
Automations                     | Liste der gefundenen Automation-Einträge der oben unter "Kachel-Visualisierung" gewählten Instanz mit Quelltext und je einer Spalte pro Zielsprache - funktioniert genauso wie Objektnamen.
Begrüßung                       | Übersetzt den Begrüßungstext der Kachel-Visualisierung, unabhängig davon, ob "Show Greeting" gerade "Automatic"/"Static" (freier Text, Feld "Name"/Property `GreetingName`) oder "Variable" (Live-Wert einer String-Variable) ist - beide landen in derselben einen Zeile hier, siehe eigenen Absatz unten. Ein Hinweistext direkt über der Liste zeigt an, welcher Modus gerade aktiv ist. Bei "Show Greeting" = "None" bleibt die Liste leer.

**Wann sollte ein Rescan ausgeführt werden?**

Inhaltstyp        | Neue/verschobene Objekte | Inhaltliche Änderungen
------------------ | ------------------------- | ------------------------
Objektnamen        | Nur per Rescan (manuell/Timer) erkannt. | Ändert sich ein Name selten spontan; falls doch, Zelle im Formular leeren + Rescan.
Eigene Texte (Werte) | Nur per Rescan erkannt. | Automatisch (siehe Abschnitt 1, `VM_UPDATE`) - **kein** Rescan nötig, solange die Basissprache stimmt.
Begrüßung           | Nur per Rescan erkannt (auch ein Moduswechsel zwischen "Automatic"/"Static"/"Variable"). | Modus "Variable": automatisch, genau wie Eigene Texte (Werte). Modi "Automatic"/"Static" (freier Text im Feld "Name"): nur per Rescan.
Beschriftungen      | Nur per Rescan erkannt. | **Kein** automatisches Erkennen von Änderungen am zugrunde liegenden Profil/Template - Symcon liefert dafür keine Update-Benachrichtigung. Ändert ein anderes Modul/der Admin die Beschriftungen eines Profils, das eine bereits geforkte Variable nutzt, wird das erst nach manuellem Löschen der betroffenen Original-Import-Zelle + Rescan übernommen.

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

1. **Eigener HTML-Code für die eingebaute Kachel (empfohlen für die meisten
   Fälle).** Aktiviere dazu "Eigene Sprachauswahl-Kachel verwenden" im
   Konfigurationsformular - darunter erscheint der Button "Eigenen
   Kachel-HTML-Code bearbeiten" (nur sichtbar, solange die Checkbox aktiv
   ist, damit das Formular sonst nicht unnötig überladen wirkt). Er
   erscheint nicht sofort beim Setzen des Häkchens, sondern erst nach einem
   Klick auf "Übernehmen" (die Konsole baut das Formular dabei live neu auf -
   ein Schließen/Neuöffnen der Instanzkonfiguration ist hierfür anders als
   beim Sprachlisten-Hinweis weiter oben nicht nötig). Der Button öffnet ein
   Bearbeiten-Fenster mit dem HTML-Code, vorbefüllt mit einer
   1:1-Kopie der eingebauten `module.html`. **Diese Instanz liefert die
   Kachel weiterhin selbst aus**
   (`GetVisualizationTile()`) - nur eben mit dem editierten HTML/CSS/JS statt
   der eingebauten Optik. Der Code muss zwei Platzhalter enthalten, die bei
   jedem Laden der Kachel automatisch ersetzt werden:
   - `<!--WRAPPER_ID-->` - eine pro Instanz eindeutige DOM-ID, verhindert
     ID-Kollisionen, falls mehrere Kacheln im selben DOM landen. Kommt in der
     Standardvorlage **zweimal** vor (als `id`-Attribut des Wrapper-`<div>`
     UND im `getElementById(...)`-Aufruf im `<script>`) - beide Stellen
     müssen exakt gleich bleiben (einfach den Platzhalter selbst nicht
     anfassen, dann passt das automatisch).
   - `<!--LANGUAGE_SELECT-->` - wird durch den fertig gerenderten
     Dropdown-Block ersetzt (Weltkugel-/Info-Symbol je nach Einstellung, das
     `<select>` mit allen wählbaren Sprachen, Testphase-Hinweis) - exakt
     derselbe Inhalt wie in der eingebauten Kachel. Wer nur das Aussehen
     (CSS) ändern will, lässt diesen Platzhalter unangetastet; wer eine
     komplett andere Bedienung bauen will (z. B. Buttons statt Dropdown),
     ersetzt ihn durch eigenes Markup - dieses muss dann selbst
     `requestAction('Language', '<Sprachcode>')` aufrufen (die von Symcon in
     jede Kachel injizierte JS-Funktion), um einen Sprachwechsel auszulösen.

   Zusätzlich ruft Symcon bei jeder `UpdateVisualizationValue()`-Aktualisierung
   (siehe `PushVisualizationUpdate()`/`PushTrialExpiredAlert()`) eine globale
   JS-Funktion `handleMessage(data)` in der Kachel auf - die Standardvorlage
   definiert sie bereits (verarbeitet `{"action":"REFRESH", ...}` fürs
   Live-Nachziehen eines Sprachwechsels in anderen offenen Tabs/Geräten sowie
   `{"action":"ALERT", ...}` für den Testphase-abgelaufen-Hinweis). Wird diese
   Funktion entfernt oder umbenannt, funktioniert die Kachel beim ersten Laden
   weiterhin normal - nur diese beiden Live-Aktualisierungen bleiben dann
   stumm, ohne Fehlermeldung. Wird das Feld im Bearbeiten-Fenster komplett
   geleert, greift automatisch derselbe eingebaute HTML-Code wie ohne
   aktiviertes Feature (kein Absturz, keine leere Kachel).

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
  zur bereits aktiven Sprache oder zurück zur Basissprache/Original zählt nie
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
konfigurierte Basissprache; nur bei abweichender Fremdtext-Sprache explizit
angeben. Leerer Text, Quellsprache = aktive Sprache, oder eine wegen
abgelaufener Testphase gerade nicht kostenfreie Sprache liefern den Text
unverändert zurück - nie ein Fehler.

Beispiel (Basissprache dieser Instanz, z. B. Deutsch):
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
