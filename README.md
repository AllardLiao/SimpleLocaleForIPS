# Simple Locale (SLOC)

Mehrsprachigkeit für einzelne Kachel-Visualisierungen in [IP-Symcon](https://www.symcon.de) –
z. B. eine separate "Gäste"-Oberfläche (Ferienwohnung, Airbnb, Showroom), während andere
Visualisierungen (Admin, eigene Steuerung) unverändert bleiben.

> Im IP-Symcon Module Store verfügbar.

## Funktionsweise

Simple Locale liest den Baum einer Kachel-Visualisierung ein und übersetzt dessen Texte in
die Sprachen, die du festlegst. Nutzer wählen ihre Sprache über eine eigene, schlanke Kachel.
Umbenannt und geschrieben wird ausschließlich innerhalb dieser einen Visualisierung.

Übersetzt werden:

| Was | Beispiel |
|---|---|
| **Objektnamen** | Kategorien, Räume, Variablen, Verknüpfungen |
| **Eigene Texte** | Werte von String-Variablen, z. B. Hinweise oder HTML-Widgets – auch live, wenn andere Module sie aktualisieren |
| **Aufzählungen** | Beschriftungen aus Profilen und Darstellungen, z. B. "Offen"/"Geschlossen" |
| **Charts** | Legenden-Titel |
| **Automationen** | Namen der Automationen der Kachel-Visualisierung |
| **Begrüßung** | Begrüßungstext der Kachel-Visualisierung |

**Übersetzer:** Ab Werk ohne Konto über einen kostenfreien Anbieter (MyMemory). Optional
lassen sich Google Cloud Translate und DeepL mit eigenem API-Key einbinden; fällt ein
Anbieter aus oder ist sein Kontingent erschöpft, übernimmt automatisch der nächste.
Übersetzungen werden zwischengespeichert, bereits übersetzte Texte nicht erneut angefragt.

**Korrigieren:** Alle Übersetzungen sind im Konfigurationsformular einsehbar und
änderbar. Ein Glossar (z. B. für Einheiten und Himmelsrichtungen) und – je nach Edition –
eine eigene Übersetzungstabelle haben Vorrang vor jedem Anbieter. Ein erneutes Einlesen ergänzt nur
Neues und lässt bestehende Übersetzungen stehen.

**Testversion und Lizenz:** Die Testversion läuft 30 Tage mit vollem Funktionsumfang und
einer frei wählbaren Zielsprache. Die Vollversion ist ein Einmalkauf. Details zu den
Editionen stehen in [Kapitel 8 der Dokumentation](SimpleLocale/README.md#8-lizenz-und-testversion).

**Für Modulentwickler:** Eigene Kacheln anderer Module lassen sich über
`SLOC_TranslateExternalTexts()` mitübersetzen, siehe
[Kapitel 10](SimpleLocale/README.md#10-integration-für-modulentwickler).

## Installation

Über den Module Store das Modul **Simple Locale** installieren. Alternativ die Repo-URL
`https://github.com/AllardLiao/SimpleLocaleForIPS` in der Symcon-Konsole unter
**Kern Instanzen → Module Control** hinzufügen.

Das Repository enthält ein Modul:

- **Simple Locale** ([Dokumentation](SimpleLocale/README.md))
  Mehrsprachige Kachel-Visualisierung mit automatischer Übersetzung.

## Konfiguration

Kurzfassung:

1. Die **Kachel-Visualisierung** wählen, die übersetzt werden soll. Sie liefert
   automatisch den Baum, den Simple Locale einliest.
2. **Scan-Sprache** (die Sprache deiner Objektnamen) und **Zielsprachen** festlegen.
3. Optional einen API-Key für Google oder DeepL eintragen – ohne Key arbeitet der
   kostenfreie Anbieter.
4. "Übernehmen", dann "Visualisierung neu einlesen und fehlende Übersetzungen ergänzen".

Alle Formularfelder sind in der
[Moduldokumentation](SimpleLocale/README.md#5-einrichten-der-instanzen-in-symcon) beschrieben.

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
