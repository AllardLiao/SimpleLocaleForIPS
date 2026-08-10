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
8. [PHP-Befehlsreferenz](#8-php-befehlsreferenz)

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

### 2. Bekannte Einschränkungen

* **Eine Sprache pro Instanz, nicht pro Besucher.** Die aktuell aktive Sprache
  ist ein Zustand der Instanz, kein Zustand der einzelnen Browser-Sitzung.
  Zwei Gäste, die gleichzeitig dieselbe Visualisierung öffnen, sehen daher
  immer dieselbe Sprache - es gibt keine getrennte Sprache je Person. Werden
  wirklich gleichzeitig unterschiedliche Sprachen für unterschiedliche
  Zielgruppen benötigt, braucht es mehrere Instanzen mit jeweils eigener
  Root-Kategorie/Kachel.
* **Dynamisch aktualisierte Inhalte fallen zurück in ihre Originalsprache.**
  Simple Locale übersetzt den Wert einer String-Variable, wenn die Sprache
  gewechselt wird. Schreibt ein *anderes* Modul oder Skript diese Variable
  danach selbst erneut (z. B. ein Wetter- oder Messwert-Skript bei seinem
  nächsten Aktualisierungsintervall), steht dort wieder der von diesem Modul/
  Skript gelieferte Text - typischerweise die Sprache, in der es selbst
  schreibt, nicht zwangsläufig die zuletzt gewählte Gast-Sprache. Ein
  erneuter Sprachwechsel (oder Rescan, falls sich auch der Inhalt strukturell
  geändert hat) übersetzt den neuen Wert dann wieder passend.

  Diese beiden Punkte sind auch direkt in der Kachel über das Info-Symbol (ⓘ)
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

### 3. Voraussetzungen

- Symcon ab Version 7.1
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

Name                            | Beschreibung
-------------------------------- | ------------------
Root-Kategorie                  | Kategorie im Objektbaum, deren Inhalt (Namen + Werte von String-Variablen) übersetzt wird. Sollte nur die Gäste-Sichtbereich-Kacheln enthalten, nicht die Admin-Oberfläche.
Basissprache                    | Sprache, in der die Objektnamen/-werte ursprünglich gepflegt sind. Dient Google Translate als feste Quellsprache für alle Zielsprachen-Übersetzungen; die Basissprache selbst hat keine eigene Übersetzungsspalte, siehe [Abschnitt 7](#7-visualisierung).
Google Cloud Translate API-Key  | API-Key für die Cloud Translation API v2. **Muss zuerst eingetragen und über "Übernehmen" gespeichert werden**, bevor irgendetwas anderes funktioniert - insbesondere ist der "Hinzufügen"-Button bei den Zielsprachen bis dahin ausgegraut (nicht versteckt, sondern deaktiviert), da ohne gültigen Key keine echte Sprachliste von Google geladen werden kann.
Zielsprachen                    | Sprachen, in die übersetzt werden soll. Auswahl-Optionen kommen von Google, sobald ein gültiger API-Key gespeichert ist. Wichtig: Nach dem Klick auf "Sprachliste aktualisieren" die Instanzkonfiguration einmal schließen und neu öffnen, bevor Häkchen gesetzt werden - sonst kann die Konsole falsche Sprachen speichern.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen des Baums, 0 = nur manuell über den Button.
Aktuell aktive Sprache          | Welche Sprache gerade angezeigt wird - normalerweise über die Kachel vom Gast selbst gesteuert (siehe Abschnitt 7), lässt sich hier aber auch manuell zu Testzwecken umschalten.
Weltkugel-Symbol in der Kachel anzeigen | Blendet das 🌐-Symbol links neben dem Dropdown aus, falls nicht gewünscht (z. B. bei eigenem Kachel-Design). Standardmäßig an.
Info-Symbol in der Kachel anzeigen | Blendet das ⓘ-Symbol (Erklärung der Einschränkungen, siehe Abschnitt 2) aus. Standardmäßig an.
Objektnamen / Eigene Texte      | Listen der gefundenen Objekte mit Quelltext und je einer Spalte pro Zielsprache. Übersetzungen sind hier direkt editierbar; leere Zellen werden beim nächsten Rescan automatisch übersetzt.

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

Ein Info-Symbol (ⓘ) neben dem Dropdown öffnet auf Klick einen nativen
Browser-Dialog (`alert()`) mit den in [Abschnitt 2](#2-bekannte-einschränkungen)
beschriebenen Einschränkungen, ebenfalls live in der jeweils aktiven
Gast-Sprache. Bewusst kein eigenes HTML-Popup: die Kachel läuft in einem
eigenen iframe und eigene Overlays können dessen Grenzen nicht überschreiten -
ein Browser-Dialog dagegen schon.

Für eigene HTMLBox-Popups oder Hinweise außerhalb der live umbenannten
Objekte liefert `IPSSL_TranslateText()` den Text in der aktuell aktiven
Sprache.

### 8. PHP-Befehlsreferenz

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