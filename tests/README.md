# Regressions-Suite

Eigenstaendige PHP-Tests, die die ueber die Entwicklung hinweg gefundenen Fehler
dauerhaft absichern. Jeder Test dokumentiert im Kopfkommentar, **welcher konkrete
Fehler** ihn ausgeloest hat (haeufig ein live gemeldeter) und was genau er
verhindert.

## Ausfuehren

```bash
tests/run_all.sh
```

Einzelner Test:

```bash
php -d zend.assertions=1 -d assert.exception=1 tests/test_<name>.php
```

**Wichtig:** Die beiden `-d`-Schalter sind nicht optional. PHP laeuft
standardmaessig mit `zend.assertions=-1` - `assert()` wird dann gar nicht erst
kompiliert, und *jeder* Test waere stillschweigend "gruen", ohne je etwas
geprueft zu haben.

## Aufbau

Zwei Arten von Tests, bewusst gemischt:

1. **Replica-Tests** (die grosse Mehrheit): bilden die betroffene Logik als
   eigenstaendige Funktion nach und pruefen ihr Verhalten. Laufen ohne jede
   IP-Symcon-Installation, in Millisekunden.
2. **Symmetrie-Checks**: lesen zusaetzlich die echte `SimpleLocale/module.php`
   (bzw. `form.json`/`locale.json`) und pruefen per String-/Regex-Vergleich, dass
   die reale Umsetzung noch zur nachgebildeten Logik passt. Sie fangen genau den
   Fall ab, dass die Replica gruen bleibt, waehrend das Produkt daneben
   auseinanderlaeuft.

Fixtures (`weather_widget_*.html`) sind echte, im Feld aufgetretene HTML-Widgets,
an denen die HTML-Zerlegung geprueft wird.

## Sonderfall `test_license_features.php`

Dieser eine Test faellt in zwei Punkten aus dem Rahmen:

1. Er instanziiert die echte Modulklasse und braucht dafuer die Symcon-Stubs
   unter `.ips_stubs/`. Das Verzeichnis ist bewusst **nicht eingecheckt** (siehe
   `.gitignore`).
2. Er muss gueltige Lizenzschluessel erzeugen und braucht dazu den **privaten
   Ed25519-Signierschluessel** - exakt den, mit dem echte, verkaufbare Lizenzen
   ausgestellt werden. Der gehoert **niemals** in dieses (oeffentliche) Repo,
   siehe den Kommentar bei `LICENSE_PUBLIC_KEY` in
   `libs/SimpleLocaleConstants.php`.

Der Schluessel wird daher aus der Umgebung gelesen:

```bash
SLOC_LICENSE_SIGNING_KEY='<base64-secret-key>' \
  php -d zend.assertions=1 -d assert.exception=1 tests/test_license_features.php
```

Ohne gesetzte Variable ueberspringt sich der Test sauber (Exit 0) statt
fehlzuschlagen - `run_all.sh` bleibt dadurch auf jeder Maschine gruen. Alle
uebrigen Tests haben keine dieser beiden Abhaengigkeiten.

## Neue Tests

Bei jedem behobenen Fehler gehoert ein Test dazu, der **den Fehler selbst**
nachstellt (nicht nur die Funktion allgemein): erst rot ohne den Fix, dann gruen
mit ihm. Aussagekraeftige Assertion-Meldungen ("DER BUG: ...") beschreiben, was
schiefgeht, wenn die Zusicherung faellt - sie sind die eigentliche Dokumentation.
