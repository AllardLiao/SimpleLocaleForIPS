# Simple Locale for IPS (IPSSL)

Mehrsprachigkeit für einzelne Kachel-Visualisierungen in [IP-Symcon](https://www.symcon.de) –
z. B. eine separate "Gäste"-Oberfläche (Ferienwohnung, Airbnb, Showroom), während andere
Visualisierungen (Admin, eigene Steuerung) unverändert bleiben.

> ⚠️ Status: In Entwicklung. Noch nicht für den produktiven Einsatz oder den Module Store geeignet.

## Funktionsweise

Das Modul unterscheidet zwei Textarten:

| Textart | Quelle | Mechanismus |
|---|---|---|
| **Eigene Texte** (Popup-Texte, Hinweise, Beschreibungen) | im Modul hinterlegte Quelltexte | Automatische Übersetzung via Google Cloud Translate, mit persistentem Cache |
| **Objektbaum-Texte** (Kategorie-/Variablen-/Kachelnamen) | vom Nutzer gepflegte Referenzdatei je Sprache | Wird bei Sprachwechsel per `IPS_SetName` auf den konfigurierten Objektbaum angewendet |

Eine Dropdown-Variable in der Zielvisualisierung erlaubt Gästen die Sprachauswahl; alle
Umbenennungen bleiben auf die konfigurierte Root-Kategorie beschränkt.

## Installation

Noch nicht im Module Store verfügbar. Für Tests: Repo-URL in der Symcon-Konsole unter
**Kern Instanzen → Module Control** als eigene Quelle hinzufügen.

Folgende Module beinhaltet das Simple Locale for IP Symcon Repository:

- __Simple Locale__ ([Dokumentation](Simple%20Locale))  
	Kurze Beschreibung des Moduls.

## Konfiguration

Wird ergänzt, sobald `form.json` steht.

## Entwicklung

Voraussetzungen: [Visual Studio Code](https://code.visualstudio.com/) mit der Extension
[Symcon Module Helper](https://marketplace.visualstudio.com/items?itemName=wilkware-vscode.forminator).

```bash
git clone https://github.com/AllardLiao/SimpleLocaleForIPS.git
```

## Lizenz

[MIT](LICENSE)
