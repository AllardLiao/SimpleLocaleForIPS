# Simple Locale
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in Symcon](#4-einrichten-der-instanzen-in-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Übersetzt live die Namen aller Objekte (Kategorien, Variablen, Kacheln, Links)
  innerhalb einer frei wählbaren Root-Kategorie, per `IPS_SetName`.
* Übersetzt zusätzlich den Wert aller String-Variablen innerhalb dieser
  Root-Kategorie (z. B. Hinweis-/Popup-Texte), per `SetValueString`.
* Automatische Übersetzung über die Google Cloud Translate API, inkl.
  persistentem Cache – Google wird nur für neue oder noch unübersetzte Einträge
  aufgerufen, nie für bereits vorhandene (auch manuell korrigierte) Werte.
* Übersetzungen sind direkt im Modul-Formular überprüf- und korrigierbar.
* Der Objektbaum wird manuell (Button) oder automatisch (Timer-Intervall)
  erneut eingelesen; dabei werden nur neue Idents/Sprachen ergänzt, nichts
  wird überschrieben oder gelöscht.
* Eine Sprachauswahl-Variable (`Language`) kann per WebFront/Kachel-Dropdown
  bedient werden und löst den Sprachwechsel aus.

### 2. Voraussetzungen

- Symcon ab Version 7.1

### 3. Software-Installation

* Über den Module Store das 'Simple Locale'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 4. Einrichten der Instanzen in Symcon

 Unter 'Instanz hinzufügen' kann das 'Simple Locale'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name                            | Beschreibung
-------------------------------- | ------------------
Root-Kategorie                  | Kategorie im Objektbaum, deren Inhalt (Namen + Werte von String-Variablen) übersetzt wird. Sollte nur die Gäste-Sichtbereich-Kacheln enthalten, nicht die Admin-Oberfläche.
Basissprache                    | Sprache, in der die Objektnamen/-werte ursprünglich gepflegt sind (Quellsprache für Google Translate).
Zielsprachen                    | Sprachen, in die übersetzt werden soll. Auswahl-Optionen kommen von Google (Button "Sprachliste von Google aktualisieren").
Google Cloud Translate API-Key  | API-Key für die Cloud Translation API v2. Muss vor dem ersten Rescan/Sprachlisten-Refresh gespeichert ("Übernehmen") werden.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen des Baums, 0 = nur manuell über den Button.
Objektnamen / Eigene Texte      | Listen der gefundenen Objekte mit Quelltext und je einer Spalte pro Zielsprache. Übersetzungen sind hier direkt editierbar; leere Zellen werden beim nächsten Rescan automatisch übersetzt.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name       | Typ     | Beschreibung
---------- | ------- | ------------
`Language` | String  | Aktuell aktive Sprache (Ident-Aktion, z. B. per Dropdown in der Kachel-Visualisierung bedienbar)

#### Profile

Name              | Typ
----------------- | -------
`~IPSSL.Language` | String (Assoziationen: ein Wert je Basis-/Zielsprache)

### 6. Visualisierung

Die `Language`-Variable kann als Dropdown/Auswahl-Element direkt in der
Kachel-Visualisierung platziert werden. Für eigene HTMLBox-Popups oder Hinweise
außerhalb der live umbenannten Objekte liefert `IPSSL_TranslateText()` den
Text in der aktuell aktiven Sprache.

### 7. PHP-Befehlsreferenz

`string IPSSL_TranslateText(integer $InstanzID, string $Ident);`
Liefert den Inhalt der "Eigene Texte"-Zeile mit dem angegebenen Ident in der
aktuell aktiven Sprache (Fallback: Quelltext), z. B. für Popup-Inhalte in
eigenen HTMLBox-Skripten.

Beispiel:
`IPSSL_TranslateText(12345, "Hinweis");`

`void IPSSL_Rescan(integer $InstanzID);`
Liest die konfigurierte Root-Kategorie neu ein und übersetzt neu gefundene
oder noch unübersetzte Einträge. Entspricht dem Button "Baum jetzt neu
einlesen" im Modul-Formular.

Beispiel:
`IPSSL_Rescan(12345);`