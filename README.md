# Simple Locale for IPS (IPSSL)

Mehrsprachigkeit für einzelne Kachel-Visualisierungen in [IP-Symcon](https://www.symcon.de) –
z. B. eine separate "Gäste"-Oberfläche (Ferienwohnung, Airbnb, Showroom), während andere
Visualisierungen (Admin, eigene Steuerung) unverändert bleiben.

> ⚠️ Status: In Entwicklung. Noch nicht für den produktiven Einsatz oder den Module Store geeignet.

## Funktionsweise

Ein konfigurierter Root der Visualisierung (der Sichtbereich) wird eingelesen und liefert
zwei Textarten, die beide automatisch via Google Cloud Translate übersetzt und
persistent im Modul-Formular gecacht werden:

| Textart | Quelle | Mechanismus |
|---|---|---|
| **Objektnamen** (Kategorie-/Variablen-/Kachelnamen) | Namen aller Objekte im Root-Baum | Wird bei Sprachwechsel per `IPS_SetName` live auf den Objektbaum angewendet |
| **Eigene Texte** (Popup-/Hinweistexte) | Wert aller String-Variablen im Root-Baum (z. B. eine Variable "Hinweis") | Wird bei Sprachwechsel per `SetValueString` live geschrieben, alternativ per `IPSSL_TranslateText()` abfragbar |

Übersetzungen sind im Modul-Formular direkt einsehbar und korrigierbar (Google übersetzt
nicht immer perfekt); ein Rescan (manuell oder per Timer) übersetzt nur neue oder noch
leere Einträge nach, bestehende Werte bleiben unangetastet. Die Modul-Instanz
selbst liefert eine eigene, schlanke Dropdown-Kachel für die Zielvisualisierung
(Sprachnamen live in die aktive Sprache übersetzt, keine zusätzliche Variable
nötig); alle Umbenennungen/Wertänderungen bleiben auf den konfigurierten
Root der Visualisierung beschränkt.

## Installation

Noch nicht im Module Store verfügbar. Für Tests: Repo-URL in der Symcon-Konsole unter
**Kern Instanzen → Module Control** als eigene Quelle hinzufügen.

Folgende Module beinhaltet das Simple Locale for IP Symcon Repository:

- __Simple Locale__ ([Dokumentation](SimpleLocale))  
	Kurze Beschreibung des Moduls.

## Konfiguration

Siehe [Konfigurationsseite in der Moduldokumentation](SimpleLocale/README.md#5-einrichten-der-instanzen-in-symcon)
für die Übersicht aller Formularfelder. Kurzfassung: Kachel-Visualisierungs-Instanz
(liefert automatisch den Root der Visualisierung), Basis-/Zielsprachen und
Google-Translate-API-Key setzen, "Übernehmen", dann "Visualisierung neu einlesen und
fehlende Übersetzungen ergänzen" klicken.

## Entwicklung

Voraussetzungen: [Visual Studio Code](https://code.visualstudio.com/) mit der Extension
[Symcon Module Helper](https://marketplace.visualstudio.com/items?itemName=wilkware-vscode.forminator).

```bash
git clone https://github.com/AllardLiao/SimpleLocaleForIPS.git
```

## Lizenz

Simple Locale for IP-Symcon ist proprietäre, kommerzielle Software - dieses
Repository ist aus Transparenz- und Community-Gründen öffentlich (Code-
Review, Bug-Reports, Pull-Requests), aber kein Open-Source-Projekt. Siehe
[LICENSE](LICENSE) für die genauen Bedingungen, insbesondere zum Verbot,
die Lizenzprüfmechanismen zu entfernen oder zu umgehen (auch nicht mit
KI-Unterstützung).
