# Change-Log

Alle Builds, neueste zuerst. Die Dokumentation selbst steht in
[README.md](README.md); hier steht ausschliesslich, was sich wann geaendert hat
und warum.

Chronologische Historie aller Bugfixes, Features und Nachbesserungen aus
Build 53 bis Build 107 - ausgelagert aus Abschnitt 2, das dadurch als reine,
aktuelle Liste bestehen bleibt. Jeder Eintrag ist unverändert (verbatim) aus
der ursprünglichen Fassung übernommen.

* **Build 203 (Nutzer-Hinweis): Sprachen, die als Quellsprache einer Zeile in
  Gebrauch sind, lassen sich nicht mehr entfernen oder ändern.**
  Der Fall ist ausdrücklich beworben: ein fremdsprachiges IPS-Modul in einem
  eigenen Scan-Gang mit abweichender Scan-Sprache erfassen. Danach tragen diese
  Zeilen z. B. `en` als Zeilen-Quellsprache, während die Instanz weiter auf `de`
  scannt.

  Verschwand `en` dann aus den Zielsprachen - von Hand gelöscht oder durch die
  Kürzung am Sprachlimit -, hatte das zwei Folgen, und beide waren bisher
  ungeschützt:

  * Die Spalte `en` fiel aus allen Listen weg. Der **Rohtext** dieser Zeilen war
    damit im Formular nicht mehr erreichbar.
  * `BuildRowSourceLanguageOptions()` bot den gespeicherten Wert nicht mehr an -
    und Symcon verweigert dann das Speichern der Instanz
    (`Current value ... is not available`), derselbe Fehler wie in Build 142 bei
    der aktiven Sprache.

  Solche Sprachen sind jetzt in der Zielsprachen-Tabelle weder änderbar noch
  löschbar (Symcon kennt dafür `editable`/`deletable` je Zeile), werden von der
  Kürzung übersprungen und **zählen nicht gegen das Sprachlimit** - sie sind
  strukturell nötig, keine frei gewählte Zielsprache. Ohne diese Ausnahme
  blockierte die Struktur die freie Wahl: bei Limit 1 wäre nach so einem
  Scan-Gang gar keine Zielsprache mehr möglich gewesen.

  Die Menge wird aus den **Daten** abgeleitet, nicht mitgeschrieben - so stimmt
  sie auch für Zeilen, die vor dieser Änderung entstanden sind, und kann nicht
  auseinanderlaufen.

  Regressionstest `test_used_source_languages_protected.php` (6 Fälle, darunter
  die Gegenprobe, dass eine frei gewählte Zielsprache bedienbar bleibt).

* **Build 202 (live gemeldet): bei erreichtem Sprachlimit ließ sich die bereits
  gewählte Zielsprache weder ändern noch löschen.**
  Die Sperre setzte `enabled = false` auf die **ganze** Liste. Damit saß der
  Nutzer auf seiner ersten Wahl fest - und das widerspricht direkt der Zusage,
  die Zielsprache sei in der Testphase jederzeit wechselbar. Jetzt fällt nur der
  "Hinzufügen"-Knopf weg (`add = false`, was Symcon ausblendet - deaktiviert
  bietet es dort nicht an). Die Zeile selbst bleibt bedienbar, und der Wechsel
  läuft ohnehin natürlicher über das Umstellen der Zeile als über Löschen und
  Neuanlegen.

  Zweitens wird eine **Kürzung beim Speichern jetzt gemeldet**. Der ausgeblendete
  Knopf wird erst beim nächsten Formularaufbau neu bewertet: wer das Formular
  öffnet, solange noch Platz ist, kann darin beliebig viele Zeilen anlegen - und
  verlor sie beim Speichern kommentarlos. Das Meldungs-Log nennt jetzt Anzahl und
  Grund.

* **Build 201 (live gemeldet): das Zielsprachen-Feld meldete "Sprachlimit dieser
  Lizenz erreicht, max. 1" - in der Testphase, in der es gar keine Lizenz gibt.**
  Zwei Fehler an einer Stelle:

  * Die Sperre des Auswahlfelds zählte weiter die **Quellsprache** mit. Build 199
    hatte das nur in `EnforceLicensedLanguageLimit()` korrigiert; die Sperre im
    Formular ist ein zweiter, unabhängiger Pfad. Bei Limit 1 wäre die Liste damit
    gesperrt gewesen, **bevor** überhaupt eine Zielsprache gewählt werden konnte.
  * Der Hinweistext sprach von einer Lizenz. In der Testphase gibt es keine,
    deren Limit erreicht sein könnte - dort ist es die Testphase selbst. Der Text
    unterscheidet jetzt beide Fälle.

  Regressionstest um zwei Fälle erweitert - ausdrücklich mit der Zusicherung,
  dass die Liste allein mit der Quellsprache **nicht** gesperrt ist.

* **Build 200: die Beschreibung der Testphase im Konfigurationsformular stimmte
  nach Build 199 nicht mehr.**
  Dort stand weiterhin "voller Funktionsumfang, aber nur mit den 5 zum Testen
  freigeschalteten Sprachen (Isländisch, Walisisch, Zulu, Maori, Latein)" -
  genau das, was Build 199 abgeschafft hat. Jetzt: "voller Funktionsumfang, mit
  einer frei wählbaren Zielsprache - jederzeit während der Testphase
  wechselbar", in allen vier Sprachen.

  Der Qualitäts-Hinweis in [Abschnitt 2](README.md#2-bekannte-einschränkungen)
  nannte dieselben fünf Sprachen als "Testphasen-Sprachen". Sachlich stimmt der
  Hinweis weiterhin - die Übersetzungsqualität ist für selten unterstützte
  Sprachen schwerer einzuschätzen -, nur der Bezug zur Testphase ist entfallen.

* **Build 199 (Nutzer-Entscheidung): die Testphase ist "Pro mit einer
  Zielsprache" statt fünf praxisferner Sprachen.**
  Bisher waren es Isländisch, Walisisch, Zulu, Maori und Latein - bewusst
  praxisfern, damit sich die Testversion nicht produktiv nutzen lässt. Damit
  ließ sich zwar der Mechanismus prüfen, aber nie die Übersetzungsqualität der
  **eigenen** Inhalte: niemand lässt Maori vor Gästen laufen. Und weil die
  Kachel ohnehin nie live war, fiel der Rückfall aufs Original nach 30 Tagen
  niemandem auf - der wirksamste Kaufanreiz verpuffte.

  Jetzt: jede Sprache wählbar, genau **eine Zielsprache**, in den 30 Tagen
  jederzeit wechselbar. Nach Ablauf greift wie bisher die Sperre - alles außer
  der Quellsprache ist blockiert, die Kachel fällt aufs Original zurück. Die
  fünf Demo-Sprachen entfallen dabei ersatzlos: frei ist nur noch, was eine
  laufende Marketing-Aktion freigibt.

  **Dabei ist ein Fehler aufgefallen, der auch bezahlte Lizenzen betraf.** Das
  Sprachlimit zählte die Quellsprache mit, und `EnsureSourceLanguageIsTarget()`
  trägt sie immer selbst ein - eine Edition mit Limit 3 lieferte damit nur zwei
  tatsächliche Zielsprachen, obwohl der Shop "Zielsprachen" bewirbt. Ohne diese
  Korrektur wäre das Testlimit 1 sogar wertlos gewesen: es hätte die
  Quellsprache belegt und keine einzige Zielsprache übrig gelassen. Das Limit
  zählt jetzt ausschließlich Zielsprachen; **bestehende Lizenzen erlauben
  dadurch je eine Sprache mehr als bisher.**

  Regressionstest `test_trial_one_target_language.php` (7 Fälle).

* **Build 198: der Change-Log steht in einer eigenen Datei, die Dokumentation
  ist von Build-Archäologie befreit.**
  Mit 145 Einträgen über 4.400 Zeilen war er dreimal so lang wie die
  Dokumentation, an die er angehängt war - und uneinheitlich sortiert: die
  neueren Einträge standen als absteigender Block **über** den älteren, weshalb
  die Datei scheinbar bei Build 154 endete. Jetzt hier, durchgehend neueste
  zuerst; alle Einträge unverändert übernommen.

  In den Doku-Abschnitten steckten außerdem zwanzig "seit Build N"-Marken und
  einige "ein Fehler hat X aufgedeckt, deshalb Y"-Passagen. Wer die Anleitung
  liest, will wissen **wie etwas funktioniert**, nicht mit welchem Build es kam -
  diese Stellen sind ins Präsens umgeschrieben. Inhaltlich wurde nichts
  entfernt, nur die Historie, und genau dafür gibt es diese Datei.

  Zwei Build-Nummern haben übrigens keinen eigenen Eintrag: **56** (aus
  frühester Zeit) und **193** (ging in den gemeinsamen Eintrag zu 192 ein und
  wurde beim Wechsel von Stunden auf Minuten zu 194 umnummeriert).

* **Build 197: `rpm` heißt auf Deutsch `U/min`, auf Französisch `tr/min`.**
  Der Eintrag stand in allen Sprachen als `rpm` - englisch abgeleitet
  (revolutions per minute) und damit genau einer der 17 Fälle aus Build 196.
  Ergänzt für Deutsch, Französisch und Luxemburgisch (wie Deutsch);
  Englisch/Spanisch/Portugiesisch verwenden `rpm` tatsächlich, dort bleibt es.

  **Zu beachten:** die deutsche Spalte ist zugleich der Wert, über den ein Text
  mit deutscher Quellsprache gefunden wird. Zeigt eine Visualisierung das Kürzel
  buchstäblich als `rpm` an - bei Sensoren durchaus üblich, weil sie englische
  Suffixe liefern -, trifft die Zeile nun nicht mehr. Gewollt ist der Tausch:
  wer `U/min` anzeigt, wird jetzt geschützt und in andere Sprachen korrekt
  übersetzt; wer `rpm` anzeigt, trägt es bei Bedarf als eigene Glossar-Zeile
  nach.
* **Build 196 (Rückfrage beim Testen): "Einheiten sind in jeder Sprache gleich"
  war zu pauschal - jetzt wird nach Verlässlichkeit vorbelegt.**
  Auf die Frage, ob `°C` denn auch im Chinesischen so heißt, lautet die ehrliche
  Antwort: nicht durchgängig. Der Beweis steht im eigenen Code - für Russisch
  überschreiben wir **65 der 73** Einheiten mit kyrillischen Kürzeln. Wir wussten
  also längst, dass die Symbole nicht universell sind; Build 195 hat es für alle
  anderen Sprachen stillschweigend vorausgesetzt.

  Die Trennlinie verläuft zweifach:

  * **56 der 73 sind international genormte SI-Symbole** (`V`, `W`, `Hz`, `Pa`,
    `m`, `kg`, `J`, `°C` …). Die gelten sprachunabhängig und werden weiterhin
    für **jede** Sprache vorbelegt - auch für Chinesisch.
  * **17 sind es nicht** (`psi`, `km/h`, `kn`, `ppm`, `ppb`, `mg/l`, `µg/m³`,
    `g/m³`, `kB`, `MB`, `GB`, `TB`, `kbps`, `Mbps`, `kcal`, `rpm`, `UV`) -
    englisch abgeleitet oder sprachabhängig. `rpm` heißt auf Deutsch `U/min`,
    `kn` (knots) französisch `nd` (nœuds).

  Für die **15 geprüften** Sprachen ändert sich nichts: dort ist die Vorbelegung
  beim Aufbau des Katalogs durchgesehen worden. Für jede andere Sprache bleiben
  die 17 Zellen **leer**, statt eine Vermutung als Vorgabe auszuliefern - die
  restlichen 56 stehen weiterhin.

  Am Konfigurationsformular ändert das nichts: eine Tabelle, dieselben Spalten,
  nur vorsichtiger vorbelegt.
* **Build 195 (Nutzer-Wunsch): Einheiten für **jede** konfigurierte Sprache, und
  der Katalog-Schlüssel ist keine Sprache mehr.**
  Zwei Dinge, die zusammengehören:

  *Einheiten.* Vorbelegt waren sie für eine feste Liste von neun Sprachen. Eine
  Zielsprache außerhalb davon bekam eine leere Spalte - und `°C` ging dort
  wieder an den Anbieter, mit demselben Ergebnis wie in Build 158 (`°F`).
  Einheiten sind aber Symbole und bleiben unverändert: `kWh` ist überall `kWh`.
  Sie werden jetzt für jede konfigurierte Sprache vorbelegt. **Für
  Kompassrichtungen gilt das ausdrücklich nicht** - die hängen an den Wörtern
  der Sprache (deutsch `O` für Ost wird tschechisch `V` für východ) und stehen
  nur dort, wo wir sie tatsächlich kennen. Alles andere wäre geraten.

  Ergänzt wurden Kompassrichtungen für **Dänisch, Norwegisch, Schwedisch,
  Tschechisch und Luxemburgisch** - damit sind es 14 Sprachen plus Deutsch.

  *Der Schlüssel.* Bisher diente die deutsche Spalte als Primärschlüssel der
  Katalogzeilen. Das widersprach der Idee der Tabelle, in der ausdrücklich keine
  Sprache ausgezeichnet ist - und es ging schief, sobald jemand auf Englisch
  arbeitet: trägt er eine eigene Zeile ein, deren deutsche Spalte zufällig auf
  einen bestehenden Katalogeintrag fällt, wären beide nicht mehr auseinander zu
  halten, und die Nachbefüllung hätte seine Zeile erwischt statt der eigenen.

  Jede mitgelieferte Zeile trägt jetzt einen technischen Katalog-Schlüssel, der
  keine Sprache ist. Eigene Zeilen haben keinen und werden nie angefasst - egal,
  was in welcher Sprachspalte steht. Die Spalte steht sichtbar, aber nicht
  editierbar vorn und zeigt damit auch die Herkunft jeder Zeile.
* **Build 194 (Nutzer-Wunsch): die Sperrfrist zwischen zwei Sprachwechseln ist
  jetzt frei festlegbar - als Zeitwert aus der Lizenz, `0` = unbegrenzt.**
  Vorher gab es nur ein Ja/Nein-Feature (`unlimited_language_switch`) und eine
  fest verdrahtete Konstante von 24 Stunden. In einer **Spezialversion**, die
  Features einzeln zusammenstellt und kein Tier kennt, ließ sich die Dauer damit
  gar nicht festlegen - nur an oder aus.

  Neu in der Lizenz-Nutzlast: `switchIntervalMinutes` - **minutengenau**, damit
  sich auch kurze Fristen abbilden lassen. Das ist ausdrücklich weder
  `languageLimit` (die **Anzahl** der Sprachen) noch `interval` (der
  Abo-Zyklus) - beide bleiben unberührt.

  Bereits ausgestellte Schlüssel kennen das Feld nicht. Damit sich keiner von
  ihnen stillschweigend anders verhält, gilt die Reihenfolge: ausdrücklicher
  Zeitwert, sonst das alte Ja/Nein-Feature als Altlast-Schreibweise, sonst der
  bisherige Tag. Ohne gültige Lizenz bleibt es unbegrenzt - die Sperre war nie
  als Testphasen-Beschränkung gedacht.

  Regressionstest `test_language_switch_interval.php` (7 Fälle).
* **Build 192 (Nutzer-Wunsch): "Übersetzung je Objekt abschaltbar" hat ein
  eigenes Lizenz-Feature (`disable_single_translations`).**
  Bis dahin hing der Schalter an `edit_translations` - beide ließen sich also
  nur gemeinsam vergeben. In einer Spezialversion war er dadurch nicht getrennt
  festlegbar: wer ihn wollte, musste das komplette Editieren der gescannten
  Tabellen mitgeben.

  `edit_translations` behält seinen eigentlichen Umfang: die editierbaren Zellen
  und die Quellsprache je Zeile (in `BuildLanguageColumnSet()` und
  `BuildRowSourceLanguageColumn()` als Vorgabewert, gut ein Dutzend
  Aufrufstellen). Der Lizenzblock im Formular zeigt jetzt auch die beiden neuen
  Features an.
* **Build 191 (live gemeldet): die Glossar-Tabelle blieb bei einer frisch
  angelegten Instanz leer - der Erklärtext darüber erschien, die Tabelle nicht.**
  Befüllt wurde das Glossar bis dahin **ausschließlich** in `ScanRootTree()`,
  also erst beim ersten Rescan. Genau davor schlägt ein Nutzer die Tabelle aber
  zum ersten Mal auf: Modul installieren, Lizenz eintragen, hinschauen. Die
  Befüllung läuft jetzt zusätzlich in `ApplyChanges()`, an derselben Stelle wie
  `EnsureSourceLanguageIsTarget()` - und wie dort nur, wenn sich tatsächlich
  etwas ändert, sonst wäre es ein `IPS_ApplyChanges()`-Reentry bei jedem
  Speichern.

  Zweite Absicherung an derselben Stelle: eine Liste **ohne Spalten** rendert
  Symcon als gar nichts. Solange keine Zielsprache konfiguriert ist, hätte die
  Tabelle deshalb unsichtbar bleiben können, obwohl der Text darüber steht - die
  Spalten fallen jetzt notfalls auf die Quellsprache zurück, die immer vorhanden
  ist.
* **Build 190: auch die DOM-Bezeichner heißen jetzt `sloc` statt `ipssl`.**
  Beim Präfixwechsel in Build 185 blieben die kleingeschriebenen Bezeichner
  bewusst stehen - CSS-Klassen (`ipssl-select-row`, `ipssl-globe`,
  `ipssl-tile-icon`, `ipssl-select-wrapper-<id>` …), der optionale JS-Haken
  `window.ipsslOnLanguageChange` und die Katalog-ID des mitgelieferten Symbols.
  Sie sind keine Symcon-Funktionsnamen, und ein Umbenennen bricht jede bereits
  gebaute eigene Kachel und jedes ausgelieferte Design.

  Auf ausdrücklichen Wunsch fällt das jetzt trotzdem - die betroffenen Designs
  werden nachgezogen. **Das ist ein Bruch für eigenes Kachel-HTML:** wer
  `window.ipsslOnLanguageChange` definiert oder die Klassen anspricht, muss auf
  `sloc…` umstellen. Die Platzhalter selbst (`<!--LANGUAGE_SELECT-->`,
  `<!--TILE_ICON-->`, `<!--ACTIVE_LANGUAGE-->` …) sind unverändert.

  Mit umgestellt ist die Katalog-ID `ipssl` des mitgelieferten Symbols. Eine
  gespeicherte alte Auswahl fällt über `ResolveCatalogId()` sauber auf den
  Standard zurück, es bleibt also nichts kaputt stehen.

  Nach dieser Umstellung trägt der ausgelieferte Code den alten Namen nirgends
  mehr - die verbliebenen Nennungen stehen ausschließlich in den Changelog- und
  Begründungskommentaren.
* **Build 189 (Nutzer-Wunsch): das Glossar ist jetzt eine eigene Tabelle - ohne
  Quellsprache, dafür in jede Richtung gültig.**
  Bis Build 188 schrieb das Modul **89 mitgelieferte Zeilen** (73 Einheiten +
  16 Kompassrichtungen) in die "Eigene Übersetzungstabelle" - alle fest auf
  Quellsprache Deutsch. Das hatte zwei Folgen: das eigene Glossar lag unter
  Fremdzeilen begraben, und für ein Objekt mit **anderer** Zeilen-Quellsprache
  griff keine davon. "km/h" hätte je Quellsprache eine eigene Zeile gebraucht.

  Jetzt zwei getrennte Tabellen mit unterschiedlicher Bedeutung:

  * **Eigene Übersetzungen** - unverändert. Eine Quellsprachen-Spalte legt je
    Zeile die Richtung fest. Nur noch das, was der Admin selbst einträgt.
  * **Glossar** - mitgelieferte Einheiten und Kompassrichtungen. **Keine**
    Quellsprachen-Spalte: je Sprache eine Spalte, und jede kann die Quelle sein.
    Der Eintrag einer Spalte übersetzt sich in den jeder anderen. Ein Begriff
    braucht deshalb genau eine Zeile, gleich welche Quellsprache ein Teil der
    Visualisierung verwendet.

  Getroffen wird ausschließlich über die Spalte der **Quellsprache**: "km/h" aus
  einer deutschen Zeile trifft über die deutsche Spalte, aus einer englischen
  über die englische. Ein Text, der sich als spanisch ausgibt, trifft nur, wenn
  die spanische Spalte den Wert trägt. Damit ist die Zuordnung von jeder Spalte
  in jede andere eindeutig - das ist hier die Bedeutung von "Glossar".

  Die eigenen Übersetzungen behalten **Vorrang**: sie sind die ausdrückliche
  Festlegung für genau diese Installation. Erst danach das Glossar.

  Bearbeitet werden darf es **ab der Standard-Edition** (Feature `glossary`).
  Der Nachschlag selbst läuft weiterhin in **jeder** Edition - Einheiten müssen
  überall richtig behandelt werden, verkauft wird das Bearbeiten (siehe
  Build 158 für die Begründung: "°C" ging sonst an die API und kam als "°F"
  zurück, eine Einheitenumrechnung statt einer Übersetzung).

  Der Schutz gegen zurückkehrende gelöschte Zeilen bleibt: wer "SSW" entfernt,
  weil es in seiner Installation ein Personenkürzel ist, bekommt es nicht wieder
  vorgeschlagen.

  Regressionstest `test_glossary_separate_table.php` (7 Fälle), die drei
  bestehenden Glossar-Tests auf die neue Struktur nachgezogen. Dabei ist
  `attributeSeededManualTranslationKeys` als tot weggefallen.
* **Build 188 (Rückfrage beim Testen): die Regel aus Build 187 war zu grob und
  behandelte Portugiesisch anders als Englisch.**
  Auslöser war eine gute Frage zu drei englischen Einträgen ("English",
  "English (British)", "English (American)"). Die sind korrekt - beim Nachrechnen
  fiel aber auf, dass dieselbe Lage bei Portugiesisch anders ausging.

  Build 187 strich jede Region, die der Sprache entspricht (`de-de` → `de`).
  Das traf `DE-DE`/`FR-FR` richtig, aber auch `PT-PT` - und dort ist es falsch:
  DeepL kennt **kein** einfaches `PT`, europäisches Portugiesisch ist dort keine
  Dublette, sondern die einzige europäische Fassung. Aus `PT-PT` wurde dadurch
  `pt`, was den eingebauten Namen "Português" überschrieb und die Variante
  verschwinden ließ, während Englisch seine beiden Varianten behielt.

  Die Regel prüft jetzt den Kontext: die Eigenregion fällt nur weg, wenn die
  Basissprache in **derselben** Anbieter-Liste steht. `DE` ist dort, also ist
  `DE-DE` redundant; `PT` ist es nicht, also bleibt `PT-PT`. Das braucht die
  ganze Liste und liegt deshalb in `DropRedundantRegionVariants()`;
  `NormalizeLanguageCode()` bleibt rein syntaktisch (Kleinschreibung + Aliasse).

  Ergebnis an der echten Liste: aus 110 rohen Codes werden 109 interne
  (`ZH`/`ZH-HANS` fallen über die Alias-Tabelle zusammen), nach der Bereinigung
  107 - weggefallen sind genau `de-de` und `fr-fr`. Englisch und Portugiesisch
  stehen jetzt gleich da: Basissprache plus die Varianten, die der Anbieter
  tatsächlich unterscheidet.

  Regressionstest auf 11 Fälle erweitert, darunter ausdrücklich der Fall, den
  Build 187 falsch machte: ohne Basissprache in der Liste bleibt die Eigenregion.
* **Build 187 (live gemeldet): trotz Build 186 standen Deutsch und Französisch
  doppelt in der Sprachauswahl.**
  Ursache war nicht die Normalisierung, sondern DeepL selbst: die Liste führt die
  Basissprache **und** ihre gleichnamige Eigenregion als getrennte Einträge -
  `DE` und `DE-DE` heißen beide "German", `FR` und `FR-FR` beide "French". Zwei
  Zeilen mit identischem Namen, nicht auseinanderzuhalten.

  Eine Region, die der Sprache selbst entspricht, trägt keine Information:
  `de-de` **ist** `de`. `NormalizeLanguageCode()` streicht sie deshalb. Fremde
  Regionen bleiben unangetastet - `de-ch`, `fr-ca`, `pt-br`, `en-gb`, `en-us`,
  `es-419` und `zh-tw` sind echte, eigene Zielsprachen.

  `PT-PT` fällt dabei bewusst auf `pt`: DeepL kennt gar kein einfaches `PT`,
  europäisches Portugiesisch ist dort die Basissprache. Bliebe es eigenständig,
  stünde Portugiesisch wieder zweimal da - einmal als eingebautes `pt`, einmal
  als `pt-pt`.

  Gegenprobe an der **echten** Liste (110 Zielsprachen, live abgefragt): genau
  drei Paare werden zusammengeführt - `DE`/`DE-DE`, `FR`/`FR-FR` und
  `ZH`/`ZH-HANS` -, aus 110 rohen werden 107 interne Codes. Kein vierter Fall,
  keine Sprache verschwindet.

  Nebenbefund: **DeepL bietet inzwischen 110 Zielsprachen**, nicht mehr die gut
  30 von früher. Die Annahme "DeepL kann deutlich weniger als Google" stimmt
  nicht mehr.

  Regressionstest `test_language_code_normalization.php` um drei Fälle erweitert
  (11 gesamt), darunter die Abgrenzung in beide Richtungen: die Eigenregion muss
  fallen, eine fremde Region darf es **nicht** - der umgekehrte Fehler wäre der
  schlimmere, dabei verschwände stillschweigend eine Sprache aus der Auswahl.
* **Build 186 (beim Testen aufgefallen): Google und DeepL lieferten dieselbe
  Sprache in unterschiedlicher Schreibweise - jetzt gilt intern genau eine.**
  Google liefert klein und regionslos (`de`, `en`), DeepL groß und für
  Englisch/Portugiesisch nur mit Region (`DE`, `EN-GB`, `PT-BR`). Beides wurde
  **wortwörtlich** übernommen und wortwörtlich als `target_lang` weitergereicht.
  Das hatte zwei Folgen:

  * `GetKnownLanguages()` mischt die eingebaute Liste mit der geholten. Mit einem
    DeepL-Key standen dadurch **20 der 30 eingebauten Sprachen doppelt** in der
    Auswahl - einmal `de`, einmal `DE`.
  * Wer erst nur DeepL einträgt und später einen Google-Key ergänzt, bekommt die
    Liste plötzlich in der anderen Schreibweise. Die bereits gewählten
    Zielsprachen kommen darin nicht mehr vor und müssen neu gewählt werden -
    **ohne dass der Nutzer etwas umgestellt hätte**. Der Hinweis am Dropdown
    beschrieb das zwar, aber als unvermeidliche Eigenschaft.

  Intern gilt jetzt genau eine Schreibweise: klein, Region mit Bindestrich
  (`de`, `en-gb`). `NormalizeLanguageCode()` bildet beim Einlesen darauf ab,
  `LanguageCodeForProvider()` beim Hinausgehen zurück - Google ohne Region
  (`en`), DeepL groß mit Region (`EN-GB`), MyMemory gemischt (`en-GB`).

  Der Eingriff blieb klein, weil Sprachcodes das Modul an nur **sieben** Stellen
  berühren: zwei Eingänge (die beiden Sprachlisten) und fünf Ausgänge. Sämtliche
  Übersetzungsaufrufe laufen durch dieselben drei `TranslateChunk*`-Funktionen,
  es gibt keinen zweiten Pfad an ihnen vorbei.

  Die Doppelliste verschwindet dabei nicht durch Aufräumen, sondern **von
  selbst**: beide Quellen liegen jetzt im selben Codesatz, und `$byCode[$code]`
  fällt zusammen. Ergänzt wurden außerdem die sechs Sprachen, die DeepL kennt und
  die eingebaute Liste bisher nicht (`bg`, `et`, `lt`, `lv`, `sk`, `sl`) -
  MyMemory kann sie ebenfalls, sie sind also auch ohne bezahlten Anbieter
  sinnvoll wählbar. Norwegisch (DeepLs `NB`) wird auf das eingebaute `no`
  gelegt, sonst stünde es zweimal da.

  Eine Datenmigration gibt es bewusst nicht: verkauft ist noch keine Version,
  und Sprachcodes sind hier nicht nur Werte, sondern **Spaltennamen** in sieben
  Listen-Properties. Auf einer gelebten Installation hätte die Umstellung jede
  Zeile umschlüsseln müssen, samt einer Regel für den Fall, dass `DE` und `de`
  beide gefüllt sind. Genau dieser Teil entfällt im jetzigen Zeitfenster.

  Regressionstest `test_language_code_normalization.php` (8 Fälle, darunter die
  strukturelle Zusicherung, dass kein Code mehr roh an eine API geht - ein
  einziger übersehener Aufruf hätte den Fehler wieder eingeschleppt, sichtbar
  erst live an einer fehlgeschlagenen Anfrage).
* **Build 185 (Symcon-Review): die Quelltexte des Konfigurationsformulars sind
  jetzt Englisch, Deutsch ist eine Übersetzung wie jede andere.**
  Vorher war es umgekehrt - die Texte standen auf Deutsch in `form.json`, und
  `locale.json` bot `en`/`es`/`it`/`fr` an, aber **kein `de`**. Symcon geht die
  Sprachliste des Browsers durch und nimmt die erste Sprache, für die eine
  Sektion existiert. Ein deutscher Browser meldet typischerweise
  `de-DE, de, en-US, en`: `de` fehlte, also griff `en` - und der deutsche
  Nutzer sah das Modul auf **Englisch**. Nur wer gar kein Englisch in seiner
  Browser-Liste führt, sah den unübersetzten Quelltext und damit zufällig das
  Richtige. Deshalb fiel es hier nie auf.

  Die Umstellung lief mechanisch, nicht von Hand: die englischen Formulierungen
  existierten ja bereits als Übersetzung. Neuer Quelltext = bisheriger
  `en`-Wert, neue `de`-Sektion = bisheriger deutscher Schlüssel, `es`/`it`/`fr`
  auf die englischen Schlüssel umgehängt. Voraussetzung dafür war, dass die
  Zuordnung eindeutig umkehrbar ist - **keine zwei deutschen Texte teilten
  sich eine englische Übersetzung**, sonst wäre beim Umschlüsseln stillschweigend
  eine Zeile verloren gegangen.

  Zwei Dinge brauchten Handarbeit: die zur Laufzeit zusammengesetzten
  Beschriftungen (`'Automatic (' . Label . ')'` in `BuildCatalogOptions()`,
  die Pro-Suffixe, die Fortschrittstexte) und `Favorites`, das bisher als
  einziger Text überhaupt keine Übersetzung hatte.

  Dabei fielen zwei tote `locale.json`-Einträge auf (`Zusatzfunktionen`,
  `keine`). Sie galten bisher als "in Benutzung", weil der Dead-Code-Test die
  Schlüssel als Teilzeichenkette suchte und dabei einen **PHP-Kommentar** traf.
  Mit englischen Schlüsseln griff diese Zufallstreffer-Logik nicht mehr, und
  beide standen als das da, was sie sind.
* **Build 185 (Symcon-Review): der Store-Name trägt kein "for IP Symcon" mehr,
  und das Funktions-Präfix heißt `SLOC` statt `IPSSL`.**
  Symcon lässt "Symcon"/"IPS" im Namen eines Store-Moduls nicht zu - im Store
  ist der Bezug ohnehin selbstverständlich. Betroffen war nur `library.json`;
  der Modulname in `module.json` hieß bereits "Simple Locale".

  **Achtung, das ist ein Bruch:** sämtliche öffentlichen Befehle heißen jetzt
  `SLOC_…` statt `IPSSL_…` - `SLOC_Rescan()`, `SLOC_TranslateText()`,
  `SLOC_SetLanguage()` und die übrigen aus [Abschnitt 9](#9-php-befehlsreferenz).
  Bestehende eigene Skripte müssen entsprechend angepasst werden.

  Mit umgestellt sind auch zwei **persistierte** Bezeichner, bei denen das
  Folgen hat: das Präfix der per `RegisterTimer()` angelegten Timer-Idents und
  der Name der privaten Variablenprofile aus Build 164, auf die vorhandene
  Variablen per `IPS_SetVariableCustomProfile` zeigen. Auf einer bereits
  gelebten Installation bliebe dadurch ein Timer mit totem Callback zurück und
  ein verwaistes Profil. Zum Zeitpunkt der Umstellung existierten ausschließlich
  eigene Testinstanzen, die neu angelegt wurden - deshalb bewusst der saubere
  Schnitt statt eines dauerhaften Altlast-Präfixes.

  Regressionstest `test_function_prefix.php` (6 Fälle, darunter die stille
  Falle: Symcon speichert den Timer-Callback als Text, ein zum Präfix
  unpassender Name fällt beim Registrieren nirgends auf und der Timer liefe
  danach stumm ins Leere).
* **Build 185 (Symcon-Review): `ApplyChanges()` schreibt die eigene Konfiguration
  nicht mehr nach - die Umstellung der aktiven Sprache läuft über `Migrate()`.**
  Stand in `CurrentLanguage` noch die interne Pseudo-Sprache `ORIGINAL_IMPORT`,
  schrieb `ApplyChanges()` sie per `IPS_SetProperty` + `IPS_ApplyChanges` auf die
  Quellsprache um - ein Reentry in den eigenen Konfigurationslauf, laut Review nur
  für Ausnahmefälle gedacht. Zuständig ist `Migrate()`.

  Die Stelle war allerdings nicht nur Migration, sondern auch für **brandneue**
  Instanzen tragend: der Registrierungs-Default war ebenfalls der Sentinel, und
  der ist seit Build 79 keine Option des Selects mehr - ohne die Normalisierung
  hätte Symcon das Speichern verweigert (`Current value ... is not available`,
  siehe Build 142). `Migrate()` läuft bei einer neuen Instanz aber gerade nicht.
  Deshalb drei Änderungen, die nur zusammen tragen:

  * **`Migrate()`** schreibt den Wert für bestehende Instanzen um und gibt sonst
    einen leeren String zurück ("keine Änderung nötig").
  * **Der Registrierungs-Default** ist jetzt die Quellsprache (`'de'`, derselbe
    Literalwert wie bei `SourceLanguage`) und damit von sich aus gültig:
    `EnsureSourceLanguageIsTarget()` trägt sie bei jedem `ApplyChanges()` als
    echten Zielsprachen-Eintrag nach, und Symcon ruft `ApplyChanges()` direkt
    nach `Create()` auf.
  * **`RequestAction()`** bildet den Sentinel am Eingang auf die Quellsprache ab,
    bevor er irgendwohin geschrieben werden kann. Eine eigene Kachel kann ihn
    schicken - bis Build 183 tat das mitgelieferte Beispiel genau das. Die
    Sperrfrist verhält sich unverändert: eine Rückkehr auf das Original hat sie
    nie gestartet, und das bleibt so.

  In `ApplyChanges()` bleibt genau eine Schreibstelle auf `CurrentLanguage`: die
  Selbstheilung aus Build 142, wenn der Code gar nicht (mehr) unter den
  Zielsprachen ist. Das ist keine Migration, sondern greift genau dann, wenn der
  Admin gerade die aktive Zielsprache entfernt hat - und ohne sie ließe sich die
  Instanz danach nicht mehr speichern.

  Regressionstest `test_migrate_current_language.php` (7 Fälle, darunter die
  beiden Lücken, die `Migrate()` allein **nicht** schließt: die neue Instanz und
  die eigene Kachel).
* **Build 184: eigene Kacheln kennen jetzt die Konfiguration -
  `<!--AVAILABLE_LANGUAGES-->` und `<!--ACTIVE_LANGUAGE-->`.**
  Bis dahin musste ein eigenes Template die Sprachcodes fest eintippen. Das ging
  regelmäßig an den tatsächlich konfigurierten Zielsprachen vorbei - genau die
  Fehlerquelle, für die Build 175 den Gast-Hinweis nötig machte. Beide
  Platzhalter liefern JSON: eine Liste aus `{code, name, current}` bzw. den Code
  der aktiven Sprache, siehe [Abschnitt 7](#7-visualisierung).

  Drei Dinge, die beim Einbau auffielen und mitbehoben wurden:

  * Die Sperre saß an der falschen Ebene. `<!--AVAILABLE_LANGUAGES-->` hing
    zunächst selbst an `custom_tile` und setzte ohne das Feature einen
    **Klartextsatz** ein - der landet in einem Template aber typischerweise
    direkt in einer JS-Zuweisung (`var langs = <!--AVAILABLE_LANGUAGES-->;`),
    also ein Syntaxfehler, der das komplette Skript mitreißt, inklusive einer
    eigenen `handleMessage()`. Ausgesperrt hätte sie ohnehin niemanden: eigenes
    Kachel-HTML wirkt sich überhaupt nur mit `custom_tile` aus, ein Anwender
    ohne das Feature kann den Platzhalter also gar nicht erst einschleusen. Der
    einzige andere Weg in die Kachel ist ein mitgeliefertes Editions-Design -
    und genau die wären leer geblieben, obwohl sie nicht von Anwendern stammen.
    Der Platzhalter ist deshalb ungesperrt; der Aufbau liegt jetzt in
    `BuildAvailableLanguagesJson()`, und die **Funktion**
    `SLOC_GetAvailableLanguages()` bleibt hart gesperrt - sie ist der Weg, eine
    eigene Auswahl per Skript an der Kachel vorbei zu bauen, wo keine
    vorgelagerte Prüfung greift.
  * `ORIGINAL_IMPORT` wäre nach außen gedrungen - der Registrierungs-Default der
    Property *ist* der Sentinel, und ein Wechsel zurück aufs Original schreibt
    ihn kurzzeitig hinein. Wird auf die Quellsprache abgebildet, damit genau der
    Wert, den Build 183 aus dem Beispielcode entfernt hat, nicht durch die
    Hintertür zurückkommt.
  * Die beiden Namen standen zunächst mit in der Suchliste von
    `ApplyTranslationStatsPlaceholders()`. Dort ersetzt `str_replace()` über zwei
    parallele Arrays - sechs Platzhalter gegen vier Werte, und PHP füllt still
    mit Leerstring auf. Folgenlos nur, weil beide vorher schon ersetzt sind:
    toter Code mit scharfer Kante. Zurückgebaut.

  **Live statt eingefroren.** Beide Platzhalter werden beim Rendern eingesetzt,
  und `GetVisualizationTile()` läuft nur einmal - ein Template hätte nach dem
  ersten Klick die falsche Flagge hervorgehoben. Deshalb trägt `REFRESH` jetzt
  zusätzlich `activeLanguage` und `languages` und geht **auch an Vorlagen ohne**
  `<!--LANGUAGE_SELECT-->`. Der in Build 179/180 gefundene zerstörerische Teil
  war immer nur das Feld `html`; genau das wird jetzt weggelassen, statt die
  ganze Nachricht zu verwerfen. Beide Handler prüfen es einzeln, fehlt es, wird
  nichts gelöscht.

  Abgeholt wird das über eine **optionale** Funktion des Templates,
  `window.slocOnLanguageChange(activeLanguage, availableLanguages)`. Wer sie
  nicht definiert, merkt keinen Unterschied. Sie erreicht auch ein Template mit
  **eigenem** `handleMessage`: ein aus einer älteren `module.html` abgeleitetes
  Template brachte bisher einen Handler ohne den Haken mit und wurde deshalb
  übersprungen - der wahrscheinlichste Weg, auf dem ein bestehender Pro-Nutzer
  den neuen Platzhalter benutzt. `EnsureLanguageChangeHook()` legt den Haken
  darum herum, statt den fremden Handler zu ersetzen; er wird unverändert weiter
  aufgerufen.

  Die laufende Nummer aus Build 176 gilt jetzt für beide Nachrichtenarten
  (`tileMessageSequence`): ohne `html` ist eine REFRESH-Nutzlast bei einem
  **abgelehnten** Wechsel identisch zur vorigen - und eine identische Nutzlast
  löst in der Kachel gar kein Ereignis aus, weil
  `UpdateVisualizationValue()` einen Wert setzt, keine Nachricht.

  `<!--ACTIVE_LANGUAGE-->` läuft bewusst über die **öffentliche** Funktion
  `GetCurrentLanguageCode()` statt über einen eigenen Lesepfad - sonst könnte
  ein Template etwas anderes anzeigen, als ein Skript daneben ausliest. Sie war
  bereits vorhanden und ungesperrt; [Abschnitt 7](#7-visualisierung) führt sie
  jetzt auch bei der komplett eigenständigen Kachel auf, wo sie genauso
  gebraucht wird.

  Regressionstest `test_config_placeholders.php` (8 Fälle, darunter die
  Symmetrie: Ladezeit-Wert und Live-Aktualisierung müssen aus derselben Quelle
  kommen, sonst wäre der Unterschied nur live zu sehen); `test_no_refresh_without_selector.php`
  und `test_tile_message_handler_injected.php` auf den neuen, engeren Zuschnitt
  nachgezogen.
* **Build 183: der mitgelieferte Beispielcode für die eigene Sprachauswahl-Kachel
  schickte für Deutsch `ORIGINAL_IMPORT`.**
  Das Beispiel aus `GetDefaultCustomLanguageSelectHtml()` ist der Startwert des
  Feldes - jeder Pro-Kunde bekommt es vorbefüllt zu sehen und baut sein eigenes
  Design typischerweise darauf auf. Es trug damit ausgerechnet den Sentinel nach
  außen, der seit Build 175 als modulintern aus der Anleitung für eigene Kacheln
  entfernt ist: das Beispiel lehrte das Gegenteil der Dokumentation.

  Jetzt steht dort der Sprachcode selbst (`'de'`). Das ist gleichwertig, nicht
  nur kosmetisch: `IsSelectableGuestLanguage()` lässt die Scan-Sprache in einem
  eigenen Zweig neben `langOriginalImport` durch, und
  `IsLanguageSwitchRateLimited()` nimmt sie ebenso ausdrücklich vom Tagesschalter
  aus - ein Klick auf die Scan-Sprache verhält sich also identisch.

  Der Kommentar darüber sagte außerdem noch, ein nicht konfigurierter Sprachcode
  werde "ignoriert". Seit Build 175 stimmt das nicht mehr: die aktive Sprache
  bleibt stehen, und der Gast bekommt einen Hinweis in der Kachel. Ergänzt wurde
  zudem, dass die Scan-Sprache immer als Zielsprache mitgeführt wird und `'de'`
  entsprechend anzupassen ist, wenn sie eine andere ist.

  Regressionstest `test_default_custom_tile_example.php` (4 Fälle, darunter die
  Zusicherung der Freistellung der Scan-Sprache auf **beiden** Wegen - ohne die
  wäre der Wechsel weg von `ORIGINAL_IMPORT` eine Verschlechterung).
* **Build 182: die Ausweich-Protokollierung im `MessageSink` ist stillgelegt -
  Fehler erscheinen dort wieder rot als "FEHLER".**
  Bis Build 181 wich `LogTranslateMessage()` im `MessageSink`-Kontext auf die
  globale `IPS_LogMessage()` aus, weil dort einmal (17.08.2026, live beobachtet)
  *"Warning: InstanceInterface is not available"* auftrat. Das kostete den
  Schweregrad: `IPS_LogMessage()` kennt keinen, die Meldung erschien als graues
  "Custom" mit Text-Präfix statt als rotes "FEHLER" - ausgerechnet im Pfad der
  Live-Nachübersetzung.

  Nachgestellt wurde der Fehler anschließend in einem eigenen Minimalmodul
  ([TestIPSLogMessage](https://github.com/AllardLiao/TestIPSLogMessage)) in vier
  Konstellationen: schlichtes Loggen im `MessageSink`; zusätzlich
  `IPS_SetProperty` + `IPS_ApplyChanges` auf die **eigene** Instanz von dort
  aus; zusätzlich Zurückschreiben in die überwachte Variable, also verschachtelte
  Zustellung derselben Nachricht; und ein zehn Sekunden langer Durchlauf.
  Einzeln und kombiniert - **durchweg fehlerfrei**.

  Die Zuordnung war also vermutlich falsch: die Warnung fiel zeitlich mit dem
  Log-Aufruf zusammen, stammte aber wohl von woanders. Passend dazu definiert
  dieses Modul überhaupt kein Interface.

  Der Rückbau läuft über eine Konstante (`LOG_VIA_GLOBAL_IN_MESSAGE_SINK`), die
  Ausweichlogik bleibt **vollständig im Code**: taucht die Warnung je wieder
  auf, stellt ein Wort den alten Weg wieder her.

  Regressionstest `test_log_severity_restored.php` (5 Fälle, darunter die
  Zusicherung, dass der alte Weg erhalten und mit einem Wort umkehrbar bleibt).
* **Build 181 (live gemeldet): die Instanz stand dauerhaft auf "Übersetzung
  fehlgeschlagen", obwohl gar nichts fehlgeschlagen war.**
  "Wenn ich die Lizenz aktiviere meldet sie direkt *aktiv* - kurz danach aber
  wieder *fehlgeschlagen*." Im Debug-Dump liefen alle 47 Übersetzungen sauber
  durch; im Meldungs-Log stand die Ursache eine Zeile höher: einzelne Texte
  überschritten MyMemorys **500-Byte-Grenze** und wurden übersprungen.

  Da nur der kostenfreie Anbieter in der Kette steht, galt ein Chunk aus lauter
  übersprungenen Texten als komplett gescheitert - und das setzte den
  Instanz-Fehlerstatus. Doppelt falsch: die Meldung lautet "kein Anbieter war
  erreichbar", obwohl MyMemory sauber geantwortet hat, und der Zustand war nicht
  abstellbar, weil jeder Lauf ihn neu setzte. An der Textlänge ändert kein
  Wiederholungsversuch etwas.

  Der Längen-Wächter liefert bewusst `''` statt `null` - der Text ist nicht
  fehlgeschlagen, er wurde übersprungen. Diese Unterscheidung wird jetzt bis nach
  oben durchgereicht: scheiterte **jeder** Text ausschließlich an der Grenze,
  wird eine Warnung geloggt und **kein** Fehlerstatus gesetzt. Ein einziger
  echter Fehlschlag genügt weiterhin für den Status, und ein bezahlter Anbieter
  kennt diese Grenze ohnehin nicht - schlägt er fehl, ist es ein echter Ausfall.

  Sichtbar bleibt es trotzdem: als Warnung im Log und als eigene Zeile im
  Formular (Build 152), die zum richtigen Mittel rät - einem Google-/DeepL-
  Schlüssel ohne diese Grenze. Die übersprungenen Texte bleiben leer und werden
  später erneut versucht, sobald ein solcher Anbieter da ist.

  Regressionstest `test_too_long_is_not_an_outage.php` (6 Fälle).
* **Build 180: kein Neuzeichnen mehr an Vorlagen, die es nicht vertragen -
  diesmal an der Quelle.**
  Build 179 hatte das zerstörerische Neuzeichnen für den vom Modul **ergänzten**
  Handler abgestellt. Wer aber `module.html` als Vorlage nimmt und nur
  `<!--LANGUAGE_SELECT-->` durch eigenes Markup ersetzt - der naheliegendste Weg
  überhaupt -, bringt den Handler **selbst** mit. Er wird also nicht ergänzt, und
  seine `REFRESH`-Behandlung hätte weiterhin den Inhalt des Wrappers gelöscht.

  Jetzt entscheidet die sendende Seite: benutzt die aktive Vorlage den
  Platzhalter nicht, wird gar kein `REFRESH` mehr geschickt. Was nicht gesendet
  wird, kann nichts zerstören - unabhängig davon, welcher Handler in der Kachel
  sitzt. Die Gast-Hinweise laufen über `ALERT` und sind davon unberührt.

  Ebenfalls in diesem Build: Abschnitt 7 bekommt einen Kasten **für Autoren einer
  gelieferten Vorlage** mit den drei Punkten, über die diese Runde gestolpert ist
  - kein `margin`/`padding` auf `body` (Symcon reserviert dort den Platz für
  Titel und Vergrößern-Symbol), `<!--WRAPPER_ID-->` und `<!--LANGUAGE_SELECT-->`
  gehören auf ein Element, das sonst nichts enthält, und was es bedeutet, den
  Platzhalter wegzulassen.

  Regressionstest `test_no_refresh_without_selector.php` (4 Fälle, darunter die
  Zusicherung, dass die Gast-Hinweise NICHT an dieser Bedingung hängen).
* **Build 179 (live gemeldet): das Popup zerlegte die Kachel.**
  "Sobald das Popup aufpoppt wird das Tile zerstört - ein Refresh bringt das
  korrekte zurück."

  Das Modul zeichnet die Kachel vor jeder Ablehnung neu, und `REFRESH` ersetzt
  den **kompletten Inhalt** des Elements mit `<!--WRAPPER_ID-->` durch die
  Sprachauswahl. In `module.html` steht dort auch genau nur sie. Eine gelieferte
  Vorlage kann die ID aber am **äußeren** Element tragen und daneben eigenes
  Layout enthalten - genau das wurde weggelöscht. Ein Seiten-Reload stellte es
  wieder her, weil dann das Original-HTML neu gerendert wurde.

  Doppelt sinnlos war es obendrein: eine Vorlage **ohne**
  `<!--LANGUAGE_SELECT-->` baut ihre Auswahl selbst, es gibt gar nichts
  nachzuzeichnen. Der ergänzte Handler kennt deshalb jetzt nur noch die
  Nachrichten, die zur Vorlage passen - `ALERT` immer, `REFRESH` nur, wenn die
  Vorlage den Platzhalter tatsächlich benutzt.

  Entschieden wird das am **Original**, vor den Ersetzungen: danach steht der
  Platzhalter ja nicht mehr im Dokument, und die Prüfung wäre immer negativ.

  *Für Vorlagen-Autoren:* eine gelieferte Vorlage ist die komplette Hülle, kein
  Einsatz - sie ersetzt `module.html` vollständig und wird **nicht** an
  `<!--LANGUAGE_SELECT-->` eingesetzt. Wer die Auswahl selbst baut, lässt den
  Platzhalter weg; wer die eingebaute will (und das Nachzeichnen inklusive der
  laufenden Statistik-Zähler), führt `<!--WRAPPER_ID-->` und
  `<!--LANGUAGE_SELECT-->` gemeinsam auf einem Element auf, das sonst nichts
  enthält.

  Regressionstest `test_refresh_only_with_selector.php` (6 Fälle).
* **Build 178 (live gefunden): eine vom Server gelieferte Kachel-Vorlage bekam
  weder Popups noch das automatische Neuzeichnen.**
  Das Modul schickt der Kachel zwei Arten von Nachrichten - `REFRESH` (Auswahl
  neu zeichnen) und `ALERT` (Gast-Hinweise: Testphase, Sprachwechsel-Limit,
  unbekannter Sprachcode). Verarbeitet werden sie von `handleMessage()`, und die
  stand ausschließlich in `module.html`. Eine gelieferte Vorlage (Build 172)
  ersetzt aber die **komplette Hülle**: wer ein Design anlegt, hatte damit
  unbemerkt sämtliche Hinweise und das Neuzeichnen abgeschaltet.

  Live genau so aufgetreten: der Wechsel auf einen nicht konfigurierten
  Sprachcode wurde korrekt abgelehnt, die Ablehnung stand im Log - in der Kachel
  geschah nichts.

  Die Verdrahtung ist Sache des Moduls, nicht des Designers: fehlt einer Vorlage
  der Handler, ergänzt ihn das Modul. Bringt sie einen eigenen mit, bleibt sie
  unangetastet. Fehlt das Ziel-Element fürs Neuzeichnen (die Vorlage nutzt
  `<!--WRAPPER_ID-->` nicht), wird nur dieser Teil still übersprungen - die
  Hinweise kommen trotzdem an.

  *Zur Fehlersuche:* die Spur führte zunächst in die falsche Richtung, weil die
  Felder für eigenes Kachel-HTML gefüllt aussahen. Sie tragen aber nur die
  Vorbefüllung und werden bei ausgeschaltetem "Eigene Sprachauswahl-Kachel
  verwenden" gar nicht verwendet - gerendert wurde die gelieferte Vorlage.

  Regressionstest `test_tile_message_handler_injected.php` (6 Fälle).
* **Build 177 (an einem Kunden-Template aufgefallen): `<!--WRAPPER_ID-->` blieb
  in einer eigenen Sprachauswahl wörtlich stehen.**
  `ApplyTilePlaceholders()` ersetzte `<!--WRAPPER_ID-->` **vor**
  `<!--LANGUAGE_SELECT-->`. Zum Zeitpunkt der Ersetzung war das eigene
  Sprachauswahl-HTML also noch gar nicht im Dokument - sein Platzhalter wurde nie
  gesehen und landete unverändert in der Ausgabe.

  `<!--TILE_ICON-->` und die vier Zähler standen schon immer **nach** der
  Sprachauswahl und funktionierten deshalb in beiden Feldern. Genau diese
  Ungleichbehandlung war der Fehler: die Sprachauswahl wird jetzt zuerst
  eingesetzt, danach laufen alle übrigen Platzhalter über das fertige Dokument.

  Regressionstest `test_placeholder_order.php` (5 Fälle, darunter der
  Symmetrie-Check auf die Reihenfolge selbst - sie ist der ganze Fix).
* **Build 176 (live gemeldet): Gast-Popups erschienen nur beim ersten Mal.**
  Gemeldet für den neuen Hinweis bei unbekanntem Sprachcode - betroffen waren
  aber **alle drei** Gast-Popups (Testphase, Sprachwechsel-Limit, unbekannte
  Sprache).

  `UpdateVisualizationValue()` setzt einen **Wert**, keine Nachricht. Ein Wert,
  der sich nicht ändert, löst in der Kachel kein Ereignis aus. Zweimal dieselbe
  Ablehnung - gleicher ungültiger Sprachcode, gleiche Meldung - ergab eine
  byteweise identische Nutzlast: das Popup erschien einmal und danach nie wieder.
  Beim Testen fällt genau das auf, weil man den Fall wiederholt.

  Alle drei laufen jetzt über einen gemeinsamen Sender, der jeder Nutzlast eine
  laufende Nummer mitgibt. Bewusst ein **Zähler** und nicht nur ein Zeitstempel:
  zwei Versuche innerhalb derselben Sekunde wären sonst wieder identisch. Die
  Kachel muss davon nichts wissen - `handleMessage()` ignoriert unbekannte
  Felder, `module.html` bleibt unverändert.

  Regressionstest `test_tile_alert_repeats.php` (6 Fälle: der gemeldete Fall; der
  Fix; die Zeitstempel-Falle; der angezeigte Text bleibt gleich; Symmetrie-Check
  inklusive der Zusicherung, dass genau eine Stelle die ALERT-Nutzlast baut und
  kein Pfad den gemeinsamen Sender umgeht).
* **Build 175 (vier Nutzer-Wünsche): Rückmeldung bei Serverproblemen, Gast-Hinweis
  bei unbekannter Sprache, `ORIGINAL_IMPORT` aus der Custom-Tile-Doku, und ein
  neues Editions-Design wird gleich aktiv.**

  *Der Aktivierungsknopf war bei einem Serverproblem stumm.* Es erschien
  kommentarlos "Lizenz gültig", obwohl die Bestätigung beim Server ausgeblieben
  war - genau die Situation, in der man eine Rückmeldung braucht. Jetzt sagt ein
  eigenes Popup, dass die Aktivierung nicht bestätigt werden konnte und man es
  später erneut versuchen soll **und** dass die Lizenz davon unberührt bleibt:
  lokal geprüft, gültig, das Modul arbeitet vollständig weiter. Beide Popups
  schließen sich gegenseitig aus, sonst wäre die Aussage widersprüchlich.

  *Fehlschläge der Serverkommunikation landen jetzt im Symcon-Log*, als echte
  Fehlermeldung statt nur im Debug-Fenster - der Nutzer soll sehen, dass etwas
  klemmt, auch wenn es täglich klemmt. **Bewusst ohne Instanz-Fehlerstatus:** ein
  nicht erreichbarer Meldeserver ist eine Randnotiz, kein Betriebsausfall.
  Geloggt wird über `LogTranslateMessage()` statt über `$this->LogMessage()` -
  der Aufruf kann innerhalb von `MessageSink` landen (`IM_CHANGESETTINGS` →
  `IPS_ApplyChanges` → passiver Melde-Pfad), und dort scheitert die geerbte
  Methode nachweislich (siehe Build 130).

  *Ein Gast, der eine nicht konfigurierte Sprache anfordert, bekommt jetzt ein
  Popup.* Der Fall wird seit Build 142 sauber abgefangen, war dem Gast gegenüber
  aber stumm: er klickte, nichts geschah, die Erklärung stand im Debug-Log, das
  er nie sieht. Typisch bei einer eigenen Sprachauswahl mit fest eingetragenen
  Codes, die nicht mehr zu den Zielsprachen passen. Übersetzt wird in die
  **aktuell aktive** Sprache, nicht in die angeforderte - die ist ja gerade die
  unbekannte.

  *`ORIGINAL_IMPORT` ist aus der Custom-Tile-Dokumentation verschwunden.* Der
  Wert ist seit Build 79 keine wählbare Gast-Sprache mehr und rein modulintern;
  in einer eigenen Kachel hat er nichts zu suchen. Die Beispiele nutzen jetzt die
  Scan-Sprache (`de`), die ohnehin immer als Zielsprache mitgeführt wird und
  denselben Zweck erfüllt.

  *Ein erstmals eingetroffenes, editionsgebundenes Design wird gleich aktiv
  gesetzt* - der Käufer soll sein Design sehen, ohne es zu suchen. **Nur beim
  ersten Mal:** kommt dasselbe Design bei einer späteren Aktivierung erneut mit,
  bleibt die Auswahl unangetastet. Was der Kunde einmal weggeklickt hat, soll
  weggeklickt bleiben. Editionslose Designs stellen nie etwas um.

  Regressionstest `test_activation_feedback_and_autoselect.php` (7 Fälle).
* **Build 174 (live gefunden): ein HTTP-Fehlerstatus galt als erfolgreiche
  Meldung - dadurch kamen Kachel-Designs dauerhaft nie an.**
  Symbol und Vorlage einer Edition tauchten nicht in den Auswahlfeldern auf,
  obwohl der Server sie nachweislich auslieferte (HTTP 200 mit `assets` im JSON).

  Die Kette: beim ersten Aktivieren antwortete der Endpunkt mit **HTTP 500**
  (eine fehlende `require`-Zeile auf der Website). `CallActivationReportAPI()`
  wertete aber alles außer einem Transportfehler als "angekommen" - und eine 500
  liefert eine nicht-leere Fehlerseite als Body. Der Schlüssel galt damit als
  erfolgreich gemeldet. Seitdem lief jeder Klick über den `statusOnly`-Pfad, wo
  der Server die Designs bewusst weglässt. **Das konnte sich nie von selbst
  auflösen:** Designs reisen nur mit einer echten Aktivierung mit, und die findet
  je Schlüssel genau einmal statt.

  *Erste Reparatur:* "Erfolg" heißt jetzt **verwertbare Antwort**, nicht bloß
  empfangene Bytes - ein Status ab 400 zählt als nicht gemeldet. Damit greift das
  Nachholen aus Build 170 wieder, und ein defekter Endpunkt kann eine Meldung
  nicht mehr dauerhaft verschlucken.

  *Zweite Reparatur:* der ausdrückliche Klick auf "Lizenz aktivieren/
  aktualisieren" fordert die Designs zusätzlich an (`withAssets`), ohne
  `statusOnly` aufzuheben - es wird also **keine** weitere Aktivierung
  eingetragen. Ohne diesen Weg bliebe eine Instanz, deren Schlüssel längst
  gemeldet ist, dauerhaft ohne Designs. Die tägliche Prüfung fragt bewusst nicht
  danach und bleibt klein.

  Regressionstest `test_activation_report_http_error.php` (6 Fälle: die 500 gilt
  nicht mehr als Erfolg, die leere 204 weiterhin schon; die Meldung bleibt danach
  offen; der Klick holt Designs ohne neue Aktivierung; die Tagesprüfung bleibt
  klein; eine echte Aktivierung bekommt sie wie bisher; Symmetrie-Check inklusive
  der Zusicherung, dass `withAssets` `statusOnly` nicht aufhebt).
* **Build 173 (Nutzer-Frage): das gewählte Symbol lässt sich jetzt einzeln in
  ein eigenes Template setzen - `<!--TILE_ICON-->`.**
  Die Frage lautete, wie man das Symbol in ein eigenes Template einbindet.
  Antwort war: gar nicht. Es wurde ausschließlich **innerhalb** der generierten
  Sprachauswahl gebaut (`<span class="sloc-globe">…`). Wer eine eigene
  Sprachauswahl hinterlegte, ersetzte damit den ganzen Block und verlor das
  Symbol ersatzlos - es gab keinen Weg, es zurückzuholen.

  Das traf ausgerechnet Build 172: dort werden editionsgebundene Symbole vom
  Server ausgeliefert, und ein eigenes Template hätte sie nie zeigen können. Der
  Wiedererkennungswert wäre genau dort verlorengegangen, wo am meisten gestaltet
  wird.

  Der Platzhalter respektiert die Checkbox "Symbol in der Kachel anzeigen" -
  steht sie aus, bleibt er leer, statt sie zu übergehen. Die eingesetzte
  Fassung trägt die Klasse `sloc-tile-icon` und eine Größenangabe, die **nicht
  kollabiert**: die eingebaute Kachel setzt das Symbol in einen Rahmen mit fester
  Höhe, wo `height:100%` passt - ein eigenes Template hat diesen Rahmen nicht,
  dort wäre das Symbol unsichtbar geworden. Die eingebaute Kachel behält ihre
  bisherige Angabe unverändert.

  Ebenfalls in diesem Build: **Abschnitt 7 der Dokumentation nachgebessert**
  (Nutzer-Rückmeldung, das sei "nicht so einfach" zu verstehen gewesen). Neu
  vorangestellt sind die Entscheidungslogik - welches HTML überhaupt verwendet
  wird und dass `module.html` der Rückfall ist, nicht der Träger -, der Hinweis
  auf die zwei unabhängig austauschbaren Ebenen (Hülle und Sprachauswahl) und
  eine Tabelle aller sieben Platzhalter. Vorher fing der Abschnitt direkt bei
  den Feldern des Bearbeiten-Dialogs an.

  Regressionstest `test_tile_icon_placeholder.php` (6 Fälle: der Platzhalter
  wirkt; die Checkbox wird respektiert und der Platzhalter verschwindet trotzdem;
  die Fassung kollabiert nicht ohne Elternrahmen; bestehende Templates bleiben
  unverändert; mehrfaches Vorkommen wird ersetzt; Symmetrie-Check inklusive der
  Zusicherung, dass die eingebaute Kachel ihre Style-Angabe behält).
* **Build 172 (Nutzer-Wunsch): Kachel-Symbole und -Vorlagen je Edition kommen
  jetzt vom Server - kein Modul-Release mehr pro Sonder-Edition.**
  Bis Build 171 waren `TILE_ICON_CATALOG` und `TILE_TEMPLATE_CATALOG`
  einkompilierte Konstanten, die Inhalte Dateien im Modulverzeichnis. Ein neues
  Design für eine Sonder-Edition hieß: neue Datei, neuer Katalogeintrag, neues
  Release - inklusive Begutachtung durch Symcon. Genau das sollte entfallen.

  Die Designs werden jetzt auf der Website gepflegt
  (`shop/admin/tile-assets.php`) und reisen bei der **Lizenz-Aktivierung** mit.
  Dort - und nur dort - steht fest, zu welcher Edition eine Installation gehört;
  die tägliche Statusprüfung (`statusOnly`, Build 169) lässt sie bewusst weg,
  sonst wäre sie jeden Tag für jede Installation unnötig groß.

  **Signiert, nicht bloß ausgeliefert.** Das Paket trägt dieselbe
  Ed25519-Signatur wie ein Lizenzschlüssel und wird gegen den einkompilierten
  `LICENSE_PUBLIC_KEY` geprüft; ohne gültige Signatur wird nichts gespeichert.
  Das Modul lädt also Inhalte aus dem Netz, akzeptiert aber ausschließlich, was
  mit dem privaten Offline-Schlüssel signiert wurde - ein manipulierter DNS, ein
  übernommener Webserver oder ein Man-in-the-Middle können nichts einschleusen.
  Für die Symcon-Begutachtung ist außerdem der Präzedenzfall im eigenen Modul
  wichtig: die Kachel rendert seit jeher frei editierbares HTML aus einem
  Property (`custom_tile`). Signierte Herstellervorlagen sind damit keine neue
  Fähigkeitsklasse, sondern dieselbe Renderstrecke mit einer *strengeren*
  Herkunftsprüfung.

  **Zwei Bindungen:** ein Design mit Edition geht nur an deren Käufer und wird
  von "Automatisch" **von selbst gewählt** - der Wiedererkennungswert, um den es
  bei einer Sonder-Edition geht. Ein editionsloses geht an alle und verhält sich
  wie der Standard: immer wählbar, nie automatisch.

  **Dauerhaft:** das geprüfte Paket liegt im Attribut
  `attributeTileAssetBundle`. Ein einmal ausgeliefertes Design bleibt auswählbar -
  auch ohne Netz und auch, wenn es auf der Website später entfernt wird. Ein
  eingebauter Katalogeintrag wird nie überschrieben, sonst ließe sich die Kachel
  nicht mehr auf den Auslieferungszustand zurücksetzen.

  *Bekannte Einschränkung:* die Bezeichnung eines gelieferten Designs erscheint
  in genau der Sprache, in der sie eingetragen wurde. Anders als die eingebauten
  Bezeichnungen kann sie nicht über `locale.json` übersetzt werden - dem Modul
  ist sie beim Erstellen ja nicht bekannt (siehe Build 156).

  Regressionstest `test_tile_asset_bundle.php` (7 Fälle: der Rundlauf; ein fremd
  signiertes Paket wird verworfen; nachträglich veränderter Inhalt bricht die
  Signatur; kaputte Pakete bleiben folgenlos; unvollständige Einträge werden
  aussortiert; Symmetrie-Check inklusive der Zusicherung, dass kein Aufrufer mehr
  direkt auf die Konstanten zugreift; nur editionsgebundene Designs gewinnen
  "Automatisch"). Zusätzlich wurde der Interop beider realer Implementierungen
  geprüft: die Signierfunktion der Website erzeugt ein Paket, das die exakte
  Prüfstrecke des Moduls akzeptiert.
* **Build 171 (live gemeldet, per Screenshot belegt): der Ablauf-Hinweis in der
  Kachel wurde abgeschnitten.**
  Er gab die komplette Kauf-URL als **Linktext** aus - "Deine Lizenz läuft ab am
  28.08.2026. Verlängern: https://www.synergetix.de/simplelocale/pricing.php" -
  und das umbrach auf drei Zeilen. Bei Visu-Höhe 1 ist die Kachelhöhe fest (siehe
  Build 143), der Text wurde also unten abgeschnitten; im Screenshot brach er
  mitten in "Verlängern:" ab. Der Testphasen-Hinweis daneben war schon immer
  einzeilig - nur dieser eine druckte die URL aus.

  Verlinkt wird jetzt das **Wort selbst**, die URL steckt im `title`-Attribut und
  bleibt beim Darüberfahren sichtbar. Der abschließende Doppelpunkt der Vorgabe
  ("Verlängern:") fällt weg - vor einer URL war er richtig, vor einem verlinkten
  Wort zeigt er ins Leere. Abgeschnitten wird bewusst am **Wert**, nicht an der
  Vorgabe: eine bereits vom Kunden übersetzte Zeile trägt ihren eigenen
  Doppelpunkt (siehe `GetOwnUiText`).

  Regressionstest `test_license_notice_fits_tile.php` (6 Fälle: die URL
  verschwindet aus dem sichtbaren Text und dieser wird deutlich kürzer; die URL
  bleibt Ziel und Tooltip - sonst wäre der Hinweis eine Sackgasse; der
  Doppelpunkt fällt weg; auch bei einer übersetzten Beschriftung; eine
  Beschriftung ohne Doppelpunkt bleibt unangetastet; Symmetrie-Check inklusive
  der Zusicherung, dass die URL nur noch zweimal vorkommt).
* **Build 170 (Nutzer-Hinweis): eine fehlgeschlagene Erstmeldung wurde nie
  nachgeholt.**
  Der Hinweis lautete, man könne eine "vergessene" Aktivierung doch bei der
  Gültigkeitsprüfung nachholen - es sei ja derselbe Aufruf. Vergessen kann man
  sie zwar nicht (`TrackLicenseActivationIfNew()` meldet auch beim bloßen
  "Übernehmen"), aber der Gedanke traf eine echte Lücke im **Fehlschlag**:
  `attributeLastCheckedLicenseKeyHash` wurde **vor** dem Netzwerkaufruf gesetzt,
  und der ist bewusst "fail open". War der Server in dem Moment nicht erreichbar -
  oder die Instanz offline -, galt die Meldung dauerhaft als erledigt.

  Erschwerend kam dazu: `CallActivationReportAPI()` lieferte `null` sowohl bei
  einem Netzwerkfehler als auch bei der völlig normalen leeren 204-Antwort
  ("nichts zu melden"). Erfolg und Fehlschlag waren nicht unterscheidbar - ohne
  diese Unterscheidung lässt sich ein Nachholen gar nicht bauen. `null` bedeutet
  jetzt ausschließlich "Server nicht erreicht", ein leerer String "angekommen,
  nichts zu melden".

  Darauf setzt `attributeReportedLicenseKeyHash` auf: es hält den Hash, der
  **tatsächlich** angekommen ist. Die tägliche Prüfung schickt daraufhin
  entweder die nachzuholende echte Meldung oder - im Normalfall - nur die
  `statusOnly`-Abfrage aus Build 169.

  Der **passive** Pfad (jedes "Übernehmen") wiederholt bewusst **nicht**: bei
  einem dauerhaft nicht erreichbaren Server löste sonst jeder Formular-Klick
  einen weiteren Netzwerk-Request aus. Die tägliche Prüfung ist der natürliche
  Wiederholungspunkt.

  Regressionstest `test_activation_report_retry.php` (6 Fälle: die
  Unterscheidung 204 vs. Transportfehler; ein Fehlschlag markiert nichts als
  erledigt; die Tagesprüfung holt nach; danach wieder nur Statusabfrage, damit
  Build 169 nicht zunichte wird; ein dauerhaft unerreichbarer Server wird täglich
  erneut versucht; Symmetrie-Check inklusive der Reihenfolge).
* **Build 169 (Nutzer-Frage): ein zweiter Klick auf "Lizenz aktivieren" tat
  nichts - und die Tagesprüfung meldete täglich eine Aktivierung.**
  Auf die Frage, was bei erneutem Klick mit demselben Schlüssel passiert, war die
  Antwort: nichts. `TrackLicenseActivationIfNew()` steigt bei unverändertem
  Schlüssel vorher aus (richtig, sonst gäbe es Melde-Spam). Das stand aber dem
  Kulanz-Weg im Weg: setzt der Admin "Ablaufdatum überschreiben" für eine
  Bestellung, holte das Modul den neuen Wert nicht - er kam erst mit der
  Tagesprüfung, also bis zu 24 Stunden später. Der Kunde drückte den Knopf und
  sah nichts passieren.

  Dabei fiel ein zweiter, schwererer Befund auf: die **Tagesprüfung** schickte
  dieselbe Nutzlast wie eine echte Erstaktivierung, und der Endpunkt legt pro
  Aufruf eine Zeile in `slips_license_activations` an - pro Lizenz also **jeden
  Tag** eine. Die Weiterverkaufs-Erkennung (derselbe Hash mit abweichenden
  `licensee`-Werten) ersoff in diesem Rauschen.

  Beides löst dasselbe Flag: `"statusOnly": true` fragt den Stand ab, ohne eine
  Aktivierung zu melden. Es nutzen jetzt die Tagesprüfung und der ausdrückliche
  Klick bei unverändertem Schlüssel; ein neuer oder als geblockt bekannter
  Schlüssel meldet unverändert eine echte Aktivierung.

  Das Flag ist **bewusst keine Sicherheitsgrenze** - der Endpunkt ist
  unauthentifiziert, wer sich der Registrierung entziehen wollte, müsste einfach
  gar nicht anfragen. Es verhindert nur, dass ehrliche Clients Aktivierungen
  eintragen, die sie gar nicht vornehmen.

  Der Knopf heißt jetzt **"Lizenz aktivieren/aktualisieren"** (Nutzer-Wunsch, in
  allen vier Sprachen) - der zweite Klick tut ja jetzt etwas.

  Regressionstest `test_license_status_only_refresh.php` (5 Fälle: der zweite
  Klick holt den Stand ohne erneute Aktivierung; ein neuer Schlüssel meldet wie
  bisher und fragt nicht zusätzlich ab; ein geblockter Schlüssel wird weiterhin
  neu gemeldet; die Tagesprüfung baut keine eigene Nutzlast mehr; die neue
  Beschriftung ist in allen Sprachen vorhanden und die alte überall entfernt).
* **Build 168 (Nutzer-Auftrag "prüfe auf weiteren toten Code"): drei
  nur-geschriebene Attribute und vier verwaiste Übersetzungen entfernt.**
  `attributeEffectiveRootCategoryID`, `attributeLastDailyLicenseCheckAt` und
  `attributeLicenseInfo` waren registriert und wurden beschrieben, aber **nie
  gelesen** - jeder Schreibvorgang darauf war Arbeit ohne Wirkung. Die ersten
  beiden waren als "informativ" gedacht, das dritte als Anzeige-Cache fürs
  Formular, der nie abgefragt wurde. Dazu vier `locale.json`-Schlüssel, deren
  Texte es im Formular und im Code längst nicht mehr gibt (in allen vier
  Sprachen, 16 Zeilen).

  Der Rest ist sauber: 213 private Methoden - alle aufgerufen; 148 Konstanten -
  alle verwendet.

  `PROMOTIONAL_LANGUAGE_CAMPAIGNS` **bleibt**, obwohl das Array leer ist. Der
  Mechanismus funktioniert und deckt etwas ab, das die Aktionsverwaltung auf der
  Website nicht kann: Sprachen ohne jeden Schlüssel freischalten, auch für
  Instanzen mit abgelaufener Testphase. Ein Promo-Lizenzschlüssel erreicht die
  nicht.

  Regressionstest `test_no_dead_code.php` ist zugleich ein **Dauerwächter** (6
  Fälle): kein Attribut darf nur geschrieben werden, keine private Methode ohne
  Aufrufer bleiben, keine Konstante ungenutzt, kein `locale.json`-Eintrag
  verwaisen, und alle vier Sprachen müssen denselben Schlüsselsatz tragen - eine
  Sprache, der ein Schlüssel fehlt, fällt sonst still auf Deutsch zurück.

  Die Prüfung auf verwaiste Übersetzungen vergleicht bewusst gegen die
  **dekodierten** Formularwerte und kennt die zur Laufzeit zusammengesetzten
  "Automatisch (…)"-Kombinationen (Build 156) - beides sonst sichere Fehlalarme.
* **Build 167 (Nutzer-Entscheidung): die beiden Bereinigungen für
  Bestandsinstallationen sind entfernt.**
  Das Modul ist bis heute unveröffentlicht - außer der Entwicklungsinstanz gibt
  es keine Installation mit Altobjekten. Entfallen sind daher die einmalige
  Löschung der früheren HTMLBox-Dropdown-/"Sprache"-Variable und die des toten
  Build-98-Verzögerungstimers, beides Existenz-Prüfungen, die auf einer frischen
  Instanz nie zutrafen. Ihre Konstanten sind mit weg.

  **Bewusst NICHT entfernt**, obwohl es nach Migration aussieht - diese
  Abgrenzung ist der eigentliche Inhalt des Builds:

  * `BackfillRowSourceLanguage()` und `BackfillTranslationActiveFlag()` tragen
    ebenso jede **frisch gescannte** und jede von Hand im Formular angelegte
    Zeile. Ohne sie hätte eine neue Zeile keine Quellsprache.
  * Der `sourceChangedAt === 0`-Zweig in `IsRowLanguageTranslationCurrent()`
    deckt nicht nur Altzeilen ab, sondern jede Zeile, die seit ihrer Erfassung
    nie geändert wurde. Ohne ihn gälte der komplette Bestand als veraltet und
    würde neu übersetzt - teuer und falsch.
  * `TRANSLATION_CACHE_SCHEMA_VERSION` ist keine Migration, sondern die
    Invalidierung für **künftige** Versionssprünge.

  Der Checklistenpunkt "Upgrade-Pfad testen" entfällt damit - nicht wegen der
  Entfernung, sondern weil es keine Population mit Altdaten gibt. Die dort
  erwähnte Build-132-Anomalie (Counter-Reset) war bereits mit Build 149 geklärt:
  Ursache war eine wertlesende Migration in `Create()`, wo Attribute nur
  deklariert werden und `ReadAttribute*` nicht zuverlässig den persistierten Wert
  liefert.

  Regressionstest `test_no_legacy_install_cleanup.php` (7 Fälle: beide
  Bereinigungen samt Konstanten entfernt; der gleichnamige Aktions-Ident
  "Language" bleibt; die drei Abgrenzungsfälle bleiben erhalten; und `Create()`
  liest kein Attribut mehr - die Lehre aus Build 132).
* **Build 166 (live gemeldet): ein Umschalten der Checkbox "Übersetzung aktiv"
  wurde gespeichert, aber nicht in die Visualisierung durchgereicht.**
  Gemeldet an Begrüßung und Charts, mit der Vermutung "vermutlich aber überall" -
  die stimmte, es betraf alle Zeilen-Tabellen.

  `ApplyChanges()` entscheidet über `ComputeActiveLanguageContentFingerprint()`,
  ob `ApplyLanguage()` anlaufen muss: ändert sich der für die aktive Sprache
  aufgelöste Inhalt irgendeiner Zeile, wird geschrieben (Build 104). Der
  Fingerabdruck löste aber mit `$CurrentLanguage` auf, während **jede**
  Schreibstelle `GetEffectiveSelectedLanguage()` verwendet und damit die Checkbox
  berücksichtigt. Ein Umschalten änderte den Fingerabdruck also nicht - die
  Änderung war gespeichert, wurde aber erst beim nächsten Sprachwechsel oder
  Rescan sichtbar.

  Der Fingerabdruck muss abbilden, was tatsächlich geschrieben **würde**; weicht
  er davon ab, entscheidet er falsch. Das Sprachspalten-Feld bleibt dabei bewusst
  an der aktiven Sprache, nur die Auswahl folgt dem Flag - genau wie an den
  Schreibstellen.

  Regressionstest `test_translation_active_takes_effect.php` (6 Fälle: der
  gemeldete Fall mit identischem Fingerabdruck; der Fix; der Fingerabdruck bildet
  ab, was geschrieben würde; kein Fehlalarm ohne Änderung - `ApplyChanges()` läuft
  auch re-entrant bei jedem `VM_UPDATE`; bei aktiver Basissprache ist die Checkbox
  wirkungslos und der Fingerabdruck bleibt gleich; Symmetrie-Check). Der Zähler in
  `test_translation_active_flag.php` steht damit bei **acht** Auswertungsstellen.
* **Build 165 (live gemeldet): eine geänderte Begrüßung wurde nicht übernommen
  und beim nächsten Sprachwechsel wieder überschrieben.**
  Im Modus "Name" steckt die Begrüßung in der Property `GreetingName` der
  Visualisierungs-Instanz - dafür gibt es kein `VM_UPDATE`. Aufgefrischt wurde
  sie nur bei einem Rescan, und auch dann nur, wenn zufällig die Basissprache
  aktiv war (`MergeGreetingRows`, `$IsSourceLanguageActive`). Wer die Begrüßung
  bearbeitet, während eine Zielsprache läuft, kam damit nie durch.

  Der Guard selbst war richtig: bei aktiver Zielsprache steht in `GreetingName`
  unsere **eigene** Übersetzung, die niemals zum Rohtext werden darf. Es fehlte
  die Unterscheidung zwischen "eigene Übersetzung" und "Änderung von außen" -
  gelöst wie bei "Eigene Texte" über einen Selbst-Schreib-Marker
  (`attributeLastSelfWrittenGreetingName`, gesetzt **vor** dem Schreibvorgang,
  siehe die Falle aus Build 154).

  Dazu ein Listener: die Visualisierungs-Instanz wird auf `IM_CHANGESETTINGS`
  überwacht, die Änderung also sofort übernommen statt erst beim nächsten
  Rescan - ohne das schriebe ein Sprachwechsel bis dahin den alten Stand zurück,
  genau die gemeldete Beschwerde. Die Rückkopplung ist abgesichert:
  `ApplyGreetingLanguage()` schreibt selbst per `IPS_SetProperty` +
  `IPS_ApplyChanges` in dieselbe Instanz und löst dieselbe Nachricht erneut aus -
  entspricht der gefundene Text dem zuletzt selbst geschriebenen, endet der
  Durchlauf sofort. Im Modus "Variable" hält sich der Handler heraus, dort läuft
  die Aktualisierung bereits über `VM_UPDATE`.

  Ebenfalls in diesem Build (Nutzer-Wunsch): die Begrüßungstabelle ist nur noch
  eine Zeile hoch - mehr kann es dort nicht geben.

  Regressionstest `test_greeting_external_edit.php` (7 Fälle: der gemeldete Fall;
  die Gegenprobe, dass die eigene Übersetzung nie zum Rohtext wird; das bisherige
  Verhalten bei aktiver Basissprache; ein unveränderter Text löst nichts aus;
  Marker-Reihenfolge; Listener samt Rückkopplungsschutz; die Tabellenhöhe).
* **Build 164 (live gemeldet, per Diagnose-Dump belegt): Aufzählungs-Captions
  wurden nur bei Variablen mit Variablen-Aktion übersetzt.**
  An einem Nuki-Schloss übersetzte "Locking action" korrekt, "Blocking state",
  "Batteries", "Battery charge time" und "Keypad Battery" nicht - obwohl alle
  fünf gefüllte Übersetzungen in der Tabelle hatten und alle fünf identisch
  behandelt wurden.

  Symcon erlaubt die Enumeration-Präsentation nur für Variablen mit
  Variablen-Aktion ("This presentation is only available for variables with a
  variable action"). Bis Build 163 wurde trotzdem **jede** Legacy-Variable darauf
  umgestellt. Der Fork kam durch - `IPS_GetVariablePresentation()` lieferte die
  übersetzten Captions -, die Visualisierung verwarf ihn aber und zeigte weiter
  das Profil. Genau deshalb war es im Objektbaum-Dialog zu sehen, in der Visu
  nicht.

  Der Dump belegte beides: alle fünf bekamen `PRESENTATION {52D9E126…}`
  (Enumeration) statt `{4153A8D4…}` (Legacy), und nur "Locking action" trug
  `VariableAction = 16422` - die Nuki-Instanz selbst, gesetzt von
  `EnableAction()`. Im Dialog sieht das täuschend leer aus: dort steht "Custom
  Action: (None)", während die vom Modul gelieferte "Default Action" darunter
  aktiv ist.

  *Fix:* Variablen ohne Aktion bekommen statt der Präsentation ein geforktes
  **Profil** - eine private Kopie mit übersetzten Assoziationsnamen, gesetzt als
  `VariableCustomProfile`. Das geteilte Originalprofil bleibt unangetastet, genau
  wie beim Präsentations-Fork. Symbol, Farben, Einheiten und Wertebereich werden
  unverändert übernommen; weicht keine einzige Caption ab, wird gar kein Profil
  angelegt.

  Zwei Fallen, die der Test festhält: beim Zurückstellen auf die Quellsprache
  muss die Variable **zuerst** auf ihr altes Profil zurückgesetzt und das eigene
  **danach** gelöscht werden (Symcon verweigert das Löschen eines noch
  zugewiesenen Profils), und ein bereits vorhandenes Fork-Profil wird
  weiterverwendet statt neu angelegt - beim zweiten Sprachwechsel hängt es noch
  an der Variable.

  Regressionstest `test_profile_fork_without_action.php` (7 Fälle) plus
  angepasster Zähler in `test_translation_active_flag.php`: das
  "Übersetzung aktiv"-Flag wird jetzt an **sieben** Schreibstellen ausgewertet.
* **Build 163 (live gemeldet, per Screenshot belegt): die Statuszeile meldete
  "Google Translate Fehler - bitte API-Key prüfen", obwohl gar kein
  Google-Schlüssel hinterlegt war.**
  Status 203 wird gesetzt, sobald **alle** Anbieter der Kette gescheitert sind -
  der Text stammte aber noch aus der Zeit, als Google der einzige Anbieter war.
  Eine Instanz, die ausschließlich den kostenfreien Anbieter nutzt, bekam damit
  die Aufforderung, einen API-Key zu prüfen, den es nicht gibt.

  Neu: "Übersetzung fehlgeschlagen - kein Anbieter war erreichbar (Details siehe
  Konfigurationsformular)" - anbieterneutral und mit Verweis auf die seit Build
  152 dort stehende Fehlerbilanz. In allen vier Sprachen; der alte Text ist
  restlos entfernt.

  Regressionstest `test_status_text_provider_neutral.php` (5 Fälle: kein
  Anbietername und keine API-Key-Aufforderung mehr; der Text bleibt trotzdem
  aussagekräftig; der Status gilt weiterhin nur für den Totalausfall der Kette;
  in allen Sprachen registriert und auch dort ohne Google; der alte Text ist
  überall entfernt).
* **Build 162 (live gemeldet): ein wegen des Tageslimits abgelehnter
  Sprachwechsel ließ die Kachel auf der abgelehnten Sprache stehen.**
  Von "de" auf "en" gewechselt, der Wechsel wurde korrekt verweigert - in der
  eingebauten Auswahl stand danach trotzdem "en", obwohl weiterhin "de" aktiv
  war.

  `RequestAction('Language')` kennt drei Ablehnungspfade. Der für unbekannte
  Sprachen und der für die abgelaufene Testphase zeichneten die Kachel jeweils
  neu, genau damit die Auswahl nicht falsch stehenbleibt - der Rate-Limit-Pfad
  war der einzige ohne diesen Aufruf.

  Reihenfolge bewusst wie in den anderen Pfaden: erst neu zeichnen, dann die
  Meldung. Beides läuft über `UpdateVisualizationValue()`; umgekehrt würde das
  Neuzeichnen die ALERT-Nutzlast wieder überschreiben und der Gast bekäme keine
  Erklärung. Unverändert bleibt, dass hier **nicht** auf Original zurückgesetzt
  wird - anders als beim Testphasen-Fall bleibt die bisher aktive Sprache aktiv,
  verweigert wird nur der Wechsel.

  Regressionstest `test_rejected_switch_redraws_tile.php` (6 Fälle: der
  gemeldete Fall; die Reihenfolge; kein Reset auf Original; alle drei
  Ablehnungspfade zeichnen neu, damit derselbe Fehler nicht beim nächsten Pfad
  wieder anfällt; der Erfolgsfall zeichnet nicht doppelt; Symmetrie-Check).
* **Build 161 (Nutzer-Wunsch): das Anbieter-Panel beschrieb ohne das Feature
  `paid_providers` eine Funktion, die es in dieser Edition nicht gibt.**
  "Sind beide eingetragen, wird zuerst der bevorzugte versucht" gilt nur bei
  voller Verkettung. Ohne das Feature bleibt der kostenfreie Anbieter primär und
  höchstens **ein** bezahlter greift als Rückfall dahinter (siehe
  `GetProviderChain()`). Einleitungstext und die Beschriftung des Auswahlfelds
  bekommen für solche Editionen jetzt eine eigene Formulierung samt Hinweis, ab
  welcher Edition die Verkettung greift.

  Der Hinweis nennt bewusst die **Standard** Edition, nicht Pro:
  `paid_providers` ist ab Standard enthalten.

  Das Auswahlfeld bleibt **bedienbar** - der ursprüngliche Wunsch war, es
  auszugrauen, aber es ist dort weiterhin wirksam: es entscheidet, welcher der
  beiden eingetragenen Schlüssel dieser eine Rückfall ist. Ausgrauen hätte echte
  Funktion weggenommen; falsch war nur die Beschriftung.

  Regressionstest `test_provider_texts_without_paid_chain.php` (6 Fälle: die
  Wirksamkeit des Felds ohne Feature in beide Richtungen; ein einzelner
  Schlüssel gewinnt unabhängig von der Präferenz; die volle Verkettung mit
  Feature; beide Texte hängen am Feature und nichts wird deaktiviert; das
  Einleitungs-Label trägt einen Namen - ohne den liefe der Fall ins Leere; beide
  Ersatztexte in allen vier Sprachen registriert).
* **Build 160 (Nutzer-Wunsch): ohne das Feature `manual_translations`
  verschwindet die "Eigene Übersetzungstabelle" jetzt ganz.**
  Bis Build 159 stand sie auch ohne das Feature im Formular: leer, nicht
  vorbefüllt, aber mit funktionierendem Papierkorb. Der Nutzer sah also eine
  Tabelle, erwartete dass sie etwas tut, und bekam nichts.

  Überschrift und Beschreibung bleiben bewusst stehen - er soll lesen können,
  was ihm entgeht -, darunter erscheint "Die eigene Übersetzungstabelle steht in
  dieser Edition nicht zur Verfügung." Nichts zum Klicken, keine falsche
  Erwartung. Der mitgelieferte Katalog für Einheiten und Kompassrichtungen wirkt
  davon unberührt weiter im Hintergrund (siehe Build 158/159).

  Regressionstest `test_manual_table_hidden_without_feature.php` (5 Fälle:
  Tabelle weg ohne Feature; Überschrift und Absage erscheinen stattdessen; mit
  Feature genau umgekehrt, damit die Überschrift nicht doppelt steht;
  Symmetrie-Check inklusive der Reihenfolge, dass für eine unsichtbare Tabelle
  keine Spalten mehr aufgebaut werden; beide Texte in allen vier Sprachen
  registriert).
* **Build 159 (live gemeldet): Build 158 hatte nur die halbe Strecke gebaut -
  bereits falsch gespeicherte Zellen wurden nicht korrigiert.**
  Der mitgelieferte Katalog griff seit Build 158 bei **neuen** Übersetzungen,
  aber eine einmal falsch gespeicherte Zelle wurde nie wieder angefasst: befüllte
  Zellen werden nicht neu übersetzt. Der einzige Durchlauf, der gespeicherte
  Zellen nachträglich korrigiert, ist `ApplyManualTranslationOverrides()` - und
  der stieg ohne das Feature `manual_translations` sofort aus. Live sichtbar
  daran, dass ein einmal als `°F` gespeichertes `°C`-Suffix auch nach dem Update
  stehenblieb.

  *Fix:* der Durchlauf läuft jetzt auch ohne das Feature, nur eben gegen den
  mitgelieferten Katalog statt gegen die gespeicherte Tabelle. Die Abkürzung
  "nichts zu prüfen" greift nur noch, wenn das Feature vorhanden **und** die
  Tabelle leer ist - ohne Feature ist die leere Liste der Normalfall und darf
  nicht zum Aussteigen führen. Genau diese Abkürzung war der Fehler.

  Regressionstest `test_bundled_glossary_corrects_stored_cells.php` (6 Fälle:
  der gemeldete Fall; der Fix; die Abkürzung bleibt mit Feature erhalten; ein
  Symmetrie-Check auf genau diese Bedingung; eine bereits korrekte Zelle bleibt
  unangetastet; normale Übersetzungen werden nicht überschrieben).
* **Build 158 (Nutzer-Entscheidung nach dem Befund aus Build 157): die
  mitgelieferte Nachschlagetabelle für Einheiten und Kompassrichtungen greift
  jetzt in JEDER Edition.**
  Bis Build 157 hingen zwei Dinge an einem einzigen Lizenz-Flag
  (`manual_translations`): die editierbare "Eigene Übersetzungstabelle" **und**
  der mitgelieferte Katalog. Eine Edition ohne das Feature schickte damit auch
  `°C` ganz normal an die API - und bekam auf Englisch `°F` zurück. Eine
  Einheitenumrechnung als Übersetzung ist in jeder Edition falsch, und dem
  Kunden ist nicht vermittelbar, dass das an seiner Edition liegt.

  Verkauft wird die **editierbare** Tabelle, nicht die korrekte Behandlung von
  Einheiten. Ohne das Feature bleibt sie unsichtbar und unbearbeitbar, der
  interne Lookup greift trotzdem - das kostet nichts und spart sogar Kontingent.

  Die Gegenrichtung ist bewusst unverändert: **mit** dem Feature gibt es keinen
  Fallback. Dort ist die Tabelle maßgeblich, und eine bewusst gelöschte Zeile
  (z. B. weil "SSW" in dieser Installation ein Personen-Kürzel ist, keine
  Windrichtung) muss gelöscht bleiben - ein Fallback würde genau diese Löschung
  wirkungslos machen.

  Regressionstest `test_bundled_glossary_all_editions.php` (6 Fälle: der
  gemeldete Fall; die Gegenrichtung mit Feature; ein eingetragener Wert gewinnt
  immer; der Katalog greift nur bei deutscher Quellsprache; unbekannte Texte
  gehen weiterhin an die API; Symmetrie-Check inklusive der Pufferung der Karte,
  da die Suche je Text läuft).
* **Build 157 (live gemeldet, per Screenshot belegt): die mitgelieferten
  Vorschlagszeilen der "Eigenen Übersetzungstabelle" blieben leer - und dadurch
  wurde `°C` nach Englisch zu `°F`.**
  In der Tabelle standen die Einheiten-Zeilen zwar da, ihre Sprachspalten waren
  aber leer. `MergeBundledManualTranslations()` übersprang eine Zeile
  vollständig, sobald es sie schon gab - kam eine Zielsprache erst **später**
  dazu, wurde ihre Spalte nie mehr befüllt. Eine leere Zelle gilt in
  `FindManualTranslation()` als "kein Treffer", der Text lief also ganz normal in
  die API. Sichtbar an einem Einheiten-Suffix: aus `°C` wurde `°F` - eine
  Einheitenumrechnung, keine Übersetzung.

  *Fix:* bestehende Vorschlagszeilen werden nachbefüllt, aber **nur leere
  Zellen** - ein vom Admin eingetragener Wert gewinnt immer. Der bestehende
  Schutz bleibt unangetastet: eine bewusst gelöschte Vorschlagszeile kehrt
  weiterhin nicht zurück (`attributeSeededManualTranslationKeys`). Anlegen und
  Nachbefüllen speisen jetzt aus einer gemeinsamen Quelle
  (`BuildBundledManualTranslationMap()`), damit sie nicht auseinanderlaufen
  können.

  Regressionstest `test_bundled_glossary_backfill.php` (6 Fälle: der gemeldete
  Fall inklusive des ausbleibenden Glossartreffers; der Fix; ein Admin-Wert wird
  nie überschrieben; gelöschte Vorschläge kehren nicht zurück; eigene Zeilen
  bleiben unangetastet; Symmetrie-Check inklusive der Gegenseite, dass eine
  leere Zelle weiterhin als "kein Treffer" gilt).
* **Build 156 (live gemeldet, per Screenshot belegt): die Auswahltexte der
  Kachel-Kataloge folgten der Symcon-Systemsprache statt der Konsolensprache.**
  Bei englischer Konsole standen die Feldbeschriftungen korrekt auf Englisch
  ("Tile template", "Icon in the tile"), die Auswahleinträge daneben aber weiter
  auf "Automatisch (Standard)".

  `BuildCatalogOptions()` setzte die Beschriftung zur Laufzeit aus mehreren
  `Translate()`-Fragmenten zusammen. Eine so zusammengebaute Zeichenkette matcht
  nie einen `locale.json`-Eintrag und bleibt dadurch an die Systemsprache
  gebunden - derselbe Fehler wie zuvor bei den Anbieter-Pausen-Zeilen und bei
  `propertyAutoRescanInterval`, und dort genauso behoben: fester,
  vollständig vorregistrierter deutscher Gesamttext ohne `Translate()`, damit
  die Konsole exakt matchen und selbst übersetzen kann.

  Preis dafür: jeder neue Katalogeintrag braucht zwei `locale.json`-Zeilen je
  Sprache - sein Label und die "Automatisch (Label)"-Kombination. Damit das bei
  künftigen Editions-Symbolen und -Vorlagen nicht vergessen wird, prüft
  `test_tile_catalog_captions_localized.php` beides und nennt fehlende Strings
  einzeln pro Sprache (5 Fälle, inklusive einer Kontrollprobe, dass die Prüfung
  bei einem neuen Eintrag tatsächlich anschlägt).

  Ebenfalls in diesem Build (Nutzerwunsch): im Kachel-Panel steht die
  Kachel-Vorlage jetzt an erster Stelle, und das Symbol-Dropdown sitzt in einem
  `RowLayout` direkt neben seiner "Symbol anzeigen"-Checkbox.
* **Build 155 (live gemeldet): das Ergebnis-Popup von "Aufräumen" ging nach dem
  automatischen Neuladen des Formulars ein zweites Mal auf - diesmal ohne Zahl.**
  `PopulateFormElements()` stieg nur in `$element['items']` ab. Die Inhalte
  eines `PopupAlert` liegen aber eine Ebene tiefer, in
  `$element['popup']['items']`, und wurden deshalb nie befüllt. Das Popup selbst
  ist ein Element oberster Ebene und wurde korrekt sichtbar gesetzt - daher
  "Popup ja, Zahl nein". Dass das ERSTE Popup die Zahl noch zeigte, liegt an
  `UpdateFormField()`: das adressiert ein Feld über seinen Namen und erreicht es
  unabhängig von der Verschachtelung.

  Geprüft, bevor die Rekursion erweitert wurde: von den 24 Feldern unterhalb
  eines `popup`-Knotens hat genau eines (`CleanupResultCountLabel`) überhaupt
  einen Zweig in `PopulateFormElements()` - der Eingriff ändert also an keinem
  anderen Popup etwas.

  Im selben Build auf Nutzerwunsch die **doppelte Anzeige** abgestellt: das
  Popup ging sichtbar zweimal auf, weil es zwei unabhängige Wege gab - das
  sofortige Live-Einblenden per `UpdateFormField()` (Build 98) und das Attribut
  `attributeLastCleanupRemovedCount`, das `PopulateFormElements()` beim
  automatischen Neuaufbau liest. Der Neuaufbau ist der verlässlichere Weg (er
  überlebt den Reload, der Live-Push nicht), deshalb entfällt das
  Live-Einblenden ersatzlos. `test_cleanup_deferred_reload.php` wurde dabei
  durch `test_cleanup_result_popup_shown_once.php` ersetzt: der alte Test
  beschrieb noch den Build-98-Stand samt verzögertem `CleanupReloadTimer`, den
  Build 116 längst entfernt hatte, und prüfte nur noch seine eigene Replik.

  Regressionstest `test_popup_items_populated.php` (6 Fälle: der gemeldete Fall
  mit der lückenhaften Rekursion; der Fix; kein Popup ohne vorangegangenes
  Aufräumen; ein Ergebnis von 0 erscheint als "0" statt als Leerfeld; beliebig
  tief verschachtelte Popup-Inhalte; Symmetrie-Check inklusive des Nachweises,
  dass das Feld real unterhalb eines `popup`-Knotens liegt).
* **Build 154 (live gemeldet, per `dump23` nachgewiesen): Datenverlust - die
  Spalte `ORIGINAL_IMPORT_Text` in "Eigene Texte" wurde durch die eigene
  Übersetzung ersetzt.**
  Der Nutzer änderte nur die Begrüßung in der Visu-Instanz und sah daraufhin 88
  Übersetzungsanfragen. 67 davon trugen als Quelltext exakt das, was ein früherer
  Lauf als *Ergebnis* geliefert hatte - bei `langpair=de|la`. Die Mapping-Zeile
  zeigte den Rohtext von sechs Objekten in Latein, eines bereits mit arabischen
  Fragmenten: der Text war mehrfach im Kreis gelaufen (Deutsch → Latein →
  Latein-von-Latein). Auf der Live-Instanz war praktisch jede Zeile betroffen.

  *Ursache 1 (die eigentliche):* `WriteTrackedValueString()` setzte den
  Selbst-Schreib-Marker (`attributeLastSelfWrittenValues`) **nach**
  `SetValueString()`. Symcon stellt `VM_UPDATE` synchron zu -
  `HandleTrackedVariableUpdate()` lief also bereits, während der Marker noch den
  alten Stand trug, und hielt den eigenen Schreibvorgang für eine externe
  Änderung. Das ist das "seltene Timing-Fenster", das der Build-95-Kommentar
  nicht auflösen konnte: kein Zufall, sondern schlicht die Reihenfolge.

  *Ursache 2 (warum das Netz darunter riss):* der Build-95-Schutz verglich den
  externen Wert nur mit der Zelle der **aktuell aktiven** Sprache. Genau die war
  nach dem Kontingent-Abbruch aus `dump22` leer bzw. nur teilweise gefüllt - der
  Vergleich griff nicht.

  *Fix:* Marker vor dem Schreibvorgang persistieren; zusätzlich eine
  Rückübersetzungs-Sperre, die den externen Wert gegen **alle** gespeicherten
  Zielsprachen-Zellen der Zeile prüft (Quellsprache bewusst ausgenommen, dort ist
  Gleichheit der Normalfall) und im Debug als
  `TrackedValue_BackTranslationBlocked` sichtbar wird.

  Ebenfalls in diesem Build: die Debug-Kategorie `GoogleTranslate_Mapping` heißt
  jetzt `Translate_Mapping`. Sie wird anbieterunabhängig geschrieben und hatte
  den Nutzer zweimal glauben lassen, es gehe ein Aufruf an Google, obwohl gar
  kein Google-Key konfiguriert war.

  Für die Reparatur der betroffenen Live-Installation entstand ein
  eigenständiges Skript (`tools/repair_original_import.php`, Modi `diagnose`,
  `freeze`, `copy` aus einer intakten zweiten Instanz, `backup` aus einer
  `settings.json`, `restore` aus dem Symcon-Archiv und `reset_texts`). Es wird
  bewusst **nicht** mit dem Modul ausgeliefert - IP-Symcon liest jede PHP-Datei
  im Modulverzeichnis ein und meldet bei einem eigenständigen Skript mit Code
  auf oberster Ebene Fehler. Bei Bedarf lässt es sich aus der Historie holen
  (Commits `f342aa6` und `e4cc37c`).

  Regressionstest `test_back_translation_cycle.php` (8 Fälle: die
  Marker-Reihenfolge; der gemeldete Fall mit leerer Zelle der aktiven Sprache;
  der bestehende Build-95-Schutz bleibt wirksam; echte externe Änderungen kommen
  weiterhin durch; die Quellsprache ist ausgenommen; ein leerer Wert löst die
  Sperre nicht aus; zwei Sprachwechsel-Runden lassen den Rohtext unverändert;
  Symmetrie-Checks gegen die reale Umsetzung).
* **Build 153 (live gemeldet, per `dump22` nachgewiesen): zwei Regressionen aus
  Build 151 behoben - Weiterfragen trotz erschöpftem Kontingent, und eine
  Anbieter-Sperre, die sich selbst sofort wieder aufhob.**
  Der Nutzer fuhr bewusst gegen MyMemorys Tageslimit, um das Verhalten zu sehen,
  und meldete zwei Auffälligkeiten. Beide gehen auf denselben Umbau zurück: Seit
  Build 151 kann ein Anbieter **teilweise** liefern - und an dieser neuen
  Möglichkeit hingen zwei Stellen, die ich damals nicht mitgedacht hatte.
  - **Weiterfragen nach dem Rate-Limit.** Bis Build 151 stoppte der erste
    Fehlschlag den Durchlauf (mit dem Nebeneffekt, alle Teilerfolge zu
    verwerfen - deshalb der Umbau). Seitdem lief die Schleife stur weiter:
    laut Dump 90 Texte erfolgreich, dann HTTP 429 mit
    "YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY" - und danach **35
    weitere, völlig aussichtslose Aufrufe**, die nur Zeit kosteten und einen
    ohnehin überlasteten Fremdserver zusätzlich belasteten.
    `TranslateChunkFree()` prüft jetzt vor jedem Aufruf, ob der Anbieter
    inzwischen gesperrt wurde. Die restlichen Texte bleiben offen und werden
    beim nächsten Rescan erneut versucht - genau wie ein einzelner Fehlschlag.
  - **Die Sperre hob sich selbst auf.** MyMemorys 429 enthält das Wort "TODAY",
    die Erkennung greift also korrekt und setzt die volle Tagessperre mit exakt
    geparstem Reset (im Dump: 7 h 15 min). Unmittelbar danach lief aber
    `ClearProviderPause()` - denn ein Teilerfolg galt als Anbieter-Erfolg. Die
    gerade gesetzte Sperre war damit sofort wieder weg. Folgen: Die Statuszeile
    blieb fälschlich auf "Aktiv" statt "pausiert" (genau die Beobachtung des
    Nutzers), und der nächste Chunk rannte ungebremst in dieselbe Wand.
    Die Sperre wird jetzt nur noch bei **vollständiger** Lieferung aufgehoben -
    ein unvollständiger Lauf ist kein Gesundheitsnachweis. Ein vollständig
    gelieferter Lauf hebt sie weiterhin auf, damit ein längst wieder gesunder
    Anbieter nicht unnötig gesperrt bleibt.
  **Drittens gemeldet: erneutes Abfragen bereits übersetzter Texte** - das war
  kein neuer Fehler, sondern die Nachwirkung des in Build 151 behobenen. Der
  betreffende Lauf im Dump lief noch auf dem alten Stand: Der HTTP 504 verwarf
  dort alle 21 Erfolge, und da `TranslateBatchUncached()` nur nicht-leere
  Ergebnisse cacht, landete auch **nichts im Cache**. Der spätere Lauf musste
  sie deshalb neu holen. Seit Build 151 werden Teilerfolge gespeichert *und*
  gecacht; im aktuellen Lauf des Dumps sind die 90 Erfolge nachweislich beides.
  Neuer Regressionstest (nach dem Rate-Limit wird kein weiterer Aufruf mehr
  abgesetzt; die Erfolge davor bleiben trotzdem erhalten; ein unvollständiger
  Lauf hebt die Sperre nicht auf; ein vollständiger sehr wohl; schlägt schon der
  erste Text fehl, kommt genau ein Aufruf und danach `null` für den
  Kettenwechsel; Symmetrie-Checks für Prüf-Reihenfolge und Aufhebe-Bedingung).
* **Build 152 (Nutzer-Frage: "Wie bekommt der User vom Ausfall eines Anbieters
  mit? Wenn es keine Information darüber gibt, könnte er denken, dass unsere App
  nicht richtig funktioniert."): Ausfälle sind jetzt im Formular sichtbar - mit
  konkreter Handlungsanweisung.**
  Berechtigter Einwand, und Build 151 hatte das Problem sogar **verschärft**:
  Vorher scheiterte bei einem Anbieterfehler der ganze Durchlauf und setzte
  wenigstens einen Fehlerstatus. Seit Build 151 bleiben Teilerfolge erhalten
  (richtig so), der Lauf gilt damit als gelungen - und der Ausfall wurde
  unsichtbar. Der Nutzer sah nur leere Zellen und musste annehmen, das Modul
  funktioniere nicht.
  Ein neues Attribut hält die Bilanz des **letzten** Durchlaufs (wird zu dessen
  Beginn zurückgesetzt) und speist zwei Hinweiszeilen direkt unter dem
  Rescan-Button, jeweils nur sichtbar, wenn die zugehörige Zahl > 0 ist.
  **Bewusst zwei getrennte Zähler**, weil sie zu gegensätzlichen Ratschlägen
  führen:
  - *Anbieter nicht erreichbar* (jeder HTTP >= 400, cURL-Fehler, Timeout - also
    nicht nur der beobachtete 504, sondern auch 500/502/503, DNS-Ausfälle,
    Verbindungsabbrüche): ein erneuter Scan kann helfen, also wird
    **ausdrücklich dazu aufgefordert** ("Bitte führe einen erneuten Scan durch -
    ggf. nach ein paar Minuten Wartezeit"). Eine Formulierung wie "wird
    nachgeholt" wäre missverständlich - sie klänge, als geschähe das von selbst.
  - *Zu lang für den kostenfreien Anbieter* (500-Byte-Grenze): ein erneuter Scan
    ändert daran **nichts**, die Grenze bleibt exakt dieselbe. Hier lautet der
    Rat stattdessen, einen Google- oder DeepL-Schlüssel zu hinterlegen. Wären
    beide Ursachen zusammengeworfen, bekäme jemand mit zu langen Texten die
    Aufforderung zum erneuten Scan - und der zweite Lauf endete exakt gleich.
  **Statuszeile und Kachel bleiben bewusst unberührt** (Nutzer-Vorgabe): Das
  Modul selbst ist in Ordnung, ein vorübergehend überlasteter Fremdserver ist
  kein Instanzfehler; und der Gast kann daran ohnehin nichts ändern. Beides ist
  per Test festgenagelt, damit es nicht später "hilfreich" ergänzt wird.
  Da Teilerfolge seit Build 151 erhalten bleiben, **sinkt die Zahl von Lauf zu
  Lauf** - der Nutzer sieht unmittelbar Fortschritt statt immer derselben
  Meldung, und am Ende verschwindet der Hinweis von allein.
  **Fehler aus Build 151 dabei mitkorrigiert:** Ein Text über der Byte-Grenze
  liefert einen Leerstring, nicht `null` - der wurde dort fälschlich als Erfolg
  gewertet. Ein Chunk aus lauter zu langen Texten hätte damit "Anbieter hat
  funktioniert" gemeldet, und die Kette hätte Google/DeepL **nicht mehr
  gefragt** - obwohl genau die diese Grenze nicht kennen. Jetzt zählt nur ein
  nicht-leeres Ergebnis als Erfolg.
  Neuer Regressionstest (Ausfall wird sichtbar, mit Anzahl; ein sauberer Lauf
  zeigt nichts; beide Ursachen werden getrennt gezählt und gemeldet; zu lange
  Texte fordern nicht zum sinnlosen erneuten Scan auf; die Bilanz gilt je
  Durchlauf; die Zahl sinkt von Lauf zu Lauf und verschwindet am Ende;
  Symmetrie-Checks inklusive der bewussten Nicht-Änderung von Statuszeile und
  Kachel).
* **Build 151 (live gemeldet, per `dump21` nachgewiesen): ein Serverfehler
  mitten im Durchlauf warf alle bereits fertigen Übersetzungen desselben Laufs
  weg.**
  Gemeldetes Symptom: Ein Rescan lief minutenlang, übersetzte nachweislich
  erfolgreich - und trug trotzdem nichts in die Tabelle ein. Im Log stand die
  letzte erfolgreiche Abfrage direkt vor einem Serverfehler: "erfolgreich
  abgefragt, aber nicht in der Liste eingetragen".
  Der Dump zeigte den Ablauf: MyMemory lieferte **21 Übersetzungen sauber aus**
  (darunter die im Screenshot leer gebliebene Zeile sowie die beiden langen
  Texte, die also *nicht* an der 500-Byte-Grenze scheiterten), dann kam ein
  **HTTP 504 Gateway Time-out** - der Anbieter war schlicht überlastet.
  `TranslateChunkFree()` brach daraufhin mit `return null` ab und verwarf dabei
  **alle 21 fertigen Übersetzungen**; `TranslateChunk()` wertete das als
  Anbieter-Ausfall und füllte den gesamten Chunk mit Leerstrings.
  Das Kontingent war für diese 21 Anfragen längst verbraucht - beim nächsten
  Rescan begann alles von vorn, inklusive erneutem Verbrauch. Bei einem
  zeitweise überlasteten Anbieter konnte ein größerer Baum dadurch **nie fertig
  übersetzt werden**.
  Kern des Problems: MyMemory hat keinen Batch-Endpunkt und ruft pro Text
  einzeln auf - ein **Teilerfolg ist dort der Normalfall**, anders als bei
  Google/DeepL, wo ein Aufruf den ganzen Chunk abdeckt. Der Code behandelte
  beide gleich.
  - `TranslateChunkFree()` arbeitet jetzt weiter: erfolgreiche Texte behalten
    ihr Ergebnis, nur der tatsächlich fehlgeschlagene bleibt offen. `null` ist
    dem **Totalausfall** vorbehalten (kein einziger Text durchgekommen) - nur
    dann gilt der Anbieter als gescheitert, was Kettenwechsel und
    Pausen-Eskalation weiterhin korrekt auslöst.
  - `TranslateChunk()` sammelt Teilergebnisse jetzt **über die Anbieter-Kette
    hinweg** und reicht an den nächsten Anbieter nur die noch offenen Texte
    weiter - der verbraucht damit auch kein Kontingent für bereits Übersetztes,
    und ein bereits gutes Ergebnis wird nie überschrieben.
  Nach der ganzen Kette noch offene Texte bleiben bewusst **leer** statt mit
  dem Originaltext gefüllt: Eine leere Zelle gilt als "nicht aktuell" und wird
  beim nächsten Rescan erneut versucht, ein eingetragener Originaltext würde
  fälschlich als fertige Übersetzung gelten und nie wieder angefasst.
  Beim Erkennen leerer Ergebnisse bewusst eine explizite Prüfung auf `!== ''`
  statt `array_filter()` - letzteres wertet auch eine Übersetzung, die wörtlich
  `"0"` lautet, als leer und würde sie verwerfen.
  **Außerdem (Nutzer-Wunsch): der Laufbalken nennt jetzt die zu erwartende
  Dauer.** Gerade der erste Scan läuft bei vielen Objekten minutenlang, weil
  jeder noch nicht gecachte Text einzeln an den Anbieter geht - ohne Zeitangabe
  wirkt das wie ein Hänger. Die beiden Übersetzungs-Stufen tragen den Hinweis
  "(je nach Anzahl der Objekte kann das einige Minuten dauern)" in allen vier
  Sprachen. Die schnellen Stufen ("Baum wird eingelesen", "Ergebnis wird
  gespeichert") bewusst **nicht** - dort wäre die Ankündigung einer
  minutenlangen Wartezeit falsch und würde den Hinweis überall entwerten; ein
  Test sichert beide Richtungen ab.
  Neuer Regressionstest (Erfolge überleben einen Serverfehler im selben
  Durchlauf; alle 21 Erfolge aus dem Report bleiben erhalten; ein Totalausfall
  liefert weiterhin `null`; die Kette holt offene Texte beim nächsten Anbieter
  nach; bereits Übersetztes wird nicht überschrieben; durchgehend
  fehlgeschlagene Texte bleiben leer für den nächsten Rescan; Symmetrie-Checks
  inklusive des `array_filter()`-Fallstricks).
* **Build 150 (live gemeldet, per Debug-Dump nachgewiesen): ein einzelnes
  `&nbsp;` konnte bis zu 127 völlig unbeteiligte Texte unübersetzt lassen.**
  Gemeldetes Symptom: Nach einem Rescan blieben in "Eigene Texte" praktisch alle
  Zielsprachen-Zellen leer - auch triviale wie "Bernd" oder "Wohnbereich".
  Gefüllt waren nur Zahlen/Daten, die der lokale Filter ohne API bedient.
  Gleichzeitig war kein Kontingent erschöpft, keine Pause aktiv, und die
  Objektnamen-Tabelle übersetzte einwandfrei.
  Der Dump zeigte die Ursache: MyMemory antwortet für manche Eingaben mit
  **HTTP 200 und `"translatedText": null`** - live für `&nbsp;` aus einem
  HTML-Widget. Das ist kein Anbieter-Fehler, sondern schlicht "dafür habe ich
  nichts". Der Code machte daraus aber ein `null`, und `TranslateChunkFree()`
  bricht bei einem `null` den **kompletten Chunk** ab. `TranslateChunk()` wertet
  das als Anbieter-Fehlschlag, findet keinen weiteren Anbieter und füllt **alle**
  Texte des Chunks mit Leerstrings. Da ein Chunk bis zu 128 Texte fasst, riss ein
  einziges `&nbsp;` bis zu 127 unbeteiligte Texte mit - deren eigene Anfragen
  waren im Dump nachweislich erfolgreich.
  Exakt dieselbe Fehlerklasse wie beim zu langen Text, die dort bereits behoben
  war (siehe `test_free_provider_oversized_text_no_longer_blocks_batch`) - dieser
  Pfad wurde damals übersehen.
  `TranslateSingleFree()` unterscheidet jetzt sauber: ein echter Fehlschlag
  (Transport, Kontingent) liefert weiterhin `null`, damit die Anbieter-Kette auf
  den nächsten Anbieter ausweichen kann; eine gültige Antwort ohne Übersetzung
  liefert den **Originaltext** zurück. Bewusst nicht den Leerstring: bei
  HTML-Knoten würde der den Knoten beim Zusammensetzen löschen (aus `&nbsp;`
  würde nichts) und damit das Dokument beschädigen.
  **Zwei Diagnose-Mängel, die die Fehlersuche aktiv in die Irre geführt hatten,
  sind mitbehoben:**
  - Die Anbieter-Prüfung testete fest verdrahtet `de → en` und rief die
    Anbieter-Funktion direkt auf, also am Notaus-Schalter und an der
    Pausen-Prüfung vorbei. Sie konnte damit "funktioniert" melden, während die
    tatsächlich konfigurierte Sprachrichtung scheiterte. Geprüft wird jetzt die
    echte Scan-Sprache gegen die erste abweichende Zielsprache, und der
    Ergebnis-Dialog weist die geprüfte Richtung aus. Ist noch keine abweichende
    Zielsprache konfiguriert, wird das als Ersatz-Paarung gekennzeichnet, mit
    dem Hinweis, dass ein Erfolg dann nichts über die späteren Zielsprachen
    aussagt.
  - Ein Text über der 500-**Byte**-Grenze von MyMemory wurde wortlos
    übersprungen: leere Zelle, kein Log-Eintrag. Jetzt mit Klartext-Meldung
    inklusive tatsächlicher Bytezahl (Umlaute zählen doppelt, die sichtbare
    Textlänge führt in die Irre), Kontext und Textanfang.
  Außerdem aufgeräumt: die einmalige Zähler-Migration aus Build 132 wurde
  entfernt. Sie war für jede künftige Installation strukturell unerreichbar (die
  alten Attribute werden von keinem Codepfad mehr beschrieben, stehen also
  dauerhaft auf 0, und die Migration sprang nur bei einem Wert ungleich 0 an) -
  und ihr Lesevorgang lag ohnehin an der falschen Stelle: in `Create()` werden
  Attribute erst **deklariert**, ein `ReadAttribute*` liefert dort nicht
  zuverlässig den persistierten Wert. Das erklärt rückblickend den beim Testen
  beobachteten Zähler-Reset. Die Lehre steht jetzt als Kommentar genau an der
  Stelle, an der man sie wieder falsch machen würde: wertlesende Migrationen
  gehören nach `ApplyChanges()`.
  Neuer Regressionstest (ein einzelnes `&nbsp;` reißt die übrigen Texte nicht
  mehr mit; der untranslatierbare Knoten bleibt unverändert erhalten statt
  geleert zu werden; ein echter Fehlschlag liefert weiterhin `null`, damit die
  Kette greift; alle 128 Texte eines Durchlaufs überleben einen einzelnen
  untranslatierbaren; mehrere gleichzeitig sind ebenso unschädlich;
  Symmetrie-Check der Unterscheidung). Bestehende Tests um die Log-Pflicht bei
  der Byte-Grenze erweitert.
* **Build 149 (Nutzer-Wunsch beim Testen): neuer Knopf "Verknüpfungen
  automatisch nach ihrem Ziel benennen" - spart beim Einrichten viel
  Handarbeit.**
  Ein Rescan bricht ab, solange irgendein Objekt im Baum keinen Namen hat (ein
  leerer Name lässt sich nicht übersetzen und würde als leere Beschriftung in
  der Gäste-Visualisierung landen). Beim Einrichten eines größeren Baums sind
  das schnell Dutzende Objekte - und der Großteil davon sind **Verknüpfungen**:
  Symcon zeigt für eine namenlose Verknüpfung automatisch den Namen ihres Ziels
  an. In der Visualisierung sieht also alles richtig aus, während `IPS_GetName()`
  leer bleibt. Der Admin müsste von Hand exakt den Namen abtippen, den Symcon
  ohnehin schon anzeigt.
  Der Knopf erscheint direkt unter der Liste der unbenannten Objekte (und nur
  dann, wenn es welche gibt) und übernimmt für jede betroffene Verknüpfung den
  Namen ihres Ziels. **Optisch ändert sich dadurch nichts** - der vergebene Name
  ist genau der, den Symcon vorher automatisch eingeblendet hat. Deshalb bewusst
  ohne Rückfrage: es gibt nichts zu überschreiben.
  Bewusst **nur** Verknüpfungen: eine unbenannte Kategorie oder Variable hat kein
  Ziel, aus dem sich ein sinnvoller Name ableiten ließe. Solche Objekte bleiben
  stehen und werden in der Rückmeldung getrennt ausgewiesen.
  Drei Fälle, die bewusst abgefangen werden:
  - Zeigt die Verknüpfung auf ein **selbst unbenanntes** Ziel, wäre der
    übernommene Name genauso wertlos - dann bleibt sie stehen, damit der Admin
    die eigentliche Ursache sieht statt einer Platzhalter-Kette.
  - Fehlendes/gelöschtes Ziel oder ein inzwischen entferntes Objekt brechen den
    Durchlauf nicht ab.
  - Ein **gesperrtes** Objekt lehnt das Umbenennen ab. Nach dem Schreiben wird
    der Name deshalb noch einmal gelesen und nur gezählt, was nachweislich
    angekommen ist - sonst würde Erfolg gemeldet und der nächste Rescan meckerte
    trotzdem weiter.
  Die Rückmeldung nennt die Zahl der benannten Verknüpfungen, weist übrige
  Objekte getrennt aus und bittet um einen erneuten Rescan.
  Neuer Regressionstest (Hauptfall; Platzhalter-Name "(ID: n)" wird ersetzt;
  Nicht-Verknüpfungen bleiben unangetastet; selbst unbenanntes Ziel erzeugt keine
  Platzhalter-Kette; fehlendes Ziel/gelöschtes Objekt brechen nicht ab;
  fehlgeschlagene Umbenennung wird ehrlich als übersprungen gezählt; gemischter
  Bestand; Symmetrie-Check der Verdrahtung).
* **Build 148 (Nutzer-Vorgaben zum Abo-Modell): App-Seite für Abos
  fertiggestellt - die Abo-Verwaltung ist damit reine Website-Arbeit.**
  Ausgangspunkt war die Frage, welche Informationen die App künftig braucht,
  damit später *nichts* mehr am Modul geändert werden muss. Die Bestandsaufnahme
  ergab, dass der **Verlängerungsmechanismus app-seitig bereits fertig war**:
  die tägliche Statusprüfung übernimmt ein vom Server geliefertes `expiresAt`
  als Override über das im Schlüssel signierte Datum (siehe Build 120). Eine
  Abo-Verlängerung ist damit ein reiner Schreibvorgang auf
  `slips_licenses.expires_at_override` - kein neuer Schlüssel, kein Versand,
  keine App-Änderung. Eine Kündigung ebenso: der Server hört einfach auf zu
  verlängern.
  Ergänzt wurden daher nur die fehlenden Anzeige- und Schutzteile:
  - **`interval` im signierten Schlüssel** (`month`/`year`), angezeigt als neue
    Zeile "Abozeitraum: monatlich/jährlich" im Lizenz-Panel. Gehört bewusst in
    den Schlüssel und nicht in die tägliche Prüfung: der Wert ist statisch und
    lässt sich nicht aus `expiresAt` ableiten (ein Jahresabo kurz vor Ablauf
    sieht aus wie ein Monatsabo). Streng normalisiert - alles Unerwartete wird
    zu `''` und blendet die Zeile aus. Ältere Schlüssel ohne das Feld
    funktionieren unverändert weiter.
  - **Ablaufhinweis in der Kachel**, im selben roten Stil wie der Pause-Hinweis:
    ab 7 Tagen vor Ablauf "Deine Lizenz läuft ab am TT.MM.JJJJ. Verlängern:
    <Link>", danach "Deine Lizenz ist abgelaufen. Verlängern: <Link>". Als
    Gast-Oberflächentexte umgesetzt, erscheinen also in der Gastsprache. Ein
    unbefristeter Einmalkauf (`expiresAt` = 0) bekommt nie einen solchen
    Hinweis.
  - **Zielsprachen gesperrt bei abgelaufener Lizenz.** Eine neue Zielsprache
    würde eine Übersetzung auslösen, die nicht mehr erworben ist. Das übrige
    Formular bleibt ausdrücklich bedienbar - insbesondere das Lizenzfeld selbst,
    denn ein neuer Schlüssel ist der einzige Weg zurück. Ein Regressionstest
    sichert genau das ab (Sackgassen-Schutz).
  Das Zurückfallen der Übersetzungen auf die Quellsprache bei Ablauf war bereits
  vorhanden (`IsTrialLocked()`/`ResetToOriginalLanguageIfNeeded()`) - eine
  abgelaufene Lizenz landet automatisch in derselben Mechanik, ebenso der
  blockierte Rescan.
  **Zwei bewusste Nicht-Entscheidungen:** Kulanzfristen rechnet der **Server**
  in `expiresAt` ein, die App kennt gar keine Grace-Logik - dadurch bleibt die
  Kulanzpolitik jederzeit serverseitig änderbar, ohne je ein Modul-Update. Und
  es gibt **kein** frei belegbares Hinweisfeld vom Server: die beiden festen
  Zustände decken den Bedarf ab, und ein Servertext hätte Sprach- und
  Escaping-Fragen aufgeworfen, die er nicht wert ist.
  Neuer Regressionstest (Vorwarnung exakt ab 7 Tagen und nicht früher;
  Abgelaufen-Zustand; ein unbefristeter Einmalkauf wird nie zur Verlängerung
  aufgefordert; eine serverseitige Verlängerung lässt den Hinweis von selbst
  verschwinden; serverseitige Kulanz wirkt ohne Grace-Logik; `interval` streng
  normalisiert; alte Schlüssel ohne das Feld funktionieren weiter;
  Symmetrie-Checks inkl. Sackgassen-Schutz fürs Lizenzfeld).
* **Build 147 (Nutzer-Vorgabe zur offenen Frage aus Build 146): eine
  Sonder-Edition mit eigenem Design zeigt dieses jetzt von sich aus, statt es
  erst auswählen zu lassen.** Genau darum geht es beim Wiedererkennungswert -
  der Käufer soll sein Design nicht suchen müssen.
  Beide Auswahlfelder stehen im Auslieferungszustand auf **"Automatisch"** (ein
  reservierter Wert, bewusst keine Katalog-ID, damit er nie mit einem echten
  Design kollidiert). Automatisch bedeutet: das neueste Saison-Design, für das
  eine Berechtigung vorliegt - sonst der neutrale Auslieferungszustand. Die
  Auswahlliste zeigt dabei an, was das gerade konkret heißt
  ("Automatisch (Weihnachten 2026)"), damit die Einstellung nicht undurchsichtig
  wirkt.
  Ein statischer Property-Default hätte das **nicht** leisten können: die Lizenz
  wird typischerweise erst nach dem Anlegen der Instanz eingetragen, der Default
  steht zu diesem Zeitpunkt längst fest. Die Auswertung passiert deshalb bei
  jedem Auflösen, nicht einmalig beim Registrieren.
  Drei Feinheiten, die den Unterschied zwischen "funktioniert" und "nervt"
  ausmachen:
  1. Eine **ausdrückliche** Wahl schlägt die Automatik und bleibt bestehen. Wer
     trotz Saison-Lizenz bewusst das neutrale Symbol will, behält es - sonst
     wäre die Auswahl wertlos. Nur der Wert "Automatisch" wird neu bewertet.
  2. Besitzt jemand **mehrere** Sonder-Editionen, gewinnt deterministisch die
     zuletzt erschienene (der letzte passende Katalogeintrag; neue Designs
     werden angehängt).
  3. Verliert eine ausdrückliche Wahl ihre Berechtigung, fällt sie auf den
     **neutralen** Standard zurück - nicht auf ein anderes Saison-Design, das
     zufällig ebenfalls vorliegt. Sonst spränge die Optik beim Ablaufen einer
     Lizenz überraschend auf etwas völlig anderes.
  Bewusst **keine** Datumslogik ("nur im Dezember"): die Berechtigung selbst ist
  der Punkt, und ein erworbenes Design soll nicht ungefragt wieder verschwinden.
  Wer den neutralen Zustand will, wählt ihn ausdrücklich.
  Regressionstest um fünf Prüfungen erweitert (Sonder-Edition zeigt ihr Design
  ohne Zutun; ausdrückliche Wahl schlägt die Automatik; bei mehreren
  Berechtigungen gewinnt deterministisch die neueste; eine ungültig gewordene
  Wahl fällt neutral zurück statt auf ein fremdes Design; Symmetrie-Check des
  Auslieferungszustands).
* **Build 146 (Nutzer-Wunsch): auswählbares Kachel-Symbol und auswählbare
  Kachel-Vorlage, als Wiedererkennungswert für Sonder-Editionen.** Ein
  Xmas-Special kann damit eine eigene Optik mitbringen, ohne dass jemand HTML
  anfassen muss.
  Zwei Kataloge im Code (`TILE_ICON_CATALOG`, `TILE_TEMPLATE_CATALOG`) führen die
  auslieferbaren Symbole bzw. Vorlagen. Jeder Eintrag trägt einen Anzeigenamen
  und optional ein benötigtes Lizenz-Feature; die Auswahlfelder im Formular
  werden daraus dynamisch gefüllt und dabei nach Berechtigung gefiltert - ein
  Saison-Design taucht also nur bei den Editionen überhaupt auf, die es erworben
  haben (Xmas 2026 sieht kein Nikolaus-Design und umgekehrt).
  **Die Berechtigung hängt an `features[]` im Lizenzschlüssel** - derselben
  Spalte, die es in `slips_orders` und `slips_promo_licenses` bereits gibt. Ein
  neues Saison-Design braucht damit keinerlei Schema-Änderung, sondern nur einen
  weiteren Eintrag in der Feature-Liste des Produkts (z. B. `theme_xmas2026`).
  Bewusst **nicht** am `edition`-Feld: das ist laut Schema ausdrücklich ein
  Werbename mit Fallback, ein Marketing-Umbenennen würde sonst still eine
  Berechtigung entziehen.
  **Zwei Entwurfsentscheidungen, die den Anforderungen "zurücksetzbar" und
  "geht bei Updates nicht verloren" zugrunde liegen:**
  1. Gespeichert wird **nur die ID**, nie der Inhalt. Der Inhalt kommt bei jedem
     Rendern frisch aus dem Code bzw. der Vorlagendatei. `propertyCustomTileHtml`
     zeigt das Gegenbeispiel: dort steht der Inhalt in der Instanz, und weil
     Properties bei einem Update zu Recht nicht überschrieben werden, erreichen
     spätere Korrekturen sie nie - real passiert, der Scrollbalken-Fix aus Build
     143 kam bei keiner Instanz an, die die eigene Kachel bereits aktiviert
     hatte. Mit einer ID bleibt die Auswahl stabil **und** die Vorlage
     wartbar. Zurücksetzen heißt schlicht: ID wieder auf `default`.
  2. Die Berechtigung läuft **nicht** über `HasLicenseFeature()`. Das gibt
     während der Testphase absichtlich alles frei, damit sich der Mechanismus
     vor dem Kauf ausprobieren lässt - für Saison-Designs würde genau das den
     Wiedererkennungswert aushebeln. Eigene Prüfung `HasThemeEntitlement()`:
     Einträge ohne Feature-Anforderung sind immer wählbar (die reinen
     Auslieferungszustände), alles Weitere ausschließlich mit einer **gültigen**
     Lizenz, die das Feature auch führt.
  Bei Downgrade/Ablauf greift die Auswahl nicht mehr, der gespeicherte Wert
  bleibt aber erhalten und lebt nach erneuter Lizenzierung sofort wieder auf -
  dasselbe Muster wie `custom_tile`/`auto_rescan`, kein Datenverlust.
  Ausgeliefert werden zunächst die beiden Auslieferungszustände: das
  Simple-Locale-Symbol (Standard) und die Weltkugel, die damit von einem reinen
  Notbehelf zu einer echten Auswahl wird. Der Katalog unterstützt sowohl
  Bilddateien als auch reine Zeichen - ein Saison-Symbol lässt sich dadurch auch
  ganz ohne neue Grafik ausliefern (z. B. 🎄), was den Modul-Download nicht
  vergrößert. Wer echte Grafik einsetzt, legt bewusst nur eine 48px-Variante
  dazu, nicht die 1024px-Datei (`module_icon.png` ist allein 1,1 MB).
  Der Haken heißt jetzt neutral "Symbol in der Kachel anzeigen" statt fest
  "Simple-Locale-Symbol …" - der Property-Name (`ShowGlobeIcon`) bleibt
  unverändert, damit bestehendes, an die CSS-Klasse gebundenes Kachel-HTML nicht
  ohne Not bricht.
  Neuer Regressionstest (jede Sonder-Edition sieht ausschließlich ihr eigenes
  Design; Auslieferungszustände sind überall wählbar; die Testphase bekommt
  trotz "alle Features frei" kein Saison-Design; Zurücksetzen und unbekannte IDs
  fallen sauber auf den Standard; Downgrade deaktiviert ohne zu verwerfen;
  Symmetrie-Checks, dass die Berechtigung `HasLicenseFeature()` umgeht und die
  Vorlage beim Rendern aus der Datei statt aus der Property kommt).
* **Build 145 (Nachtrag zu Build 144): Unterstützung der alten
  WebFront-Visualisierung nach Prüfung einer echten Instanz bewusst verworfen -
  die dabei geratenen Property-Namen wieder entfernt.**
  Build 144 hatte die Startkategorie über eine Liste möglicher Property-Namen
  gesucht, weil unklar war, wie die alte WebFront ihre Wurzel benennt. Die
  Konfiguration einer laufenden WebFront-Instanz zeigte dann: sie hat
  **überhaupt keine** Startkategorie-Property auf oberster Ebene. Ihr Aufbau
  steckt in `Items` - einem JSON-String mit Widgets, deren Kategorie-Verweise
  erst in einem **zweiten**, darin verschachtelten JSON liegen
  (`ClassName: "Category"` → `Configuration` → `baseID`). Zusätzlich kann eine
  WebFront **mehrere gleichrangige Wurzeln** haben (mehrere Category-Widgets),
  während das Modul strukturell von genau einer ausgeht.
  Nach Abwägung des Aufwands gegen den erwarteten Nutzen wurde die
  Unterstützung verworfen. Damit sind die in Build 144 aufgenommenen, nur
  geratenen Namen (`RootID`, `BaseCategory`, …) nicht bloß nutzlos, sondern ein
  Risiko: träfe so ein Name zufällig eine gleichnamige Property eines fremden
  Moduls, würde stillschweigend der **falsche** Baum übersetzt - deutlich
  schlimmer als ein sauberes `STATUS_ROOT_CATEGORY_MISSING`. Die Liste ist
  daher wieder auf den einzigen verifizierten Namen (`BaseID`,
  Kachel-Visualisierung) zurückgestutzt; ein Regressionstest hält sie dort.
  **Ausdrücklich erhalten bleibt der eigentliche Fund aus Build 144**: der
  Absturzschutz beim Zugriff auf die fremde Visualisierungs-Instanz. Der ist
  vom WebFront-Thema unabhängig und greift für **jede** dort ausgewählte
  Instanz, die eine erwartete Property nicht kennt - statt eines abgebrochenen
  Rescans gibt es jetzt eine saubere Statusmeldung.
  Bekannte Grenze, damit sie dokumentiert ist: die alte
  WebFront-Visualisierung wird nicht unterstützt. Sie lässt sich zwar auswählen,
  liefert dann aber `STATUS_ROOT_CATEGORY_MISSING` - bewusst, statt zu raten.
* **Build 144 (Nutzer-Wunsch: auch die alte WebFront-Visualisierung
  unterstützen): die Startkategorie wird jetzt über mehrere bekannte
  Property-Namen erkannt - und, deutlich wichtiger, der Zugriff auf die fremde
  Visualisierungs-Instanz stürzt nicht mehr ab.**
  Gemeldet war zunächst nur, dass die Root-Kategorie einer WebFront-Instanz
  nicht erkannt wird ("vermutlich nutzt Symcon einen anderen Namen"). Die
  Ursachensuche förderte aber ein zweites, ernsteres Problem zutage:
  `IPS_GetProperty()` wirft bei einem **unbekannten** Property-Namen eine
  Exception - und `@` unterdrückt in PHP nur Warnungen, **niemals** Exceptions.
  Der Code las die fremde Instanz an zehn Stellen direkt per
  `@IPS_GetProperty($visu, 'Automations'/'GreetingName'/'ShowGreeting'/…)` aus.
  Bei einer Instanz, die diese Properties nicht kennt - genau der Fall bei der
  alten WebFront-Visualisierung - riss damit nicht nur die Root-Erkennung ab,
  sondern der komplette Rescan mit einer unbehandelten Exception.
  Alle zehn Lesezugriffe laufen jetzt über `IPS_GetConfiguration()` (liefert das
  JSON **aller** vorhandenen Properties; ein fehlender Schlüssel ist damit ein
  ganz normaler Array-Miss statt eines Abbruchs), gekapselt in
  `GetVisuInstanceProperties()`/`GetVisuInstanceProperty()`. Die beiden
  **schreibenden** Zugriffe (`GreetingName`, `Automations`) prüfen über
  `VisuInstanceHasProperty()` vorher, ob es die Property dort überhaupt gibt -
  `IPS_SetProperty()` wirft bei unbekanntem Namen genauso. Das ist nicht
  theoretisch: wer seine Instanz zuerst mit der Kachel-Visualisierung betreibt
  (dabei entstehen Begrüßungs-/Automations-Zeilen) und sie danach auf die alte
  WebFront-Visualisierung umstellt, behält diese Zeilen.
  Die Startkategorie löst `ResolveVisuRootCategoryID()` über eine feste
  Kandidatenliste auf (`BaseID` zuerst, damit die Kachel-Visualisierung immer
  Vorrang behält). Bewusst **keine** blinde Suche nach "irgendeiner
  ID-Property": die könnte eine Verweis-Property auf eine ganz andere Kategorie
  erwischen und stillschweigend den falschen Baum übersetzen - deutlich
  schlimmer als ein sauberes `STATUS_ROOT_CATEGORY_MISSING`. Passt kein
  Kandidat, werden die tatsächlich vorhandenen Property-Namen einmal in die neue
  Debug-Kategorie `SLOC_Visu` geschrieben, damit sich ein bislang unbekanntes
  Visualisierungs-Modul ohne Raterei nachtragen lässt.
  Funktionsumfang bei der alten WebFront-Visualisierung: Objektnamen, Eigene
  Texte, Aufzählungen und Charts werden normal übersetzt. Automations,
  Begrüßung und Favoriten sind Eigenschaften der Kachel-Visualisierung und
  bleiben dort naturgemäß leer - jetzt aber sauber leer statt mit einem
  Abbruch.
  Neuer Regressionstest (9 Prüfungen: `BaseID` funktioniert unverändert;
  abweichende Namen werden erkannt; die Reihenfolge entscheidet; ein Kandidat
  mit gelöschtem Objekt wird übersprungen; eine unbekannte Visualisierung
  liefert sauber 0 statt blind irgendeiner ID; fehlende Properties ergeben leere
  Werte statt einer Exception; kaputtes JSON/unerreichbare Instanz ebenso;
  Symmetrie-Check, dass kein ungeschützter Zugriff auf die fremde Instanz mehr
  existiert).
* **Build 143 (Nutzer-Wunsch mit Screenshot): die eingebaute Kachel zeigte bei
  Visualisierungs-Höhe "1" einen Scrollbalken, weil sie nur wenige Pixel zu hoch
  war.** Höhe "1" ist für eine reine Sprachauswahl die naheliegende Einstellung,
  entsprechend viele Nutzer werden sie wählen - ein Scrollbalken für ein paar
  ungenutzte Pixel sieht dort schlicht unfertig aus.
  Der Platz unter dem Dropdown ist genau dann ungenutzt, wenn keine der drei
  optionalen Hinweiszeilen (Testphase / Anbieter-Pause / Statistik) angezeigt
  wird. Nur in diesem Fall bekommt die Zeile jetzt die zusätzliche CSS-Klasse
  `sloc-compact` und holt sich den Platz per negativem unteren Rand zurück.
  Sind Hinweise sichtbar, braucht die Kachel die Höhe ohnehin - dann bleibt
  alles unverändert (genau der vom Nutzer benannte Kompromiss: "wenn der User
  die Statistiken sehen will, lässt sich das nicht ändern").
  Bewusst **nur nach unten**: oben reserviert Symcon den Platz für Titel und
  Vergrößern-Symbol der Kachel (siehe den langjährigen Kommentar am Anfang von
  `module.html`) - ein negativer Rand dort würde das Dropdown unter die
  Titelzeile schieben. Die Höhe der Bedienelemente selbst (`--sloc-control-height`,
  38px) bleibt ebenfalls unangetastet; verkleinert wird ausschließlich
  ungenutzter Leerraum. Reicht der zurückgewonnene Platz auf einer bestimmten
  Installation nicht ganz, ist der `margin-bottom`-Wert in dieser einen
  CSS-Regel der vorgesehene Stellwert.
  Hinweis für bestehende Instanzen mit **eigener** Kachel (Pro-Feature
  `custom_tile`): deren HTML ist eine zum Anlegezeitpunkt gezogene Kopie von
  `module.html` und enthält die neue Regel daher nicht. Die Klasse wird dort
  gesetzt, bleibt aber wirkungslos - kein Fehler, und die Regel lässt sich bei
  Bedarf von Hand nachtragen.
  Neuer Regressionstest (kompakt genau dann, wenn keine Hinweiszeile vorliegt;
  jede der drei Hinweisarten verhindert den Kompaktmodus einzeln; die
  Basisklasse bleibt immer erhalten, da Layout und ggf. eigenes Nutzer-CSS daran
  hängen; die Hinweise werden nur noch an einer Stelle gebaut, damit sie nicht
  doppelt gerendert werden; die CSS-Regel wirkt nachweislich ausschließlich nach
  unten, nie in den Titelbereich).
* **Build 142 (live gemeldeter Bug): ein Klick in der eigenen
  Sprachauswahl-Kachel konnte die Instanz dauerhaft unspeicherbar machen.**
  Szenario: frische Instanz, Testversion, eigene Sprachauswahl-Kachel
  (Pro-Feature `custom_tile`) aktiviert. Deren mitgeliefertes **Beispiel** zeigt
  zwei feste Flaggen mit fest eingetragenen Sprachcodes - ein Klick auf die
  englische Flagge schickte `en`, obwohl `en` auf dieser Instanz gar keine
  konfigurierte Zielsprache war. Der Code landete ungeprüft in
  `propertyCurrentLanguage`; Symcons Konfigurationsformular baut daraus ein
  Select, das nur die tatsächlich konfigurierten Sprachen kennt, und verweigerte
  daraufhin **jedes weitere Speichern der Instanz** ("Invalid configuration /
  Current value "en" is not available"). Da praktisch jeder Nutzer das
  mitgelieferte Beispiel einmal ausprobiert - das ist ja sein Zweck -, hätte das
  potenziell sehr viele Neuinstallationen getroffen, jeweils mit einem Modul,
  das sich plötzlich "nicht mehr bedienen lässt". Zusätzlich kann ein Nutzer in
  seiner eigenen Kachel jederzeit beliebige weitere ungültige Codes eintragen.
  Zwei Ebenen dagegen:
  1. **Vorbeugend:** `RequestAction()` prüft den gewünschten Sprachcode jetzt
     zuerst gegen die tatsächlich wählbaren Sprachen (neuer Helfer
     `IsSelectableGuestLanguage()` - dieselben Codes, die auch das Formular-Select
     anbietet, zusätzlich die Quellsprache und der interne Rückfall
     `ORIGINAL_IMPORT`). Ein unbekannter Code wird abgelehnt, die aktive Sprache
     bleibt unverändert stehen, und die Ablehnung landet mit Angabe der
     konfigurierten Sprachen in der neuen Debug-Kategorie `SLOC_Language`. Die
     Prüfung läuft bewusst **vor** der Testphasen- und Rate-Limit-Behandlung,
     sonst könnte ein ungültiger Code an ihr vorbei in die Property gelangen.
  2. **Heilend:** eine Instanz, die bereits in diesem Zustand feststeckt, wäre
     davon allein nicht gerettet - ihr Formular lässt sich ja gerade nicht mehr
     übernehmen, um den Wert von Hand zu korrigieren. `ApplyChanges()` prüft die
     gespeicherte aktive Sprache daher ebenfalls und setzt sie bei Bedarf auf die
     Quellsprache zurück. Das greift auch in einem Fall ganz ohne eigene Kachel:
     wenn der Admin die gerade aktive Zielsprache aus der Liste entfernt.
  Das mitgelieferte Kachel-Beispiel trägt jetzt außerdem die Überschrift
  "Custom tile example:" sowie einen deutlichen Kommentar, dass die Sprachcodes
  darin fest eingetragen sind und zu den eigenen Zielsprachen passen müssen.
  Neuer Regressionstest (9 Prüfungen: nicht konfigurierter Code wird abgelehnt
  ohne die aktive Sprache anzutasten; konfigurierte Codes funktionieren normal
  weiter; Quellsprache und `ORIGINAL_IMPORT` bleiben immer zulässig; leerer Code
  wird abgelehnt; eine bereits blockierte Instanz heilt sich selbst; gültige
  Sprachen werden von der Heilung nie angefasst; das Entfernen der aktiven
  Zielsprache führt sauber zurück; Symmetrie-Check inkl. der Reihenfolge der
  Prüfung).
* **Build 141 (zwei live gemeldete Bugs auf einer frischen Instanz, beide rund
  um "unbenannte Objekte"): die Liste der unbenannten Objekte blieb nach einem
  Rescan-Abbruch unsichtbar, und ein späteres "Übernehmen" setzte den Status
  fälschlich zurück auf "Aktiv".**
  Bug 1: Der erste Klick auf "Rescan" meldete korrekt "Unnamed objects found -
  see list in form." - die Liste war im Formular aber nirgends zu sehen. Sie
  tauchte erst später auf, als der Nutzer aus einem völlig anderen Grund (eine
  Zielsprache hinzugefügt) auf "Übernehmen" klickte. Ursache: der
  Abbruch-Zweig in `ScanRootTree()` kehrt VOR dem abschließenden
  `IPS_ApplyChanges()` zurück. Die Annahme aus Build 116 ("die Konsole lädt das
  Formular nach jedem RequestAction ohnehin selbst neu") stimmt aber nur, WEIL
  der normale Durchlauf dieses `IPS_ApplyChanges()` erreicht - DAS löst den
  Reload aus, nicht die RequestAction an sich. Der Abbruch-Zweig bekam dadurch
  nie einen Reload, und das gerade frisch geschriebene Attribut wurde nie
  gerendert. `ScanRootTree()` bekommt dafür einen neuen Parameter
  `$IsInteractive`, den ausschließlich der manuelle Weg (`Rescan()`/
  `SLOC_Rescan()`) setzt - der Auto-Rescan-Timer läuft bewusst weiterhin ohne,
  damit der bereits in Build 60 behobene Bug (ein Hintergrund-Rescan reißt dem
  Admin das offene Formular mitten in der Bearbeitung weg und verwirft unsavte
  Änderungen) nicht wieder eingeschleppt wird.
  Bug 2: Nach jenem "Übernehmen" zeigte die Statuszeile "Aktiv" (102), obwohl
  die Liste der unbenannten Objekte im selben Formular unverändert sichtbar
  darunter stand und weiterhin jeden Rescan blockiert - Formular und
  Statuszeile widersprachen sich offen. Ursache: `ApplyChanges()` hat den von
  `ScanRootTree()` gesetzten `STATUS_UNNAMED_OBJECTS` kommentarlos
  überschrieben, da es die anstehenden unbenannten Objekte gar nicht kannte.
  Die Status-Kaskade berücksichtigt sie jetzt - eingeordnet nach den
  fundamentaleren Blockern (fehlender Visualisierungs-Root, abgelaufene
  Testphase), aber vor der sich selbst auflösenden Anbieter-Pause.
  Strukturelle Absicherung gegen ein erneutes Auseinanderlaufen: das Attribut
  `attributeUnnamedObjects` wird jetzt nur noch an EINER einzigen Stelle
  gelesen (neuer gemeinsamer Helfer `GetPendingUnnamedObjects()`/
  `HasPendingUnnamedObjects()`), aus der sich sowohl die Statuszeile als auch
  die Sichtbarkeit der Liste im Formular speisen.
  Neuer Regressionstest (manueller Rescan-Abbruch lädt das Formular selbst
  neu; der Hintergrund-Rescan tut das weiterhin NIE; der normale Durchlauf
  bleibt unberührt; ein späteres "Übernehmen" meldet weiterhin
  STATUS_UNNAMED_OBJECTS statt "Aktiv"; nach behobenen Benennungen meldet der
  Status wieder normal "Aktiv"; die Status-Rangfolge stimmt; Symmetrie-Check
  inkl. der einzigen verbliebenen Lesestelle des Attributs).
  Enthält außerdem eine bereits zuvor vorbereitete, noch nicht eingecheckte
  Textkorrektur: die beiden Begrüßungs-Modushinweise sagten "wird unten
  übersetzt", obwohl die Begrüßungstabelle im umgebauten Formular (siehe Build
  130) nicht mehr zwingend direkt darunter steht - das "unten" ist jetzt in
  allen vier Sprachen entfernt.
* **Build 140 (Nutzer-Wunsch, im Vorfeld der IPS-Store-Einreichung): alle
  während der Live-Fehlersuche dieser Session eingebauten, als "temporaer"
  markierten Diagnose-Ausgaben sowie weitere inzwischen überflüssige
  Debug-Zeilen entfernt.** Betroffen: der einmalige Marker in `AutoRescan()`
  (Build 122, unterschied den Auto-Timer vom manuellen Rescan-Klick), der
  komplette Vorher-Zustand-Mitschnitt in `ReconcileRowSourceLanguageChanges()`
  (Build 121, ursprünglich für den Automations/ObjectNames/Begrüßung-
  Korruptionsverdacht gedacht), die beiden "Cache-Miss"-Zeilen in
  `GetCachedTranslation()` sowie die Cache-Größen-Mitschnitte in
  `StoreCachedTranslation()`/`StoreCachedTranslationsBatch()` (Build 126-128,
  Cache-Selbstverdrängungs-Untersuchung). Der dadurch nutzlos gewordene
  `$DebugContext`-Parameter dieser drei Cache-Funktionen wurde ebenfalls
  entfernt (inkl. aller Aufrufstellen) statt als toter Parameter stehen zu
  bleiben.
  Zusätzlich (Nutzer: "ggf. auch nun überflüssige Debugs") eigenständig
  identifiziert und entfernt: die vier Zeilen-für-Zeilen-Mitschnitte der
  kompletten Begrüßungs-Zeile bei jedem einzelnen Rescan-Durchlauf in
  `ScanRootTree()` (existingGreeting/scannedGreeting, mergedGreeting,
  filledGreeting, persisted - allesamt Teil derselben, inzwischen
  abgeschlossenen Untersuchung, aber nie explizit als "temporaer" markiert),
  die beiden reinen Ablauf-Meldungen zu unbenannten Objekten (der eigentliche
  Zustand steht ohnehin bereits in `attributeUnnamedObjects`/`SetStatus`),
  sowie alle vier Zeilen in `EnsureSourceLanguageIsTarget()` (Build 79,
  reine Ablaufverfolgung einer inzwischen längst stabilen, einfachen
  Funktion, keine erkennbare weitere Diagnose nötig).
  Bewusst NICHT angetastet: die permanenten Diagnose-Kategorien
  `GoogleTranslate_Request`/`_Response`, `DeepLTranslate_Request`/`_Response`,
  `FreeTranslate_Request`/`_Response`, `GoogleTranslate_Mapping` sowie deren
  Fehler-Gegenstücke - das sind die eigentlichen, dauerhaft nützlichen
  Diagnose-Werkzeuge dieses Moduls (in dieser Session wiederholt zur echten
  Fehlersuche anhand von Nutzer-Dumps verwendet), ebenso `ClearTranslationCache`
  (seltene, admin-ausgelöste Aktion) und die Meldung in
  `DeduplicateTextRowsByValueObjectID()` (seltenes, potenziell
  datenverlust-relevantes Ereignis).
  `php -l` sauber, volle Test-Suite grün, keine funktionale Änderung -
  reines Aufräumen vor der Einreichung im IP-Symcon Module Store.
* **Build 139 (direkter Nachbericht zu Build 138, Nutzer-Feedback per
  Screenshot): das gerade erst auf "ⓘ" umgestellte Info-Zeichen sah in der
  echten Konsole aus wie ein "Aus"-/Standby-Symbol, nicht wie ein
  Info-Zeichen.** Ursache: die Kachel selbst rendert "ⓘ" über ihr eigenes,
  freies HTML/CSS (siehe `module.html`), während die vier Info-`PopupButton`s
  im Konfigformular von der nativen Symcon-Konsole mit deren eigener
  Systemschrift dargestellt werden - dort erschien exakt dasselbe
  Unicode-Zeichen missverständlich. Auf Vorschlag des Nutzers ("das ist am
  sichersten") auf reinen, eindeutigen Klartext "Information" umgestellt
  statt eines erneuten Icon-Versuchs - Breite von 40px (Icon-Größe) auf 110px
  (Textbreite) angepasst, neue Übersetzung in allen vier Sprachen ergänzt.
  Der vom Nutzer eigentlich bevorzugte Ansatz - ein rahmenloses Icon wie in
  der Kachel, das dennoch beim Klick das Popup öffnet - ist über form.json
  technisch nicht erreichbar: jeder Button-/PopupButton-Typ der Symcon-
  Konsole rendert zwingend mit eigenem Rahmen/Chrome; nur die Kachel selbst
  kann als freies HTML/CSS komplett ohne Button-Optik auskommen. Klartext
  bleibt damit die zuverlässigste Lösung innerhalb der Konsole.
  Bestehender Regressionstest angepasst (Buttons zeigen jetzt "Information"
  statt eines Icon-Zeichens, entsprechend breiter statt Icon-schmal), volle
  Suite grün.
* **Build 138 (zwei Nutzer-Wünsche): die vier Info-Buttons unten im
  Konfigformular sehen jetzt aus wie das Info-Symbol der Kachel, UND die
  "Übersetzung aktiv"-Checkbox aus Build 135-137 ist ab sofort ein
  Pro-Feature.** Erster Punkt, direkter Nachbericht zu Build 130/131: die vier
  Info-`PopupButton`s trugen bisher WIDERSPRÜCHLICH gleichzeitig ein
  Symcon-eigenes Icon (`"icon": "information"`) UND das Emoji "ℹ️" als
  Caption - vermutlich der eigentliche Grund für den schon damals gemeldeten
  "sieht komisch aus"-Eindruck. Die Kachel selbst verwendet für ihr eigenes
  Info-Symbol gar kein Symcon-Icon, sondern schlicht das Zeichen "ⓘ" als
  reinen Text-Span (siehe `$infoIconHtml` in `GetVisualizationTile()`) - die
  vier Buttons wurden exakt darauf angeglichen (Caption jetzt "ⓘ", das
  zusätzliche Symcon-Icon entfernt). Nicht änderbar bleibt dagegen das
  "Zahnradsymbol" im Kopf des sich öffnenden Popup-Dialogs selbst - das ist
  Teil von Symcons eigener Konsolen-Darstellung für `PopupButton`-Popups und
  wird nicht über form.json konfiguriert.
  Zweiter Punkt: die "Übersetzung aktiv"-Checkbox ist jetzt ein Pro-Feature
  (`edit_translations`, dasselbe bereits bestehende Feature, das auch die
  manuelle Korrektur einzelner Übersetzungszellen freischaltet) - ohne Pro
  wird die Spalte komplett WEGGELASSEN, nicht nur schreibgeschützt (anders als
  z. B. bei der "Quellsprache"-Spalte). Bewusst NUR die Formular-Spalte
  selbst ist lizenzabhängig - `GetEffectiveSelectedLanguage()`,
  `BackfillTranslationActiveFlag()` und die neue automatische
  JSON-Erkennung aus Build 137 laufen unverändert für JEDE Lizenz: eine
  bereits gespeicherte Deaktivierung bleibt so auch nach einem Downgrade von
  Pro konsistent wirksam, und die schon lange vor dieser Checkbox bestehende
  automatische JSON-Ausnahme (Build 84) funktioniert unabhängig von der
  Lizenz weiterhin in jeder Edition. Wie vom Nutzer selbst angemerkt: dieselbe
  Wirkung ließe sich in jeder Edition ohnehin schon manuell nachbilden (die
  jeweilige Zielsprachen-Zelle einzeln leeren) - Pro schaltet also nur den
  Komfort ("einmal ankreuzen, gilt für alle Sprachen gleichzeitig") frei,
  keine grundsätzlich neue technische Möglichkeit.
  Neuer Regressionstest (die Info-Buttons verwenden jetzt exakt dasselbe
  "ⓘ"-Zeichen wie die Kachel, ohne redundantes Symcon-Icon; die Checkbox-
  Spalte fehlt komplett ohne die Pro-Lizenz statt nur schreibgeschützt zu
  sein; die Auflösungslogik bleibt nachweislich frei von jedem eigenen
  Lizenz-Check), volle Suite grün.
* **Build 137 (direkter Nachbericht zu Build 135/136, Nutzer-Wunsch): die
  "Übersetzung aktiv"-Checkbox wird jetzt automatisch auf "inaktiv" gesetzt,
  sobald der Rohtext einer Zeile gültiges JSON ist.** Hintergrund: JSON-
  Rohtext (siehe `LooksLikeJson()`, Build 84 - erkennt Konfigurations-/
  Steuerdaten für ein anderes Modul statt echten Anzeigetext) wurde in
  `FillLanguageColumn()` schon immer UNBEDINGT von jeder Übersetzung
  ausgenommen, komplett unabhängig vom Stand der Checkbox - für so eine Zeile
  hatte die Checkbox also faktisch nie eine Wirkung, konnte der Konsole aber
  trotzdem fälschlich "wird übersetzt" (angehakt) anzeigen. Ein Rescan setzt
  die Checkbox jetzt aktiv auf "inaktiv", sobald das zutrifft - neue Funktion
  `AutoDeactivateTranslationForJsonContent()`, läuft direkt nach
  `BackfillTranslationActiveFlag()` an denselben sechs Stellen in
  `ScanRootTree()`, mit dem jeweils passenden Rohtext-Feld je Zeilenform
  (`ORIGINAL_IMPORT_Text` bei "Eigene Texte", sonst überall `ORIGINAL_IMPORT`).
  Bewusst nur EINSEITIG wirksam (niemals automatisch wieder auf "aktiv"
  zurückgesetzt): hört ein Rohtext auf, JSON zu sein, bleibt eine
  zwischenzeitlich vom Admin aus einem völlig anderen Grund manuell
  deaktivierte Zeile (z. B. ein Eigenname) unangetastet deaktiviert, statt
  stillschweigend reaktiviert zu werden.
  Neuer Regressionstest (eine Zeile mit JSON-Rohtext und noch aktivem
  Standardwert wird beim nächsten Rescan automatisch deaktiviert; eine Zeile
  mit normalem Text bleibt von der Automatik komplett unberührt; eine bereits
  aus anderem Grund deaktivierte, nicht-JSON-Zeile wird NIE automatisch
  wieder aktiviert; Symmetrie-Check, dass die neue Funktion tatsächlich mit
  dem jeweils korrekten Rohtext-Feld je Tabelle in alle sechs Stellen
  verdrahtet ist), volle Suite grün.
* **Build 136 (direkter Nachbericht zu Build 135, Nutzer-Korrektur): die
  "Übersetzung aktiv"-Checkbox war als "jede einzelne Zeile in DEN
  Übersetzungstabellen" gemeint, nicht nur für Objektnamen/Automations/
  Begrüßung.** Konkret gewünscht: bei "Eigene Texte" soll sich damit z. B.
  gezielt eine einzelne Stringvariable von der Übersetzung ausschließen
  lassen, deren Inhalt in Wahrheit JSON-Steuerdaten für ein anderes Modul ist
  - "implizit, als ob die Übersetzungszellen gelöscht wurden, nur mit einer
  Checkbox". Die Checkbox ist jetzt zusätzlich in "Eigene Texte",
  "Aufzählungen" und "Charts" verfügbar - zusammen mit den bereits
  vorhandenen drei Tabellen aus Build 135 damit in allen sechs gescannten
  Zeilen-Tabellen. Bewusst weiterhin NICHT in der "Eigenen
  Übersetzungstabelle" (ManualTranslations) - dort wird strukturell nie
  automatisch übersetzt, ein "nie übersetzen"-Schalter wäre dort wirkungslos.
  Wichtig zur Abgrenzung (vom Nutzer selbst angesprochen): die bereits
  bestehende automatische JSON-Erkennung (`LooksLikeJson`, Build 84) bleibt
  von dieser Checkbox unberührt - sie ist ein zusätzlicher, admin-
  gesteuerter Mechanismus, kein Ersatz dafür. Bei echtem JSON-Inhalt bleibt
  die Checkbox typischerweise angehakt/aktiv, die automatische Erkennung
  verhindert eine Übersetzung unabhängig davon bereits von selbst.
  Technisch etwas anspruchsvoller als die ursprünglichen drei Tabellen, da
  zwei Fälle eine echte Zeilen-für-Zeilen-Granularität statt einer einzigen
  Sprachweiche pro Aufruf brauchen: `ApplyEnumerationOptionsToVariable()`
  baut eine EINZIGE Variablen-Präsentation aus MEHREREN Zeilen zusammen (ein
  Feld je Caption/Prefix/Suffix) - hier wird die Weiche pro Feld einzeln
  angewendet, sodass ein deaktiviertes Feld seinen Rohtext zeigt, während ein
  anderes, weiterhin aktives Feld DERSELBEN Variable normal übersetzt wird
  (kein Alles-oder-nichts-Schalter für die ganze Variable). `ApplyChartsLanguage()`
  bekam dieselbe Behandlung pro Datenreihen-Titel. Zusätzlich musste die
  LIVE-Nachübersetzung bei externen Schreibvorgängen
  (`ApplyTrackedVariableUpdate`, gemeinsam genutzt von "Eigene Texte" und
  "Begrüßung" im Modus "Variable") um dieselbe Prüfung ergänzt werden - sonst
  hätte ein deaktiviertes "Eigene Texte"-Feld bei jedem externen
  Schreibvorgang trotzdem unnötig API-Kontingent verbraucht, auch wenn das
  Ergebnis nie geschrieben worden wäre.
  Neuer Regressionstest (eine deaktivierte "Eigene Texte"-Zeile löst exakt
  wie vom Nutzer gefordert zum Rohtext auf; innerhalb einer geteilten
  Variablen-Präsentation bleibt ein deaktiviertes Feld roh, während ein
  aktives Geschwisterfeld weiterhin übersetzt; eine deaktivierte Zeile wird
  bei einem live ankommenden externen Wert nicht mehr an die API geschickt;
  Symmetrie-Check, dass Checkbox und Auflösungslogik jetzt tatsächlich in
  allen sechs Tabellen verdrahtet sind, weiterhin NICHT in der Eigenen
  Übersetzungstabelle), volle Suite grün.
* **Build 135 (Nutzer-Wunsch): neue Checkbox "Übersetzung aktiv" in
  "Objektnamen", "Automations" und "Begrüßung" - deaktiviert für genau eine
  Zeile dauerhaft jede Übersetzung.** Gedacht für Einträge, die bewusst NIE
  übersetzt werden sollen (Eigennamen, Marken, technische Kürzel) - z. B. ein
  Mitbewohner-Name in einer Präsenz-Anzeige, der sonst je nach Zielsprache
  unerwünscht "übersetzt" würde. Eine deaktivierte Zeile zeigt ab sofort immer
  ihren Rohtext, unabhängig von der aktuell aktiven Gast-Sprache - technisch
  über eine neue, zentrale Weiche `GetEffectiveSelectedLanguage()`, die für
  eine deaktivierte Zeile an allen drei Schreibstellen
  (`ApplyLanguage`/`ApplyAutomationsLanguage`/`ApplyGreetingLanguage`)
  konsequent die Pseudo-Sprache `ORIGINAL_IMPORT` statt der echten Zielsprache
  an `ResolveRowValue()` übergibt.
  Bewusst mit "true" (Übersetzung aktiv) vorbelegt - der Admin schaltet
  gezielt einzelne Ausnahmen ab, nicht umgekehrt. Ein Rescan fragt für eine
  deaktivierte Zeile erst gar keine Übersetzung mehr an (spart API-Kontingent
  für Inhalte, die ohnehin nie in übersetzter Form gezeigt würden).
  Sorgfältig gegen bereits VOR Build 135 gespeicherte Installationen
  abgesichert: eine alte Zeile ohne das neue Feld wird beim nächsten Rescan
  aktiv mit `true` nachgetragen (neue Funktion
  `BackfillTranslationActiveFlag()`, analog zu `BackfillRowSourceLanguage()`
  aus Build 70) - ohne diesen Schritt hätte die Checkbox in der Konsole für
  JEDEN bestehenden Eintrag fälschlich "nicht abgehakt" angezeigt (Symcons
  List-Element zeigt eine fehlende Checkbox-Vorbelegung als unchecked an),
  obwohl die Zeile weiterhin ganz normal übersetzt worden wäre - ein rein
  kosmetischer Unterschied hätte bei einer versehentlichen Bearbeitung eines
  ANDEREN Felds derselben Zeile sofort zu echtem, unbeabsichtigtem
  Übersetzungsausfall geführt. Der Backfill überschreibt dabei nie eine
  bereits bewusst deaktivierte Zeile (`array_key_exists()`-Prüfung, nicht nur
  "ist das Feld leer").
  Zunächst nur für "Objektnamen", "Automations" und "Begrüßung" umgesetzt -
  siehe Build 136 für die direkt im Anschluss erfolgte Ausweitung auf alle
  übrigen gescannten Zeilen-Tabellen.
  Neuer Regressionstest (eine deaktivierte Zeile löst immer zum Rohtext auf,
  unabhängig von der aktiven Sprache; eine aktive Zeile übersetzt weiterhin
  ganz normal; eine Zeile ohne das Feld gilt als aktiv UND wird korrekt mit
  `true` nachgetragen; eine bewusst deaktivierte Zeile wird vom Backfill nie
  zurückgesetzt; Symmetrie-Check, dass die Checkbox exakt in den drei
  angefragten Listen verdrahtet ist, in keiner weiteren), volle Suite grün.
* **Build 134 (direkter Nachbericht zu Build 133, Nutzer-Wunsch: "prüfe alle
  Übersetzungen der Abkürzungen mal explizit auf Korrektheit"): nicht jede
  Einheit aus Build 133 ist tatsächlich in jeder der 9 Sprachen identisch.**
  Konkreter Auslöser: "km/h" wird im Spanischen umgangssprachlich als "kph"
  abgekürzt, nicht mit dem lateinischen SI-Kürzel "h" für Stunde. Bei der
  daraufhin angeforderten vollständigen Prüfung aller ~68 Einheiten- und
  aller 16×9 Kompass-Kürzel gegen die jeweilige Sprachlogik (Wortmuster,
  Zwischenrichtungs-Systematik) bestätigten sich zwei weitere,
  strukturell identische Fälle: Niederländisch schreibt Geschwindigkeit
  ("uur" = Stunde) als "km/u", Türkisch ("saat" = Stunde) als "km/sa". Neue
  Konstante `UNIT_BUNDLED_LANGUAGE_OVERRIDES` (Einheit => Sprache =>
  abweichendes Kürzel), angewendet NACH dem universellen Durchreichen aus
  Build 133 - reine Energie-Einheiten mit "h" (Wh/kWh/Ah/mAh) sind davon
  bewusst NICHT betroffen, da Stromrechnungen/Batteriepackungen dort auch in
  diesen drei Sprachen weiterhin unverändert das international übernommene
  SI-Kürzel verwenden, nur die Geschwindigkeitsangabe weicht ab.
  Größerer Fund bei derselben Prüfung: Russisch schreibt in der Praxis
  (Konsumgeräte, Windows-Lokalisierung, GOST-Normschreibweise) fast
  durchgehend KYRILLISCHE Kürzel statt lateinischer SI-Symbole - "kg" wäre
  dort als reines Durchreichen schlicht falsch, korrekt ist "кг". Rund 60
  der 68 Einheiten-Einträge bekommen daher jetzt eine eigene russische
  Übersetzung (u. a. "км/ч", "кВт·ч", "Гц", "Дж", "об/мин") - bewusst
  ausgenommen bleiben "%"/"‰" (universelle Symbole), "°F"/"psi" (in Russland
  praktisch nie genutzte nicht-metrische Einheiten) sowie "ppm"/"ppb" (auch
  im Russischen überwiegend als lateinisches Fachkürzel übernommen).
  Bei der gleichen Gelegenheit wurde die komplette Kompass-Tabelle aus Build
  133 nochmal Zeile für Zeile gegen die jeweilige Richtungs-Systematik
  durchgerechnet (Niederländisch: Z für Zuid statt S, O für Oost statt E;
  Polnisch: bewusst identisch zu Englisch, da "Północ"/"Południe" beide mit
  P beginnen und eigene Initialen dort unbrauchbar wären; Russisch und
  Türkisch: vollständig gegen die systematische
  "nähere-Haupt-plus-Zwischenrichtung"-Logik verifiziert) - keine weiteren
  Fehler gefunden. Eine echte Unsicherheit blieb: europäisches Portugiesisch
  nutzt "E" (Este) für Ost, brasilianisches Portugiesisch dagegen häufig "L"
  (Leste) - da das Modul nur ein einziges "pt" ohne BR/PT-Unterscheidung
  kennt und beide Varianten sprachlich korrekt sind, wurde dies dem Nutzer
  explizit zur Entscheidung vorgelegt statt einseitig geraten; Ergebnis:
  bleibt beim bisherigen "E" (europäisch).
  Neuer Regressionstest (km/h bekommt die bestätigten es/nl/tr/ru-Ausnahmen
  statt naivem Durchreichen des deutschen Kürzels, Einheiten ohne eigene
  Ausnahme fallen weiterhin auf das universelle Kürzel zurück, Russisch
  bekommt für "kg" & Co. tatsächlich die kyrillische Form, Symmetrie-Check
  gegen die reale Konstante inkl. mehrerer Stichproben-Einträge), volle
  Suite grün.
* **Build 133 (Nutzer-Wunsch, gemeinsam hergeleitet): "Eigene
  Übersetzungstabelle" wird jetzt ab Standard-Lizenz mit Vorschlagszeilen
  für gängige Maßeinheiten und Kompassrichtungen vorbefüllt - erspart
  unnötige (und teils sogar fehleranfällige) API-Aufrufe für Zahl-plus-
  Einheit-Texte.** Auslöser war ein Dump-Fund: kurze Texte wie "0.82 m/s"
  gingen bisher immer an den Übersetzungsanbieter, obwohl praktisch jede
  gängige SI-/Alltagseinheit (°C, kg, km/h, hPa, kWh, ...) in jeder
  unterstützten Sprache identisch geschrieben wird - reine Verschwendung
  von Kontingent. Schlimmer: dabei beobachtet wurde eine tatsächlich
  FALSCHE automatische Übersetzung von "°C" nach "°F" (Zahlenwert blieb
  gleich, Anzeige wäre dadurch falsch gewesen).
  Kompassrichtungen sind dagegen das Gegenteil von universell -
  dieselbe Buchstabenfolge kann in verschiedenen Sprachen Gegenteiliges
  bedeuten: deutsch "O" = Ost/East, spanisch "O" = Oeste/**West** -,
  Russisch und Türkisch verwenden zusätzlich völlig andere Buchstaben statt
  N/O/S/W. Ein reines 1:1-Durchreichen (wie bei den Einheiten) wäre hier
  also grundsätzlich falsch.
  Bewusst NICHT als unsichtbare interne Tabelle umgesetzt (erste Idee),
  sondern als vorbefüllte Zeilen der bereits vorhandenen, sichtbaren
  "Eigenen Übersetzungstabelle" (`MergeBundledManualTranslations()`,
  analog zu `MergeOwnUiTextRows()` aus Build 78/85) - der Admin sieht die
  Vorschläge direkt im Formular und kann jeden einzelnen jederzeit löschen
  (Beispiel: "SSW" kollidiert in einer Installation zufällig mit einem
  Personen-Kürzel statt einer Windrichtung). Eine einmal gelöschte
  Vorbelegung kehrt dank eines neuen Merkzettel-Attributs
  (`attributeSeededManualTranslationKeys`) bei einem späteren Rescan nicht
  zurück - anders als bei `propertyOwnUiTexts` gibt es hier also KEINE
  Zwangs-Neuerzeugung.
  9 Zielsprachen (Englisch, Spanisch, Französisch, Italienisch,
  Portugiesisch, Niederländisch, Polnisch, Russisch, Türkisch) - bewusst
  nicht weiter ausgebaut: jede zusätzliche Sprache müsste die
  Kompass-Kürzel und ihre sprachspezifische Bedeutung einzeln verifizieren,
  sonst droht genau die Art Fehler, die diese Tabelle eigentlich vermeiden
  soll. Wie beim restlichen Glossar naturgemäß nur ab Standard-Lizenz
  (`manual_translations`) - die Light-Edition ruft für diese Fälle
  weiterhin ganz normal die API auf.
  Neuer Regressionstest (universelle Einheiten identisch über alle
  Sprachen, Kompassrichtungen genuin sprachspezifisch übersetzt statt naiv
  durchgereicht inkl. des deutsch/spanisch-"O"-Bedeutungswechsels,
  gelöschte Vorschläge bleiben dauerhaft gelöscht, bestehende Zeilen werden
  nie dupliziert/überschrieben, Light-Edition bekommt nichts,
  Symmetrie-Check gegen die reale Umsetzung), volle Suite grün.
* **Build 132 (Nutzer-Frage, gemeinsam hergeleitet): die vier
  Übersetzungs-Statistik-Zähler liefen als klassische 32-Bit-Integer-
  Attribute, ganz ohne Überlaufschutz.** IP-Symcons `Integer`-Attributtyp
  ist laut SDK ein 32-Bit-Integer (Bereich bis 2.147.483.647) - unabhängig
  davon, dass PHP selbst auf jedem 64-Bit-System einen 64-Bit-Integer
  verwendet. Bei sehr langer Laufzeit (mehrere Jahre) hätte das
  theoretisch zu einem stillen Überlauf/Wraparound beim Schreiben in
  Symcons 32-Bit-Speicher führen können, obwohl PHPs eigene
  Rechenoperation (`+1`/`+N`) selbst nie überläuft - eine Instanz, die
  jahrelang unbeaufsichtigt läuft, ist genau das erwartete
  Einsatzszenario dieses Moduls.
  `attributeStatsRequestCount`/`CharacterCount`/`CacheSavedRequestCount`/
  `CacheSavedCharacterCount` sind jetzt `RegisterAttributeString` (praktisch
  unbegrenzt, Rechnen weiterhin über normale PHP-Ints, nur die
  Persistierung ändert sich) statt `RegisterAttributeInteger`, unter neuen
  Attributnamen ("V2"-Suffix) statt denselben Namen mit geändertem Typ
  wiederzuverwenden (unklares/riskantes Symcon-Verhalten). `Create()`
  migriert dabei einmalig die bereits real angesammelten alten
  Integer-Zählerstände einer laufenden Installation in die neuen
  String-Attribute, bevor die alten Namen als reine, fortan nie mehr
  aktualisierte Altlast liegen bleiben (harmlos, da Attribute nicht im
  sichtbaren Objektbaum stehen).
  Neuer Regressionstest (reale historische Zählerstände werden korrekt
  migriert statt verloren zu gehen, eine frische Installation bleibt bei
  0, die Migration greift garantiert nur einmal und überschreibt keinen
  bereits weitergezählten Wert, Symmetrie-Check gegen die reale
  Umstellung), volle Suite grün.
* **Build 131 (direkter Nachbericht zu Build 130, Nutzer-Feedback): mehrere
  Korrekturen und Ergänzungen an der Formular-Überarbeitung.**
  - **"Statistiken" fehlte komplett in `locale.json`** - live gefunden,
    blieb in einer nicht-deutschen Konsolensprache unübersetzt stehen.
    Ergänzt in allen vier Sprachen. Bei der Gelegenheit auch zwei weitere,
    schon länger bestehende Lücken geschlossen ("- Google Cloud
    Translate:", "- DeepL:" in den Anbieter-Pausenzeilen, sowie
    "Quelltext" in der Eigenen Übersetzungstabelle).
  - Neuer, genereller Regressionstest: gleicht jetzt JEDE buchstabenhaltige
    Formular-Beschriftung (inkl. Popups, Spalten-Überschriften) gegen
    `locale.json` ab, mit expliziten Ausnahmen für reine Marken-/
    Produktnamen und das Info-Symbol - hätte den "Statistiken"-Bug von
    Anfang an gefangen.
  - Neue Erklärung direkt nach "Bevorzugter Anbieter" (Nutzer-Nachfrage,
    gemeinsam verifiziert): DeepL verlangt für Englisch/Portugiesisch
    zwingend eine großgeschriebene Regionsvariante (EN-GB/EN-US,
    PT-PT/PT-BR) statt eines einfachen "EN"/"PT" wie bei Google - ein
    Anbieterwechsel kann daher bereits gewählte Zielsprachen ungültig
    machen. Verweist auf Abschnitt 7 der Dokumentation.
  - Die vier Info-Popup-Buttons aus Build 130 sind jetzt schmal (40px)
    statt in voller Button-Breite - sollte optisch eher wie ein
    Icon-Knopf statt wie ein zweiter vollwertiger Button wirken. Zusätzlich
    ein experimenteller `"icon": "information"`-Versuch, um Symcons
    Standard-Zahnradsymbol auf PopupButton-Elementen zu ersetzen -
    ungewiss, ob Symcons Formular-Renderer das tatsächlich unterstützt,
    Rückmeldung nach Installation nötig.
  Neuer Regressionstest (locale.json-Vollständigkeit für das gesamte
  Formular, DeepL-Erklärung vorhanden und korrekt platziert, schmale
  Info-Buttons), volle Suite grün.
* **Build 130 (Nutzer-Wunsch): Überarbeitung der Nutzerführung im
  Konfigurationsformular, mehrere Verbesserungen für mehr Übersicht.**
  - Alle Kachel-bezogenen Optionen (Symbole ein-/ausblenden,
    Übersetzungsstatistik in der Kachel, eigene Sprachauswahl-Kachel inkl.
    HTML-Editor) sind jetzt in einem eigenen, standardmäßig eingeklappten
    ExpansionPanel "Kachel-Einstellungen" zusammengefasst, statt einzeln
    im Konfiguration-Panel zu stehen.
  - Die Statistiken sind jetzt zweispaltig/eingerückt dargestellt: links
    steht einmalig "Statistiken" als Überschrift, rechts die eigentlichen
    Werte (Laufzeit, stündlich, insgesamt, durch Cache eingespart).
  - "Automatischer Rescan" steht jetzt direkt über dem neuen
    "Kachel-Einstellungen"-Panel statt am Ende des Konfiguration-Panels.
  - Neue kurze Erklärung direkt unter "Scan-Sprache": weist auf das in
    Abschnitt 7 dokumentierte Verhalten hin, dass bereits erfasste Zeilen
    ihre eigene, eingefrorene Quellsprache behalten, und dass ein
    laufender Automatischer Rescan ein neues Objekt fälschlich in der
    falschen Sprache einfrieren kann, wenn dabei nicht "Aktuell aktive
    Sprache" = Scan-Sprache aktiv ist.
  - Alle neun Übersetzungstabellen (Zielsprachen, Objektnamen, Eigene
    Texte, Beschriftungen, Automations, Charts, Begrüßung, Unbenannte
    Objekte, Eigene Übersetzungstabelle) sind jetzt einheitlich auf 100%
    Bildschirmbreite gestreckt.
  - Das Lizenz-Panel klappt nicht mehr allein deshalb automatisch auf,
    weil irgendein Lizenzschlüssel eingetragen ist (betraf praktisch jede
    aktiv genutzte Instanz dauerhaft) - nur noch, wenn die Testphase
    tatsächlich abgelaufen ist.
  - Die langen Erläuterungstexte unter den vier Aktions-Buttons ("Rescan",
    "Aufräumen", "Cache leeren", "Anbieter prüfen") stehen nicht mehr als
    Dauertext im Formular, sondern sind in ein Info-Popup ausgelagert,
    erreichbar über ein ℹ️-Symbol direkt neben dem jeweiligen Button.
  Zwei neue Sprachdatei-Einträge (Panel-Beschriftung "Kachel-
  Einstellungen", neue Scan-Sprachen-Erklärung) in allen vier
  Konsolensprachen ergänzt. Neuer Regressionstest (neues Panel + Inhalt,
  Positionierung von Automatischem Rescan, zweispaltige Statistiken,
  Scan-Sprachen-Erklärung, 100%-Breite aller Tabellen, Info-Popups statt
  Dauertext, Lizenz-Panel-Bedingung, generische Formular-Rekursion), volle
  Suite grün.
* **Build 129, zwei gemeinsam hergeleitete Verbesserungen des
  Übersetzungs-Caches (Nutzer-Wunsch/-Report):**
  - **Cache-Kapazität weiter erhöht, 3000 → 10.000.** Gemeinsame Herleitung
    mit dem Nutzer: der "harte Kern" tatsächlich dauerhaft wertvoller
    Cache-Einträge ist klein (nur Zeilen, deren Rohtext sich bei JEDER
    Aktualisierung ändert, durchlaufen überhaupt `TranslateBatch()` -
    bereits gefüllte statische Zeilen wie Objektnamen/Automations werden
    über `ResolveRowValue()` DIREKT aus der Property gelesen, nie über den
    Cache). Grob geschätzt 50-150 wirklich wiederkehrende Rohtexte
    (Wochentags-Kürzel, gängige Wetterbeschreibungen, feste Widget-Label) ×
    2-3 Zielsprachen ergeben etwa 100-450 Einträge harten Kern. Da der
    Cache (ein lokaler JSON-Attribut-Zugriff, keine Netzwerklatenz) selbst
    bei deutlich größeren Werten um Größenordnungen schneller bleibt als
    jeder Anbieter-Aufruf (spürbare Verlangsamung realistisch erst im
    Bereich mehrerer Zehntausend Einträge/mehrerer MB JSON), gibt es keinen
    Nachteil darin, die Kapazität komfortabel über den harten Kern zu
    setzen - schützt insbesondere deutlich größere Installationen als
    diese vor dem in Build 126-128 behobenen Verdrängungseffekt und spart
    Übersetzungskontingent.
  - **Der äußere, zeilen-weite Cache-Check in `TranslateBatch()` wird für
    `$IsHtml`-Inhalte komplett übersprungen.** Live per Debug-Log gefunden
    (Nutzer-Beobachtung): seit Build 127 landet der komplette
    HTML-Zeilen-Rohtext nie mehr im Cache (nur noch seine Knoten, siehe
    `TranslateBatchUncached`/`StoreCachedTranslationsBatch`) - ein
    `GetCachedTranslation()`-Aufruf dafür ist bei `$IsHtml=true` also
    strukturell IMMER ein Fehlschlag, kostete aber trotzdem
    Semaphor-Erwerb, volles Lesen/Dekodieren des gesamten (jetzt bis zu
    10.000 Einträge großen) Caches und eine Hash-Berechnung über ein
    komplettes HTML-Dokument - live bestätigt als wiederholte garantierte
    Leerläufe für ganze `<!doctype html>`/`<style>`/`<table>`-Blöcke.
    Nicht-HTML-Inhalte (Objektnamen, Automations, ...) sind unverändert
    weiterhin auf Zeilenebene gecacht, da dort ein Treffer tatsächlich
    möglich ist.
  Neuer Regressionstest für den übersprungenen Zeilen-Cache-Check bei
  HTML-Inhalten (kein Aufruf mehr, Text läuft trotzdem normal in die
  Knoten-Aufteilung weiter; Nicht-HTML-Zeilen unverändert), volle Suite
  grün.
* **Build 128 (direkter Nachbericht zu Build 127, live per Debug-Log
  lückenlos bestätigt): der Cache stand bei jedem einzelnen Zugriff exakt
  auf seiner Obergrenze - "Überwiegend Klar" verschwand innerhalb
  desselben Wetter-Widget-Updates wieder und war eine Sekunde später für
  eine ANDERE Zeile bereits erneut weg, obwohl es gerade erst gecacht
  wurde.** Ursache: ein einzelnes HTML-Widget übersetzt oft mehrere
  brandneue Knoten auf einmal (z.B. "Überwiegend Klar" + Sonnenauf-/
  -untergang + Windgeschwindigkeit + Windrichtung) - `TranslateBatchUncached()`
  rief dafür `StoreCachedTranslation()` bisher einzeln je Knoten auf, jeder
  einzelne Aufruf sperrte/las/schrieb den Cache für sich.
  Präzisierung (per Nachrechnen bestätigt, siehe Test in
  `test_batch_cache_write_no_self_eviction.php`): rein rechnerisch, ohne
  Nebenläufigkeit, liefern Einzeln- und gesammeltes Schreiben bei
  gleichem Ausgangszustand dasselbe Endergebnis - das gesammelte Schreiben
  "rettet" für sich allein keinen Knoten vor echter Reservoir-Knappheit
  (zu wenige längst etablierte, gering genutzte Alteinträge, die die
  Verdrängung stattdessen treffen könnte). Der tatsächliche, belegbare
  Vorteil des gesammelten Schreibens (neue `StoreCachedTranslationsBatch()`,
  ersetzt den bisherigen Aufruf pro Knoten) ist ein anderer: nur noch EIN
  Sperr-/Lese-/Schreibzyklus pro Übersetzungs-Batch statt N einzelner -
  verkleinert das Zeitfenster für die in Build 126 bereits bestätigte
  Ursache (überlappende, gleichzeitig laufende Skriptausführungen bei
  einem Script, das mehrere Variablen kurz hintereinander setzt) und senkt
  den Gesamt-Overhead.
  Die eigentliche Abhilfe gegen das beobachtete "ständig voll"-Symptom ist
  die gleichzeitig erhöhte Cache-Kapazität: `TRANSLATION_CACHE_MAX_ENTRIES`
  1000 → 3000. Mehrere Echo-/Alexa-Geräte mit ständig wechselndem
  Songtitel/Interpret pro Update erzeugen einen konstanten Strom echt
  einmaliger Knoten-Einträge, die zwar korrekt via Hit-Zähler verdrängt
  werden, dafür bei nur 1000 Plätzen aber zu wenig Puffer ließen, bis
  häufig wiederverwendete Kerntexte (der feste "Echo Info"-Titel,
  wiederkehrende Wetterbeschreibungen) einen rettenden zweiten Treffer
  verzeichnen konnten.
  Neuer Regressionstest: bestätigt per Nachrechnen, dass beide
  Schreibvarianten bei knappem UND bei ausreichendem Reservoir dasselbe
  Ergebnis liefern (keine falsche "Wunder-Fix"-Erwartung), verifiziert den
  tatsächlichen Vorteil (ein einziger Sperr-/Lese-/Schreibzyklus statt N),
  und bestätigt die erhöhte Kapazität, volle Suite grün.
* **Build 127 (direkter Nachbericht zu Build 126, live per Debug-Log
  aufgeklärt): der persistente Übersetzungs-Cache war bei jedem Zugriff
  exakt bei seiner Obergrenze von 1000 Einträgen - ständige Verdrängung,
  selbst ein bei jedem Update exakt gleichbleibender Text ("Echo Info" in
  einer Alexa-Medienplayer-Kachel) landete zehn Minuten in Folge wiederholt
  beim Anbieter statt aus dem Cache bedient zu werden.** Ursache
  gemeinsam mit dem Nutzer aufgeklärt: `TranslateBatch()` cachte bisher
  zusätzlich zum (eigentlich wertvollen) Knoten-Cache aus
  `TranslateBatchUncached()` auch den KOMPLETTEN Zeilen-Rohtext. Für ein
  HTML-Widget, dessen Gesamtinhalt sich durch neue Messwerte/Songtitel bei
  praktisch jedem Update ändert (live bestätigt: der Rohtext enthielt den
  aktuell auf dem Echo-Gerät laufenden Songtitel/Interpreten, z.B. "Más
  Cara [Explicit]" von "Bad Gyal"), ist so ein Eintrag so gut wie nie
  wiederverwendbar - belegt aber dauerhaft einen der 1000 begrenzten
  Cache-Plätze und verdrängt dadurch echte, oft wiederverwendete
  Knoten-Einträge wie den festen HTML-`<title>`-Text "Echo Info", noch
  bevor die überhaupt einen zweiten Treffer landen konnten. Für
  `$IsHtml`-Inhalte wird die ganze Zeile jetzt nicht mehr zusätzlich
  gecacht - nur noch die (bereits vorhandene, feingranulare) Knotenebene,
  wie vom Nutzer bestätigt ("im Cache sollten nur die API-gefeuerten
  Übersetzungen landen, nicht der HTML... Zerlegte HTML im Cache ist ok").
  Nicht-HTML-Zeilen (Objektnamen, Automations, ...) sind unverändert
  weiterhin auf Zeilenebene gecacht, da die typischerweise kurz und exakt
  wiederkehrend sind.
  Zusätzlich (Nutzer-Wunsch): die Diagnose-Log-Zeilen aus Build 126
  (Cache-Hit/Miss, Schreibvorgang) zeigen jetzt zusätzlich den mitgegebenen
  Kontext (z.B. `ValueObjectID=46091`) statt nur den Text - macht einen
  Log-Eintrag im Konfigurationsformular eindeutig einem konkreten Objekt
  zuordenbar.
  Neuer Regressionstest (HTML-Inhalt landet nicht mehr als ganze Zeile im
  Cache, Nicht-HTML-Zeilen bleiben unverändert gecacht, Symmetrie-Check
  gegen den realen `$IsHtml`-Schutz), volle Suite grün.
* **Build 126 (dringende Korrektur eines mit Build 125 live ausgelösten
  Fatal Error, sofort nach Meldung behoben): `ApplyChanges()` schlug für
  jede Instanz mit mindestens einer rein numerischen Original-Import-Zeile
  (z.B. ein rein aus Ziffern bestehender Automations-/Objektname) komplett
  fehl.** Ursache: `SyncCurrentLanguageIntoCache()` (Build 125) verwendet
  den Rohtext als Array-Schlüssel (`$updates[$sprache][$rohtext] = ...`) -
  PHP wandelt einen rein numerisch aussehenden String-Schlüssel dabei
  automatisch in einen echten Integer um (bekanntes PHP-Verhalten). Der so
  wieder ausgelesene Integer wurde ungeprüft an `BuildTranslationCacheKey()`
  weitergereicht, deren dritter Parameter zwingend einen String erwartet -
  Ergebnis: `TypeError: ... must be of type string, int given` bei JEDEM
  `ApplyChanges()`-Lauf, live bestätigt als "Error while applying changes".
  Erneutes `(string)`-Casting direkt vor der Verwendung behebt es. Zusätzlich
  in diesem Build:
  - Die "GoogleTranslate_Mapping"-Debug-Zeile (Diagnosehilfe, zeigt Rohtext
    je Batch-Position) kürzte lange Rohtexte nicht - ein umfangreiches
    HTML-Widget erzeugte dadurch eine einzelne Debug-Zeile von über 60.000
    Zeichen, obwohl die tatsächlich gesendeten Anfragen dank Knoten-
    Aufteilung längst klein waren. Auf 200 Zeichen pro Zeile gekürzt
    (Gesamtlänge bleibt sichtbar, ObjectID-Zuordnung bleibt vollständig
    erhalten).
  - Der persistente Übersetzungs-Cache (`GetCachedTranslation`/
    `StoreCachedTranslation`/`SyncCurrentLanguageIntoCache`) hatte keinerlei
    Schutz gegen überlappende Zugriffe. Nutzer-Report + eigene Bestätigung
    ("Vorhersage und Aktuelle Bedingungen werden vom gleichen Script
    aktualisiert"): Symcon dispatcht VM_UPDATE-Nachrichten nicht blockierend
    im auslösenden Skript, sondern als eigene, potenziell überlappende
    Skriptausführungen - setzt ein externes Skript kurz hintereinander
    mehrere Variablen, können zwei Übersetzungsläufe für denselben Rohtext
    (z.B. "Überwiegend Klar", das sowohl in "Aktuelle Bedingungen" als auch
    in der "Vorhersage" vorkommt) einander überholen: beide lesen den Cache,
    bevor der jeweils andere geschrieben hat - live bestätigt als zwei
    identische Anbieter-Anfragen im Sekundenabstand, bei einem alle 180
    Sekunden aktualisierenden Echo-Widget sogar wiederholt. Ein knapper,
    instanzweiter `IPS_SemaphoreEnter()`/`IPS_SemaphoreLeave()`-Sperrbereich
    um die jeweilige Lese-/Schreibsequenz auf `attributeTranslationCache`
    schließt die Lücke - best-effort (gelingt der Sperrerwerb binnen 1s
    nicht, wird ohne Sperre weitergemacht statt die Übersetzung ganz zu
    verwerfen). Zusätzlich temporäres Diagnose-Logging bei jedem Cache-Miss/
    -Schreibvorgang, um eine über die reine Gleichzeitigkeit hinausgehende
    Cache-Lücke (derselbe Text blieb auch nach 10 Minuten Abstand
    ungecacht) weiter einzugrenzen - wird nach Abschluss der Untersuchung
    wieder entfernt.
  Neuer Regressionstest für den numerischen-Schlüssel-Fix (löst keinen
  TypeError mehr aus, Symmetrie-Check gegen den realen `(string)`-Cast) und
  für die Mapping-Kürzung (lange Zeilen werden gekürzt, ObjectID-Zuordnung
  bleibt erhalten, kurze Texte unverändert), volle Suite grün.
* **Build 125 (Nutzer-Wunsch, direkter Nachbericht der Automations/
  Objektnamen-Korruptions-Untersuchung): der persistente Übersetzungs-Cache
  war nie mit manuellen Korrekturen synchronisiert.** Eine im Formular von
  Hand korrigierte Zielsprachen-Zelle (z.B. "Salir" statt der ursprünglich
  maschinell übersetzten "Andar") landete bisher ausschließlich in der
  jeweiligen Zeilen-Property - der persistente Cache
  (`StoreCachedTranslation`) wird ausschließlich nach einem frischen
  Anbieter-Aufruf befüllt, nie bei einer manuellen Eingabe. Wurde eine
  solche Zeile später aus irgendeinem Grund erneut als "veraltet" erkannt
  (z.B. durch `ReconcileRowFields`, siehe die noch laufende Untersuchung in
  Build 122/123), lieferte ein Cache-Treffer für denselben Rohtext die
  ALTE, vor der Korrektur gecachte Maschinenübersetzung zurück - und die
  landete dann ganz normal wieder in der Property: die manuelle Korrektur
  wurde nicht nur angezeigt-überschrieben, sondern dauerhaft persistiert
  verloren, unabhängig davon, was genau die Neuübersetzung ursprünglich
  ausgelöst hat.
  `ApplyLanguage()` synct jetzt bei jedem tatsächlichen Lauf (läuft dank
  des bestehenden Fingerprint-Kurzschlusses nicht bei jedem VM_UPDATE) den
  aktuell aufgelösten Zellwert jeder Zeile für die gerade aktive Sprache in
  den Cache zurück - ob der Wert ursprünglich von einem Anbieter oder von
  Hand kam, spielt für den Cache ab sofort keine Rolle mehr. Behebt damit
  nicht zwingend die noch unbekannte URSACHE der Automations/Objektnamen-
  Korruption, verhindert aber wirksam, dass eine erneute Übersetzung
  überhaupt noch auf einen veralteten, vor-korrigierten Cache-Eintrag
  zurückgreifen kann. Ein Lese-/Schreibvorgang für die gesamte
  Cache-Property statt je Zeile einzeln, schreibt nur bei tatsächlicher
  Änderung. Neuer Regressionstest (manuelle Korrektur überschreibt den
  alten Cache-Eintrag, bereits synchroner Eintrag verursacht keinen
  Schreibvorgang, Quellsprache und leere Zellen werden nie gesynct), volle
  Suite grün.
* **Build 124 (direkter Nachbericht zu Build 121, live per Debug-Log
  bestätigt): ein HTML-Segment ganz ohne echten Text landete weiterhin im
  "ganzer Block als ein Knoten"-Fallback.** Build 121 verhinderte, dass ein
  eingebettetes Base64-Bild selbst zum Übersetzungsgegenstand wird - aber
  der umgebende Rest (z.B. leere `<div>`s einer Medienplayer-Kachel ohne
  aktuell laufenden Titel) hatte danach zwar keine Bilddaten mehr, aber
  eben auch KEINEN echten Text - und wurde trotzdem komplett an den
  Übersetzer geschickt, live bestätigt als wiederholte identische Anfrage
  bei jedem Update (derselbe leere Block ändert sich ja nie). `SplitHtmlIntoTextNodes()`
  unterscheidet jetzt sauber zwischen einem echten Parse-Fehler (weiterhin
  konservativ: ganzer Block als ein Knoten, da unbekannter Inhalt) und
  einem erfolgreich geparsten, aber ausschließlich aus Tags/Leerraum
  bestehenden Segment (liefert jetzt gar keinen Übersetzungs-Knoten mehr -
  nichts zu tun, keine Anfrage nötig). Regressionstest erweitert (leeres
  Segment mit eingebettetem Bild liefert null Knoten, Bild bleibt trotzdem
  exakt erhalten; echter Text neben einem Bild liefert weiterhin genau
  diesen einen Knoten), volle Suite grün.
* **Build 123, rein diagnostisch: `AutoRescan()` loggt jetzt seinen eigenen
  Start.** Bisher waren ein manueller Rescan (Button) und ein
  Timer-ausgelöster Auto-Rescan im Debug-Log nicht unterscheidbar (beide
  riefen `ScanRootTree()` identisch auf). Neue Zeile
  `AutoRescan: Timer-ausgeloester Rescan startet jetzt` direkt beim
  Timer-Callback - hilft, im Rahmen derselben Automations-Korruptions-
  Untersuchung (siehe Build 122) zu bestätigen oder auszuschließen, ob ein
  gemeldeter Vorfall zeitlich mit einem automatischen Hintergrund-Rescan
  zusammenfällt. Wird zusammen mit dem Build-122-Logging wieder entfernt.
* **Build 122, rein diagnostisch: temporäres Debug-Logging in
  `ReconcileRowSourceLanguageChanges()`.** Läuft der Untersuchung eines
  gemeldeten Falls nach, bei dem manuell korrigierte Übersetzungen in
  "Automations" und "Objektnamen" ohne erkennbaren Auslöser (kein
  "Aufräumen"-Klick, keine manuelle Löschung) durch frische
  Maschinenübersetzungen ersetzt wurden - ein Property-Dump zeigte dabei
  alle betroffenen Zielsprachen-Zellen mit nahezu identischem Zeitstempel
  neu befüllt, ein Muster, das zu `ReconcileRowFields()`s
  "Quellsprache hat sich geändert"-Erkennung passt. Loggt vor jeder
  Mutation Property, Zeilen-Schlüssel sowie die alten
  `Quellsprache`/`UebersetztGegen`-Werte einer Zeile, für die dieser Pfad
  auslösen wird - wird nach Abschluss der Untersuchung wieder entfernt.
* **Build 121 (Nutzer-Report, live per Debug-Log gefunden): ein Medienplayer-
  Widget ("Echo Info", Alexa/Echo-Kachel) mit eingebettetem Cover-Bild
  (`<img src="data:image/png;base64,...">`, mehrere zehntausend Zeichen ganz
  ohne `<`/`>`) verbrauchte bei JEDER Aktualisierung einen zweistelligen
  Kilozeichen-Betrag Übersetzungs-Kontingent, obwohl das Widget gar keinen
  echten Text enthielt.** Ursache: `SplitHtmlIntoTextNodes()`s Tag-
  Aufteilungs-Regex (`preg_split('/(<[^>]*>)/s', ...)`) scheiterte an so
  einem großen, zusammenhängenden Block an PHPs PCRE-Backtrack-Grenze -
  `preg_split()` lieferte `false`, und der dafür bereits vorhandene Fallback
  griff: der KOMPLETTE Rohinhalt (inklusive Bilddaten) wurde unverändert als
  EIN einziger "Textknoten" an den Übersetzer geschickt, statt (wie
  beabsichtigt) in einzelne kurze Textstücke zerlegt zu werden. Kein Crash,
  keine kaputte Rekonstruktion - aber live per Debug-Log bestätigt: über
  22.000 Zeichen pro Anfrage, wiederholt bei praktisch jeder
  Medienplayer-Aktualisierung (VM_UPDATE), nicht nur bei einem Rescan.
  Fix: Data-URIs (`data:...;base64,...`) werden jetzt VOR jeder weiteren
  Verarbeitung durch kurze Platzhalter ersetzt (macht die Regex wieder
  unproblematisch kurz) und beim Zusammenbau des übersetzten Ergebnisses
  exakt wieder eingesetzt - unabhängig davon, ob am Ende doch noch der
  Fallback greift. Betrifft jedes HTML-Widget mit eingebettetem Bild
  (Cover-Art, Icons, ...) als "Eigene Texte"-Zeile oder Begrüßung, nicht nur
  Echo-Kacheln. Neuer Regressionstest (großes eingebettetes Bild taucht in
  keinem Textknoten mehr auf, Rekonstruktion bleibt exakt, kein
  Platzhalter-Rest im Ergebnis, normales HTML ohne Data-URI unverändert),
  volle Suite grün.
* **Build 120 (Nutzer-Wunsch): Lizenz-Widerruf braucht eine Möglichkeit,
  eine bereits ausgestellte Lizenz zu deaktivieren.** Bisher gab es nur den
  einmaligen Aktivierungs-Check (siehe Abschnitt 8, "Upgrade-Lizenzen und
  der Blockier-Mechanismus") - der greift aber nur bei einer ÄNDERUNG des
  eingetragenen Schlüssels, nicht laufend. Für einen Widerruf/eine
  Rückerstattung reicht das nicht: die Installation läuft dabei ja
  unverändert weiter.
  Neuer täglicher Timer (`CheckLicenseStatus()`/`PerformDailyLicenseCheck()`,
  Ident `timerIdentLicenseCheck`, 24h-Intervall wie von Kai gewünscht) fragt
  denselben Meldeserver-Endpoint unabhängig von einer Schlüssel-Änderung ab.
  Zwei neue Antwort-Formen, ausgewertet über eine mit `RecordLicenseActivation()`
  geteilte `ApplyActivationReportResponse()`: `{"revoked": true}` (Admin hat
  im Shop deaktiviert - anders als "blocked" OHNE Testphasen-Reset, siehe
  `attributeRevokedLicenseKeyHash`) und `{"active": true, "expiresAt": ...}`
  (bestätigt aktiv, liefert das aktuell effektive Ablaufdatum). Letzteres
  ermöglicht als Nebeneffekt eine Abo-Verlängerung/-Verkürzung rein
  serverseitig, ohne neuen signierten Schlüssel (`GetLicenseInfo()`
  überschreibt das signierte `expiresAt` mit einem passenden, per Hash an
  den aktuellen Schlüssel gebundenen Override). Fail-open wie beim
  bestehenden Meldeserver-Aufruf: eine nicht erreichbare/fehlerhafte
  Antwort ändert nichts am zuletzt bekannten Stand.
  Standalone-Simulationstest (7 Szenarien: Widerruf ohne Testphasen-Reset,
  serverseitiges Zurücknehmen, Verlängerung/Verkürzung per Override,
  Fail-open, "blocked"-Regression) gegen einen echt signierten Testschlüssel
  (reales Ed25519-Testpaar) grün. Entsprechende Shop-Seite (Checkbox
  "Aktiv" + Ablaufdatum-Override, Synergetix-Website-Repo,
  `shop/admin/order.php`) ist ein separater Commit in diesem Repo.
* **Build 119 (Nutzer-Wunsch, direkt im Anschluss an Build 118): warum
  profitieren Wochentags-Kürzel und wiederkehrende Wetterbeschreibungen
  nicht vom persistenten Cache, obwohl sie bei jeder Aktualisierung
  identisch bleiben?** Berechtigter Einwand. Sowohl der persistente
  Übersetzungs-Cache (`GetCachedTranslation`/`StoreCachedTranslation`) als
  auch die "Eigene Übersetzungstabelle" (`FindManualTranslation`) wurden
  bislang nur in `TranslateBatch()` geprüft - auf der Ebene ganzer
  Zeilen-Rohtexte. Ein Wetter-Widget als HTML-Zeile ändert aber bei JEDER
  Aktualisierung seinen GESAMTEN Roh-Inhalt (neue Messwerte), daher trifft
  dieser Zeilen-Cache so gut wie nie - obwohl viele einzelne Text-Knoten
  darin (Wochentags-Kürzel, Beschreibungen wie "Überwiegend bewölkt")
  Aktualisierung für Aktualisierung identisch bleiben und daher eine hohe
  Trefferquote haben müssten. Genau die Knotenebene, auf der Build 118 die
  Innerhalb-eines-Aufrufs-Deduplizierung eingeführt hat, kannte weder den
  Cache noch die manuelle Tabelle.

  Fix: `TranslateBatchUncached()` prüft jetzt jeden eindeutigen Knoten
  zusätzlich einzeln gegen die manuelle Übersetzungstabelle und den
  persistenten Cache, BEVOR er überhaupt an den Anbieter geschickt wird -
  nur tatsächlich unbekannte Knoten lösen noch einen echten
  Anbieter-Aufruf aus, dessen Ergebnis anschließend selbst wieder gecacht
  wird. Damit werden Wochentags-Kürzel und wiederkehrende Beschreibungen
  spätestens ab der zweiten Aktualisierung nie wieder angefragt, egal wie
  oft sich der umgebende HTML-Rohtext (Messwerte) ändert. Nebenbei
  bestätigt: die "Aktiv, aber pausiert"-Sperre (`GetGlobalPauseUntil()`,
  siehe `TranslateChunk()`) sitzt bereits strukturell UNTERHALB von
  Cache-/Tabellen-Prüfung - ein Cache- oder Tabellentreffer wurde und wird
  nie durch eine laufende Anbieter-Pause blockiert.

  Regressionstest ergänzt (ein bereits gecachtes Wochentags-Kürzel/eine
  bereits gecachte Beschreibung dürfen bei einem neuen Update mit
  komplett anderem umgebendem Rohtext nicht erneut angefragt werden;
  ein manueller Tabelleneintrag greift ebenfalls auf Knotenebene), volle
  Suite grün.
* **Build 118 (live gefunden, Build 117 reichte nicht aus): dieselbe
  Wetter-Beschreibung wurde weiterhin bis zu zwölf Mal identisch beim
  Anbieter angefragt, trotz des Build-117-Fixes.** Ein neuer Debug-Log-
  Export bestätigte, dass das Problem unverändert bestand. Ursache: Build
  117 deduplizierte identische Texte nur auf der Ebene ganzer
  Zeilen-Rohtexte (dem `$Texts`-Array, das `TranslateBatch()` von seinem
  Aufrufer bekommt) - für HTML-Inhalte (z. B. ein Wetter-Widget als einzelne
  "Eigene Texte"-Zeile) zerlegt `TranslateBatchUncached()` aber JEDE Zeile
  intern NOCHMAL in einzelne Text-Knoten (`SplitHtmlIntoTextNodes`) und
  sammelt ALLE Knoten über alle Zeilen hinweg in einer eigenen, flachen
  Liste (`$translatable`), die erst DANACH an den Anbieter geschickt wird -
  diese Knoten-Ebene liegt UNTERHALB der Ebene, auf der Build 117
  dedupliziert. Teilen sich mehrere Vorhersage-Tage INNERHALB EINER
  EINZIGEN Wetter-Widget-Zeile zufällig dieselbe Beschreibung, blieben die
  daraus resultierenden identischen Text-Knoten von Build 117 komplett
  unberührt und wurden weiterhin einzeln angefragt.

  Fix: `TranslateBatchUncached()` dedupliziert jetzt zusätzlich auf dieser
  tieferen Knotenebene, direkt bevor die Liste an den Anbieter geschickt
  wird (`array_unique($translatable)`, Übersetzung nur für die eindeutigen
  Werte, danach per `array_combine()` auf die volle, ursprüngliche
  Knotenliste zurückgemappt). Die nachgelagerte Cursor-basierte
  Rekonstruktion (die exakt dieselbe Länge/Reihenfolge wie die Eingabe
  erwartet, siehe Kommentar bei `SplitHtmlIntoTextNodes`) bleibt dadurch
  unverändert kompatibel - nur die tatsächliche Anzahl der
  Anbieter-Anfragen sinkt auf die Anzahl eindeutiger Knoten. Bonus-Effekt:
  da Duplikate jetzt schon vor dem Chunking (`translateMaxTextsPerRequest`)
  herausfallen, geht das Chunk-Größenlimit nicht mehr unnötig durch
  wiederholte identische Knoten verloren.

  Regressionstest ergänzt (bildet exakt den gemeldeten Fall nach - fünf
  Text-Knoten einer einzigen HTML-Zeile mit drei eindeutigen Werten), volle
  Suite grün.
* **Build 117 (live gefunden): dieselbe Wetter-Beschreibung wurde acht Mal
  hintereinander identisch beim kostenfreien Anbieter angefragt, statt nur
  einmal.** Ein Debug-Log-Export zeigte acht exakt identische
  MyMemory-Requests für denselben Text ("Überwiegend bewölkt") innerhalb
  weniger Sekunden während einer Wetter-Kachel-Aktualisierung. Ursache:
  eine Wettervorhersage-HTMLBox (z. B. "8-Tage Vorhersage") wird per
  `SplitHtmlIntoTextNodes()` in einzelne Text-Knoten zerlegt und alle
  zusammen in einem einzigen `TranslateBatch()`-Aufruf übersetzt - teilen
  sich mehrere Tage zufällig dieselbe Beschreibung, landete `TranslateBatch()`
  bislang jedes weitere Vorkommen genauso im "braucht frische Übersetzung"-
  Topf wie das erste, weil der persistente Cache
  (`GetCachedTranslation`/`StoreCachedTranslation`) für diesen Text zu
  diesem Zeitpunkt noch leer war - er wird erst NACH Abschluss des
  GESAMTEN Batches befüllt, zu spät für weitere Vorkommen IM SELBEN Batch.
  Für den kostenfreien Anbieter (kein echter Batch-Endpunkt, ein
  HTTP-Request pro Text, siehe `TranslateChunkFree`) bedeutete das: derselbe
  Text wurde so oft angefragt, wie er im Batch vorkam, statt nur einmal -
  unnötiger Verbrauch des ohnehin knappen Tageskontingents.

  Fix: `TranslateBatch()` merkt sich jetzt zusätzlich zum bestehenden
  persistenten Cache eine reine Batch-interne Text→Position-Zuordnung -
  jedes weitere Vorkommen desselben Rohtexts im selben Batch wird direkt als
  Duplikat erkannt und übernimmt nach Abschluss des Batches das bereits für
  sein erstes Vorkommen aufgelöste Ergebnis, ganz ohne eigenen Anbieter-
  Aufruf. Zählt dabei genauso in die Statistik "Durch den Cache eingespart"
  wie ein echter Cache-Treffer - aus Nutzersicht ist es exakt dieselbe Art
  von vermiedener Anfrage. Betrifft strukturell jeden Aufrufer von
  `TranslateBatch()` mit mehreren, potenziell identischen Texten im selben
  Aufruf (v. a. HTML-Widgets mit mehreren, inhaltlich manchmal
  übereinstimmenden Text-Knoten), nicht nur Wetter-Widgets.

  **Separat dazu, kein Modul-Bug:** derselbe Nutzer beobachtete außerdem,
  dass MyMemorys Web-Oberfläche für denselben Suchbegriff (z. B.
  "Sprache"→"Idioma") ein ANDERES Ergebnis liefert als die von diesem Modul
  genutzte API (z. B. "Español"). Das ist eine Eigenheit des kostenfreien
  Anbieters selbst - MyMemory ist eine reine Übersetzungsspeicher-Datenbank
  (Translation Memory) aus unzähligen, unterschiedlich zuverlässigen
  Quellen; Web-Oberfläche und API können für sehr kurze, mehrdeutige Texte
  (einzelne Wörter ohne Satzkontext, z. B. "Sprache" oder "Shuffle") einen
  unterschiedlichen Top-Treffer aus dieser Datenbank ausliefern. Simple
  Locale übernimmt exakt das, was die API liefert - dieselbe bereits
  dokumentierte Einschränkung wie "Die automatische Übersetzung kann
  trotzdem Fehler machen" ([Abschnitt 2](#2-bekannte-einschränkungen)): bei
  kurzen, kontextlosen Einzelwörtern lohnt sich eine manuelle Prüfung/
  Korrektur besonders.

  Regressionstest ergänzt, volle Suite grün.
* **Build 116 (Nutzer-Wunsch): sowohl "Rescan" als auch "Aufräumen" luden das
  Konfigurationsformular bislang sichtbar ZWEIMAL neu pro Klick.** Der
  Build-115-Debug-Log (siehe dort) hatte es bereits nebenbei aufgedeckt:
  Symcons Konsole ruft `GetConfigurationForm()` nachweislich SELBSTSTÄNDIG
  ein weiteres Mal auf, kurz (~30ms) nach jedem `RequestAction()` - dieses
  automatische Verhalten war die ganze Zeit schon da, unabhängig davon, ob
  das Modul selbst zusätzlich einen eigenen `ReloadForm()`-Aufruf machte.
  Sowohl `ScanRootTree()` (nach einem manuellen Rescan-Klick) als auch
  `CleanupOrphanedRows()` (über den in Build 98 eingeführten, um
  `CLEANUP_RELOAD_DELAY_SECONDS` verzögerten `ProcessDeferredCleanupReload()`)
  lösten zusätzlich zu diesem automatischen Reload noch einen EIGENEN aus -
  macht zwei komplette Formular-Neuaufbauten kurz nacheinander pro Klick,
  sichtbar als spürbares doppeltes Neuladen.

  Fix: beide eigenen, jetzt nachweislich redundanten `ReloadForm()`-Aufrufe
  wurden ersatzlos entfernt - Symcons automatischer Reload übernimmt beide
  ursprünglichen Aufgaben bereits vollständig (frische Listendaten anzeigen
  UND den "Übernehmen"-Puffer aktualisieren, siehe Build 60/88 für den Grund,
  warum Letzteres überhaupt nötig ist - ein expliziter `ReloadForm()` und der
  automatische Konsolen-Reload lösen strukturell denselben
  `GetConfigurationForm()`-Neuaufbau aus, es gibt keinen funktionalen
  Unterschied). Damit vollständig entfernt: der komplette Build-98-
  Verzögerungsmechanismus (`ProcessDeferredCleanupReload()`,
  `GetCleanupReloadTimerIdent()`, `CLEANUP_RELOAD_DELAY_SECONDS`, der
  zugehörige `RegisterTimer()`-Aufruf in `Create()` samt einmaliger
  Alt-Instanzen-Bereinigung des dadurch verwaisten Timer-Objekts) sowie der
  komplette `$ReloadFormAfterward`-Parameter von `ScanRootTree()` (war seit
  dieser Entfernung in beiden Aufrufern `Rescan()`/`AutoRescan()` ohne jede
  Wirkung mehr). `GetConfigurationForm()` setzt den "Aufräumen"-Zähler
  dadurch auch wieder wie ursprünglich in Build 76 sofort beim ersten Lesen
  zurück (die Build-114-Sonderbehandlung, die einen konkurrierenden ZWEITEN
  Aufruf überleben musste, ist mit dessen Wegfall hinfällig - es gibt jetzt
  wieder nur den einen automatischen Konsolen-Aufruf). Ein automatischer
  Hintergrund-Rescan (`AutoRescan()`, kein `RequestAction()`, daher auch kein
  automatischer Konsolen-Reload) bleibt davon unberührt und lädt weiterhin
  nie ein gerade offenes Formular neu (Build 60), wie schon zuvor.
  Regressionstests aktualisiert, volle Suite grün.
* **Build 115 klärt den "N+1"-Zähler bei "Aufräumen" endgültig auf (kein
  Bug) und entfernt als direkte Folge davon eine echte, vom Nutzer
  identifizierte strukturelle Redundanz.** Ein exakter Vorher/Nachher-
  Vergleich aller Zeilen-Properties (eigens dafür geschriebenes
  Diagnose-Skript, dreimal ausgeführt: vor dem Anlegen einer Test-Instanz,
  danach, nach deren Löschen + "Aufräumen") zeigte: 5 gelöschte Objekte →
  6 gemeldete Zeilen, exakt weil eine der 5 Variablen ("Aktive Szene") eine
  String-Variable war und dadurch sowohl in "Objektnamen" (1 Zeile) als
  auch in "Eigene Texte" (1 weitere Zeile) stand - macht 4×1 + 1×2 = 6.
  Derselbe Mechanismus erklärte rückwirkend auch einen früheren Test (7
  Objekte → 8 gemeldet). "Aufräumen" zählt schlicht Zeilen über mehrere
  Listen hinweg, nicht "Objekte" - korrektes, erwartbares Verhalten.

  **Die zugrunde liegende Redundanz selbst wurde vom Nutzer zu Recht als
  eigenständiges Problem erkannt:** da "Objektnamen" ausnahmslos JEDES
  Objekt im Baum abdeckt (siehe `WalkTree()`), war die zusätzliche, eigene
  Namens-Übersetzung, die "Eigene Texte" bislang für ihre Zeilen führte
  (`ORIGINAL_IMPORT_Name`/`Name_<lang>`, siehe Build 107), für JEDES
  Objekt strukturell redundant - nie eine eigenständige Datenquelle,
  sondern immer eine zweite, unabhängig editierbare und unabhängig
  übersetzte Kopie desselben Namens. Build 107 hatte nur den daraus
  entstehenden SCHREIB-Konflikt entschärft (`$writtenNameObjectIDs` ließ
  "Objektnamen" beim Sprachwechsel gewinnen) - der Name ließ sich in der
  Admin-Tabelle aber weiterhin an ZWEI Stellen bearbeiten, mit dem Risiko,
  dass beide auseinanderlaufen, und wurde weiterhin zweimal (unnötig)
  übersetzt.

  Build 115 entfernt die zweite Datenquelle komplett statt nur ihren
  Schreibkonflikt zu entschärfen: "Eigene Texte" trackt jetzt
  ausschließlich noch den WERT einer String-Variable, keinen Namen mehr -
  der Name kommt ausschließlich aus "Objektnamen". Entfernt: die Felder/
  Konstanten `fieldOriginalImportName`/`fieldNamePrefix`, die komplette
  `$writtenNameObjectIDs`-Logik in `ApplyLanguage()` (die "Eigene
  Texte"-Schleife ruft jetzt nie mehr `IPS_SetName()` auf, nur noch
  `WriteTrackedValueString()`), die Spalte "Original-Import (Name)" samt
  ihrem Sprachspalten-Satz in `BuildListColumns()`/`form.json`, und das
  Namensfeld beim Scan in `WalkTree()`. Bestehende Installationen behalten
  die alten `ORIGINAL_IMPORT_Name`/`Name_<lang>`-Schlüssel als harmlosen,
  nie wieder gelesenen/geschriebenen Ballast in ihrer gespeicherten
  `ObjectTexts`-Property - keine Migration nötig.

  Bei dieser Gelegenheit umbenannt, da explizit nachgefragt und bestätigt
  (`ResolveStringVariableID()` filtert ausschließlich auf
  `VARIABLETYPE_STRING`): "Eigene Texte" heißt jetzt "Eigene Texte
  (String-Variablen)", in allen vier Sprachen.

  Als Nebenaufräumung außerdem entfernt: das komplette temporäre
  Diagnose-Logging aus Build 111/113 (`SLOC_CleanupCountDiag`/
  `SLOC_ChartScanError`) - beide damit untersuchten Verdachtsfälle sind
  jetzt aufgeklärt bzw. abgesichert, die zugehörigen `try`/`catch`-Schutz-
  mechanismen selbst bleiben unverändert bestehen, nur ihr Logging wurde
  entfernt. Regressionstest komplett auf die neue Architektur umgeschrieben
  (kein Name-Feld mehr in "Eigene Texte", kein Schreibkonflikt mehr
  möglich, da strukturell ausgeschlossen statt nur verhindert), volle
  Suite grün.
* **Build 114 löst den in Build 111 diagnostizierten leeren Zähler im
  "Aufräumen"-Ergebnis-Popup - ein echter Debug-Log-Export bewies die
  exakte Ursache mit Zeitstempeln.** Ablauf laut Log: `CleanupOrphanedRows()`
  schreibt den Zähler (T+0,00s) - rund 30 Millisekunden später ruft die
  Symcon-Konsole SELBSTSTÄNDIG (nicht vom Modul ausgelöst) erneut
  `GetConfigurationForm()` auf und liest den Zähler korrekt, setzte ihn
  bislang aber SOFORT wieder auf -1 zurück (Build 76) - lange bevor der
  bewusst um `CLEANUP_RELOAD_DELAY_SECONDS` (5s) verzögerte, eigentlich für
  die Anzeige vorgesehene `ProcessDeferredCleanupReload()`-Reload überhaupt
  feuert (T+5,00s). Dessen `GetConfigurationForm()`-Aufruf sah den Zähler
  dadurch IMMER schon verbraucht (-1) und zeigte das Popup folgerichtig mit
  leerem statt dem tatsächlichen Wert - unabhängig vom tatsächlichen
  `removedCount` und reproduzierbar bei jedem einzelnen "Aufräumen"-Lauf,
  kein seltenes Race. Fix: `GetConfigurationForm()` liest den Zähler jetzt
  nur noch (beliebig oft wiederholbar, kein Zustand wird dabei verändert) -
  das tatsächliche Zurücksetzen übernimmt `ProcessDeferredCleanupReload()`
  selbst, erst NACHDEM sein eigener `ReloadForm()`-Aufruf (der letzte
  beabsichtigte Aufruf für diesen Cleanup-Lauf) abgeschlossen ist. Der in
  Build 113 geäußerte Verdacht auf massenhaften Datenverlust durch einen
  abgebrochenen `WalkTree()`-Durchlauf ist durch denselben Log NICHT
  bestätigt (in diesem Lauf war `removedCount=0`, kein
  `SLOC_ChartScanError` aufgetreten) - bleibt aber vorsorglich abgesichert,
  bis ein Lauf mit tatsächlich zu entfernenden Zeilen das endgültig klärt.
  Regressionstest ergänzt, volle Suite grün.
* **Build 113 (live gemeldet, schwerwiegend): nach "Aufräumen" fehlten
  plötzlich viele manuell korrigierte "Objektnamen"-Zeilen, deren Objekte in
  der Visualisierung nachweislich noch existierten - u. a. eine seit
  Stunden stabile eigene Korrektur ("Idioma") wurde durch eine frische
  Maschinenübersetzung ersetzt.** Auslöser laut Nutzer: ein einmaliger
  Testklick auf "Aufräumen". Root Cause noch nicht abschließend bestätigt
  (Diagnose läuft), aber starker, plausibler Verdacht: `@IPS_GetMedia()`/
  `@IPS_GetMediaContent()` (neu seit Build 108, siehe dort) wirft für ein
  ungewöhnlich konfiguriertes oder defektes Medienobjekt eine echte
  PHP-Exception - der `@`-Operator unterdrückt nur Warnungen/Notices,
  NIEMALS eine tatsächlich geworfene Exception. Eine solche Exception würde
  mitten im `WalkTree()`-Durchlauf den KOMPLETTEN restlichen Baum-Scan
  abbrechen: jedes ab diesem Punkt noch nicht besuchte, in Wahrheit
  weiterhin existierende Objekt fehlt dann in `$ScannedNames` - "Aufräumen"
  hält es fälschlich für verwaist und löscht seine Zeile unwiederbringlich;
  ein nachfolgender Rescan legt sie als "neu" an und übersetzt sie komplett
  frisch, jede manuelle Korrektur ist damit verloren. Fix: der gesamte
  Chart-Scan-Block in `WalkTree()` läuft jetzt in einem eigenen
  `try`/`catch (\Throwable $e)` - ein Fehler bei einem einzelnen Chart wird
  geloggt (`SendDebug('SLOC_ChartScanError', ...)`) und übersprungen, statt
  den kompletten restlichen Baum-Scan (und damit potenziell zahllose andere,
  völlig unbeteiligte Objekte) zu gefährden. Zusätzlich neues
  `SendDebug('SLOC_CleanupCountDiag', ...)` in `CleanupOrphanedRows()`:
  protokolliert vor jedem Löschen die Größe des frischen Live-Scans gegen
  die bestehende Property sowie die exakten ObjectIDs jeder tatsächlich zu
  entfernenden "Objektnamen"-Zeile - damit sich ein unvollständiger Scan
  (deutlich kleinerer `liveNames`-Count als erwartet) im Debug-Log sofort
  erkennen lässt, auch falls die eigentliche Ursache doch woanders liegt.
  Regressionstest ergänzt (Chart-Scan-Block läuft nachweislich in
  try/catch), volle Suite grün.
* **Build 112 (live gefunden, direkt im Anschluss an Build 110):
  "Aufräumen" löschte fälschlich eine Chart-Zeile mit einem echten, im
  Chart selbst gesetzten Titel.** Nach dem Build-110-Fix funktionierte
  "Humedad del aire" korrekt, aber bei "Temperaturas" verschwand
  "Außentemperatur" aus der "Charts"-Liste - im Chart selbst blieb die
  Datenreihe (samt bereits übersetztem Titel) unverändert bestehen, nur die
  Admin-Tabelle zeigte die Zeile nicht mehr. Ursache:
  `ExcludeChartRowsForIndependentlyNamedVariables()` (Build 109) prüfte
  ausschließlich, ob die zugrunde liegende Variable zusätzlich eigenständig
  im Baum steht - unabhängig davon, ob der Chart-Titel überhaupt aus dem
  Leer-Titel-Fallback (Build 110) stammte oder ein echter, eigener Text
  war. "Außentemperatur" hatte einen expliziten Titel, dessen Variable
  ZUFÄLLIG zusätzlich eigenständig im Baum stand (genau die Konstellation,
  vor der schon beim Feature-Wunsch selbst gewarnt wurde: "Vermutlich
  können wir aber auch den Titel separat überschreiben") - die Regel
  "Symcon synct das schon selbst" gilt aber ausschließlich für den
  Leer-Titel-Fall; ein gesetzter Titel wird von Symcon immer unverändert
  angezeigt, unabhängig vom Variablennamen. Fix: `WalkTree()` markiert jede
  gescannte Zeile jetzt mit einem rein transienten, nie persistierten
  Merkmal `_EmptyTitleFallback` (true nur, wenn der ORIGINALE Chart-Titel
  leer war) - `ExcludeChartRowsForIndependentlyNamedVariables()` wendet die
  Ausschluss-Regel nur noch an, wenn dieses Merkmal gesetzt ist.
  `MergeChartRows()` entfernt das Merkmal wieder, bevor eine neue Zeile
  persistiert wird (kein Ballast in der gespeicherten Property/Tabelle).
  Regressionstest um beide Fälle nebeneinander ergänzt (echter Titel bleibt
  erhalten, Leer-Titel-Fallback wird weiterhin korrekt ausgeschlossen).
* **Build 111, rein diagnostisch: live gemeldet, dass "Aufräumen" zwar
  tatsächlich verwaiste Zeilen entfernt (nach Löschen einer kompletten
  Instanz samt vier Kind-Variablen aus dem Root-Baum), das Ergebnis-Popup
  aber "Removed: " mit LEEREM statt dem tatsächlichen Zähler anzeigt.**
  Betrifft "Objektnamen", nicht die neue "Charts"-Liste. Der Code-Pfad
  selbst (`CleanupOrphanedRows()`/`GetConfigurationForm()`, Build 76/98)
  sieht bei genauer Durchsicht strukturell korrekt aus - Verdacht: der
  "einmal lesen, dann auf -1 zurücksetzen"-Mechanismus für
  `attributeLastCleanupRemovedCount` wird zwischen dem sofortigen
  Live-Push (`UpdateFormField('CleanupResultCountLabel', ...)`) und dem um
  `CLEANUP_RELOAD_DELAY_SECONDS` (5s) verzögerten `ReloadForm()`
  (`ProcessDeferredCleanupReload()`) durch einen ZUSÄTZLICHEN,
  nicht selbst ausgelösten `GetConfigurationForm()`-Aufruf vorzeitig
  konsumiert - noch nicht bestätigt. Neues `SendDebug('SLOC_CleanupCountDiag',
  ...)` protokolliert mit `microtime(true)`-Zeitstempeln jeden
  `GetConfigurationForm()`-Aufruf (gelesener Zählerwert, ob zurückgesetzt),
  das Ende von `CleanupOrphanedRows()` (geschriebener Zählerwert) und den
  Zeitpunkt, an dem `ProcessDeferredCleanupReload()` tatsächlich feuert -
  damit sich die genaue Reihenfolge/das Timing zwischen diesen Ereignissen
  rekonstruieren lässt. Rein additiv, keine Verhaltensänderung (volle
  Regressionssuite unverändert grün) - wird entfernt bzw. durch die
  eigentliche Korrektur ersetzt, sobald die Logs den Mechanismus bestätigt
  haben.
* **Build 110 (live gefunden, direkt im Anschluss an Build 109): Build 109
  behob den Ausschluss-Fehler, aber "Humedad del aire" tauchte danach
  IMMER NOCH nicht in "Charts" auf.** Ein direkter `IPS_GetMediaContent()`-
  Rohdump dieses Charts brachte die eigentliche Ursache zutage: alle drei
  Datenreihen hatten `"title":""` - einen komplett LEEREN Titel, nicht
  einen mit dem Variablennamen übereinstimmenden (die Annahme aus Build
  109, "Symcon füllt title beim Anlegen standardmäßig", war selbst
  ebenfalls falsch - Symcon lässt das Feld schlicht leer). Der
  ursprüngliche Scan-Code (`if ($datasetVariableID === 0 || $datasetTitle
  === '') continue;`, unverändert seit Build 108) übersprang jede Zeile mit
  leerem Titel von Anfang an - unabhängig von Build 109s Filterung kam für
  "Humedad del aire" also nie auch nur eine Zeile zustande. Die Kachel
  zeigte dabei nachweislich (Screenshot) weiterhin unübersetzt
  "Luftfeuchtigkeit" in der Legende: Symcon rendert bei leerem Titel live
  den AKTUELLEN Namen der Variable - bei "Irradiación luminosa" (ebenfalls
  vermutlich leere Titel, nie direkt geprüft) funktionierte das nur, WEIL
  die Variablen zusätzlich eigenständig im Baum standen und dadurch
  bereits übersetzt waren; bei "Humedad del aire" standen sie das nicht,
  weshalb ihr roher, nie übersetzter Name für immer stehen geblieben wäre.
  Fix: der Quelltext einer Datenreihe ist jetzt der explizite Titel, falls
  gesetzt, sonst ersatzweise der aktuelle Name der Variable
  (`$sourceText = $datasetTitle !== '' ? $datasetTitle : (string)
  @IPS_GetName($datasetVariableID);`) - genau das, was Symcon selbst in
  diesem Fall in der Legende anzeigen würde. Der bereits in Build 109
  eingeführte `ExcludeChartRowsForIndependentlyNamedVariables()`-Filter
  bleibt unverändert bestehen und greift weiterhin zuverlässig für
  eigenständig im Baum stehende Variablen wie bei "Irradiación luminosa".
* **Build 109 (live gefunden, direkt im Anschluss an Build 108): der dortige
  Live-Namens-Vergleich schloss ein zweites, unabhängiges Chart komplett
  aus der neuen "Charts"-Liste aus.** Ein vom Nutzer getesteter
  Luftfeuchtigkeits-Chart mit drei Datenreihen tauchte nach Build 108 gar
  nicht mehr in "Charts" auf - keine der drei Zeilen wurde angelegt.
  Ursache: Symcon füllt das `title`-Feld beim erstmaligen Anlegen einer
  Datenreihe standardmäßig mit dem DAMALIGEN Namen der gewählten Variable -
  unabhängig davon, ob diese Variable jemals sonst irgendwo im Baum
  eigenständig auftaucht. Build 108s Vergleich "Titel == aktueller
  Live-Name" traf auf alle drei Datenreihen zu (der Titel war seit dem
  Anlegen nie geändert worden), obwohl keine der drei Variablen anderswo im
  Baum stand und sie folglich NIE von irgendetwas übersetzt worden wären -
  der Live-Namens-Vergleich ist also kein zuverlässiges Signal dafür, ob
  Symcon tatsächlich synct, sondern trifft schlicht auf den unveränderten
  Symcon-Standardzustand IMMER zu, auch ohne jede Synchronisierung. Die
  tatsächlich zuverlässige Bedingung ist stattdessen exakt die aus dem
  Build-108-Nachtrag beschriebene Ursache selbst: nicht der Titel-Vergleich,
  sondern ob die Variable TATSÄCHLICH als eigenständiges Objekt im selben
  Root-Baum-Scan gefunden wurde. Neue Funktion
  `ExcludeChartRowsForIndependentlyNamedVariables()` prüft das jetzt direkt
  gegen die bereits gescannten Objektnamen (`$ScannedNames`/`$ScannedCharts`
  aus demselben `WalkTree()`-Durchlauf) - bewusst ERST NACH dessen
  vollständigem Abschluss aufgerufen (sowohl in `ScanRootTree()` vor dem
  Merge als auch in `CleanupOrphanedRows()`), da die referenzierte Variable
  an einer beliebigen anderen Stelle im Baum liegen kann, vor oder nach dem
  Chart selbst - erst ein vollständiges `$ScannedNames` beantwortet
  zuverlässig, ob eine Variable eigenständig vorkommt. Der fehlerhafte
  `IPS_GetName()`-Vergleich wurde komplett entfernt. Regressionstest um den
  genau gegenteiligen Fall ergänzt (keine der Variablen eigenständig im
  Baum → alle Zeilen müssen erhalten bleiben), damit dieser konkrete Fehler
  nicht erneut einschleicht.
* **Build 108 (Nutzer-Wunsch): übersetzt zusätzlich die Legenden-Titel von
  Symcons eingebautem Chart-Element.** Ein per "Add Chart" in der
  Kachel-Visualisierung angelegtes Diagramm zeigt pro Datenreihe einen
  eigenen Titel (z. B. "Außentemperatur", "Wohnzimmer") - diese Titel lagen
  bisher außerhalb jeder Übersetzung und blieben bei jedem Sprachwechsel
  fest Deutsch, während alles andere um sie herum (inkl. des Objektnamens
  des Charts selbst, der bereits über "Objektnamen" lief) korrekt
  wechselte. Konfiguriert ist ein Chart NICHT über eine normale Property,
  sondern als eigenes Symcon-Medienobjekt (`ObjectType` 5, `MediaType` 4 =
  `MEDIATYPE_CHART`) mit dem gesamten Aufbau (Datenreihen, Farben, Titel)
  als base64-kodiertes JSON im Medien-Inhalt selbst
  (`IPS_GetMediaContent()`/`IPS_SetMediaContent()`, kein `IPS_ApplyChanges()`
  nötig - Medienobjekte kennen diesen Mechanismus nicht). Ein Chart sitzt
  (anders als "Automations", die separat über die Kachel-Visualisierungs-
  Instanz gescannt werden) als ganz normales Objekt im Root-Baum und wird
  daher direkt von `WalkTree()` mit erfasst - neue Property
  `ObjectCharts`/Liste "Charts", eindeutiger Zeilen-Schlüssel ist
  ChartID+VariableID (ein Chart kann mehrere Datenreihen gleichzeitig
  zeigen). Schreiben passiert über die neue `ApplyChartsLanguage()`, nach
  demselben Muster wie `ApplyAutomationsLanguage()`.

  **Live gefundener Nachtrag, noch während derselben Anfrage:** ein zweiter
  vom Nutzer getesteter Chart übersetzte seine Legenden-Titel bereits VOR
  diesem Fix scheinbar von selbst korrekt - ohne dass Simple Locale je
  dessen Medien-Inhalt angefasst hätte. Ursache, per Screenshot des
  "Configure Graph"-Dialogs bestätigt: Symcon hält den Titel einer
  Datenreihe automatisch synchron mit dem LIVE-Namen ihrer zugrunde
  liegenden Variable. Ist diese Variable zusätzlich an anderer Stelle im
  Root-Baum als eigenes Objekt platziert (z. B. eine eigene
  Anzeige-Kachel), wird sie ohnehin schon über "Objektnamen" umbenannt -
  und Symcon zieht diesen neuen Namen automatisch auch in die
  Chart-Legende nach, eine eigene Übersetzung wäre hier doppelte
  (und potenziell mit Symcons eigener Synchronisierung konkurrierende)
  Arbeit. Als erster Ansatz verglich `WalkTree()` dafür jeden
  Datenreihen-Titel beim Scan direkt gegen `IPS_GetName()` der zugehörigen
  Variable - **dieser Ansatz erwies sich kurz darauf als falsch, siehe
  Build 109.**
* **Build 107 fand die eigentliche Ursache - nicht in `FillLanguageColumn()`,
  sondern in `ApplyLanguage()` selbst.** Der Build-106-Diagnose-Export zeigte
  bei allen 99 protokollierten Zeilen `isCurrent=true` (korrekt nicht
  pending), und die tatsächlich bearbeitete `ObjectID` tauchte im gesamten
  Log kein einziges Mal auf - der Verdacht aus Build 106 war damit widerlegt.
  Ein direkt per Skript in der Symcon-Konsole ausgelesener Property-Snapshot
  bewies stattdessen, dass die manuelle Korrektur den Rücksprung binnen des
  GLEICHEN `ApplyLanguage()`-Laufs erlitt, nicht erst Sekunden später durch
  einen Rescan. Grund: `WalkTree()` legt für JEDES Objekt mit gesetztem Ident
  eine Zeile in "Objektnamen" an - unabhängig davon, ob es sich zusätzlich um
  eine getrackte "Eigene Texte"-Variable handelt. Eine solche Variable landet
  dadurch zwangsläufig gleichzeitig in BEIDEN Listen, und "Eigene Texte"
  pflegt für ihre eigenen Zeilen eine komplett unabhängige, eigene
  Namens-Übersetzung (`ORIGINAL_IMPORT_Name`/`Name_<lang>`). `ApplyLanguage()`
  rief für dasselbe Objekt daher zwei voneinander unabhängige `IPS_SetName()`
  auf - zuerst aus "Objektnamen" (die gerade korrigierte, richtige Zeile),
  direkt darauf aus "Eigene Texte" (der eigene, unveränderte, damit
  zwangsläufig veraltete Stand) -, wobei der zweite Aufruf den ersten
  kommentarlos wieder überschrieb. Ein Schutz gegen doppeltes Schreiben
  existierte bereits für Werte (`$writtenValueObjectIDs`, gegen zwei
  "Eigene Texte"-Zeilen mit derselben `ValueObjectID`), aber nicht für Namen
  über beide Listen hinweg. Fix: neues `$writtenNameObjectIDs`, von der
  "Objektnamen"-Schleife befüllt - die "Eigene Texte"-Schleife überspringt
  ihren eigenen `IPS_SetName()`-Aufruf jetzt für jedes Objekt, das bereits
  über "Objektnamen" benannt wurde. Betraf nur Objekte, die in beiden Listen
  gleichzeitig auftauchen (jede sinnvoll benannte "Eigene Texte"-Variable mit
  gesetztem Ident, ein häufiger Fall) - reiner Namens-Bug, Werte
  (`SetValueString`) waren nie betroffen. Kein Rescan/Sprachwechsel nötig,
  damit der Fix greift: er wirkt bereits beim nächsten `ApplyLanguage()`-Lauf
  nach der jeweiligen Korrektur. Für "Beschriftungen", "Automations" und
  "Begrüßung" besteht dasselbe Risiko nicht - sie schreiben auf strukturell
  andere Ziele (Custom Presentation bzw. die `Automations`-/`GreetingName`-
  Property der Kachel-Visualisierungs-Instanz), nie über `IPS_SetName()`; der
  einzige denkbare Überschneidungsfall (Begrüßung im Variable-Modus teilt
  sich eine `ValueObjectID` mit einer "Eigene Texte"-Zeile) war bereits vor
  Build 107 über denselben `$writtenValueObjectIDs`-Mechanismus abgesichert.
* **Build 106, rein diagnostisch: Build 105 hat das live gemeldete Problem
  NICHT behoben - live gefunden, dass es sich um "Objektnamen" handelt (nicht
  "Eigene Texte"), für die aktuell aktive Sprache.** Ein frischer Debug-Export
  zeigte statt eines Puffer-Flushes einen kompletten RESCAN rund 2 Sekunden
  nach der manuellen Korrektur (zwei `EnsureSourceLanguageIsTarget`-Zeilen im
  Abstand von 2s, vermutlich der Auto-Rescan-Timer) - `MergeRows()` (per
  Code-Analyse bestätigt korrekt: friert Rohtext/Übersetzungen für bereits
  bekannte `ObjectID`s ein) und ein Abgleich mit der "Eigenen
  Übersetzungstabelle" (Build 93 - Glossareintrag würde jede Rescan-gestützte
  Korrektur zurücksetzen) wurden beide geprüft und ausgeschlossen (keine
  passende Glossar-Zeile vorhanden). Bleibt als Verdacht:
  `FillLanguageColumn()`s "bereits aktuell"-Prüfung (`IsRowLanguageTranslationCurrent()`)
  erkennt die frisch manuell bearbeitete Zelle fälschlich als "veraltet" und
  übersetzt sie beim Rescan neu, wodurch die manuelle Korrektur überschrieben
  wird - dieselbe Funktion, bei der Build 100/101 bereits einmal eine
  verwandte Lücke fand (dort: Rohtext wurde leer). Neues
  `SendDebug('SLOC_NameRevertDiag', ...)` in `FillLanguageColumn()` (ersetzt
  das in Build 101 entfernte `SLOC_TranslateGapDiag`) protokolliert für jede
  nicht-leere, nicht als JSON erkannte Zeile die vollständige
  Pending/Aktuell-Entscheidung. Rein additiv, keine Verhaltensänderung (volle
  Regressionssuite unverändert grün) - wird entfernt bzw. durch die
  eigentliche Korrektur ersetzt, sobald die Logs den Mechanismus bestätigt
  haben.
* **Build 105 behebt einen direkten Nebeneffekt von Build 104, live gefunden:
  eine manuelle Korrektur wurde kurz angezeigt, dann aber "einen Augenblick
  später" wieder auf den alten Wert zurückgesetzt - in der Tabelle stand
  weiterhin die Korrektur, in der Visualisierung wieder der alte Wert.**
  Ursache: `StagePendingTrackedRowUpdates()` (der verzögerte, gepufferte
  Flush externer VM_UPDATE-Änderungen, siehe `BufferPendingTrackedRowUpdate`/
  `PENDING_ROW_UPDATE_DEBOUNCE_SECONDS` oben) überschrieb Zeilenfelder beim
  tatsächlichen Schreiben bislang bedingungslos mit dem gepufferten Wert -
  völlig unabhängig davon, ob die Zelle inzwischen längst anderweitig
  geändert wurde. `FlushPendingTrackedRowUpdates()` läuft am Anfang JEDES
  `ApplyChanges()`-Durchlaufs, auch genau desjenigen, den das eigene
  "Übernehmen" der manuellen Korrektur gerade selbst auslöst - ein zu diesem
  Zeitpunkt noch ausstehender, längst veralteter Puffer-Eintrag (aus einem
  früheren externen Schreibvorgang oder einem Selbst-Schreib-Echo, siehe
  Build 95) konnte die frische Korrektur damit im selben oder einem knapp
  späteren `ApplyChanges()`-Durchlauf kommentarlos wieder überschreiben -
  ein Bug, der schon vor Build 104 bestand, aber unsichtbar blieb, weil
  `ApplyLanguage()` bis dahin nur bei einem echten Sprachwechsel erneut
  lief. Build 104s neue, häufigere `ApplyLanguage()`-Aufrufe machten genau
  diesen alten Bug jetzt sichtbar. Fix: `BufferPendingTrackedRowUpdate()`
  sichert jetzt zusätzlich eine Baseline (den Feldwert UNMITTELBAR VOR der
  externen Änderung) für die beiden eigentlichen Inhaltsfelder (Rohtext und
  ggf. die live nachübersetzte Zielsprachen-Zelle - reine
  Zeitstempel-Buchführung bleibt unverändert bedingungslos). Beim
  tatsächlichen Schreiben wendet `StagePendingTrackedRowUpdates()` ein
  gepuffertes Feld nur noch an, wenn der aktuelle Zeilenwert noch exakt der
  Baseline entspricht (also seither NICHTS anderes - typischerweise eine
  manuelle Korrektur - die Zelle verändert hat) - andernfalls wird gezielt
  NUR dieses eine Feld übersprungen, alle anderen gepufferten Felder
  derselben Zeile sowie alle anderen Zeilen werden weiterhin normal
  angewendet. Das ursprüngliche Ziel von Build 71 (ein gepufferter externer
  Schreibvorgang darf durch ein unabhängiges "Übernehmen" nicht verloren
  gehen) bleibt für jedes nicht betroffene Feld unverändert bestehen.
* **Build 104, auf Nutzer-Nachfrage: eine manuell im Formular korrigierte
  Übersetzungszelle wurde bislang nicht sofort in der Visualisierung
  sichtbar.** Nicht wie zunächst vermutet die für externe VM_UPDATE-
  Schreibvorgänge gedachte 12-Minuten-Debounce
  (`PENDING_ROW_UPDATE_DEBOUNCE_SECONDS`, siehe `BufferPendingTrackedRowUpdate`
  weiter unten) - die betrifft ausschließlich fremde Schreibzugriffe, nicht
  eine manuelle Bearbeitung im Konfigurationsformular. Der tatsächliche Grund:
  `ApplyLanguage()` (die Funktion, die Namen/Wert tatsächlich ans lebende
  Objekt schreibt) lief in `ApplyChanges()` bisher NUR erneut, wenn sich
  entweder die aktuell aktive Gast-Sprache selbst geändert hatte oder eine
  Zeilen-Quellsprache reconciled wurde - eine reine Korrektur einer
  Übersetzungszelle löst keines von beidem aus. Die Korrektur landete zwar
  sofort gespeichert in der Property, wurde aber erst beim nächsten
  tatsächlichen Sprachwechsel ans Objekt gepusht. Neue, güns­tige
  Fingerprint-Prüfung (`ComputeActiveLanguageContentFingerprint()`, kein
  API-Aufruf, reiner `md5()`-Vergleich über die für die aktuell aktive
  Sprache aufgelösten Zellwerte, analog zur bereits bestehenden
  `ComputeRowSourceLanguageFingerprint()` für Zeilen-Quellsprachen) stößt
  `ApplyLanguage()` jetzt zusätzlich an, sobald sich irgendein für die aktuell
  aktive Sprache relevanter Zellinhalt seit dem letzten Durchlauf geändert
  hat - eine Korrektur an einer GERADE NICHT aktiven Zielsprache löst dabei
  bewusst nichts aus (für den aktuellen Gast ändert sich ja nichts sichtbar).
* **Build 103 behebt einen direkten Nebeneffekt dieser Längengrenze, live
  gefunden anhand einer irreführenden Meldungen-Log-Zeile.** Ein Eintrag
  "alle Anbieter der Kette (deepl [pausiert], google [pausiert], free) haben
  'de' -> 'es' abgelehnt (77 Text(e), erster Text: 'Echo Info')" sah aus, als
  hätte selbst ein triviales 9-Zeichen-Wort abgelehnt - der zugehörige Debug-
  Log-Eintrag zeigte für "Echo Info" aber eine echte, erfolgreiche MyMemory-
  Antwort (HTTP 200, `quotaFinished: false`). Der Text im Log ist nur der
  ERSTE von 77 angefragten Texten (`$Texts[0]` in der Fehler-Zusammenfassung),
  nicht zwangsläufig der eigentliche Übeltäter. Ursache: MyMemory hat keinen
  echten Batch-Endpunkt (ein HTTP-Request pro Text, siehe oben) -
  `TranslateChunkFree()` brach bei EINEM einzelnen `null`-Ergebnis (egal an
  welcher Position) bislang sofort für den GESAMTEN Aufruf ab und verwarf
  dabei alle bereits erfolgreich übersetzten Texte desselben Aufrufs. Ein
  einzelner über 500 Byte langer Text irgendwo unter den 77 angefragten
  reichte also aus, um alle 76 übrigen, problemlos übersetzbaren Texte mit
  sich zu reißen. `TranslateSingleFree()` liefert für diesen Fall jetzt `''`
  (Leerstring) statt `null` - dasselbe Signal wie beim bereits bestehenden
  Leerstring-Fall für einen leeren Rohtext direkt darüber -
  `TranslateChunkFree()` fährt dadurch mit den restlichen Texten fort, statt
  abzubrechen. Die zu lange Zelle selbst bleibt weiterhin leer (wird beim
  nächsten Rescan erneut versucht, dann ggf. über einen zwischenzeitlich
  wieder verfügbaren bezahlten Anbieter ohne diese Längenbegrenzung) - alle
  anderen Texte desselben Aufrufs werden jetzt aber sofort korrekt gefüllt,
  statt unnötig auf den nächsten Rescan warten zu müssen.
* **Build 102, auf Nutzer-Hinweis: DeepLs kostenfreie Stufe ist inzwischen
  KEIN wiederkehrendes Monats-/Tageskontingent mehr, sondern ein EINMALIGES
  Frei-Kontingent (aktuell 1 Mio. Zeichen), danach bleibt der Key dauerhaft
  gesperrt.** `DetectRateLimitCooldown()` behandelte DeepLs dediziertes HTTP
  456 ("Quota Exceeded") bisher wie jedes andere erkannte
  Tageskontingent-Signal (`DAILY_QUOTA_COOLDOWN_SECONDS`, 24h) - das Modul
  hätte einen einmalig aufgebrauchten DeepL-Key dadurch jeden einzigen Tag
  aufs Neue (erfolglos) angefragt, obwohl das Kontingent nie zurückkehrt.
  HTTP 456 bekommt jetzt die deutlich längere, neue
  `DEEPL_QUOTA_EXHAUSTED_COOLDOWN_SECONDS` (30 Tage) - stoppt die
  automatischen Wiederholungsversuche faktisch, ohne den Anbieter für immer
  zu deaktivieren; ein Klick auf "Übersetzungsanbieter prüfen" nach einem
  Key-Wechsel/Upgrade beendet die Sperre wie gewohnt sofort bei Erfolg. Dabei
  einen zweiten, unabhängigen Bug in `RecordProviderPaused()` gefunden und
  mitbehoben: die Eskalations-Deckelung rundete JEDEN übergebenen
  Basis-Cooldown ab `DAILY_QUOTA_COOLDOWN_SECONDS` bedingungslos auf genau
  24h herunter - das hätte die neue, längere DeepL-Sperre stillschweigend
  wirkungslos gemacht. Ein bereits als "langfristig bekannt" erkannter
  Fehlschlag (Tageskontingent ODER jetzt auch DeepLs Einmalkontingent) startet
  jetzt direkt beim tatsächlich übergebenen Wert; nur die generische
  Kurzsperren-Eskalation (Streak-Verdopplung für ein nicht näher erkanntes
  Rate-Limit) bleibt weiterhin auf 24h gedeckelt.
* **Build 101 behebt einen per direkter Property-Abfrage bestätigten Bug:
  wird ein Rohtext leer, bleiben veraltete Übersetzungen in den
  Zielsprachen-Spalten unverändert stehen, statt mit-geleert zu werden.**
  Live gefunden (Nutzer-Diagnose über ein kleines Inspektions-Skript, siehe
  unten): eine "Eigene Texte"-Zeile mit dynamischem Inhalt (springt je nach
  Bedingung zwischen echtem Text und `""`) zeigte `ORIGINAL_IMPORT_Text` aktuell
  leer, `Text_en` aber weiterhin eine längst nicht mehr zutreffende alte
  Übersetzung. Ursache: `ApplyTrackedVariableUpdate()` übernimmt einen leeren
  Wert korrekt als frischen Rohtext und markiert per `MarkRowSourceChanged()`
  bewusst alle Zielsprachen-Zellen als veraltet, OHNE ihren bisherigen
  (Fallback-)Wert zu löschen (siehe dortiger Kommentar) - die eigentliche
  Auffrischung sollte der nächste Rescan übernehmen. `FillLanguageColumn()`/
  `FillLanguageColumnFromRawSource()` übersprangen eine Zeile mit leerem
  Rohtext bisher aber komplett ("nichts zu übersetzen") - dabei blieb die
  laengst veraltete Zielsprachen-Zelle als Karteileiche stehen, statt
  wenigstens geleert zu werden. Trifft ein Rescan die Zeile wiederholt in
  ihrem leeren Zustand (z. B. weil der Inhalt öfter leer als gefüllt ist),
  konnte das faktisch dauerhaft so bleiben. Fix: eine bereits befüllte
  Zielsprachen-Zelle wird jetzt aktiv mit-geleert, sobald der Rohtext selbst
  leer ist - konsistent mit `ResolveRowValue()`, das bei leerem Rohtext
  ohnehin nichts anzuzeigen hätte. Entfernt außerdem das temporäre Build-100-
  Logging wieder. **Der zweite, separate Verdacht aus derselben Live-Diagnose
  ist inzwischen aufgeklärt** (siehe Build 102 direkt im Anschluss) - zwei
  statische "Eigene Texte"-Zeilen mit langen Rohtexten zeigten dauerhaft
  leeres Spanisch bei gefülltem Englisch; der Meldungen-Log des Nutzers zeigte
  den tatsächlichen Grund: "alle Anbieter der Kette (deepl [pausiert], google
  [pausiert], free) haben 'de' -> 'es' abgelehnt" - kein Logikfehler, sondern
  eine echte Anbieter-Erschöpfung (siehe Build 102).
* **Build 100, rein diagnostisch: temporäres `SendDebug`-Logging (Kategorie
  `SLOC_TranslateGapDiag`) in `FillLanguageColumn()`.** Live gemeldet: nach
  einem vollständigen Rescan blieben viele "Eigene Texte"-Zellen für eine
  einzelne Zielsprache (hier: Spanisch) leer, obwohl weder eine Anbieter-Pause
  aktiv war noch der Rohtext als JSON erkannt wurde (das wäre erwartetes
  Verhalten, siehe Build 84) - ein per Debug-Log bestätigter kompletter
  Rescan-Durchlauf zeigte für die betroffenen Zeilen ueberhaupt KEINEN
  `FreeTranslate_Request`/`GoogleTranslate_Mapping`-Eintrag für
  `Text_es`, obwohl die Original-Zelle sichtbar nicht-leeren, echten Text
  enthielt. Reine Code-Analyse von `IsRowLanguageTranslationCurrent()` (der
  einzige Ort, an dem eine Zeile hier übersprungen werden kann, wenn
  `$fromText` nicht leer und kein JSON ist) zeigt keinen offensichtlichen
  Fehler - der Kernverdacht ist daher, dass die betroffene Zielsprachen-Zelle
  entgegen dem Anschein in der Formular-Ansicht in Wahrheit NICHT den leeren
  String `''` enthält (z. B. ein einzelnes Leerzeichen, übrig von einer
  manuellen Lösch-Aktion im Formular) - der strikte `===''`-Vergleich würde
  das fälschlich als "bereits übersetzt" werten. Das neue Logging macht den
  tatsächlichen Zellenwert per `json_encode()` eindeutig sichtbar (deckt auch
  unsichtbare Whitespace-/Sonderzeichen auf) und protokolliert für jede nicht
  als JSON erkannte Zeile mit nicht-leerem Rohtext die vollständige
  Entscheidungsgrundlage (aktueller Zellenwert, JSON-Erkennung,
  "bereits aktuell"-Ergebnis, Zeitstempel). Rein additiv, keine
  Verhaltensänderung (volle Regressionssuite unverändert grün) - wird
  entfernt bzw. durch die eigentliche Korrektur ersetzt, sobald die Logs den
  Mechanismus bestätigt haben.
* **Build 99, auf Nutzer-Wunsch: Tausendertrennzeichen in den
  Übersetzungsstatistiken.** Live gemeldet anhand eines Cache-Ersparnis-Werts
  von über 1,6 Millionen Zeichen, der als reine Ziffernfolge kaum lesbar war.
  Neue Funktion `FormatStatsCountForDisplay()` (Format "1.622.345", dieselbe
  feste, nicht konsolensprachenabhängige Konvention wie das bereits
  bestehende `date('d.m.Y', ...)` an anderer Stelle) wird jetzt in den
  Konfigurationsformular-Statistikzeilen, im Gast-Info-Popup der Kachel und
  im kleinen Hinweistext unter dem Sprach-Dropdown verwendet. Bewusst NICHT
  in die bestehende `FormatStatsCount()` eingebaut, sondern als eigene
  Funktion daneben: `FormatStatsCount()` liefert auch die Werte für die
  `<!--COUNT_TRANSLATIONS-->`/`<!--COUNT_SIGNES-->`-Platzhalter in eigenen
  Kacheln (siehe Abschnitt 7) - dort laut Dokumentation bewusst "nur die
  reine Zahl", da Nutzer sich daraus eigenen Text/JS/CSS bauen; ein
  Trennzeichen hätte dort z. B. ein eigenes `parseInt()` stillschweigend
  brechen können.
* **Build 98 behebt einen live gemeldeten Bug: das Ergebnis-Popup von
  "Aufräumen" verschwand direkt wieder, weil der dafür nötige
  Formular-Reload (`ReloadForm()`) im selben Aufruf lief.** `ReloadForm()`
  ist bei "Aufräumen" nicht verzichtbar - ein bereits offenes
  Konfigurationsformular hätte sonst weiterhin den alten (längeren)
  Listen-Stand im "Übernehmen"-Puffer und würde ihn beim nächsten Speichern
  über das gerade bereinigte Ergebnis zurückschreiben (dieselbe Begründung
  gilt für den manuellen Rescan, siehe dortiger Kommentar) - aber genau
  dieser komplette Formular-Neuaufbau riss auch das gerade erst über
  `UpdateFormField()` gezeigte `CleanupResultPopup` sofort wieder mit aus dem
  DOM, bevor der Nutzer die Meldung lesen konnte. Fix: `CleanupOrphanedRows()`
  ruft `ReloadForm()` nicht mehr synchron im selben Durchlauf auf, sondern
  blendet das Ergebnis-Popup zuerst live auf dem noch offenen Formular ein
  (derselbe bereits bewährte Mechanismus wie bei `CacheClearedPopup`/
  `ProviderCheckResultPopup`, die beide ganz ohne Reload auskommen) und
  startet dann einen einmaligen, um `CLEANUP_RELOAD_DELAY_SECONDS` (5s)
  verzögerten Timer (`ProcessDeferredCleanupReload()`), der den eigentlichen
  Reload erst danach nachholt - genug Zeit, die Meldung zu lesen, bevor die
  Liste im Hintergrund aktualisiert wird. Das Popup bleibt dabei nahtlos
  sichtbar: der zugrunde liegende Zähler-Wert wird weiterhin erst beim
  tatsächlichen Reload (in `PopulateFormElements()`) einmalig verbraucht,
  nicht schon beim Live-Einblenden.
* **Build 97, auf Nutzer-Nachfrage geprüft: die in `library.json` deklarierte
  Mindestversion Symcon 7.1 stimmt weiterhin, ein realer Lücke in der
  Absicherung von `ApplyEnumerationOptionsToVariable()` wurde dabei aber
  gefunden und geschlossen.** `IPS_SetVariableCustomPresentation()` und
  `IPS_GetVariablePresentation()` - Symcons Presentation-System, seit Build 90
  Grundlage für die Übersetzung von Enum-/Profil-Beschriftungen (Dropdowns,
  Status-Variablen usw.) - sind laut offizieller Symcon-Dokumentation erst
  "seit 8.0" verfügbar. Die Lese-Seite (`ReadTranslatablePresentation()`,
  Quelle für `propertyEnumerationOptions`) hat dafür bereits seit jeher einen
  `function_exists('IPS_GetVariablePresentation')`-Schutz (liefert auf
  Symcon < 8.0 einfach `null`, siehe auch [Abschnitt
  3](#3-voraussetzungen): "bleibt komplett inaktiv, kein Fehler") -
  `ApplyEnumerationOptionsToVariable()` (die Schreib-Seite, angewendet bei
  jedem Sprachwechsel) hatte denselben Schutz aber NIE bekommen (nur `@`,
  unterdrückt bloß Warnungen, keinen Fatal Error bei einer unbekannten
  Funktion). Auf einer frisch auf Symcon < 8.0 gescannten Instanz blieb das
  bisher folgenlos, weil `propertyEnumerationOptions` dort dank der
  Lese-Seiten-Sperre ohnehin nie Zeilen enthält (die Schleife, die
  `ApplyEnumerationOptionsToVariable()` aufruft, läuft dann schlicht nie an) -
  betroffen wäre aber eine Instanz gewesen, deren Konfiguration ursprünglich
  auf Symcon ≥ 8.0 gescannt wurde (also bereits reale Zeilen in
  `propertyEnumerationOptions` stehen) und die anschließend auf eine Version
  < 8.0 zurückgestuft bzw. deren Konfiguration dorthin übertragen wird - dort
  hätte der nächste Sprachwechsel einen Fatal Error ausgelöst. Jetzt trägt
  `ApplyEnumerationOptionsToVariable()` denselben Schutz wie die Lese-Seite.
  Der ursprünglich für Symcon 7.1 dokumentierte Kompatibilitätsanspruch bleibt
  damit korrekt, ohne Einschränkung.
* **Build 96, auf Nutzer-Wunsch: sichtbare Rückmeldung für alle Buttons im
  Konfigurationsformular.** "Lizenz aktivieren" hatte bereits ein passendes
  Popup und "Übersetzungs-Cache leeren" stellte sich bei der Durchsicht als
  bereits vollständig umgesetzt heraus (Popup `CacheClearedPopup` mit
  Erfolgsmeldung, in allen vier Sprachen lokalisiert, nach demselben Muster
  wie das Ergebnis-Popup der Anbieter-Prüfung) - keine Änderung nötig. Neu
  bekommen "Übersetzungen gelöschter Elemente entfernen" (Aufräumen) und
  "Übersetzungsanbieter prüfen" je einen eigenen Fortschrittsbalken
  (`CleanupProgressBar`/`ProviderCheckProgressBar`, dieselbe `ProgressBar`-
  Anzeige wie beim Rescan seit Build 88), sichtbar ab Klick bis kurz vor dem
  jeweiligen Ergebnis (Popup bzw. Formular-Neuladen) - selbst wenn "Aufräumen"
  in der Praxis meist nur einen kurzen Moment dauert, bestätigt das kurze
  Aufblitzen dem Nutzer, dass der Klick etwas ausgelöst hat, statt scheinbar
  wirkungslos zu bleiben. Neue gemeinsame Hilfsfunktion `SetButtonProgress()`
  - dieselbe Live-Push-Logik wie `SetRescanProgress()`, aber ohne dessen
  persistierten Attribut-Zustand (der nur für einen ggf. minutenlangen Rescan
  gebraucht wird, den ein wieder geöffnetes Formular nachträglich anzeigen
  können muss - "Aufräumen"/"Anbieter prüfen" laufen synchron innerhalb eines
  einzigen `RequestAction()`-Aufrufs und sind dafür zu kurzlebig). Das
  bestehende Ergebnis-Popup der Anbieter-Prüfung bleibt unverändert - der
  Fortschrittsbalken blendet sich unmittelbar davor aus.
* **Build 95 behebt den in Build 94 diagnostizierten Bug und entfernt das
  dortige temporäre Logging wieder.** Der Log-Dump des Nutzers hat den
  Mechanismus zweifelsfrei belegt (Kandidat b aus Build 94): `existingGreeting`
  und `mergedGreeting` beim Rescan waren korrekt deutsch -
  `IsSourceLanguageActive` in `MergeGreetingRows()` funktioniert also wie
  vorgesehen. Trotzdem zeigte die Zeile direkt nach `IPS_SetProperty()` +
  `IPS_ApplyChanges()` wieder den englischen Text, während `de`/`en`/`es`
  unverändert blieben - ein klares Zeichen für einen gezielten Feld-Patch,
  keinen vollständigen Zeilenaustausch. Ursache: irgendwann zuvor (Zeitpunkt
  unklar, vermutlich ein seltenes Timing-Fenster im Selbst-Schreib-Schutz
  `attributeLastSelfWrittenValues`) hatte `WriteTrackedValueString()`s
  eigener Übersetzungs-Schreibvorgang für die Begrüßungsvariable
  `HandleTrackedVariableUpdate()` ausgelöst, ohne vom Selbst-Schreib-Schutz
  erkannt zu werden - `ApplyTrackedVariableUpdate()` hat den englischen Text
  daraufhin (ohne jeden `IsSourceLanguageActive`-Schutz, den nur
  `MergeGreetingRows()` kennt) als vermeintlich frischen deutschen Rohtext
  übernommen und über `BufferPendingTrackedRowUpdate()` gepuffert. Dieser
  längst veraltete Puffer-Eintrag blieb liegen, bis der o. g. Rescan lief:
  dessen abschließendes `IPS_ApplyChanges()` reentert in `ApplyChanges()`,
  das als einen seiner ersten Schritte `FlushPendingTrackedRowUpdates()`
  aufruft - und DAS hat den falschen Puffer-Eintrag über das gerade eben
  korrekt geschriebene Ergebnis geschrieben. Ein zeitpunktunabhängiger Fix
  direkt am Symptom statt am (schwer greifbaren) Timing-Fenster:
  `ApplyTrackedVariableUpdate()` bricht jetzt sofort ab, wenn der neu
  beobachtete Wert exakt der bereits gespeicherten Übersetzung dieser Zeile
  für die AKTUELL aktive Sprache entspricht - das ist so gut wie sicher ein
  Echo des eigenen Schreibvorgangs, kein echter externer Inhaltswechsel (ein
  Fremdmodul/Zeitplan-Skript, das z. B. eine Tageszeit-Begrüßung schreibt,
  träfe kaum je zufällig exakt den Text einer vorhandenen Übersetzung). Der
  legitime externe-Update-Anwendungsfall aus Build 70 (z. B. ein häufig
  aktualisiertes Wetter-Widget) bleibt davon unberührt, da ein echter neuer
  Messwert praktisch nie mit einer gespeicherten Übersetzung übereinstimmt.
* **Build 94, rein diagnostisch: temporäres `SendDebug`-Logging (Kategorie
  `SLOC_GreetingDiag`) rund um die "Begrüßung" (Modus "Variable").** Live
  gemeldet: nach einem Sprachwechsel in der Gäste-Visu (de → en) und einem
  anschließenden Rescan stand `ORIGINAL_IMPORT` der Begrüßungs-Zeile
  fälschlich auf dem englischen Text, obwohl die Quellsprache weiterhin
  `de` ist - trotz des seit Build (siehe Commit `6ae1bd3`) bestehenden
  `IsSourceLanguageActive`-Schutzes in `MergeGreetingRows()`, der genau
  das verhindern soll. Reine Code-Analyse konnte den genauen Leck-Punkt
  nicht zweifelsfrei bestimmen - zwei Kandidaten: (a) der Schutz in
  `MergeGreetingRows()` selbst greift in diesem konkreten Fall nicht wie
  erwartet, oder (b) der eigentliche Schreib-/Übersetzungsvorgang von
  `ApplyGreetingLanguage()` (beim Sprachwechsel selbst, nicht erst beim
  Rescan) über `HandleTrackedVariableUpdate()`/`ApplyTrackedVariableUpdate()`
  läuft - letztere Funktion hat KEINEN `IsSourceLanguageActive`-Schutz und
  würde jeden als "extern" erkannten Schreibvorgang ungeprüft als frischen
  Rohtext übernehmen. Das Logging deckt beide Kandidaten ab: Reihenfolge
  von `SetValueString()` und dem Self-Write-Guard-Attribut in
  `WriteTrackedValueString()`, den tatsächlich ausgewerteten
  `IsSourceLanguageActive`-Wert in `ScanRootTree()`, sowie jeden Schreibpfad
  in `ApplyGreetingLanguage()`/`ApplyTrackedVariableUpdate()` mit den
  jeweils beteiligten Rohwerten. Rein additiv, keine Verhaltensänderung
  (volle Regressionssuite unverändert grün) - wird entfernt bzw. durch die
  eigentliche Korrektur ersetzt, sobald die Logs den Mechanismus bestätigt
  haben.
* **Build 93 behebt einen wichtigen Bug in der "Eigenen
  Übersetzungstabelle" (Build 89): ein Glossar-Eintrag wirkte bisher nur
  auf noch LEERE Zellen, nicht auf bereits (ggf. falsch) automatisch
  übersetzte.** Live gefunden: "SSW" (Windrichtung Süd-Südwest) wurde von
  Google fälschlich als Abkürzung für "Schwangerschaftswoche" erkannt und
  mit "week of pregnancy" "übersetzt" - ein extra dafür angelegter
  Glossar-Eintrag ("SSW" → "SSW") blieb trotzdem komplett wirkungslos,
  weil die betroffene Zielsprachen-Zelle bereits (falsch) befüllt war und
  die Glossar-Prüfung bisher nur innerhalb `TranslateBatch()` lief - dort
  aber nur für noch unübersetzte ("pending") Zellen erreichbar ist, exakt
  wie bei jeder anderen Zelle auch (schützt normalerweise echte manuelle
  Korrekturen vor versehentlichem Überschreiben). Das widersprach aber der
  ursprünglichen Anfrage wörtlich: ein Glossar-Eintrag soll "immer
  Vorrang vor Online-Übersetzungen" haben - was nur funktionieren kann,
  wenn er auch eine bereits gefüllte Zelle überschreiben darf. Neue
  `ApplyManualTranslationOverrides()` läuft jetzt bei JEDEM Rescan VOR der
  normalen Fülllogik und prüft JEDE Zelle jeder Zeile gegen das Glossar -
  unabhängig davon, ob sie bereits befüllt ist -, und überschreibt sie,
  sobald ein passender Eintrag mit abweichendem Wert existiert. Eine
  bereits korrekte Zelle (Wert entspricht bereits dem Glossar-Eintrag)
  bleibt dabei unverändert (kein unnötiges Neu-Markieren). Betrifft
  bewusst auch die Quellsprachen-Spalte selbst (z. B. um einen Tippfehler
  im gescannten Rohtext gezielt zu korrigieren, ohne den eigentlichen
  Objektnamen anzufassen). Die bisherige Prüfung innerhalb
  `TranslateBatch()` bleibt zusätzlich bestehen - notwendig für die live
  nachübersetzten "Eigenen Texte" (siehe `ApplyTrackedVariableUpdate`),
  die nicht über den normalen Rescan-Pfad laufen.
* **Build 92:** die Überschrift der Liste selbst ("Eigene
  Übersetzungstabelle", live gemeldet) fehlte noch in `locale.json` und
  blieb dadurch bei jeder Konsolensprache auf Deutsch stehen, obwohl die
  Spalten-/Beschreibungstexte bereits korrekt übersetzt wurden - Build 89
  hatte nur Letztere ergänzt, den kurzen Listentitel selbst aber
  übersehen. Ergänzt für en/es/it/fr.
* **Build 91, auf Nutzer-Wunsch: der "Hinzufügen"-Button der "Eigenen
  Übersetzungstabelle" ist jetzt ebenfalls deaktiviert, wenn die Lizenz
  das Feature `manual_translations` (Build 89) nicht enthält.** Die
  Zellen selbst waren dafür bereits schreibgeschützt (siehe
  `BuildListColumns`), der Button blieb bisher aber unabhängig davon
  klickbar - ein Klick legte eine neue Zeile an, die sich anschließend
  gar nicht mehr bearbeiten ließ, ein verwirrender Sackgassen-Zustand.
  Außerdem ist `manual_translations` jetzt ab der Standard-Lizenz Teil
  des offiziellen Feature-Sets (Standard UND Pro, siehe
  `includes/products.php` im Shop-Repository) - bereits VOR diesem Update
  ausgestellte Lizenzschlüssel enthalten dieses Feature naturgemäß noch
  nicht (ein Lizenzschlüssel ist beim Ausstellen kryptografisch signiert
  und lässt sich nicht nachträglich ändern) und benötigen einen neu
  ausgestellten Schlüssel, um die "Eigene Übersetzungstabelle"
  tatsächlich nutzen zu können.
* **Build 90 behebt einen strukturellen Bug, live gefunden: ein
  Sprachwechsel konnte in "Aufzählungsoptionen" ("Captions") Dopplungs-
  Zeilen erzeugen, deren "Original import" tatsächlich die zuletzt
  angezeigte ÜBERSETZUNG war, fälschlich als Quellsprache markiert.**
  Zeigt eine Variable ihre Beschriftung über ein geteiltes Profil/Template
  (z. B. "An"/"Aus"), kann Simple Locale beim Live-Anzeigen einer anderen
  Sprache keine einzelnen Felder an einer WEITERHIN referenzierten
  Quelle überschreiben - die Variable wird deshalb bewusst "geforkt"
  (die Profil-/Template-Referenz entfernt, die Übersetzung stattdessen
  direkt inline in die Variable geschrieben, siehe
  `ApplyEnumerationOptionsToVariable`). Bis Build 90 verlor die Variable
  dadurch aber auch dauerhaft die einzige Spur ihrer ursprünglichen,
  geteilten Identität: ohne erkennbare Profil-/Template-Referenz fiel die
  Zeilenerkennung beim nächsten Rescan auf einen Hash über den GERADE
  angezeigten Text zurück (Build 75, für Variablen, die von Haus aus kein
  geteiltes Profil/Template nutzen) - und dieser Hash änderte sich mit
  JEDEM Sprachwechsel, weil sich der angezeigte Text änderte. Der nächste
  Rescan erkannte die Zeile dadurch nicht wieder, hielt den gerade
  angezeigten (oft längst übersetzten) Text für frischen Quelltext und
  legte eine neue, falsch beschriftete Dopplungs-Zeile an - reproduzierbar
  bei jedem automatischen Rescan, solange die Kachel auf einer anderen
  als der Quellsprache stand. Der bereits vor dem allerersten Fork
  gesicherte Rücksprung-Zustand
  (`attributeEnumerationPresentationBackup`, ursprünglich nur fürs
  Zurückschalten auf "Original" gedacht) kennt die echte, stabile
  Profil-/Template-Referenz aber weiterhin - `GetPresentationSourceKey()`
  leitet den Zeilen-Schlüssel jetzt bevorzugt daraus ab, sobald ein
  Backup existiert, unabhängig davon, welche Sprache die Variable gerade
  live anzeigt.

  Zweiter, tieferer Teil desselben Bugs: Variablen OHNE jedes geteilte
  Profil/Template (eigenständige, direkt vom Gerätetreiber gesetzte
  Inline-Präsentation, siehe Build 75) verlieren durch den Fork nicht nur
  eine Referenz, sondern ihren GESAMTEN stabilen Rohtext - für sie reicht
  die stabile Referenz allein nicht, da der Rohtext selbst (nicht nur ein
  Verweis darauf) in den Content-Hash einfließt. `ReadTranslatablePresentation()`
  liest den zu extrahierenden Inhalt jetzt ebenfalls bevorzugt aus dem
  Backup, sobald dieses selbst keine Profil-/Template-Referenz enthält -
  hat der Backup dagegen eine Referenz (der häufigere Fall), bleibt es bei
  der live aufgelösten Präsentation für den Inhalt (der Backup ist dort
  nur eine dünne Referenz ohne eigene Beschriftungen), die
  Zeilenerkennung ist für diesen Fall bereits über die Schlüssel-Ableitung
  oben abgesichert.

  **Bereits bestehende Installationen:** durch den Bug bereits entstandene
  Dopplungs-Zeilen verschwinden nicht automatisch rückwirkend - einmal
  "Baum neu einlesen" (damit die betroffenen Variablen wieder unter ihrem
  stabilen Schlüssel erkannt werden) und danach einmal "Aufräumen"
  (entfernt die jetzt nicht mehr auffindbaren alten Dopplungs-Zeilen,
  siehe Build 76) klicken.
* **Build 89, auf Nutzer-Wunsch: neue Liste "Eigene Übersetzungstabelle" -
  ein admin-gepflegtes Glossar, das jeder automatischen Übersetzung
  (Google/DeepL/MyMemory) vorgezogen wird.** Aufbau wie "Objektnamen"
  (eine Quellsprachen-Spalte + eine Spalte je Zielsprache), aber komplett
  eigenständig admin-editierbar statt aus dem Objektbaum gescannt - über
  den "Hinzufügen"-Button der Liste selbst legt der Admin eine neue Zeile
  an, wählt die Quellsprache, trägt den Quelltext ein und füllt beliebig
  viele Zielsprachen-Zellen aus. Trifft der Quelltext einer Zeile hier
  (zeichengenau, kein Fuzzy-Matching) auf den Rohtext IRGENDEINER anderen
  Zeile in dieser Instanz zu (Objektnamen, Eigene Texte,
  Aufzählungsoptionen, Automatisierungen, Begrüßung), wird für jede
  ausgefüllte Zielsprachen-Zelle diese Übersetzung verwendet statt eines
  Anbieter-Aufrufs - und zwar mit höherer Priorität als sogar der interne
  Übersetzungs-Cache. Bewusst zellenweise: eine ansonsten passende Zeile
  mit einer noch LEEREN Zelle für eine bestimmte Zielsprache blockiert die
  automatische Übersetzung NUR für andere, bereits ausgefüllte Sprachen
  nicht - für diese eine Sprache läuft die automatische Übersetzung ganz
  normal weiter. Neues Lizenz-Feature `manual_translations` (ab
  Standard-Lizenz, unabhängig vom bestehenden `edit_translations` für das
  nachträgliche Korrigieren einzelner Auto-Übersetzungszellen) - ohne
  dieses Feature bleibt die Property zwar erhalten (kein Datenverlust bei
  einem Lizenz-Downgrade), wirkt sich aber gar nicht mehr aus, weder beim
  Bearbeiten noch bei der Anwendung bereits gespeicherter Zeilen.
* **Build 88, auf Nutzer-Wunsch: "Baum neu einlesen" zeigt jetzt einen
  Fortschrittsbalken im Konfigurationsformular, solange der Rescan
  läuft.** Bisher war ein länger laufender Rescan (viele neue
  Übersetzungen, mehrere API-Aufrufe) im Formular selbst nicht von einem
  eingefrorenen/nicht reagierenden Modul zu unterscheiden - erkennbar war
  das bislang nur über die Debug-Meldungen-Konsole, die kaum ein
  gewöhnlicher Nutzer je öffnet. Neues `ProgressBar`-Formularelement
  (`indeterminate`, also eine laufende Animation statt eines exakten
  Prozentwerts - eine echte Prozentanzeige würde eine deutlich tiefere
  Umstrukturierung der Übersetzungs-Batches erfordern, für vergleichsweise
  wenig zusätzlichen Nutzen), dessen Beschriftung während `ScanRootTree()`
  bei jedem Verarbeitungsschritt per `UpdateFormField()` live aktualisiert
  wird ("Baum wird eingelesen…" → "Objektnamen und Texte werden
  übersetzt…" → "Weitere Inhalte werden übersetzt…" → "Ergebnis wird
  gespeichert…") - nach demselben Prinzip, nach dem auch die
  Debug-Konsole schon während eines laufenden Skripts live neue Einträge
  zeigt, nicht erst nach Skriptende. Wird sowohl beim Abbruch (unbenannte
  Objekte gefunden) als auch beim regulären Abschluss zuverlässig wieder
  ausgeblendet - unabhängig davon, ob es sich um einen manuellen oder
  einen automatischen Hintergrund-Rescan handelt (letzterer ruft nie
  `ReloadForm()` auf, das Ausblenden darf deshalb nicht daran hängen -
  sonst genau dieselbe Art von hängenbleibender Anzeige wie der in
  Build 87 behobene Pause-Hinweis).
* **Build 87 behebt zwei weitere, live gefundene Probleme und dokumentiert
  eine strukturelle Einschränkung.**

  Erstens: eine korrekte, mit hoher Konfidenz bestätigte Übersetzung, die
  zufällig identisch zum Ausgangstext blieb (z. B. "Cover" bleibt auch auf
  Spanisch "Cover" - ein echtes Lehnwort, von MyMemory selbst mit
  `match: 1` bestätigt), wurde bisher fälschlich als gescheiterter
  Übersetzungsversuch gewertet und dauerhaft verworfen - die Zelle blieb
  leer UND wurde bei JEDEM weiteren Rescan erneut angefragt, ohne je
  "fertig" zu werden (treffend als drohender Deadlock beschrieben: Zellen,
  deren korrekte Übersetzung zufällig gleich dem Rohtext bleibt, wie auch
  technische, gar nicht übersetzbare Bezeichner wie `SetVisibilityOff`
  innerhalb von Automatisierungs-Aktionsnamen, konnten so nie einen
  stabilen, gecachten Endzustand erreichen). Ursache war eine
  Unterscheidungslücke: `TranslateBatchUncached()` fällt bei einem
  ECHTEN Anbieter-Fehlschlag bewusst auf den unübersetzten Rohtext zurück
  (verhindert eine leere/kaputte HTML-Struktur, siehe Build-70-Historie),
  aber das Ergebnis sah für die aufrufende Stelle identisch aus wie eine
  ECHTE, zufällig gleichbleibende Übersetzung - beide wurden bisher über
  einen reinen Textvergleich ("Ergebnis == Rohtext?") auseinandergehalten,
  einer strukturell unzuverlässigen Heuristik. `TranslateBatchUncached()`
  liefert jetzt zusätzlich ein echtes, aus `TranslateChunk()` stammendes
  `failed`-Flag pro Text mit - kein Rätselraten mehr nötig: eine echte
  Übersetzung (auch wenn zufällig identisch) gilt jetzt korrekt als
  erfolgreich und wird gecacht, nur ein wirklicher Fehlschlag bleibt leer
  und offen für den nächsten Versuch.

  Zweitens: der rote "Übersetzung pausiert bis..."-Hinweis auf der Kachel
  blieb nach Ende einer Anbieter-Pause teils MINUTENLANG stehen, obwohl
  `GetGlobalPauseUntil()` selbst jederzeit korrekt (nicht
  zwischengespeichert) den aktuellen Zustand lieferte und ein manueller
  Anbieter-Test im Formular längst wieder Erfolg meldete - reines
  Anzeigeproblem, keine fehlerhafte Übersetzungs-Entscheidung dahinter.
  `ClearProviderPause()` (läuft bei jedem echten Übersetzungserfolg)
  aktualisiert zwar sofort den gespeicherten Pause-Zustand, pusht aber nie
  von sich aus eine aktualisierte Anzeige an bereits geöffnete
  Gast-Kacheln - und auch ein rein zeitliches Ablaufen der Pause (ganz
  ohne neuen Übersetzungsversuch) hat dafür ohnehin keinen eigenen
  Auslöser. Der bisher nur für die Statistik-Zeile gedachte periodische
  Kachel-Refresh (`RefreshTranslationStatsTile`, zuvor an
  `propertyShowTranslationStats` gekoppelt und alle 10 Minuten) läuft ab
  jetzt IMMER (unabhängig von dieser Einstellung) und alle 2 statt 10
  Minuten - er aktualisiert ohnehin die komplette Gast-Anzeige in einem
  Rutsch (Statistik UND Pause-/Testphase-Hinweis), verursacht dabei
  weiterhin keinen einzigen API-Aufruf (reine `PushVisualizationUpdate()`-
  Neuberechnung).
* **Build 86 behebt einen Bug in Build 85, live gefunden: eine bereits als
  Gast-Sprache aktive, mitgelieferte Sprache (z. B. Englisch) zeigte
  trotzdem den deutschen Rohtext.** Ursache: die neuen mitgelieferten
  Übersetzungen (`OWN_UI_TEXT_BUNDLED_TRANSLATIONS`) landeten bisher NUR
  über `MergeOwnUiTextRows()` in der jeweiligen Zeile - und diese Funktion
  läuft ausschließlich innerhalb `ScanRootTree()`, also nur bei einem
  tatsächlichen Rescan. Vor dem allerersten Rescan (oder direkt nach dem
  Update auf Build 85, bevor ein neuer Rescan lief) blieb
  `propertyOwnUiTexts` für diese Sprache leer, und `GetOwnUiText()` fiel
  auf den deutschen Rohtext zurück - genau das Gegenteil des in Build 85
  versprochenen "sofort bereit, kein Rescan nötig". `GetOwnUiText()` (der
  eigentliche Lesepfad, den jeder Gast-Text-Baustein nutzt) greift jetzt
  zusätzlich direkt auf die mitgelieferte Übersetzungstabelle zurück, wenn
  weder eine persistierte Zeile noch eine Zelle darin etwas liefert -
  unabhängig davon, ob/wann je ein Rescan lief. Eine bereits persistierte
  Zeile (echte Provider-Übersetzung, manuelle Korrektur oder ein durch
  einen späteren Rescan eingetragener Bundled-Wert) hat weiterhin Vorrang
  vor diesem Fallback.
* **Build 85, auf Nutzer-Wunsch: die eigenen Gast-Oberflächentexte (siehe
  Build 78) bringen jetzt feste Standard-Übersetzungen fürs Ausliefern mit
  - für de/en/es/it/fr/nl sowie alle `TRIAL_LANGUAGE_CODES` (isländisch,
  walisisch, zulu, māori, latein) steht die Übersetzung dieser Texte sofort
  bereit, ganz ohne einen einzigen API-Aufruf bei irgendeinem Provider zu
  verbrauchen - selbst direkt nach einer frischen Installation.** Neue
  Konstante `OWN_UI_TEXT_BUNDLED_TRANSLATIONS`, eingebunden in
  `MergeOwnUiTextRows()`: füllt beim Rescan JEDE noch leere Sprachspalte
  einer dieser mitgelieferten Sprachen direkt mit dem fest hinterlegten
  Text - unabhängig von den aktuell konfigurierten Zielsprachen, damit die
  Übersetzung sofort bereitsteht, sobald eine dieser Sprachen jemals als
  Zielsprache gewählt wird. Eine bereits vorhandene (echte, per Provider
  erzeugte) Übersetzung wird dabei nie überschrieben. en/es/it/fr
  übernehmen bewusst denselben Wortlaut wie die längst vorhandenen
  Konsolensprachen-Übersetzungen derselben deutschen Texte (z. B.
  "Stündlich:" → "Hourly:"), damit Konsolen- und Gast-Oberfläche
  konsistent klingen.

  Außerdem, ebenfalls auf Nutzer-Wunsch: das Panel "Übersetzungsanbieter"
  nutzt für MyMemory jetzt den WIRKLICH genauen Reset-Zeitpunkt statt der
  Build-83-Annahme "nächste UTC-Mitternacht" - MyMemorys Fehlermeldung
  nennt die verbleibende Wartezeit meist direkt und exakt (z. B. "NEXT
  AVAILABLE IN 02 HOURS 51 MINUTES 23 SECONDS"), live beobachtet und
  spürbar präziser als die reine Mitternachts-Annahme (das Kontingentfenster
  scheint nicht zwingend exakt auf UTC-Mitternacht zu fallen, sondern eher
  rollierend ab dem ersten Verbrauch zu laufen). Neue
  `ParseMyMemoryNextAvailableTimestamp()` extrahiert diesen Countdown direkt
  aus MyMemorys Antworttext; die UTC-Mitternacht-Berechnung aus Build 83
  bleibt als Rückfallwert bestehen, falls das Muster einmal nicht gefunden
  wird (z. B. bei einer künftig geänderten Formulierung).
* **Build 84 behebt zwei weitere, live gefundene Probleme.** Erstens war
  das Simple-Locale-Symbol nach Build 82 auf manchen Kacheln sichtbar
  GRÖSSER als das Dropdown, nicht exakt gleich hoch: die Höhenanpassung
  lief über `align-self: stretch`, was das Icon auf die Höhe der GESAMTEN
  Zeile (`.sloc-select-row`) skalierte - in der echten Kachel-Darstellung
  bekommt diese Zeile aber offenbar mehr Höhe zugewiesen, als das Dropdown
  selbst braucht, wodurch auch das Icon zu groß wurde. Gelöst über eine
  gemeinsame, feste CSS-Variable (`--sloc-control-height`), die Dropdown
  UND Icon jetzt beide explizit auf denselben Wert setzt - unabhängig
  davon, wie viel Höhe die umgebende Zeile tatsächlich bekommt.

  Zweitens, deutlich wichtiger: eine String-Variable im gescannten Baum
  kann statt echtem Gast-Anzeigetext auch Konfigurations-/Steuerdaten für
  ein GANZ ANDERES Modul enthalten (live beobachtet: eine
  Favoriten-/Playlist-Liste mit Inhalt wie
  `{"musicProvider":"CLOUDPLAYER","searchPhrase":"Mein Discovery Mix"}`).
  Simple Locale übersetzte diesen Rohtext bisher wie gewöhnlichen
  Fließtext - Google/DeepL lieferten dabei u. a. HTML-kodierte
  Anführungszeichen (`&quot;` statt `"`) zurück, was die JSON-Struktur für
  das eigentlich konsumierende Skript zerstörte (z. B. erwartete dieses
  Skript für `musicProvider` einen bestimmten, festen Wert). Eine gezielte
  "nur die Anzeigetexte innerhalb des JSON übersetzen, Struktur/Schlüssel
  erhalten"-Lösung wäre technisch nicht zuverlässig umsetzbar: strukturelle
  Schlüssel/Enum-Werte (z. B. `"CLOUDPLAYER"`) lassen sich innerhalb
  desselben JSON nicht verlässlich von echtem, übersetzbarem Anzeigetext
  unterscheiden - eine "intelligente" JSON-Teilübersetzung würde ebenso oft
  falsch raten und stattdessen ein trügerisches Sicherheitsgefühl erzeugen.
  Erkennt Simple Locale jetzt, dass ein Rohtext gültiges JSON ist (beginnt
  mit `{` oder `[` UND lässt sich vollständig parsen - ein einzelnes Wort
  oder eine Zahl wie "42"/"true" zählt bewusst NICHT als JSON, da das
  weiterhin ganz normaler übersetzbarer Text sein kann), wird dieser
  Rohtext von der Übersetzung komplett ausgenommen - für JEDE Gast-Sprache
  bleibt automatisch der unveränderte Rohtext sichtbar (bestehender
  Rohtext-Fallback, keine neue Logik nötig). Bereits VOR diesem Update
  fehlerhaft übersetzte JSON-Zellen bleiben bestehen (wie bei jeder anderen
  Fehlübersetzung, siehe unten) - einmalig die betroffene Zelle leeren,
  "Übernehmen" klicken, dann erneut Rescan ausführen, danach bleibt sie
  dauerhaft leer/unverändert (kein erneuter Übersetzungsversuch mehr).
* **Build 83, auf Nutzer-Wunsch: das Panel "Übersetzungsanbieter" spiegelt
  jetzt wider, wie zuverlässig die angezeigte Pause-Zeit je Anbieter
  tatsächlich ist, plus dieselbe Formatierungs-Korrektur wie bei der
  Statistik.** Bisher zeigten alle drei Anbieter (Google, DeepL,
  kostenfreier Anbieter/MyMemory) dieselbe generische, EXPONENTIELL
  eskalierende Schätzung ("jetzt + 15min/30min/1h/2h/... bis maximal 24h",
  siehe `RecordProviderPaused`) als "pausiert bis" an - unabhängig davon,
  ob diese Schätzung für den jeweiligen Anbieter überhaupt etwas mit der
  Realität zu tun hat:
  - **MyMemory** setzt sein kostenfreies Tageskontingent nachweislich
    zuverlässig um Mitternacht UTC zurück (fest, bekannt) - die generische
    "jetzt + 24h"-Schätzung konnte dadurch je nach Tageszeit des
    Fehlschlags um bis zu fast 24 Stunden danebenliegen. Wird jetzt exakt
    auf die nächste UTC-Mitternacht berechnet (`GetNextUtcMidnightTimestamp`) -
    sowohl beim eindeutigen `quotaFinished`-JSON-Signal als auch beim
    generischen HTTP-429-Pfad, sofern dort ein Tageskontingent (nicht nur
    ein kurzes Burst-Limit) erkannt wurde.
  - **Google** liefert dagegen keine verlässliche Reset-Zeit (siehe
    `RecordProviderPaused`: live beobachtet, dass ein erkanntes Rate-Limit
    trotzdem über Stunden bestehen blieb) - die Zeile heißt jetzt
    "voraussichtlich pausiert bis" statt schlicht "pausiert bis", um die
    angezeigte Zeit klar als Schätzung statt als Zusage zu kennzeichnen.
  - **DeepL** bekommt dieselbe "voraussichtlich"-Formulierung PLUS einen
    zusätzlichen Hinweis darunter: bei DeepL ist ein aufgebrauchtes
    Kontingent nicht garantiert automatisch zurückgesetzt - bloßes Warten
    hilft dann nicht, nur ein Kauf bei DeepL oder ein neuer API-Key.

  Außerdem, wie von der Statistik-Sektion bekannt: die bisher separat
  schwebende ":" -Beschriftung (eigenes Label-Element, dadurch je nach
  Länge des Anbieternamens unterschiedlich weit vom Text entfernt) ist
  jetzt direkt an den Anbieternamen angehängt ("Google Cloud Translate:"
  statt "Google Cloud Translate" + ":" als getrennte Elemente) - dieselbe
  Technik wie bei "Stündlich:"/"Insgesamt:" im Statistik-Panel.
* **Build 82, auf Nutzer-Wunsch: die Spalte der Quellsprache bleibt beim
  Rescan nicht mehr leer, sondern übernimmt direkt den Rohtext.** Trifft
  eine Zeile beim Rescan auf eine Zielsprache, die genau ihrer eigenen
  Quellsprache entspricht (siehe Build 79/81), gibt es nichts zu
  übersetzen - der Rohtext IST bereits der korrekte Inhalt. Bisher blieb
  die Zelle in diesem Fall trotzdem leer (kein API-Aufruf, aber auch kein
  Kopiervorgang), was in der Admin-Ansicht wie eine fehlende Übersetzung
  aussah. Die Zelle wird jetzt direkt mit dem Rohtext befüllt - ohne
  API-Aufruf, ohne Übersetzungs-Kontingent zu verbrauchen - unter der
  Annahme, dass dieser Text bereits gut genug ist; der Admin kann ihn wie
  jede andere Zelle jederzeit manuell korrigieren, eine bereits gefüllte
  oder korrigierte Zelle wird dabei nie überschrieben.

  Außerdem, ebenfalls auf Nutzer-Wunsch: das Simple-Locale-Symbol links
  neben dem Sprach-Dropdown skaliert jetzt in der Höhe exakt auf die Höhe
  der Dropdown-Box, statt einer festen Pixelgröße - passt sich dadurch
  automatisch an, falls Schriftgröße/Innenabstand des Dropdowns sich
  künftig ändern (z. B. durch eigenes Kachel-HTML, siehe Abschnitt 7).
* **Build 81 behebt zwei weitere Anzeige-Lücken, die erst nach Build 80 im
  Live-Test auffielen - die Daten selbst waren zu diesem Zeitpunkt bereits
  korrekt gespeichert (per Debug-Meldung bestätigt), es fehlte nur die
  passende Darstellung.** Erstens übersprang `BuildLanguageColumnSet()`
  (baut die Sprachspalten für "Objektnamen"/"Eigene Texte"/"Aufzählungs-
  optionen"/"Automations"/"Begrüßung" auf) bislang grundsätzlich die Spalte
  für die instanzweite Quellsprache - korrekt VOR Build 79, als ihr Inhalt
  immer 1:1 identisch mit "Original import" war. Seitdem kann aber eine
  EINZELNE Zeile eine abweichende eigene Quellsprache tragen (z. B. eine
  ursprünglich englischsprachig gescannte Zeile in einem sonst deutschen
  Baum) - für so eine Zeile zeigt "Original import" weiterhin den
  englischen Rohtext, während die (bisher fehlende) "Deutsch"-Spalte die
  tatsächliche deutsche Übersetzung zeigen sollte, also einen eigenen,
  nicht-redundanten Wert. Die Spalte fehlte dadurch für den kompletten
  Baum, unabhängig davon, ob überhaupt eine Zeile abweichende Quellsprachen
  hatte. Für Zeilen mit einheitlicher Quellsprache bleibt der Spalteninhalt
  weiterhin redundant zu "Original import" - bewusst in Kauf genommen,
  keine Sonderlogik dafür.

  Zweitens zeigte "Zielsprachen" nach dem automatischen Ergänzen der
  Quellsprache (siehe Build 79) eine LEERE Zeile ohne sichtbaren
  Sprachnamen: `BuildTargetLanguageOptions()` liefert nicht nur die Auswahl
  für "Hinzufügen", sondern auch die Beschriftung, mit der die Liste jede
  bereits gespeicherte Zeile anzeigt - und schloss die Quellsprache
  ebenfalls grundsätzlich aus (plus, unter Testphase/`allowedLanguages`-
  Einschränkung, ein zweites Mal). Beide Ausschlüsse sind jetzt entfernt
  bzw. die Quellsprache explizit ausgenommen - dieselbe Ausnahme wie in
  Build 80 für `EnforceLicensedLanguageLimit()`, konsistent an beiden
  Stellen angewendet.
* **Build 80 behebt zwei Nachbesserungen an Build 79, die erst beim
  direkten Live-Test auffielen.** Erstens: ein reiner Rescan-Klick zeigte
  die Quellsprache weder in "Zielsprachen" noch als neue Spalte bei
  "Objektnamen"/"Eigene Texte" - `ScanRootTree()` liest die aktuelle
  Zielsprachenliste ganz am Anfang eines Durchlaufs, WEIT BEVOR
  `EnsureSourceLanguageIsTarget()` (das bisher nur in `ApplyChanges()`
  hing) überhaupt zum Zug kam - der neu ergänzte Eintrag kam dadurch immer
  einen ganzen Rescan-Durchlauf zu spät. `ScanRootTree()` ruft
  `EnsureSourceLanguageIsTarget()` jetzt selbst, ganz am Anfang, auf - ein
  einzelner Rescan reicht ab jetzt aus.

  Zweitens, unabhängig vom ersten Fund: bei einer Lizenz mit einer
  eingeschränkten `allowedLanguages`-Liste (nur bei gezielten
  Promo-Lizenzen wie "Finnisch zu Nikolaus" oder der
  "Nachbarländer"-Aktion, siehe `GetLicensedAllowedLanguages`) wurde der
  gerade erst ergänzte Quellsprachen-Eintrag von genau dieser Prüfung in
  JEDEM `ApplyChanges()`-Durchlauf sofort wieder entfernt, da die eigene
  Basissprache so gut wie nie in einer thematisch engen Promo-Sprachliste
  auftaucht - für diese Lizenzen hätte Build 79 dauerhaft überhaupt keine
  Wirkung gezeigt, egal wie oft man "Übernehmen" klickt. Die aktuelle
  Quellsprache ist jetzt explizit von der `allowedLanguages`-Einschränkung
  ausgenommen (das numerische Sprachlimit selbst bleibt davon unberührt -
  siehe oben, das ist weiterhin bewusst gewollt).
* **Build 79 behebt eine Lücke bei unterschiedlichen Quellsprachen: die
  Basissprache verschwindet nicht mehr aus der Gast-Auswahl, wenn die
  Scan-Sprache später geändert wird.** Bisher gab es neben den echten
  Zielsprachen eine separate Pseudo-Sprache "Original" (`ORIGINAL_IMPORT`),
  die sich immer auf die AKTUELL eingestellte Scan-Sprache
  (`SourceLanguage`) bezog. Das führte zu einer Lücke: wurde z. B. zuerst
  mit Deutsch gescannt und die Scan-Sprache danach auf Englisch
  umgestellt, verschwand Deutsch komplett aus der Gast-Auswahl - obwohl
  bereits gescannte Objekte weiterhin ihre deutsche Zeilen-Quellsprache
  trugen und für sie gar keine Übersetzung "zurück" nach Deutsch existierte
  (siehe `fieldRowSourceLanguage`, eingeführt in Build 57). "Original" gibt
  es ab jetzt nicht mehr als eigene wählbare Sprache: stattdessen sorgt
  eine neue Methode (`EnsureSourceLanguageIsTarget()`, aufgerufen am Anfang
  jedes `ApplyChanges()`-Laufs) dafür, dass die jeweils AKTUELLE
  Quellsprache immer als ganz normaler, dauerhafter Eintrag in den
  Zielsprachen (`TargetLanguages`) steht - ändert sich die Scan-Sprache
  später erneut, bleibt der alte Eintrag als normale Zielsprache stehen,
  statt zu verschwinden.

  **Wichtig für Lizenzen mit begrenzter Sprachanzahl** (z. B. die
  "Spezialversion"): der automatisch ergänzte Quellsprachen-Eintrag
  unterliegt exakt derselben Lizenz-Sprachobergrenze wie jede manuell
  hinzugefügte Zielsprache (`EnforceLicensedLanguageLimit()`, läuft direkt
  im Anschluss). Das ist bewusst so: ohne diese Kopplung könnte man durch
  wiederholtes Umstellen der Scan-Sprache beliebig viele "kostenlose"
  Zielsprachen an einer lizenzierten Obergrenze vorbei ansammeln. In der
  Praxis bedeutet das: bei einer Lizenz mit z. B. Sprachlimit 1, die
  bereits eine Zielsprache konfiguriert hat, verbraucht ein Wechsel der
  Scan-Sprache selbst einen Platz in dieser Obergrenze - im Zweifel wird
  dabei sogar eine zuvor konfigurierte Zielsprache automatisch verdrängt
  (dieselbe Kappungs-Logik wie bisher schon bei einem Lizenz-Downgrade).
  Unlimitierte Lizenzen sind davon nicht betroffen.

  Bereits bestehende Installationen: eine Instanz, deren aktive
  Gast-Sprache (`CurrentLanguage`) noch auf der alten Pseudo-Sprache
  "Original" stand, wird beim ersten `ApplyChanges()` nach diesem Update
  automatisch, einmalig auf die tatsächliche Quellsprache umgeschrieben -
  keine manuelle Nacharbeit nötig.
* **Build 78 macht die festen Gast-Oberflächentexte komplett unabhängig von
  Anbieter-Pausen, ergänzt den Pause-Grund und weitere kleinere
  Verbesserungen.** Der eigentliche Kern dieses Builds, direkte Folge des
  in Build 77 gefundenen Bugs: die festen Gast-Oberflächentexte
  ("Übersetzung pausiert bis", die Statistik-Beschriftungen, der
  Info-Popup-Hinweistext, ...) laufen ab sofort NICHT mehr über einen
  24h-Live-Übersetzungs-Cache (`EnsureGuestLanguageNamesFresh`), der -
  genau dann, wenn er ausgerechnet während einer Anbieter-Pause aktualisiert
  wird - für den Rest des Tages auf Deutsch hängen bleiben konnte. Diese
  Texte werden jetzt genau wie Objektnamen/Automations beim Rescan EINMALIG
  in alle konfigurierten Zielsprachen übersetzt und dauerhaft in einer
  eigenen, neuen Property (`OwnUiTexts`) gespeichert - da sie fest im
  PHP-Code stehen und sich nur mit einem künftigen Modul-Update überhaupt
  ändern können, liegt die Übersetzung dadurch strukturell IMMER schon vor,
  bevor eine Pause je eine Rolle spielen könnte. Bewusst OHNE eigene Liste
  im Konfigurationsformular und ausdrücklich NICHT von "Aufräumen" (Build
  76) betroffen - der Admin kann diese Zeilen weder versehentlich löschen
  noch verändern, sie gehören zu keinem Symcon-Objekt und sollen dauerhaft,
  unabhängig von jeder Admin-Aktion, vorhanden bleiben. Ändert ein
  künftiges Modul-Update den deutschen Wortlaut eines dieser Texte, wird
  das beim nächsten Rescan automatisch erkannt und neu übersetzt (die alte
  Übersetzung bleibt bis dahin als Fallback sichtbar, statt sofort zu
  verschwinden).

  Zusätzlich, alles auf Nutzer-Wunsch: das Info-Popup nennt jetzt auch den
  GRUND einer laufenden Anbieter-Pause ("Grund: Alle konfigurierten
  Übersetzungsanbieter melden aktuell ihr Limit erreicht."), nicht mehr
  nur "bis wann". Die Überschrift des Info-Popups wird jetzt fett
  dargestellt - technisch über die "Mathematical Sans-Serif Bold"-Zeichen
  aus dem Unicode-Block "Mathematical Alphanumeric Symbols" (U+1D5D4 ff.),
  da `alert()` reiner Text ist und keine HTML-/Markdown-Formatierung
  kennt; sehen in praktisch jedem modernen Browser/Betriebssystem
  fettgedruckt aus, sind aber technisch eigene Zeichen statt eines
  Formatierungsattributs (deckt nur A-Z/a-z/0-9 ab, Leerzeichen/
  Sonderzeichen bleiben unverändert). Und: der graue Kreis-Hintergrund
  hinter dem Simple-Locale-Symbol in der Kachel (Build 77) wurde entfernt -
  nur noch das reine Symbol, ohne umschließende Form.
* **Build 77 behebt eingefrorene deutsche Gast-Hinweise nach einer
  Anbieter-Pause und erweitert das Info-Popup der Kachel.** Live gemeldet:
  bei aktiv gewählter englischer Gast-Sprache blieben der
  Pausiert-Hinweis ("Übersetzung pausiert bis...") und der
  Statistik-Hinweis unter dem Dropdown trotzdem auf Deutsch stehen.
  Ursache: `EnsureGuestLanguageNamesFresh()` (der Cache für alle
  live in die Gast-Sprache übersetzten UI-Texte, max. 1x/Tag
  aktualisiert) lief während einer AKTIVEN Anbieter-Pause, der
  Übersetzungsversuch schlug dadurch für JEDEN Text zwangsläufig fehl
  und fiel korrekt auf den rohen deutschen Text zurück - wurde
  anschließend aber trotzdem als "heute schon erfolgreich aktualisiert"
  verbucht und dadurch bis zu 24 Stunden lang nicht erneut versucht,
  selbst nachdem die Pause längst vorbei war. Behoben durch dieselbe
  Kurzschluss-Prüfung wie in `TranslateChunk()` (während einer aktiven
  Pause lohnt sich gar kein Versuch, der bestehende - zuletzt ECHT
  erfolgreich übersetzte - Cache bleibt unangetastet) plus eine zweite
  Absicherung: der Cache gilt nur dann als "heute schon frisch", wenn
  wirklich mindestens ein Text erfolgreich übersetzt wurde, nicht bei
  einem kompletten (auch anbieter-pause-unabhängigen) Fehlschlag.
  Zusätzlich, auf Nutzer-Wunsch, zeigt das Info-Popup der Kachel (ⓘ-Symbol)
  jetzt zusätzlich dieselbe Übersetzungsstatistik (Seit Inbetriebnahme/
  Stündlich/Insgesamt/Durch den Cache eingespart, wie im
  Konfigurationsformular) sowie - falls gerade aktiv - einen Kurzhinweis
  zur laufenden Anbieter-Pause, beides live in die jeweils aktive
  Gast-Sprache übersetzt. Die bisherige Überschrift "Hinweise" wurde durch
  App-Name + Lizenz-Edition ersetzt (z. B. "Simple Locale - Pro Edition",
  ohne Lizenz schlicht "Simple Locale") - bewusst NICHT übersetzt, eine
  Marken-/Editionsbezeichnung ist sprachunabhängig, genau wie
  `$licenseInfo['edition']` im Konfigurationsformular selbst bereits als
  roher Wert behandelt wird.

  Außerdem, ebenfalls auf Nutzer-Wunsch: das 🌐-Emoji links neben dem
  Sprach-Dropdown wurde durch das eigentliche Simple-Locale-Symbol ersetzt
  (`libs/assets/module_icon_48.png`, als Base64-Grafik eingebettet - kein
  öffentlicher Pfad/Webhook nötig, funktioniert dadurch überall
  identisch). Fällt auf die alte 🌐-Glyphe zurück, falls die Bilddatei aus
  irgendeinem Grund nicht lesbar ist. Die zugehörige Einstellung heißt
  jetzt "Simple-Locale-Symbol in der Kachel anzeigen" (Property/Attribut-
  Name `ShowGlobeIcon` und CSS-Klasse `sloc-globe` bleiben aus
  Kompatibilitätsgründen unverändert - siehe Abschnitt 7 für eigene,
  darauf aufbauende Kachel-Anpassungen).
* **Build 76 ergänzt "Aufräumen": verwaiste Zeilen künftig per Klick statt
  einzeln von Hand entfernbar.** Nutzer-Wunsch nach einem Feature-Vergleich
  mit Symcons eigener, konkurrierender Lösung - die hat eine entsprechende
  Funktion, Simple Locale bislang nicht. Bereits mit Build 51 (Root-Baum-
  Merge) bewusst als Designentscheidung eingeführt: Rescan/Auto-Rescan
  lassen Zeilen, deren Objekt inzwischen gelöscht oder aus der
  Visualisierung entfernt wurde, absichtlich unangetastet stehen (siehe
  `MergeRows`/`MergeEnumerationOptions`/`MergeAutomationRows`) - ein
  Sicherheitsnetz gegen eine versehentlich falsche/unvollständige Root-
  Kategorie, das schon mehrfach in dieser Historie vor Datenverlust
  geschützt hat (u. a. Build 75s eigene Migrationsnotiz direkt darüber).
  Der neue Button "Übersetzungen gelöschter Elemente in der Visualisierung
  entfernen" (siehe [Abschnitt 5](#5-einrichten-der-instanzen-in-symcon),
  Absatz "Aufräumen: verwaiste Zeilen endgültig entfernen") macht diese
  bislang nur manuelle, zeilenweise Aufräumarbeit zu einer bewussten,
  expliziten Ein-Klick-Aktion - mit ausdrücklicher Warnung im Formular,
  dass (anders als ein normaler Rescan) dabei tatsächlich unwiederbringlich
  gelöscht wird, falls die Root-Kategorie gerade falsch/unvollständig
  gewählt ist. "Begrüßung" bleibt bewusst ausgenommen (keine gescannte
  Liste, sondern eine einzelne direkt konfigurierte Einstellung).
* **Build 75 fasst inhaltlich identische Beschriftungen ohne geteiltes
  Profil/Template zu einer Zeile zusammen:** Live gemeldet (Screenshot):
  mehrere Variablen mit exakt identischem Beschriftungs-Inhalt (z. B. eine
  ganze Reihe "Ja"/"Nein"-Variablen) erschienen als komplett getrennte
  Zeilen in "Captions", obwohl über ein geteiltes Profil oder Template
  verknüpfte Variablen bereits korrekt zu einer Zeile zusammengefasst
  wurden. Ursache: `GetPresentationSourceKey()` fiel auf einen rein
  variablenspezifischen Schlüssel zurück, sobald eine Variable ihre
  `VariableCustomPresentation` INLINE trägt (kein Profilname, keine
  Template-GUID) - ein sehr verbreitetes Muster, da viele Symcon-
  Gerätetreiber dieselbe JSON-Struktur direkt in jede einzelne Variable
  schreiben, statt ein gemeinsames Template-Objekt zu referenzieren. Fällt
  jetzt zusätzlich auf einen Hash über den tatsächlich extrahierten,
  übersetzbaren Inhalt (Feldpfad + Text) zurück, wenn weder Profil noch
  Template vorliegen - zwei Variablen mit identischem Inhalt landen jetzt
  automatisch in derselben Zeile, auch ganz ohne eine geteilte Symcon-
  Objektidentität dahinter. Profil-/Template-basierte Gruppierung (bereits
  korrekt) bleibt davon komplett unberührt und hat weiterhin Vorrang - das
  ist bewusst so: verweisen zwei Variablen auf ein ECHTES, aber (durch ein
  fremdes Modul, z. B. Echo/Alexa) je Geräteinstanz eigenes Profil, bleiben
  sie zurecht getrennte Zeilen, selbst wenn ihr Inhalt gerade zufällig
  übereinstimmt - sonst würde eine spätere, unabhängige Änderung an EINEM
  der beiden Profile fälschlich beide Zeilen gemeinsam betreffen. Hinweis:
  nach dem ersten Rescan mit diesem Build bleiben die alten,
  variablenspezifischen Zeilen als verwaiste Duplikate bestehen (wie bei
  jedem entfernten/veränderten Objekt, siehe Abschnitt "Bekannte
  Einschränkungen") - können nach Prüfung der neuen, zusammengeführten
  Zeile manuell gelöscht werden.
* **Build 74 behebt eingeschleuste Platzhalter-Tags bei DeepL-Übersetzungen
  reiner Objektnamen:** Live gemeldet (Screenshot, zweimal beobachtet): ein
  völlig einfacher Objektname ohne jedes HTML ("N-JOY") kam auf Spanisch als
  `<g id="1">N-JOY</g>                    <g id="2"><g id="3"/></g>` zurück
  - sichtbare, kaputte Auszeichnungs-Reste mitten im Klartext. Ursache:
  `TranslateChunkDeepL()` hat bei JEDER Anfrage unterschiedslos
  `"tag_handling": "html"` an DeepL geschickt, unabhängig davon, ob der Text
  tatsächlich HTML enthielt (kopiert vom analogen, aber harmloseren Muster
  bei Google, siehe unten). Anders als Googles `format`-Parameter (der nur
  steuert, ob Sonderzeichen als HTML-Entity zurückkommen) schaltet DeepLs
  `tag_handling` seine komplette Markup-Verarbeitung ein - und kann dabei
  auch bei komplett taglosem Eingabetext eigene, synthetische
  Platzhalter-Tags in die Ausgabe einschleusen. `$IsHtml` wird jetzt bis zu
  `TranslateChunkGoogle()`/`TranslateChunkDeepL()` durchgereicht:
  `tag_handling` wird bei DeepL nur noch für echte "Eigene Texte"-HTML-
  Inhalte überhaupt gesetzt (sonst fehlt der Schlüssel im Request komplett -
  DeepLs Standardmodus ohne jede Markup-Erkennung, strukturell
  ausgeschlossen, dass so ein Platzhalter-Tag je entstehen kann). Bei dieser
  Gelegenheit auch Google angepasst: `format` steht jetzt nur noch bei
  echtem HTML auf `"html"`, sonst auf `"text"` - vermeidet nicht nur den
  bisher nötigen `html_entity_decode()`-Umweg für reinen Text, sondern
  schließt auch aus, dass ein wörtliches "&"/"<" in einem Objektnamen (z. B.
  "Bad & WC") im html-Modus fälschlich als Beginn einer HTML-Entity/eines
  Tags interpretiert wird.
* **Build 73 stellt klar, dass "nur aktive Sprache" ausschließlich für den
  automatischen Live-Trigger gilt, nicht für Rescan, und macht den
  Persistierungs-Hinweis live sichtbar:** Zwei Nachbesserungen nach dem
  ersten Praxistest von Build 70/71. Erstens: Rescan (manuell wie
  Auto-Rescan) und der Quellsprachen-Abgleich (siehe Build 57) übersetzen
  ab sofort wieder ALLE konfigurierten Zielsprachen in einem Durchgang,
  nicht mehr nur die aktuell aktive - live gemeldet, nachdem gelöschte
  Objekte per Rescan zurückkehrten (bzw. eine Zelle manuell geleert wurde),
  aber keine ihrer Zielsprachen nachübersetzt wurde, solange sie nicht
  gerade aktiv war. Ein Nutzer, der "Baum neu einlesen" klickt oder eine
  Zelle absichtlich leert, erwartet zu Recht, dass JEDE fehlende
  Übersetzung nachgeholt wird, nicht nur die gerade angezeigte Sprache -
  das war ursprünglich zu weit gefasst: die eigentliche
  Kontingent-Ursache (siehe Build 70) war ausschließlich die automatische
  Live-Nachübersetzung bei externen Variablenänderungen
  (`ApplyTrackedVariableUpdate`, mehrmals pro Minute bei einer aktiven
  Wetter-/Sensor-Variable), NICHT ein einmaliger Rescan. Diese eine Stelle
  bleibt weiterhin bewusst auf die aktive Sprache beschränkt - der
  Nachhol-Mechanismus beim Sprachwechsel bleibt für sie zusätzlich als
  Backstop bestehen. Zweitens: der in Build 71 eingeführte
  Persistierungs-Hinweis im Formular wurde bislang nur beim (Neu-)Öffnen
  des Formulars berechnet - ein bereits offenes Formular zeigte ihn
  deshalb nie an, egal wie lange man wartete (folgerichtig, da Build 71
  bewusst kein `ReloadForm()` mehr bei externen Schreibvorgängen auslöst).
  Der Hinweis wird jetzt zusätzlich per `UpdateFormField()` direkt aus dem
  Puffer-Mechanismus heraus live in ein bereits offenes Formular
  eingeblendet bzw. wieder ausgeblendet - ohne jede Störung der laufenden
  Bearbeitung, exakt wie die bereits bestehenden `UpdateFormField()`-
  Aufrufe in anderen Formular-Popups.
* **Build 72 macht den Übersetzungs-Cache treffsicherer und größer:** Bisher
  war der lokale Cache (bis zu 500 Einträge) rein nach Einfügereihenfolge
  organisiert (FIFO) - wurde er voll, flog immer der ZUERST gespeicherte
  Eintrag zuerst raus, unabhängig davon, wie oft er seitdem tatsächlich
  wiederverwendet wurde. Ein Schwung einmaliger, nie wieder vorkommender
  Texte konnte dadurch theoretisch einen häufig wiederverwendeten Kern-
  Eintrag (z. B. einen festen Objektnamen) verdrängen, nur weil dieser
  zufällig zuerst im Cache landete. Jeder Eintrag führt jetzt zusätzlich
  einen Hit-Zähler und den Zeitpunkt seines letzten Zugriffs - wird der
  Cache voll, fliegt der Eintrag mit dem NIEDRIGSTEN Hit-Zähler zuerst raus
  (bei Gleichstand der am längsten nicht mehr genutzte). Ein Eintrag, der
  seit über 24 Stunden nicht mehr gelesen wurde, gilt beim nächsten Zugriff
  als "neu wieder aufgewärmt" (Zähler-Reset auf 1) statt seinen alten
  Zähler für immer fortzuschreiben - verhindert, dass ein früher einmal
  populärer, inzwischen längst nicht mehr gebrauchter Eintrag einen frisch
  aktiven verdrängt. Die Kapazität wurde gleichzeitig von 500 auf 1000
  Einträge angehoben. Da sich dabei die gespeicherte FORM eines Eintrags
  ändert (von einem reinen String zu einem kleinen Objekt mit Hit-Zähler/
  Zeitstempel), wurde `TRANSLATION_CACHE_SCHEMA_VERSION` erhöht (4) - macht
  den kompletten, bis dahin aufgewärmten Cache einmalig unerreichbar (jeder
  Text wird beim nächsten Bedarf einmal frisch übersetzt, alte Einträge
  bleiben als toter Ballast stehen, bis die neue Verdrängungslogik sie -
  mangels jedes Hit-Zählers - als Erstes wieder herausdrängt).
* **Build 71 entkoppelt die Live-Übersetzung von der Formular-Persistierung
  einer häufig aktualisierten Variable:** Live gemeldet - trotz Build 70
  konnte ein Admin praktisch keine eigene Änderung im Konfigurationsformular
  mehr speichern, wenn eine "Eigene Texte"-Variable mehrmals pro Minute von
  außen aktualisiert wurde (z. B. ein Wetter-/Sensor-Widget). Ursache: jede
  externe Änderung hat weiterhin sofort per `IPS_SetProperty()` + `IPS_
  ApplyChanges()` genau die Property umgeschrieben, die im offenen Formular
  als bearbeitbare "Eigene Texte"-Liste angezeigt wird - kein `ReloadForm()`
  nötig, das reine Überschreiben der zugrunde liegenden Property unter einer
  live gebundenen Formularliste reichte bereits, um eine laufende Bearbeitung
  zu stören. Ab Build 71 sind zwei Schreibvorgänge, die bislang immer
  gemeinsam sofort passierten, sauber entkoppelt: die **Live-Variable**
  (das, was der Gast in der Kachel sieht) wird weiterhin komplett
  unverändert/unverzögert geschrieben; nur die **Property-Persistierung**
  (die Buchführung, die ausschließlich für einen späteren, seltenen
  Sprachwechsel gebraucht wird) wird jetzt gepuffert und erst nach 12
  Minuten Ruhe auf der jeweiligen Variable tatsächlich committet (Debounce -
  jede neue Änderung schiebt den Zeitpunkt weiter nach hinten). Speichert
  der Admin währenddessen im Formular ("Übernehmen"), wird der noch
  wartende Puffer automatisch VORHER eingespielt, bevor die eigene Änderung
  verarbeitet wird - die zuletzt gepufferte externe Änderung geht dabei
  nicht verloren, unabhängig vom Timing. Ein tatsächlicher Sprachwechsel
  (selten, aber jederzeit möglich) leert den Puffer ebenfalls sofort, statt
  auf das Ende der Ruhephase zu warten - ein Gast bekommt dadurch immer den
  aktuellsten Stand zu sehen. Das Konfigurationsformular zeigt zusätzlich
  oben einen Hinweis mit der voraussichtlichen Uhrzeit der nächsten
  Persistierung an, solange etwas ansteht.
* **Build 70 übersetzt live nur noch die aktuell aktive Gast-Sprache, holt
  alle anderen bei Bedarf nach, und filtert reine Zahlen/Symbole komplett
  heraus:** Live beobachtet lief ein täglich verfügbares Übersetzungs-
  Kontingent innerhalb weniger Stunden vollständig leer (77.000 Zeichen an
  einem einzigen Tag), obwohl die Übersetzungs-Anbieter zwischenzeitlich
  sogar pausiert waren. Ursache: eine häufig extern aktualisierte "Eigene
  Texte"-Variable (z. B. ein Wetter-/Sensor-Widget, mehrmals pro Minute
  über `VM_UPDATE` aktualisiert) hat bei JEDER Änderung sofort ALLE
  konfigurierten Zielsprachen neu übersetzt, obwohl zu keinem Zeitpunkt
  mehr als eine Sprache gleichzeitig angezeigt wurde. Ab Build 70
  übersetzen der Rescan, die `VM_UPDATE`-Live-Nachübersetzung und der
  Quellsprachen-Abgleich (siehe Build 57) sofort nur noch die AKTUELL
  aktive Gast-Sprache - alle anderen Zielsprachen-Zellen bleiben dabei
  bewusst auf ihrem letzten bekannten (ggf. jetzt veralteten) Stand stehen,
  statt geleert zu werden. Ein neuer Zeitstempel-Abgleich je Zeile (wann
  wurde der Rohtext zuletzt geändert, wann wurde jede Sprache zuletzt
  tatsächlich übersetzt) erkennt zuverlässig, welche Zelle veraltet ist,
  ohne den bisherigen Fallback-Wert zu löschen. Wechselt ein Gast
  tatsächlich auf eine bisher nur lazy behandelte Sprache, holt ein neuer
  Nachhol-Mechanismus GENAU die betroffenen Zeilen gebündelt (ein
  API-Aufruf je Zeilen-Property statt einzeln je Zeile) nach, bevor die
  Sprache angezeigt wird - danach ist sie normal gecacht. Bereits vor
  diesem Build gespeicherte Zeilen ohne die neue Zeitstempel-Buchführung
  gelten dabei bewusst als "aktuell" (keine Massen-Neuübersetzung des
  kompletten Bestands nach dem Update). Zusätzlich geht ein Text-Fragment
  ganz ohne jeden Buchstaben (reine Zahlen, "%", "°", Uhrzeiten,
  Satzzeichen - besonders häufig bei der feingranularen HTML-Text-Knoten-
  Zerlegung eines Live-Widgets, siehe Build 63) gar nicht mehr an eine
  Übersetzungs-API: eine erkannte einzelne Zahl wird stattdessen über
  PHPs eingebaute `NumberFormatter`-Klasse (Intl-Erweiterung) rein lokal
  in die landesübliche Schreibweise der Zielsprache umgerechnet (z. B.
  deutsches "1.234,56" → englisches "1,234.56"), ohne dabei eine
  ungruppierte Zahl (z. B. eine Jahreszahl oder Zimmernummer wie "2026")
  fälschlich mit einem künstlich eingefügten Tausendertrennzeichen zu
  versehen. Fehlt die Intl-Erweiterung auf einer Installation, wird der
  Text stattdessen unverändert durchgereicht (kein Fehler). Einziger,
  bewusst in Kauf genommener Nebeneffekt: eine reine Zahl wird nie mehr
  durch eine Übersetzungs-API-Anfrage geschickt und kann deshalb auch
  keine darüber hinausgehende, kontextabhängige Umformatierung mehr
  erhalten, die Google/DeepL gelegentlich mitgeliefert haben.
* **Build 69 behebt einen unsichtbaren Zeichen-Artefakt aus MyMemory:** Live
  im Debug-Log beobachtet lieferte MyMemory bei einem Treffer aus seiner
  Übersetzungsspeicher-Datenbank ein zusätzliches, unsichtbares Zeichen direkt
  am Ende der Übersetzung mit ("Position" wurde tatsächlich als
  "Position " - mit einem geschützten Leerzeichen (U+00A0) dahinter -
  zurückgegeben). PHPs `trim()` entfernt nur ASCII-Leerraum (Leerzeichen, Tab,
  Zeilenumbruch), niemals Unicode-Zeichen wie ein geschütztes Leerzeichen oder
  ein Zero-Width-Space (U+200B) - ein solches Ergebnis wurde daher unverändert
  gespeichert/gecacht und sah in den allermeisten Ansichten optisch identisch
  zum sauberen Text aus, obwohl es ihm nicht exakt entsprach. Behoben an der
  einen zentralen Stelle, durch die die Ergebnisse aller drei Anbieter
  (Google/DeepL/MyMemory) laufen (`TranslateChunk()`): am Anfang und Ende
  werden jetzt gezielt nur geschützte Leerzeichen und Zero-Width-Spaces
  entfernt - bewusst NIE ein normales ASCII-Leerzeichen, da ein einzelner
  HTML-Textknoten (siehe Build 63/`SplitHtmlIntoTextNodes`) am Rand
  absichtlich ein Leerzeichen tragen kann, das für den korrekten Abstand
  zwischen zwei benachbarten Inline-Elementen gebraucht wird.
* **Build 68 rundet die Build-67-Umstellung ab:** Live beobachtet blieben
  zwei einzelne Textbausteine trotz Build 67 weiterhin unübersetzt -
  "Tag(e):" und "Zeichen." -, obwohl das bloße "Tag(e)"/"Zeichen" an anderer
  Stelle im selben Formular korrekt übersetzte. Ursache: an genau diesen
  beiden Stellen war ein Satzzeichen DIREKT an das deutsche Wort angehängt
  ("Tag(e):" statt "Tag(e)", "Zeichen." statt "Zeichen") - eine Zeichenkette,
  die dadurch nicht mehr EXAKT dem registrierten `locale.json`-Schlüssel
  entspricht, bleibt beim Abgleich unübersetzt stehen (siehe Build 67).
  Jedes Satzzeichen, das zu keinem eigenen Textbaustein gehört, sitzt jetzt
  in einem eigenen, unbenannten Element (kein `locale.json`-Eintrag nötig -
  ein Satzzeichen ohne Übersetzungstreffer wird ohnehin unverändert
  angezeigt). Zusätzlich fehlte "Kostenfreier Anbieter (MyMemory)" bisher
  komplett als registrierter Übersetzungstext (nicht falsch zusammengesetzt,
  sondern schlicht nie übersetzbar gemacht) - jetzt in allen 4 Sprachen
  ergänzt und sowohl in der Pause-Übersicht als auch im
  "Übersetzungsanbieter prüfen"-Ergebnis verwendet. Der Nutzungs-Zähler
  wurde dabei gleich klarer strukturiert: statt der bisherigen ".../h"-Suffixe
  zeigt eine neue, eigene Zeile "Stündlich" (übersetzbar) die
  Pro-Stunde-Werte, "Insgesamt" (vormals klein geschrieben "insgesamt")
  startet jetzt ebenfalls eine eigene Zeile - vier klar getrennte,
  vollständig lokalisierte Zeilen (Seit Inbetriebnahme / Stündlich /
  Insgesamt / Durch den Cache eingespart) statt eines einzigen, dichten
  Absatzes. Nach demselben Muster wurden außerdem der Testphasen-Hinweis
  (`TrialInfoFreshLabel`/`TrialInfoRunningRow`/`TrialInfoExpiredRow`, je
  nach Testphasen-Status genau eine sichtbare Variante) und das Ergebnis-
  Popup von "Übersetzungsanbieter prüfen" umgebaut (ein `RowLayout` je
  geprüftem Anbieter, per `UpdateFormField()` einzeln befüllt statt eines
  einzigen zusammengesetzten Texts) - beide hatten dieselbe Systemsprache-
  statt-Konsolensprache-Einschränkung, waren bisher aber nur noch nicht
  gemeldet worden.
* **Build 67 behebt eine Konsolensprachen-Einschränkung, die zwei
  dynamische Textbereiche im Konfigurationsformular betraf** - den
  Nutzungs-Zähler-Satz unter "Aktiv" (siehe oben) und die
  Pause-Übersicht im Panel "Übersetzungsanbieter": beide blieben live
  beobachtet dauerhaft auf Deutsch stehen, selbst bei englischer
  (oder jeder anderen) Konsolensprache des Betrachters - obwohl fest
  eingebaute Formular-Beschriftungen ("Aktiv", "Notaus-Schalter: ...")
  im selben Formular korrekt übersetzt erschienen. Ursache: `$this->Translate()`
  ist an die Symcon-SYSTEMSPRACHE gebunden (eine einzelne, installationsweite
  Kernel-Einstellung), NICHT an die individuelle Konsolensprache der gerade
  betrachtenden Admin-Sitzung - die tatsächliche, per-Betrachter korrekte
  Übersetzung von `GetConfigurationForm()`-Beschriftungen übernimmt
  stattdessen der Konsolen-Client selbst, per exaktem Textabgleich einer
  KOMPLETTEN Beschriftung gegen `locale.json` - unabhängig davon, ob diese
  Beschriftung ursprünglich statisch in `form.json` stand oder von PHP
  gesetzt wurde. Eine zur Laufzeit aus mehreren `Translate()`-Fragmenten und
  eingefügten Werten (Datum, Uhrzeit, Zahlen) ZUSAMMENGESETZTE Zeichenkette
  matcht dadurch NIE einen `locale.json`-Eintrag als Ganzes und bleibt
  unabhängig von der tatsächlichen Konsolensprache stehen - exakt dieselbe,
  bereits beim Lizenz-Infobereich gefundene und dort erfolgreich behobene
  Einschränkung (siehe die vielen einzelnen `LicenseInfoXxx`-Formularelemente).
  Beide betroffenen Bereiche wurden nach demselben, bereits bewährten Muster
  umgebaut: viele einzelne, kleine `RowLayout`/Label-Formularelemente statt
  eines zusammengesetzten Fließtexts - jedes Element trägt entweder eine
  feste, unveränderte deutsche Zeichenkette (die der Konsolen-Client korrekt
  je nach Betrachter übersetzt) oder einen rohen, nicht zu übersetzenden Wert
  (Datum/Uhrzeit/Zahl), nie beides zusammengesetzt in einer Caption. Kein
  `$this->Translate()`-Aufruf mehr in `FormatTranslationStatsValue()`/
  `PopulateProviderPauseStatusElement()`/`FormatProviderPauseUntil()`.
  Zusätzlich zeigt die Pause-Übersicht im Panel "Übersetzungsanbieter"
  jetzt auch in der Kachel selbst (roter Hinweis unter dem Dropdown) das
  Datum zusätzlich zur Uhrzeit (z. B. "18.08. 21:34" statt nur "21:34") -
  eine reine Uhrzeit war bei einer über Mitternacht hinausreichenden Pause
  (durch die Eskalation, siehe Build 57, bis zu 24h) mehrdeutig.
* **Build 66 schließt dieselbe Lücke zusätzlich im Übersetzungs-Cache:**
  Live beobachtet direkt nach Build 65 - Zellen leeren + Rescan füllte
  "Automations" trotzdem wieder mit Deutsch, obwohl "Begrüßung" im selben
  Test korrekt übersetzt wurde. Ursache: der interne Übersetzungs-Cache
  (siehe Abschnitt 1, `GetCachedTranslation`/`StoreCachedTranslation`) hatte
  denselben unübersetzten Rohtext bereits VOR Build 65 unter genau diesem
  Schlüssel als "echte Übersetzung" zwischengespeichert (z. B. "Gehen" für
  Deutsch->Englisch) - ein Cache-TREFFER läuft komplett an `TranslateBatch()`s
  frischem Übersetzungspfad (und damit am Build-65-Schutz) vorbei, liefert
  also weiterhin den vergifteten alten Eintrag. Im Debug-Log erkennbar an
  einem "..._Mapping"-Eintrag ganz ohne jeden nachfolgenden
  "..._Request"/"..._Response" - der sichere Hinweis auf einen Cache-Treffer
  statt eines echten (und damit geschützten) neuen Versuchs. Behoben wie
  beim strukturell identischen Vorfall vom 2026-08-15 (siehe
  `TRANSLATION_CACHE_SCHEMA_VERSION`): die Cache-Version wurde erneut erhöht
  (2 → 3) - macht JEDEN vor Build 66 gecachten Eintrag unerreichbar (die
  Version steckt im Cache-Schlüssel) und erzwingt für jeden betroffenen Text
  einmalig einen frischen, jetzt korrekt geschützten Übersetzungsversuch.
  **Mit Build 66 reicht das Leeren betroffener Zellen im Formular + Rescan
  wieder allein aus** - ein zusätzliches manuelles "Übersetzungs-Cache
  leeren" ist NICHT mehr nötig (macht aber ebenfalls nichts kaputt, falls
  bereits geklickt).
* **Build 65 behebt den bisher schwerwiegendsten Fund dieser gesamten
  Serie:** Live beobachtet wurden "Automations" und "Begrüßung" nach einem
  Rescan während einer Anbieter-Pause komplett auf den unübersetzten
  deutschen Rohtext eingefroren - in JEDER Zielsprachen-Spalte, dauerhaft,
  auch nachdem die Pause längst vorbei war. Ursache: `TranslateBatchUncached()`
  fällt bei einem fehlgeschlagenen Übersetzungsversuch bewusst NIE auf einen
  leeren String zurück, sondern auf den unübersetzten Quelltext (siehe Build
  58 - richtig für die dortige Wiederzusammensetzung von HTML-Widgets, die
  sonst mit leeren dynamischen Werten dastünden). Das Problem: `TranslateBatch()`
  ist der EINE zentrale Durchgangspunkt, über den ALLE anderen Funktionen
  laufen (`FillLanguageColumn` beim Rescan, `ApplyTrackedVariableUpdate` bei
  der VM_UPDATE-Live-Nachübersetzung, `ReconcileRowFields` beim
  Quellsprachen-Abgleich) - und JEDE von ihnen entscheidet ausschließlich
  anhand eines LEEREN Strings, ob eine Zelle als "fertig übersetzt, nicht
  erneut versuchen" oder "fehlgeschlagen, bitte später erneut versuchen"
  gilt. Da dieses leere Signal wegen des Fallbacks nie ankam, wurde JEDE
  Zelle, deren allererster Übersetzungsversuch während einer Pause
  stattfand, fälschlich als "erledigt" verbucht - inklusive einer
  Cache-Vergiftung (der unübersetzte Rohtext wurde als "echte Übersetzung"
  zwischengespeichert und dadurch nie wieder neu versucht, selbst nach Ende
  der Pause). Behoben an der EINEN zentralen Stelle (`TranslateBatch()`):
  ein Ergebnis, das exakt dem unübersetzten Quelltext entspricht, wird dort
  wieder in einen echten Leerstring zurückverwandelt, bevor es an
  irgendeinen Aufrufer weitergereicht oder gecacht wird - `TranslateBatchUncached()`
  selbst (und damit die HTML-Wiederzusammensetzung) bleibt unverändert.
  **Bereits vorhandene, auf diese Weise eingefrorene Zellen werden davon
  nicht rückwirkend erkannt** (der gespeicherte deutsche Text ist nicht mehr
  von einer "zufällig identischen" echten Übersetzung unterscheidbar) -
  betroffene Zellen müssen einmalig manuell im Formular geleert werden,
  danach übersetzt sie der nächste Rescan/die nächste Anbieter-Erholung
  normal nach.
* **Build 64 behebt eine fehlende Ein-/Mehrzahl-Behandlung im
  Nutzungs-Zähler-Satz** (siehe oben, Build 60/61): "Anfragen"/"Zeichen" (und
  deren Übersetzungen) standen dort bisher IMMER in der Mehrzahl fest
  codiert, auch wenn der tatsächliche Wert 1 war (z. B. "1 Anfragen" statt
  korrekt "1 Anfrage", ebenso in den anderen 4 Sprachen). Behoben nach genau
  demselben, bereits bei der Testphasen-Anzeige bewährten Muster
  (`BuildTrialInfoText()`, "Tag(e)"/"day(s)"/"día(s)"/"giorno/i"/"jour(s)"):
  EIN einzelner, nicht konjugierender Anzeige-String pro Sprache, der für
  jede Anzahl passt - kein Laufzeit-Unterschied zwischen Einzahl/Mehrzahl
  nötig. Die betroffenen `locale.json`-Schlüssel heißen jetzt "Anfrage(n)"/
  "Anfrage(n)/h" (vorher "Anfragen"/"Anfragen/h") und liefern in jeder
  Sprache eine passende Kurzform (z. B. "request(s)/h" statt nur "requests/h";
  fürs unregelmäßige Spanisch "carácter"/"caracteres" wurde stattdessen
  bewusst auf das regelmäßig pluralisierende Synonym "signo(s)" ausgewichen,
  um die im Plural verschwindende Betonungs-Markierung "á" nicht falsch
  darzustellen; Italienisch nutzt statt eines Suffixes einen Klammer-Wechsel
  der letzten Buchstaben, z. B. "richiest(a/e)", "caratter(e/i)", passend zum
  bereits bestehenden "giorno/i").
* **Build 63 korrigiert die Farbcodierung im Symcon-"Meldungen"-Log (englisch
  "Status Log"):** Übersetzungs-Fehler/-Warnungen (`LogTranslateMessage()`,
  siehe oben "MyMemorys quotaFinished" und die Anbieter-Fehlermeldungen)
  erschienen dort bisher grau als generischer "Custom"-Eintrag mit einem
  Text-Präfix "[FEHLER]"/"[WARNUNG]", statt in der eigentlich vorgesehenen
  roten/gelben Farbcodierung für "Error"/"Warning". Grund: die global
  aufgerufene `IPS_LogMessage()`-Funktion kennt gar keinen Schweregrad-
  Parameter - nur die von `IPSModule` geerbte, INSTANZ-gebundene
  `LogMessage($Message, $Type)`-Methode (mit `KL_ERROR`/`KL_WARNING`) liefert
  die echte Farbcodierung, wurde hier aber bewusst gemieden, weil sie
  nachweislich abstürzt, sobald sie aus dem `MessageSink()`/`VM_UPDATE`-
  Ausführungskontext heraus aufgerufen wird (siehe Build 54). Seit Build 63
  unterscheidet die Instanz beide Kontexte: außerhalb von `MessageSink()`
  (Rescan, "Übersetzungsanbieter prüfen", `ApplyChanges()`, ...) wird jetzt
  die typisierte `LogMessage()`-Methode verwendet - Fehler erscheinen dadurch
  korrekt rot als "Error", Warnungen gelb als "Warning". Nur innerhalb der
  einen bekannten Absturz-Situation (`MessageSink()`/`VM_UPDATE`) greift
  weiterhin die alte, sichere `IPS_LogMessage()`-Variante mit Text-Präfix,
  da dort echte Instabilität nachgewiesen wurde.
* **Build 62 behebt zwei live gefundene Bugs:** (1) **Der eigentlich
  schwerwiegendere:** Der kostenfreie Anbieter (MyMemory) meldet ein
  erschöpftes Tageskontingent NICHT über einen HTTP-Fehlercode wie Google/
  DeepL, sondern ausschließlich über das JSON-Feld `quotaFinished` bei
  weiterhin HTTP 200 - `DetectRateLimitCooldown`/`RecordProviderPaused`
  (siehe oben) wurden dadurch für diesen Fall bisher NIE ausgelöst: 'free'
  blieb in der Panel-Übersicht dauerhaft als "nicht pausiert" sichtbar,
  obwohl JEDER weitere Versuch für den Rest des Tages ebenfalls scheiterte.
  Live beobachtet: Google/DeepL waren bereits (durch intensives Testen)
  pausiert, MyMemory zusätzlich fälschlich als verfügbar geführt - jeder
  weitere Rescan-Versuch schlug dadurch für ALLE drei Anbieter fehl, ohne
  dass die Instanz das als Pause/Fehler erkannte oder meldete: Rescan lief
  zwar technisch durch, aber ohne jede neue Übersetzung und ohne
  Statusänderung - wirkte dadurch nach außen wie "Rescan tut gar nichts".
  MyMemorys `quotaFinished` löst jetzt direkt die volle Tagessperre aus,
  genau wie ein per HTTP erkanntes Tageskontingent bei Google/DeepL. (2) Die
  in Build 60/61 eingeführte Pro-Stunde-Hochrechnung des Nutzungs-Zählers
  konnte kurz nach der Inbetriebnahme (oder nach einem kurzen Anfragen-
  Ansturm, z. B. über den "Übersetzungsanbieter prüfen"-Button) eine Rate
  zeigen, die HÖHER als der tatsächliche Gesamtzähler war (z. B. "1698
  Anfragen/h" bei nur "783 Anfragen insgesamt", da erst 28 Minuten seit
  Inbetriebnahme vergangen waren) - wirkte wie ein Rechenfehler, war aber
  nur eine Hochrechnung aus einem sehr kurzen Zeitfenster. Die Rate wird
  jetzt auf mindestens eine volle Stunde gedeckelt: innerhalb der ersten
  Stunde nach Inbetriebnahme zeigt sie exakt den bisherigen Gesamtwert (nie
  mehr), erst danach weicht sie als echte Rate vom Gesamtwert ab.
* **Build 61** ergänzt den Nutzungs-Zähler aus Build 60 um eine zweite
  Statistik und einen neuen Diagnose-Button: (1) Zusätzlich zu den
  tatsächlich gestellten Anfragen zählt die Instanz jetzt auch mit, wie viele
  Übersetzungsanfragen und Zeichen dank des Caches (siehe oben,
  `GetCachedTranslation`/`StoreCachedTranslation`) gar nicht erst an einen
  Anbieter geschickt werden mussten - als reine Gesamtsumme seit
  Inbetriebnahme (keine Pro-Stunde-Rate wie beim Hauptzähler), direkt
  angehängt an den Statistik-Satz unter der Erläuterung des "Aktiv"-Schalters
  im Konfigurationsformular. Für eigene Kacheln stehen dafür zwei weitere
  Platzhalter bereit, `<!--COUNT_CACHE_TRANSLATIONS-->` und
  `<!--COUNT_CACHE_SIGNES-->` (ebenfalls reine Ganzzahl ohne Einheit), siehe
  [Abschnitt 7](#7-visualisierung). (2) Ein neuer Button "Übersetzungsanbieter
  prüfen" ganz unten im Konfigurationsformular schickt eine einzelne
  Testanfrage ("Testabfrage" -> Englisch) DIREKT an jeden gerade
  eingerichteten Anbieter (Google/DeepL, falls ein API-Key eingetragen ist,
  sowie immer MyMemory) - bewusst am Cache vorbei (immer eine echte, frische
  Antwort) und unabhängig von einer eventuell laufenden Pause (die würde
  einen normalen Übersetzungsversuch sonst überspringen, hier soll aber
  gerade geprüft werden, ob der Anbieter TROTZ Pause inzwischen wieder
  funktioniert). Meldet für jeden Anbieter einzeln zurück, ob die Antwort
  angekommen ist, und beendet dabei automatisch eine noch laufende Pause,
  sobald ein Anbieter wieder erfolgreich antwortet - nützlich z. B. direkt
  nach einem Kontingent-/Abo-Upgrade beim Anbieter, ohne auf das
  automatische Ablaufen der (ggf. bereits mehrfach eskalierten, siehe Build
  57) Pause warten zu müssen.
* **Build 60** ergänzt drei Wünsche und behebt einen weiteren, unabhängigen
  Datenverlust-Bug: (1) Ein neuer/geänderter API-Key (Google/DeepL) oder eine
  geänderte MyMemory-Kontakt-E-Mail beendet die Pause des betroffenen
  Anbieters jetzt sofort, statt auf den Ablauf der ggf. bereits eskalierten
  Sperrfrist zu warten - erkannt über einen SHA-256-Hash der zuletzt
  gesehenen Zugangsdaten (nie der Klartext-Wert selbst); beim allerersten
  `ApplyChanges()` einer Instanz (noch kein Vergleichswert vorhanden) wird
  das nie fälschlich als Änderung gewertet. (2) Der rote Testphase-/
  Pausiert-Hinweis unter dem Dropdown ist jetzt mittig statt linksbündig
  ausgerichtet. (3) Ein neuer Nutzungs-Zähler zeigt die durchschnittliche
  Anzahl an Übersetzungsanfragen und übersetzten Zeichen pro Stunde seit der
  allerersten Einrichtung der Instanz (nicht seit der ersten tatsächlichen
  Übersetzung) - als Satz direkt unter der Erläuterung des "Aktiv"-Schalters
  im Konfigurationsformular (nur bei natürlichem Öffnen/Neuaufbau des
  Formulars berechnet, löst NIE einen erzwungenen Refresh aus - siehe
  unten), und optional (Checkbox "Übersetzungsstatistik in der Kachel
  anzeigen", standardmäßig aus) auch als kleiner Hinweistext in der Kachel
  selbst, dort alle 10 Minuten aktualisiert über `PushVisualizationUpdate()`
  (nicht über einen Formular-Reload). Für eigene Kacheln stehen dafür zwei
  Platzhalter bereit, siehe [Abschnitt 7](#7-visualisierung).

  **Zusätzlich behebt Build 60 einen weiteren, unabhängigen und
  schwerwiegenden Bug:** Der automatische Hintergrund-Rescan (Timer, siehe
  "Automatischer Rescan (Minuten)") teilte sich bisher denselben internen
  Code-Pfad wie der manuelle "Baum neu einlesen"-Button - inklusive dessen
  abschließendem `ReloadForm()`. Das bedeutete: war das
  Konfigurationsformular gerade geöffnet und wurden dort z. B. Übersetzungen
  von Hand bearbeitet, konnte der Hintergrund-Timer JEDERZEIT dazwischen-
  funken und das gesamte Formular neu laden - alle noch nicht mit
  "Übernehmen" gespeicherten Änderungen gingen dabei kommentarlos verloren.
  Seit Build 60 lädt ausschließlich der manuelle Button das Formular neu;
  der automatische Hintergrund-Rescan aktualisiert die Objektliste weiterhin
  normal im Hintergrund, rührt ein gerade geöffnetes Formular aber nicht
  mehr an.
* **Build 59 behebt dieselbe Fehlerklasse an zwei weiteren, deutlich
  schwerwiegenderen Stellen - dem eigentlichen DATENVERLUST-Bug dieser
  Serie:** sowohl `ReconcileRowSourceLanguageChanges()` (läuft für ALLE fünf
  Zeilen-Properties: Objektnamen, Eigene Texte, Beschriftungen, Automations,
  Begrüßung) als auch der `VM_UPDATE`-Live-Übersetzungspfad überschrieben eine
  bereits vorhandene, funktionierende Übersetzungsspalte bedingungslos mit dem
  Ergebnis eines erneuten Übersetzungsversuchs - auch dann, wenn dieser wegen
  einer pausierten/ausgefallenen Anbieter-Kette leer zurückkam. Live
  beobachtet: nach einer längeren Pause-Phase waren in "Objektnamen" und
  teilweise "Eigene Texte"/"Beschriftungen"/"Automations" sämtliche
  Zielsprachen-Spalten leer, obwohl vorher funktionierende Übersetzungen
  vorhanden waren - nur "Original-Import" blieb erhalten. Seit Build 59 wird
  eine bestehende Spalte bei einem fehlgeschlagenen Übersetzungsversuch NIE
  mehr überschrieben (die zuletzt bekannte gute Übersetzung bleibt stehen,
  bis ein neuer Versuch tatsächlich erfolgreich war), und die interne
  Buchführung wird nur bei VOLLSTÄNDIGEM Erfolg aller Zielsprachen
  fortgeschrieben - schlägt auch nur eine einzige fehl, bleibt die betroffene
  Zeile für einen späteren erneuten Versuch vorgemerkt, statt fälschlich als
  "erledigt" zu gelten. Zusätzlich überspringt `ApplyChanges()` den kompletten
  Quellsprachen-Abgleich jetzt schon im Vorfeld, solange die Anbieter-Kette
  komplett pausiert ist (siehe oben) - der zugehörige interne Fingerprint
  bleibt dabei bewusst unverändert, damit der Abgleich zuverlässig nachgeholt
  wird, sobald mindestens ein Anbieter wieder verfügbar ist. Der eigentliche
  Übersetzungs-Cache (`GetCachedTranslation`/`StoreCachedTranslation`, siehe
  Abschnitt 1) war von diesem Bug nie betroffen - ein fehlgeschlagenes/leeres
  Ergebnis wurde dort schon immer bewusst NICHT zwischengespeichert (siehe
  `TranslateBatch`: nur tatsächlich übersetzte, nicht-leere Ergebnisse landen
  im Cache), sodass ein erneuter Versuch für denselben Text nie an einem
  fälschlich gecachten leeren Ergebnis scheitert.
* **Build 58 behebt den bisher schwerwiegendsten Fund dieser Reihe:** ein
  HTML-Widget (z. B. das Wetter-Beispiel aus Abschnitt 1) konnte bei
  pausierter/fehlgeschlagener Übersetzung komplett LEER erscheinen - Struktur
  (Rahmen, Icons, Tagesüberschriften) intakt, aber jeder einzelne dynamische
  Wert (Prozentzahlen, Windgeschwindigkeit, Temperaturen) leer, statt wie
  erwartet auf die unübersetzte Originalsprache zurückzufallen. Ursache: die
  Text-Knoten-Zerlegung für HTML-Inhalte (siehe Abschnitt 7, Build 47) setzte
  ein leeres Übersetzungsergebnis je Knoten direkt in die wiederzusammengesetzte
  HTML-Struktur ein, statt bei einem fehlgeschlagenen/pausierten
  Übersetzungsversuch auf den unübersetzten Original-Knoten zurückzufallen -
  betraf dadurch praktisch jedes mehrsprachige HTML-Widget, sobald auch nur
  EIN einzelner Übersetzungsversuch fehlschlug (nicht nur während einer
  Pause). Zusätzlich zeigte der kleine rote "Übersetzung pausiert bis"-Hinweis
  unter dem Dropdown teils nur die Uhrzeit ohne den Text davor (derselbe
  Grundfehler: ein leeres statt eines fehlenden Übersetzungsergebnisses wurde
  nicht als "fehlgeschlagen, bitte Original verwenden" erkannt).
* **Build 57** korrigiert zwei live beobachtete Inkonsistenzen aus Build 55/56:
  (1) Googles "User Rate Limit Exceeded" enthält keines der Tageskontingent-
  Schlüsselwörter und bekam daher immer nur die kurze 15-Minuten-Basissperre -
  blieb der Fehler aber über Stunden bestehen, führte das zu einem
  "Flackern" (Google fiel nach jeder abgelaufenen Sperre wieder aus der
  Pause-Übersicht heraus, obwohl der Fehler weiter auftrat). Jeder ERNEUTE
  Fehlschlag ohne zwischenzeitlichen Erfolg verdoppelt die Sperrdauer jetzt
  automatisch (15min, 30min, 1h, ... gedeckelt auf 24h) - ein tatsächlich nur
  kurz blockierter Anbieter erholt sich weiterhin schnell, ein andauernd
  fehlschlagender wandert automatisch Richtung Tagessperre. (2) Der
  Instanz-Status "Aktiv, aber pausiert" wurde bisher nur GESETZT, wenn gerade
  tatsächlich ein Übersetzungsversuch lief - fand seitdem keiner mehr statt,
  blieb die Statuszeile veraltet stehen, obwohl die Panel-Übersicht (die
  immer frisch berechnet wird) bereits alle Anbieter als pausiert zeigte.
  Seit Build 57 wird der Status zusätzlich bei jedem Öffnen des
  Konfigurationsformulars und bei jedem "Übernehmen" neu bewertet.
* **Build 55** führt die automatische Pause bei Rate-Limit/Tageskontingent ein. Meldet ein
  einzelner Übersetzungsanbieter ein Rate-Limit oder ein aufgebrauchtes
  Tageskontingent (erkannt an HTTP 429/456 bzw. HTTP 403 mit "rate limit" in
  der Antwort), wird genau dieser eine Anbieter für eine gewisse Zeit
  automatisch pausiert (kurze Sperre bei einem reinen Burst-Limit, 24h bei
  einem erkannten Tageskontingent) - er wird währenddessen nicht erneut
  angefragt, die übrigen konfigurierten Anbieter werden aber normal
  weiterversucht. Melden ALLE konfigurierten Anbieter gleichzeitig ein
  Limit (bei nur einem konfigurierten Anbieter genügt bereits dieser eine),
  lohnt sich kein weiterer Versuch mehr - die Instanz pausiert dann komplett
  bis zum frühesten Reset-Zeitpunkt: kein einziger weiterer API-Aufruf, bis
  mindestens ein Anbieter wieder verfügbar sein sollte. Sichtbar an drei
  Stellen: ein kleiner roter Hinweis "Übersetzung pausiert bis HH:MM" direkt
  unter dem Dropdown in der Kachel (live in die jeweils aktive Gast-Sprache
  übersetzt), der Instanz-Status "Aktiv, aber pausiert", und eine detaillierte
  Aufschlüsselung (welcher Anbieter pausiert bis wann) im Panel
  "Übersetzungsanbieter" des Konfigurationsformulars. Ein ungültiger/
  abgelaufener API-Key oder ein Netzwerkfehler lösen dagegen NIE eine Pause
  aus (die würde sich ja nie von selbst erledigen) - nur ein tatsächlich als
  Rate-Limit/Kontingent erkannter Fehler.
* **Build 54**
  korrigiert dabei einen Fehler in Build 53 selbst: die von IPSModule geerbte
  `LogMessage()`-Methode löste, aus dem über `MessageSink()`/`VM_UPDATE`
  erreichbaren Übersetzungs-Fehlerpfad heraus aufgerufen, zuverlässig eine
  "InstanceInterface is not available"-Warnung aus (die Methode scheint eine im
  MessageSink-Ausführungskontext nicht existierende Interface-Instanz
  vorauszusetzen) - seit Build 54 wird stattdessen die kontextunabhängige globale
  `IPS_LogMessage()`-Funktion verwendet.
* **Build 53** behebt einen internen Fehler, der diesen Live-Pfad zusätzlich
  bei JEDER Aktualisierung einer verfolgten Variable einen kompletten,
  eigentlich nur nach einer Quellsprachen-Änderung nötigen Zeilen-Abgleich
  (siehe "Quellsprache: pro Zeile individuell änderbar" in Abschnitt 7)
  erneut anstoßen und dabei in kurzer Zeit die Tageskontingente mehrerer
  Übersetzungsanbieter gleichzeitig aufbrauchen konnte - seit Build 53 läuft
  dieser Abgleich nur noch, wenn sich seit dem letzten Mal tatsächlich eine
  Quellsprache geändert hat (Fingerprint-Vergleich, kein API-Aufruf).
  Fehlerdetails zu jedem fehlgeschlagenen Übersetzungsversuch (welcher
  Anbieter, HTTP-Code, Antwort) landen seit Build 53 zusätzlich im normalen
  Symcon-Meldungen-Log der Instanz (nicht mehr nur im Debug-Panel).
