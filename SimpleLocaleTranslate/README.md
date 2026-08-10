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
feste Quell-/Zielsprache und die reine Übersetzungsfunktion beschränkt ist:

Name | Beschreibung
--- | ---
Google Cloud Translate API-Key | Ggf. derselbe wie in der echten Simple-Locale-Instanz. Muss zuerst eingetragen und über "Übernehmen" gespeichert werden, sonst bleibt die Zielsprachen-Auswahl ausgegraut (wie im Hauptmodul).
Quellsprache | Dropdown wie im Hauptmodul - die Sprache, in der der Modulentwickler seine eigenen Texte normalerweise schreibt. Immer wählbar, unabhängig vom API-Key (Fallback-Liste, solange keine echte Sprachliste geladen wurde).
Zielsprache | Dropdown mit allen von Google unterstützten Sprachen (wie im Hauptmodul, per API-Key abgerufen) - ausgegraut, bis ein gültiger Key eine echte Liste geladen hat. Für den Test egal welche - Hauptsache verschieden von der Quellsprache.

```
string IPSSLT_TranslateText(integer $InstanzID, string $Text);
```

Übersetzt `$Text` von der konfigurierten Quell- in die konfigurierte
Zielsprache. So können Modulentwickler ihre eigene Integration schon während
der Entwicklung gegen die echte Google-API testen, ganz ohne selbst schon
eine volle, lizenzierte Simple-Locale-Instanz beim Kunden zu benötigen.

Im Konfigurationsformular lässt sich die Funktion über den Button
"Testübersetzung ausführen" auch direkt in der Symcon-Konsole ausprobieren -
übersetzt den festen deutschen Testsatz "Willkommen in der Welt einfacher
Visualisierungsübersetzung!" in die eingestellte Zielsprache. Der Testsatz
ist immer auf Deutsch verdrahtet (unabhängig von der konfigurierten
Quellsprache), da er sich nicht selbst anpassen lässt - eine andere
Quellsprache anzunehmen wäre sonst schlicht falsch. Ohne gespeicherten
API-Key erscheint ein Hinweis-Popup statt eines stillen Fehlers; schlägt der
Google-Aufruf fehl (z. B. ungültiger Key), erscheint ebenfalls ein Popup statt
einfach nur den unübersetzten Text anzuzeigen, und der Button graut sich aus,
bis der Key korrigiert und erneut über "Übernehmen" gespeichert wurde.

### Status

Frühes Entwicklungsstadium ("Vorbereitung" für eine spätere Pro-Version von
Simple Locale) - Funktionsumfang, Funktionsname und Lizenzmodell können sich
noch ändern.
