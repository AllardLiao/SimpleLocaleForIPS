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
  "Eigene Texte" (und einer im Modus "Variable" verlinkten Begrüßungs-
  Variable, siehe Abschnitt 7) verfolgten String-Variablen vornehmen (z. B.
  ein Wetter- oder Messwert-Modul, das seinen Text bei jeder Aktualisierung
  selbst neu schreibt, oder eine mehrmals täglich zwischen festen Werten
  wechselnde Begrüßung): der neue Wert wird automatisch als frischer
  Rohtext übernommen und sofort **in alle konfigurierten Zielsprachen**
  nachübersetzt (nicht nur die gerade aktive) - schaltet ein Gast danach in
  eine andere Sprache, bekommt er also ebenfalls sofort die aktuelle
  Übersetzung zu sehen, nicht den unübersetzten Rohtext oder eine veraltete,
  vor der Änderung gecachte Fassung. Ganz ohne Zutun des anderen
  Modulentwicklers. Technisch über Symcons VariableManager-Update-
  Nachrichten (`VM_UPDATE`), siehe [Abschnitt 2](#2-bekannte-einschränkungen)
  für die eine Voraussetzung dabei.

  Damit das bei häufigen Änderungen nicht bei jedem Update erneut komplett
  gegen Google/DeepL/den kostenfreien Anbieter übersetzt werden muss, führt
  Simple Locale einen internen Übersetzungs-Cache (Schlüssel:
  Quellsprache + Zielsprache + Text). Kommt exakt derselbe Text in
  derselben Sprachrichtung erneut vor - z. B. eine Begrüßungs-Variable, die
  sich mehrmals täglich zwischen einer Handvoll fester Werte ("Guten
  Morgen"/"Guten Tag"/"Guten Abend"/"Gute Nacht") abwechselt -, wird ab dem
  zweiten Auftreten die bereits vorhandene Übersetzung wiederverwendet,
  **ganz ohne erneuten API-Aufruf**. Der Cache ist auf die letzten 500
  Einträge begrenzt (älteste zuerst raus) und wird auch von normalen
  Rescans mitgenutzt. Besonders spürbar bei einer per VM_UPDATE verfolgten
  Kachel wie einem Wetter-Widget: bei ca. 20 einzelnen Textbausteinen
  (Wochentage, Vorhersage, Sonnenauf-/-untergang, aktuelle Messwerte) und
  einem Update alle 10 Minuten wären das ohne Cache über 2.700
  Übersetzungsaufrufe pro Tag und Zielsprache - mit Cache nur noch die
  paar Werte, die sich tatsächlich bei jedem Update ändern (z. B.
  Temperatur, Wetterlage, Luftfeuchte), grob geschätzt 70-75 % weniger.
  Wie viele Anfragen/Zeichen dadurch konkret eingespart wurden, zeigt seit
  Build 61 der Nutzungs-Zähler im Konfigurationsformular (siehe
  [Abschnitt 2](#2-bekannte-einschränkungen)) als reine Gesamtsumme seit
  Inbetriebnahme.
  Zeigt ein Text weiterhin hartnäckig eine
  veraltete/falsche Übersetzung, hilft der Button "Übersetzungs-Cache
  leeren" im Formular - er löscht nur diesen internen Zwischenspeicher,
  nicht die bereits in Objektnamen/Eigenen Texten/Beschriftungen
  gespeicherten Übersetzungen selbst (die müssen bei Bedarf weiterhin
  einzeln im jeweiligen Feld geleert werden, damit sie beim nächsten
  Rescan/Sprachwechsel neu übersetzt werden).
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
  vorausgesetzt, sie werden in der konfigurierten Scan-Sprache geschrieben.**
  Schreibt ein *anderes* Modul oder Skript den Wert einer verfolgten
  String-Variable neu (z. B. ein Wetter-Skript bei seinem nächsten
  Aktualisierungsintervall), übersetzt Simple Locale live nach (siehe
  [Abschnitt 1](#1-funktionsumfang)) - dabei wird angenommen, dass der neue
  Wert in der Scan-Sprache verfasst ist, genau wie beim ursprünglichen Scan.
  Schreibt das fremde Modul tatsächlich in einer anderen Sprache, fällt die
  automatische Übersetzung entsprechend falsch aus (wie bei jeder
  automatischen Übersetzung, siehe unten) - Scan-Sprache in der
  Modul-Konfiguration also passend zur tatsächlich verwendeten Sprache des
  überwachten Moduls wählen. Beobachtet werden ausschließlich die Variablen
  unter "Eigene Texte" - Objektnamen ändern sich durch Fremdzugriffe
  praktisch nie und werden daher nicht überwacht.

  Dieser Punkt ist auch direkt in der Kachel über das Info-Symbol (ⓘ)
  neben dem Dropdown einsehbar, live in der jeweils aktiven Sprache.
* **Sehr häufig aktualisierte Variablen können viele API-Aufrufe erzeugen.**
  Jede externe Aktualisierung einer verfolgten "Eigene Texte"-Variable löst
  eine eigene Live-Nachübersetzung aus (siehe oben) - bei einer Variable, die
  sich mehrmals pro Minute ändert (z. B. ein sehr aktives Sensor-/Wetter-Skript),
  kann das entsprechend oft passieren. Ein interner Fehler (behoben mit Build 53)
  ließ diesen Live-Pfad zusätzlich bei JEDER dieser Aktualisierungen einen
  kompletten, eigentlich nur nach einer Quellsprachen-Änderung nötigen
  Zeilen-Abgleich (siehe "Quellsprache: pro Zeile individuell änderbar" in
  Abschnitt 7) erneut anstoßen und dabei in kurzer Zeit die Tageskontingente
  mehrerer Übersetzungsanbieter gleichzeitig aufbrauchen können - seit Build 53
  läuft dieser Abgleich nur noch, wenn sich seit dem letzten Mal tatsächlich
  eine Quellsprache geändert hat (Fingerprint-Vergleich, kein API-Aufruf). Für
  genau diesen Fall (Anbieter melden Rate-Limits/aufgebrauchte Kontingente)
  gibt es außerdem den Notaus-Schalter "Aktiv" (siehe Konfigurationstabelle
  oben) - sofort per Formular umschaltbar, kein Warten auf ein Modul-Update
  nötig. Fehlerdetails zu jedem fehlgeschlagenen Übersetzungsversuch (welcher
  Anbieter, HTTP-Code, Antwort) landen seit Build 53 zusätzlich im normalen
  Symcon-Meldungen-Log der Instanz (nicht mehr nur im Debug-Panel). **Build 54**
  korrigiert dabei einen Fehler in Build 53 selbst: die von IPSModule geerbte
  `LogMessage()`-Methode löste, aus dem über `MessageSink()`/`VM_UPDATE`
  erreichbaren Übersetzungs-Fehlerpfad heraus aufgerufen, zuverlässig eine
  "InstanceInterface is not available"-Warnung aus (die Methode scheint eine im
  MessageSink-Ausführungskontext nicht existierende Interface-Instanz
  vorauszusetzen) - seit Build 54 wird stattdessen die kontextunabhängige globale
  `IPS_LogMessage()`-Funktion verwendet.
* **Automatische Pause bei Rate-Limit/Tageskontingent (Build 55).** Meldet ein
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

  **Build 57** korrigiert zwei live beobachtete Inkonsistenzen aus Build 55/56:
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

  **Build 58 behebt den bisher schwerwiegendsten Fund dieser Reihe:** ein
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

  **Build 59 behebt dieselbe Fehlerklasse an zwei weiteren, deutlich
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

  **Build 60** ergänzt drei Wünsche und behebt einen weiteren, unabhängigen
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

  **Build 61** ergänzt den Nutzungs-Zähler aus Build 60 um eine zweite
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

  **Build 62 behebt zwei live gefundene Bugs:** (1) **Der eigentlich
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

  **Build 63 korrigiert die Farbcodierung im Symcon-"Meldungen"-Log (englisch
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

  **Build 64 behebt eine fehlende Ein-/Mehrzahl-Behandlung im
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

  **Build 65 behebt den bisher schwerwiegendsten Fund dieser gesamten
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

  **Build 66 schließt dieselbe Lücke zusätzlich im Übersetzungs-Cache:**
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

  **Build 67 behebt eine Konsolensprachen-Einschränkung, die zwei
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

  **Build 68 rundet die Build-67-Umstellung ab:** Live beobachtet blieben
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

  **Build 69 behebt einen unsichtbaren Zeichen-Artefakt aus MyMemory:** Live
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

  **Build 70 übersetzt live nur noch die aktuell aktive Gast-Sprache, holt
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

  **Build 71 entkoppelt die Live-Übersetzung von der Formular-Persistierung
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

  **Wichtige Einschränkung dieses Schutzes:** Er bewahrt nur den externen
  Puffer selbst davor, verworfen zu werden - er schützt NICHT eine eigene,
  gleichzeitige Bearbeitung DERSELBEN Zelle. Beim "Übernehmen" liest
  `ApplyChanges()` die gerade abgeschickten Zeilen (bereits mit dem eigenen
  frischen Wert) und überschreibt darin gezielt genau die Felder, die im
  Puffer stehen (Rohtext + Übersetzung der aktiven Sprache), mit dem
  ÄLTEREN, gepufferten externen Wert. Bearbeitet man eine andere Zeile oder
  eine andere Spalte derselben Zeile (z. B. eine nicht-aktive Zielsprache),
  bleibt die eigene Änderung unangetastet. Korrigiert man dagegen manuell
  genau die Zelle, die gerade auch extern (per `VM_UPDATE`) aktualisiert und
  gepuffert wird, gewinnt beim Speichern der ältere externe Wert - die
  eigene, frisch eingetippte Korrektur geht dabei ersatzlos verloren.
  Bei einer häufig extern aktualisierten Variable also am besten zügig
  speichern bzw. genau diese Zelle meiden, solange der Hinweis oben im
  Formular eine anstehende Persistierung anzeigt.

  **Build 72 macht den Übersetzungs-Cache treffsicherer und größer:** Bisher
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

  **Build 73 stellt klar, dass "nur aktive Sprache" ausschließlich für den
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

  **Build 74 behebt eingeschleuste Platzhalter-Tags bei DeepL-Übersetzungen
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

  **Build 75 fasst inhaltlich identische Beschriftungen ohne geteiltes
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

  **Build 76 ergänzt "Aufräumen": verwaiste Zeilen künftig per Klick statt
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

  **Build 77 behebt eingefrorene deutsche Gast-Hinweise nach einer
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
  Name `ShowGlobeIcon` und CSS-Klasse `ipssl-globe` bleiben aus
  Kompatibilitätsgründen unverändert - siehe Abschnitt 7 für eigene,
  darauf aufbauende Kachel-Anpassungen).

  **Build 78 macht die festen Gast-Oberflächentexte komplett unabhängig von
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

  **Build 79 behebt eine Lücke bei unterschiedlichen Quellsprachen: die
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

  **Build 80 behebt zwei Nachbesserungen an Build 79, die erst beim
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

  **Build 81 behebt zwei weitere Anzeige-Lücken, die erst nach Build 80 im
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

  **Build 82, auf Nutzer-Wunsch: die Spalte der Quellsprache bleibt beim
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

  **Build 83, auf Nutzer-Wunsch: das Panel "Übersetzungsanbieter" spiegelt
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

  **Build 84 behebt zwei weitere, live gefundene Probleme.** Erstens war
  das Simple-Locale-Symbol nach Build 82 auf manchen Kacheln sichtbar
  GRÖSSER als das Dropdown, nicht exakt gleich hoch: die Höhenanpassung
  lief über `align-self: stretch`, was das Icon auf die Höhe der GESAMTEN
  Zeile (`.ipssl-select-row`) skalierte - in der echten Kachel-Darstellung
  bekommt diese Zeile aber offenbar mehr Höhe zugewiesen, als das Dropdown
  selbst braucht, wodurch auch das Icon zu groß wurde. Gelöst über eine
  gemeinsame, feste CSS-Variable (`--ipssl-control-height`), die Dropdown
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

  **Build 85, auf Nutzer-Wunsch: die eigenen Gast-Oberflächentexte (siehe
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

  **Qualitäts-Hinweis:** für die fünf Testphasen-Sprachen (is/cy/zu/mi/la)
  gibt es keine Konsolensprachen-Referenz zum Abgleich, und die
  Übersetzungsqualität für diese seltener unterstützten Sprachen -
  insbesondere Zulu und Māori - ist spürbar weniger zuverlässig
  einzuschätzen als für die verbreiteten Sprachen. Vor produktivem
  Live-Einsatz wird eine Prüfung durch Muttersprachler empfohlen. Diese
  Zeilen sind (wie alle `propertyOwnUiTexts`-Zeilen) bewusst NICHT über das
  Konfigurationsformular editierbar - eine Korrektur kann aktuell nur über
  ein künftiges Modul-Update erfolgen.

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

  **Build 86 behebt einen Bug in Build 85, live gefunden: eine bereits als
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

  **Build 87 behebt zwei weitere, live gefundene Probleme und dokumentiert
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

  **Bekannte, strukturelle Einschränkung (dokumentiert, kein Code-Fix
  möglich): live per VM_UPDATE nachübersetzte "Eigene Texte" können für
  einen kurzen Moment unübersetzt sichtbar sein**, wenn eine externe
  Quelle (z. B. ein Wetter-/Sensor-Modul) denselben Wert schreibt, den es
  auch selbst anzeigt (Objekt-ID = Wert-Objekt-ID, der Normalfall ohne
  gesonderte Anzeige-Variable). Symcons WebFront pusht JEDEN
  Schreibvorgang sofort an verbundene Gast-Browser - inklusive des
  externen Rohtext-Schreibvorgangs selbst, BEVOR Simple Locale reagieren
  und die Übersetzung zurückschreiben kann. Wie lange dieses Fenster
  offen bleibt, hängt direkt von der Antwortzeit des jeweiligen
  Übersetzungsanbieters ab (spürbar kürzer bei einem Cache-Treffer, siehe
  `TranslateBatch`/`GetCachedTranslation`, ansonsten vom echten
  API-Antwortverhalten abhängig) - keine feste, garantierbare Ober- oder
  Untergrenze. Wer das vollständig ausschließen möchte, kann dem
  betroffenen "Eigene Texte"-Eintrag eine EIGENE, separate
  Anzeige-Variable zuweisen (`ValueObjectID` abweichend von der
  eigentlich getrackten Quellvariable) und das anzeigende Widget auf
  diese umstellen - dann schreibt die externe Quelle nie direkt in eine
  gast-sichtbare Variable, nur Simple Locales bereits übersetztes
  Ergebnis erreicht sie.
* **Die automatische Übersetzung kann trotzdem Fehler machen.** Google
  Translate liefert nicht immer eine passende Übersetzung. (Ein früherer,
  strukturell inzwischen ausgeschlossener Fall: Google erkannte bei der
  kurzen Übersetzung ohne fest vorgegebene Scan-Sprache "Haus" fälschlich
  als Hmong und lieferte "Trinken" - deshalb wird die Scan-Sprache inzwischen
  immer fest vorgegeben, siehe [Abschnitt 7](#7-visualisierung).) **Alle
  Übersetzungen in "Objektnamen" und "Eigene Texte" daher nach dem ersten
  Rescan einmal durchsehen** und falsch übersetzte Zellen manuell
  korrigieren - eigene Korrekturen werden nie automatisch überschrieben
  (siehe Abschnitt 5). Soll eine bereits gefüllte Zelle stattdessen neu von
  Google übersetzt werden: Zelleninhalt löschen, "Übernehmen" klicken, dann
  erneut Rescan ausführen - nur leere Zellen werden dabei (neu) übersetzt.
  Für die per VM_UPDATE live nachübersetzten "Eigene Texte" (siehe oben)
  greift diese manuelle Korrektur allerdings nicht dauerhaft: eine erneute
  Wertänderung überschreibt die Übersetzung sofort wieder frisch - eine
  Ausnahme sind die deutschen Wochentags-Kürzel Mo/Di/Mi/Do/Fr/Sa/So
  (häufig in Wetter-Widgets), die für Englisch/Spanisch/Französisch/
  Italienisch/Niederländisch/Portugiesisch als Zielsprache fest und
  garantiert korrekt hinterlegt sind, statt der oft unzuverlässigen
  automatischen Übersetzung zu überlassen (isoliert ohne Kontext ist z. B.
  "So" genauso das deutsche Wort "so" wie die Abkürzung für "Sonntag").
  Greift nur, wenn mindestens 4 dieser Kürzel unmittelbar hintereinander im
  selben Text vorkommen (wie bei einer echten Wochentagsliste) - ein
  einzelnes, isoliertes Kürzel (z. B. eine Windrichtungsangabe "SO" für
  Süd-Ost) bleibt davon unberührt und läuft weiterhin ganz normal durch
  die konfigurierte Übersetzungskette.
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
  ist geteilt). Beim Zurückwechseln auf die Scan-Sprache wird der Fork **pro
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
Aktiv                           | Notaus-Schalter (Standard-Konvention "Instanz aktiv"). Deaktiviert **sofort** jeden Rescan (manuell/automatisch), jede Live-Nachübersetzung (`VM_UPDATE`) und jeden Quellsprachen-Abgleich (siehe unten) - z. B. um ein aufgebrauchtes Tageskontingent oder ein Rate-Limit bei einem Übersetzungsanbieter sofort zu stoppen, ohne auf ein neues Modul-Update warten zu müssen. Bereits vorhandene Übersetzungen bleiben nutzbar, die Sprachumschaltung in der Kachel funktioniert normal weiter - es wird nur nichts NEUES mehr übersetzt. Instanz-Status zeigt währenddessen "Deaktiviert". Default: an.
Kachel-Visualisierung           | Instanz der eingebauten Kachel-Visualisierung (WebFront-Kernmodul, nicht Teil von Simple Locale). **Pflichtfeld** - ihre eigene "Startkategorie" wird automatisch als Root der Visualisierung übernommen (Kategorie im Objektbaum, deren Inhalt - Namen + Werte von String-Variablen - übersetzt wird; sollte nur die Sichtbereich-Kacheln enthalten, nicht die Admin-Oberfläche) und sie liefert zusätzlich die Automations/Favoriten-Übersetzung, siehe eigenen Absatz unten. Ohne Auswahl (oder ohne gültige Startkategorie) bleibt die Instanz im Status "Root der Visualisierung fehlt".
Scan-Sprache                    | Sprache, in der die Objektnamen/-werte ursprünglich gepflegt sind. Dient Google Translate als feste Quellsprache für alle Zielsprachen-Übersetzungen; die Scan-Sprache selbst hat keine eigene Übersetzungsspalte, siehe [Abschnitt 7](#7-visualisierung).
Aktuell aktive Sprache          | Welche Sprache gerade angezeigt wird - normalerweise über die Kachel vom Anwender selbst gesteuert (siehe Abschnitt 7), lässt sich hier aber auch manuell umschalten (inkl. aller sonst nur bei einem Kachel-Wechsel ausgelösten Umbenennungen/Wertänderungen - identisches Verhalten zur Kachel selbst). **Wichtig für die eigene Weiterentwicklung der Visualisierung, siehe Warnung unten.**
Simple-Locale-Symbol in der Kachel anzeigen | Blendet das Simple-Locale-Symbol links neben dem Dropdown aus, falls nicht gewünscht (z. B. bei eigenem Kachel-Design). Bis Build 76 die 🌐-Emoji-Glyphe, ab Build 77 das eigentliche, als Base64-Grafik eingebettete Markensymbol (siehe Abschnitt 7). Standardmäßig an.
Info-Symbol in der Kachel anzeigen | Blendet das ⓘ-Symbol (Erklärung der Einschränkungen, siehe Abschnitt 2) aus. Standardmäßig an.
Eigene Sprachauswahl-Kachel verwenden | Unterdrückt die eingebaute Dropdown-Kachel zugunsten einer selbstgebauten (siehe Abschnitt 7). **Pro-Feature** (`custom_tile`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und die eingebaute Kachel aktiv.
Übersetzungsanbieter (Panel)    | Siehe eigenen Abschnitt unten ("Übersetzungsanbieter: Google/DeepL/kostenfrei") - funktioniert ab Werk ohne jede Eingabe.
Automatischer Rescan (Minuten)  | Intervall für automatisches Neu-Einlesen der Visualisierung, 0 = nur manuell über den Button "Visualisierung neu einlesen" (siehe unten). **Pro-Feature** (`auto_rescan`, siehe [Abschnitt 8](#8-lizenz-und-testversion)) - ohne dieses Feature bleibt das Feld ausgegraut und der Timer aus, der manuelle Rescan-Button bleibt aber in jeder Edition nutzbar.
Übersetzungen gelöschter Elemente in der Visualisierung entfernen | "Aufräumen" (Build 76, siehe eigenen Absatz unten "Aufräumen: verwaiste Zeilen endgültig entfernen") - entfernt dauerhaft Zeilen, die keinem Objekt in der aktuellen Visualisierung mehr zugeordnet werden können. In jeder Edition nutzbar.

**Quellsprache: pro Zeile individuell änderbar**

Die "Scan-Sprache" oben ist nur die VOREINSTELLUNG, die eine neu gefundene Zeile
beim ersten Scan erhält - jede Zeile in "Objektnamen", "Eigene Texte",
"Beschriftungen", "Automations" und "Begrüßung" trägt zusätzlich eine eigene,
editierbare Spalte **"Quellsprache"** (Pro-Feature `edit_translations`, ohne
dieses Feature nur informativ sichtbar). Das macht auch gemischtsprachige
Installationen sauber abbildbar - z. B. ein Fremdmodul, das seine eigenen
Objektnamen/-werte dauerhaft auf Englisch liefert, während der Rest der
Installation auf Deutsch gescannt wird: die betroffenen Zeilen bekommen einfach
"en" als eigene Quellsprache, unabhängig von der instanzweiten Scan-Sprache.

Wichtiger noch: das beantwortet die Frage "was passiert, wenn sich die
Scan-Sprache zwischen zwei Scans ändert?" Vor dieser Funktion (Build 51 und
früher) galt dafür ausschließlich die Warnung im nächsten Abschnitt (neue
Objekte immer nur bei aktiver Scan-Sprache anlegen) - eine bereits bestehende,
korrekt übersetzte Zeile ließ sich nicht sauber auf eine andere Quellsprache
umstellen. Jetzt genügt es, die "Quellsprache" EINER Zeile im Formular zu
ändern und auf "Übernehmen" zu klicken: alle Übersetzungsspalten dieser Zeile
werden automatisch geleert und **sofort** neu gegen die neue Quellsprache
übersetzt (kein Warten auf den nächsten Rescan/Sprachwechsel nötig) - ist die
gerade aktive Sprache betroffen, wird der neue Wert außerdem direkt live in
die Kachel geschrieben. Rohtext, Objekt-/Wert-Objekt-ID und Pfad bleiben dabei
unangetastet, nur die Übersetzungen selbst und die Quellsprache ändern sich.

**Übersetzungsanbieter: Google/DeepL/kostenfrei**

Simple Locale funktioniert **ab Werk ohne jede Konfiguration**: Ganz ohne eingetragenen API-Key läuft die Übersetzung sofort über einen kostenfreien, account-losen Anbieter ([MyMemory](https://mymemory.translated.net)). Google Cloud Translate und DeepL sind beide **optional** und lassen sich unabhängig voneinander zusätzlich eintragen (eigene Panels "Übersetzungsanbieter" im Konfigurationsformular):

- **Nur kostenfrei** (Auslieferungszustand): 5.000 Zeichen/Tag anonym, mit hinterlegter Kontakt-E-Mail (Feld "Kontakt-E-Mail für den kostenfreien Anbieter") 50.000 Zeichen/Tag. Kein Konto, kein Key.
- **Google und/oder DeepL zusätzlich eingetragen, volle Verkettung** (Pro-Feature `paid_providers`, siehe [Abschnitt 8](#8-lizenz-und-testversion)): bezahlte Anbieter werden VOR dem kostenfreien versucht (Reihenfolge über "Bevorzugter Anbieter" wählbar, falls beide eingetragen sind), beide bezahlten Anbieter kombiniert, falls beide konfiguriert - deutlich großzügigere Freikontingente/Qualität, aber jeweils ein eigenes Konto samt API-Key nötig.
- **Google und/oder DeepL eingetragen, ohne `paid_providers`** (z. B. "Light"-Edition): der kostenfreie Anbieter bleibt immer die primäre Grundausstattung, zusätzlich darf höchstens EIN einzelner bezahlter Anbieter als Rückfall danach greifen (nie beide gleichzeitig verkettet) - welcher, entscheidet "Bevorzugter Anbieter", falls beide eingetragen sind.
- **Ausfallsicher durch Verkettung**: Schlägt ein Anbieter fehl (Tageskontingent erschöpft, Preismodell geändert, Key abgelaufen, Netzwerkfehler), übernimmt automatisch der nächste in der jeweiligen Kette - der kostenfreie Anbieter steht dabei immer als garantiert verfügbares Glied bereit (mit `paid_providers` am Ende, ohne dieses Feature am Anfang). Die Übersetzung funktioniert dadurch strukturell auch dann noch, wenn Google/DeepL komplett ausfallen oder ihr Preismodell ändern sollten.
- **Anbieter gezielt prüfen (Build 61):** Der Button "Übersetzungsanbieter prüfen" ganz unten im Konfigurationsformular schickt eine einzelne Testanfrage direkt an jeden eingerichteten Anbieter (Google/DeepL, falls konfiguriert, sowie immer MyMemory) - am Cache vorbei und unabhängig von einer eventuell laufenden Pause, meldet also auch, ob ein eigentlich pausierter Anbieter inzwischen wieder geht. Eine noch laufende Pause wird dabei automatisch beendet, sobald ein Anbieter wieder erfolgreich antwortet - praktisch z. B. direkt nach einem Kontingent-/Abo-Upgrade beim Anbieter.

Ein Anbieterwechsel (z. B. Google zu DeepL) macht bereits gewählte Zielsprachen ungültig, wenn die neue Sprachliste andere Codes verwendet (Google: klein geschrieben "de"/"en", DeepL: teils groß geschrieben mit Regionsvariante "DE"/"EN-GB") - betroffene Zielsprachen müssen dann neu ausgewählt werden. Der kostenfreie Anbieter hat keinen eigenen Sprachlisten-Endpunkt und nutzt eine eingebaute, rund 25 Sprachen umfassende statische Liste.

Bewusst außerhalb von v1: Google und DeepL gleichzeitig für dieselbe Übersetzung anzufragen, um ihre Freikontingente zu kombinieren - die Kette probiert Anbieter nacheinander, nicht parallel. Der kostenfreie Anbieter (MyMemory) unterstützt zudem keinen Batch-Aufruf (ein Text pro Request, siehe `TranslateChunkFree`) und lehnt einzelne Texte über 500 Byte ab (z. B. vollständige HTMLBox-Widgets als "Eigene Texte") - solche Texte scheitern über diesen Anbieter bewusst früh und werden von der Kette an einen bezahlten Anbieter ohne diese Begrenzung weitergereicht, sofern einer konfiguriert ist.

**Übersetzung:**

Name                            | Beschreibung
-------------------------------- | ------------------
Zielsprachen                    | Sprachen, in die übersetzt werden soll. Auswahl-Optionen kommen ab Werk aus der eingebauten Liste, mit konfiguriertem Google-/DeepL-Key aus deren dynamisch geladener Liste (siehe oben). Ausgegraut, wenn ein bezahlter Anbieter konfiguriert ist, aber noch keine Liste laden konnte, oder wenn das Sprachlimit einer "Spezialversion"-Lizenz erreicht ist (siehe Abschnitt 8). Wichtig: Nach dem Klick auf "Sprachliste aktualisieren" die Instanzkonfiguration einmal schließen und neu öffnen, bevor Häkchen gesetzt werden - sonst kann die Konsole falsche Sprachen speichern.
Objektnamen / Eigene Texte / Beschriftungen | Listen der gefundenen Objekte mit Quelltext und je einer Spalte pro Zielsprache. Übersetzungen sind hier direkt editierbar; leere Zellen werden beim nächsten Rescan automatisch übersetzt. "Beschriftungen" siehe [Abschnitt 2](#2-bekannte-einschränkungen) (Fork-Mechanismus). **Hinweis:** Das Konfigurationsformular persistiert extern (per `VM_UPDATE`) automatisch geänderte Texte alle 12 Minuten, wenn es Änderungen gibt, was zu einem Refresh dieses Formulars führt - bitte speichere Deine eigene Arbeit rechtzeitig. Solange etwas ansteht, zeigt das Formular oben einen Hinweis mit der nächsten Refresh-Zeit an (siehe [Abschnitt 2](#2-bekannte-einschränkungen) für die genauen Details, was dabei geschützt ist und was nicht).
Automations                     | Liste der gefundenen Automation-Einträge der oben unter "Kachel-Visualisierung" gewählten Instanz mit Quelltext und je einer Spalte pro Zielsprache - funktioniert genauso wie Objektnamen.
Begrüßung                       | Übersetzt den Begrüßungstext der Kachel-Visualisierung, unabhängig davon, ob "Show Greeting" gerade "Automatic"/"Static" (freier Text, Feld "Name"/Property `GreetingName`) oder "Variable" (Live-Wert einer String-Variable) ist - beide landen in derselben einen Zeile hier, siehe eigenen Absatz unten. Ein Hinweistext direkt über der Liste zeigt an, welcher Modus gerade aktiv ist. Bei "Show Greeting" = "None" bleibt die Liste leer.

**Wann sollte ein Rescan ausgeführt werden?**

Inhaltstyp        | Neue/verschobene Objekte | Inhaltliche Änderungen
------------------ | ------------------------- | ------------------------
Objektnamen        | Nur per Rescan (manuell/Timer) erkannt. | Ändert sich ein Name selten spontan; falls doch, Zelle im Formular leeren + Rescan.
Eigene Texte (Werte) | Nur per Rescan erkannt. | Automatisch (siehe Abschnitt 1, `VM_UPDATE`) - **kein** Rescan nötig, solange die Scan-Sprache stimmt.
Begrüßung           | Nur per Rescan erkannt (auch ein Moduswechsel zwischen "Automatic"/"Static"/"Variable"). | Modus "Variable": automatisch, genau wie Eigene Texte (Werte). Modi "Automatic"/"Static" (freier Text im Feld "Name"): nur per Rescan.
Beschriftungen      | Nur per Rescan erkannt. | **Kein** automatisches Erkennen von Änderungen am zugrunde liegenden Profil/Template - Symcon liefert dafür keine Update-Benachrichtigung. Ändert ein anderes Modul/der Admin die Beschriftungen eines Profils, das eine bereits geforkte Variable nutzt, wird das erst nach manuellem Löschen der betroffenen Original-Import-Zelle + Rescan übernommen.

**Aufräumen: verwaiste Zeilen endgültig entfernen (Build 76)**

Ein Rescan (siehe Tabelle oben) erkennt zwar neue/verschobene Objekte, entfernt
aber **nie** von sich aus eine bereits vorhandene Zeile, auch wenn das
zugehörige Objekt inzwischen gelöscht oder aus der Visualisierung entfernt
wurde - das ist Absicht (siehe `MergeRows`/`MergeEnumerationOptions`/
`MergeAutomationRows`): eine versehentlich falsche oder unvollständige
"Kachel-Visualisierung"-Auswahl soll niemals bereits geleistete
Übersetzungsarbeit stillschweigend vernichten. Solche verwaisten Zeilen
sammeln sich über die Zeit an und mussten bisher manuell einzeln über das
Papierkorb-Symbol gelöscht werden.

Der Button **"Übersetzungen gelöschter Elemente in der Visualisierung
entfernen"** (unterhalb von "Visualisierung neu einlesen") macht genau das in
einem Schritt: er führt intern denselben frischen Scan wie ein Rescan durch
und löscht anschließend **alle** Zeilen in "Objektnamen", "Eigene Texte",
"Beschriftungen" und "Automations", die dabei nicht mehr gefunden wurden -
unwiederbringlich (regenerieren sich automatisch neu, falls das Objekt später
wieder auftaucht). Ein Popup nach dem Klick meldet, wie viele Zeilen entfernt
wurden. **"Begrüßung" ist bewusst ausgenommen** - anders als die anderen vier
Listen ist das keine aus dem Baum gescannte, wachsende Liste, sondern eine
einzelne, direkt konfigurierte Einstellung (siehe Abschnitt 1).

**Wichtig:** genau wie bei einem Rescan gilt auch hier - die "Kachel-
Visualisierung"-Auswahl oben muss wirklich die vollständige, korrekte
Visualisierung referenzieren, bevor "Aufräumen" geklickt wird. Anders als ein
normaler Rescan (der bei einer falschen Auswahl höchstens *nichts Neues*
findet) LÖSCHT "Aufräumen" bei einer falschen/unvollständigen Auswahl aktiv
noch gültige Übersetzungen. Im Zweifel vorher einmal die betroffenen Listen
durchsehen (die "Pfad"-Spalte hilft dabei) oder ein Backup der Instanz-
Konfiguration ziehen.

> **Wichtig beim Weiterentwickeln der Visualisierung: immer mit "Aktuell aktive
> Sprache" = Scan-Sprache (bzw. "Original") arbeiten, solange neue Objekte
> hinzugefügt werden.** Ein Rescan übernimmt für ein NEUES Objekt immer dessen
> gerade live vorhandenen Namen als "Original-Import" - er kann nicht wissen,
> ob dieser Name vom Admin frisch in der Scan-Sprache getippt wurde, oder ob
> er nur deshalb so aussieht, weil zuvor auf eine andere aktive Sprache
> umgeschaltet war (jeder Sprachwechsel - auch der manuelle über das
> Auswahlfeld oben - benennt alle bereits erfassten Objekte tatsächlich live
> um, siehe `ApplyLanguage()`). Wird also ein neues Objekt angelegt, während
> z. B. gerade Englisch aktiv ist, hält der Admin dessen Namen zwangsläufig
> auf Englisch - der nächste Rescan verbucht diesen Namen aber als Scan-Sprache
> und übersetzt ihn entsprechend falsch (u. a. auch zurück ins vermeintliche
> Original). Vor dem Anlegen neuer Objekte also immer erst über "Aktuell
> aktive Sprache" zurück auf die Scan-Sprache wechseln (oder gleich durchgehend
> in dieser Ansicht entwickeln) - bereits erfasste, bestehende Objekte sind
> davon nicht betroffen (deren Original-Import bleibt beim ersten Fund
> eingefroren, siehe oben), das betrifft ausschließlich neu hinzukommende.
>
> Für die **Begrüßung** (Modus "Variable") ist dasselbe Risiko inzwischen
> abgesichert: ein Rescan aktualisiert deren Original-Import nur noch dann,
> wenn dabei zuverlässig die Scan-Sprache aktiv ist - ist gerade eine
> Zielsprache aktiv, bleibt die Zeile unverändert stehen, statt die gerade
> live angezeigte Übersetzung fälschlich als neuen Rohtext zu übernehmen
> (auch für die bereits bekannte Zeile, die sonst nicht betroffen wäre). Der
> ohnehin bereits sichere Live-Pfad (`VM_UPDATE`, siehe oben) holt die
> Aktualisierung in diesem Fall automatisch nach, sobald die überwachte
> Variable das nächste Mal tatsächlich extern geschrieben wird.
>
> Diese ganze Warnung betrifft ausschließlich NEU hinzukommende Objekte - für
> bereits erfasste, bestehende Zeilen lässt sich die Quellsprache seit der
> pro-Zeile editierbaren "Quellsprache"-Spalte (siehe oben) jederzeit sauber
> nachträglich ändern, ohne dieses Risiko: eine Änderung der instanzweiten
> Scan-Sprache selbst wirkt sich auf bereits bekannte Zeilen gar nicht erst
> aus (deren eigene Quellsprache bleibt eingefroren, siehe oben) - erst eine
> gezielte, manuelle Änderung der "Quellsprache"-Zelle einer konkreten Zeile
> löst die automatische Neuübersetzung aus.

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

Das Dropdown bietet immer folgende Sprachen zur Auswahl: die Scan-Sprache und
alle konfigurierten Zielsprachen (Flagge, live übersetzter Name, Google-Code,
z. B. "🇬🇧 English - en"). Intern gibt es zusätzlich die Pseudo-Sprache
"Original" (liefert den rohen, unangetasteten Text, exakt so wie er im
Objektbaum vorgefunden wurde, Tippfehler inklusive) - im Dropdown erscheint
dafür aber **kein separater Eintrag** "Original (unbearbeitet)": Google Cloud
Translate lehnt eine Übersetzung von einer Sprache in sich selbst ohnehin ab
(HTTP 400 "Bad language pair"), es gibt für die Scan-Sprache also gar keine
eigene, separat übersetzte Spalte - ihr Inhalt ist identisch mit dem
Rohtext. Der Scan-Sprache-Eintrag im Dropdown liefert deshalb technisch
"Original", zeigt aber ganz normal die Scan-Sprache selbst an (z. B.
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

> Weg 1 unten richtet sich an Personen mit HTML-Kenntnissen und einem
> Grundverständnis der IP-Symcon-Kachel-Mechanik (`requestAction()`,
> `handleMessage()`) - er ist bewusst kein reiner Klick-Editor, sondern
> echter, selbst zu pflegender HTML-Code.

1. **Eigener HTML-Code für die eingebaute Kachel (empfohlen für die meisten
   Fälle).** Aktiviere dazu "Eigene Sprachauswahl-Kachel verwenden" im
   Konfigurationsformular - darunter erscheint der Button "Eigenen
   Kachel-HTML-Code bearbeiten" (nur sichtbar, solange die Checkbox aktiv
   ist, damit das Formular sonst nicht unnötig überladen wirkt). Er
   erscheint nicht sofort beim Setzen des Häkchens, sondern erst nach einem
   Klick auf "Übernehmen" (die Konsole baut das Formular dabei live neu auf -
   ein Schließen/Neuöffnen der Instanzkonfiguration ist hierfür anders als
   beim Sprachlisten-Hinweis weiter oben nicht nötig). **Diese Instanz
   liefert die Kachel weiterhin selbst aus** (`GetVisualizationTile()`) -
   nur eben mit dem editierten HTML/CSS/JS statt der eingebauten Optik.

   Das Bearbeiten-Fenster enthält zwei getrennte Felder:

   - **"HTML-Code"** - der äußere Rahmen (Layout/CSS), vorbefüllt mit einer
     1:1-Kopie der eingebauten `module.html`. Muss zwei Platzhalter
     enthalten, die bei jedem Laden der Kachel automatisch ersetzt werden:
     - `<!--WRAPPER_ID-->` - eine pro Instanz eindeutige DOM-ID, verhindert
       ID-Kollisionen, falls mehrere Kacheln im selben DOM landen. Kommt in
       der Standardvorlage **zweimal** vor (als `id`-Attribut des
       Wrapper-`<div>` UND im `getElementById(...)`-Aufruf im `<script>`) -
       beide Stellen müssen exakt gleich bleiben (einfach den Platzhalter
       selbst nicht anfassen, dann passt das automatisch).
     - `<!--LANGUAGE_SELECT-->` - wird durch den Inhalt des zweiten Felds
       (siehe unten) ersetzt.

     Beispiel, wie `<!--LANGUAGE_SELECT-->` standardmäßig gefüllt wird (rein
     zur Orientierung - dieser Block wird automatisch erzeugt, siehe
     `BuildLanguageSelectHtml()`, solange das zweite Feld unten leer bleibt):
     ```html
     <div class="ipssl-select-row">
       <span class="ipssl-globe" aria-hidden="true"><img src="data:image/png;base64,..." alt=""></span>
       <select onchange="requestAction('Language', this.value);">
         <option value="ORIGINAL_IMPORT">Deutsch</option>
         <option value="en" selected>English</option>
       </select>
       <span class="ipssl-info-icon" aria-hidden="true" onclick="alert('...');">ⓘ</span>
     </div>
     <!-- + roter Testphase-Hinweis, nur solange ungelizenziert und Testphase läuft -->
     ```
     Das `<img>` (Build 77) ist das Simple-Locale-Symbol
     (`libs/assets/module_icon_48.png`), als Base64-Data-URI eingebettet -
     kein öffentlicher Pfad/Webhook nötig. Für eine eigene Kachel kann hier
     stattdessen jedes beliebige eigene Icon/Emoji stehen, die CSS-Klasse
     `ipssl-globe` (Name aus historischen Gründen unverändert) liefert
     bereits einen passenden 32×32px-Kreis als Container.

     Zusätzlich zu den beiden oben genannten PFLICHT-Platzhaltern gibt es
     zwei OPTIONALE Platzhalter für die in [Abschnitt 2](#2-bekannte-einschränkungen)
     beschriebene Nutzungsstatistik: `<!--COUNT_TRANSLATIONS-->` und
     `<!--COUNT_SIGNES-->` werden, falls im HTML vorhanden, durch die
     aktuelle durchschnittliche Anzahl an Übersetzungsanfragen bzw.
     übersetzten Zeichen pro Stunde ersetzt - jeweils als reine gerundete
     Ganzzahl ohne Einheit (z. B. "30" bzw. "500"); die passende
     Beschriftung ("Übersetzungen/h", "Zeichen/h" o. ä.) ergänzt man selbst
     im umgebenden HTML. Beide funktionieren unabhängig vom eingebauten
     Toggle "Übersetzungsstatistik in der Kachel anzeigen" (der betrifft nur
     die eingebaute Standard-Kachel) und sowohl im "HTML-Code"-Feld als auch
     im "Sprachauswahl-HTML-Code"-Feld weiter unten. Kommt keiner der beiden
     Platzhalter im HTML vor, entsteht kein zusätzlicher Aufwand. Aktualisiert
     wird alle 10 Minuten über denselben `PushVisualizationUpdate()`-
     Mechanismus, der auch den `REFRESH`-Payload weiter unten auslöst - nie
     über einen Formular-Reload.

     Seit Build 61 gibt es dazu zwei weitere, ebenfalls optionale Platzhalter:
     `<!--COUNT_CACHE_TRANSLATIONS-->` und `<!--COUNT_CACHE_SIGNES-->` liefern
     die reine Gesamtzahl der seit Inbetriebnahme durch den Übersetzungs-Cache
     eingesparten Anfragen bzw. Zeichen (ebenfalls als reine Ganzzahl ohne
     Einheit, aber - anders als die beiden oben - eine Gesamtsumme, keine
     Pro-Stunde-Rate). Gelten dieselben Regeln wie für
     `<!--COUNT_TRANSLATIONS-->`/`<!--COUNT_SIGNES-->`: unabhängig vom Toggle,
     in beiden Feldern nutzbar, kein zusätzlicher Aufwand, falls ungenutzt.

   - **"Sprachauswahl-HTML-Code"** - ersetzt `<!--LANGUAGE_SELECT-->`.
     Standardmäßig **vorbefüllt mit einem funktionierenden Beispiel** (zwei
     Flaggen statt Dropdown für Deutsch/Englisch), damit direkt nach dem
     Aktivieren etwas Sichtbares/Funktionierendes in der Kachel steht:
     ```html
     <div style="display:flex; align-items:center; gap:10px;">
         <span onclick="requestAction('Language', 'ORIGINAL_IMPORT');" style="cursor:pointer; font-size:24px;" title="Deutsch">🇩🇪</span>
         <span onclick="requestAction('Language', 'en');" style="cursor:pointer; font-size:24px;" title="English">🇬🇧</span>
     </div>
     ```
     `requestAction('Language', '<Code>')` ist der eigentliche Mechanismus -
     die von Symcon in jede Kachel injizierte JS-Funktion, die einen
     Sprachwechsel auslöst; sie ist an keine bestimmte HTML-Struktur
     gebunden (kein `<select>` nötig - jedes klickbare Element reicht).
     `<Code>` ist entweder ein echter, konfigurierter Zielsprachcode (z. B.
     `en`, `fr`) oder die interne Pseudo-Sprache `ORIGINAL_IMPORT`
     (unbearbeiteter Rohtext in der Scan-Sprache).

     **Bewusst einfach gehalten, keine Sprachenliste/Wiederholungs-Vorlage:**
     dieses Feld wird 1:1 verwendet, ohne Kenntnis der tatsächlich
     konfigurierten Zielsprachen. Kommen später weitere Zielsprachen dazu
     oder fallen welche weg, bleibt dieser Code unverändert - er muss dann
     manuell angepasst werden (weitere `<span>`s ergänzen/entfernen). Wird
     das Feld komplett geleert, greift stattdessen wieder automatisch die
     eingebaute, live aus den tatsächlich konfigurierten Zielsprachen
     erzeugte Dropdown-Sprachauswahl (siehe Beispiel oben) - für beliebig
     viele Sprachen ohne Pflegeaufwand, nur eben ohne Flaggen-Optik.

   Zusätzlich ruft Symcon bei jeder `UpdateVisualizationValue()`-Aktualisierung
   (siehe `PushVisualizationUpdate()`/`PushTrialExpiredAlert()`) eine globale
   JS-Funktion `handleMessage(data)` in der Kachel auf - die Standardvorlage
   im "HTML-Code"-Feld definiert sie bereits (verarbeitet
   `{"action":"REFRESH", ...}` fürs Live-Nachziehen eines Sprachwechsels in
   anderen offenen Tabs/Geräten sowie `{"action":"ALERT", ...}` für den
   Testphase-abgelaufen-Hinweis). Der `REFRESH`-Payload enthält dabei IMMER
   den aktuellen Inhalt des Felds "Sprachauswahl-HTML-Code" (bzw. die
   automatisch erzeugte Dropdown-Auswahl, falls dieses Feld leer ist) -
   niemals mehr fest die eingebaute `<select>`-Zeile, egal was im
   "HTML-Code"-Feld steht. Wird `handleMessage()` entfernt oder umbenannt,
   funktioniert die Kachel beim ersten Laden weiterhin normal - nur diese
   beiden Live-Aktualisierungen bleiben dann stumm, ohne Fehlermeldung. Wird
   das Feld "HTML-Code" komplett geleert, greift automatisch derselbe
   eingebaute HTML-Code wie ohne aktiviertes Feature (kein Absturz, keine
   leere Kachel).

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
  zur bereits aktiven Sprache oder zurück zur Scan-Sprache/Original zählt nie
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
konfigurierte Scan-Sprache; nur bei abweichender Fremdtext-Sprache explizit
angeben. Leerer Text, Quellsprache = aktive Sprache, oder eine wegen
abgelaufener Testphase gerade nicht kostenfreie Sprache liefern den Text
unverändert zurück - nie ein Fehler.

Beispiel (Scan-Sprache dieser Instanz, z. B. Deutsch):
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
