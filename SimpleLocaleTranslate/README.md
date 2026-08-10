# Simple Locale Translate

Schlankes Schwester-Modul zu [Simple Locale](../SimpleLocale) im selben Repository.

### Zweck

Simple Locale übersetzt Namen und Texte innerhalb einer konfigurierten
Root-Kategorie live in die gewählte Gast-Sprache. Eine bekannte Einschränkung
dabei: Inhalte, die von *anderen* Modulen/Skripten laufend automatisch
aktualisiert werden (z. B. Wetter- oder Messwerte), fallen nach jeder eigenen
Aktualisierung wieder in ihre ursprüngliche Sprache zurück, da Simple Locale
den Wert nur beim Sprachwechsel selbst überschreibt.

Die geplante Lösung dafür: Modulentwickler rufen künftig direkt eine
Übersetzungsfunktion der (dann lizenzierten) Simple-Locale-Instanz auf, bevor
sie ihren eigenen Wert schreiben - so bekommt der Gast immer die passende
Sprache, unabhängig davon, wie oft der Wert aktualisiert wird.

**Dieses Modul hier ist eine ganz schlanke Testhilfe für genau diese
Integration.** Es hat keinen Objektbaum-Scan, keine Gast-Kachel, keine
Lizenzprüfung - eine Instanz, die immer funktioniert, aber bewusst auf eine
einzige, fest eingestellte Zielsprache und die reine Übersetzungsfunktion
beschränkt ist: einen Google Cloud Translate API-Key (ggf. derselbe wie in
der echten Simple-Locale-Instanz), eine Zielsprache, und:

```
string IPSSLT_TranslateText(integer $InstanzID, string $Text, string $QuellSprache);
```

Übersetzt `$Text` von `$QuellSprache` in die im Modul konfigurierte
Zielsprache. Welche Zielsprache dabei konkret eingestellt ist, spielt für den
Test keine Rolle - Hauptsache sie unterscheidet sich von `$QuellSprache`
(Google lehnt eine Übersetzung von einer Sprache in sich selbst ab). So
können Modulentwickler ihre eigene Integration schon während der Entwicklung
gegen die echte Google-API testen, ganz ohne selbst schon eine volle,
lizenzierte Simple-Locale-Instanz beim Kunden zu benötigen.

Im Konfigurationsformular lässt sich die Funktion über den Button
"Testübersetzung ausführen" auch direkt in der Symcon-Konsole ausprobieren
(übersetzt den Testsatz "Hallo Welt").

### Status

Frühes Entwicklungsstadium ("Vorbereitung" für eine spätere Pro-Version von
Simple Locale) - Funktionsumfang, Funktionsname und Lizenzmodell können sich
noch ändern.
