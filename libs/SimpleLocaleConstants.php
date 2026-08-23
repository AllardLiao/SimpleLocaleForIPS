<?php

declare(strict_types=1);

namespace SimpleLocaleConstants;

trait SimpleLocaleConstants
{
    // Properties
    // Notaus-Schalter (Standard-Konvention "Instanz aktiv" vieler Symcon-Module) -
    // deaktiviert bei false SOFORT jede Uebersetzungsarbeit (Rescan/Auto-Rescan,
    // VM_UPDATE-Live-Nachuebersetzung, ReconcileRowSourceLanguageChanges), unabhaengig
    // von jeder anderen Einstellung - siehe ApplyChanges/ScanRootTree/
    // HandleTrackedVariableUpdate. Gedacht, um z.B. ein durchgehendes API-Rate-Limit
    // oder ein aufgebrauchtes Tageskontingent sofort zu stoppen, ohne erst ein neues
    // Modul-Build abwarten zu muessen - der Admin kann einfach dieses Haekchen im
    // Formular entfernen und "Uebernehmen" klicken. Bereits vorhandene Uebersetzungen
    // bleiben nutzbar (Sprachwechsel ueber die Kachel funktioniert weiter, es wird nur
    // NICHTS NEUES mehr uebersetzt) - kein Datenverlust, rein additiv reversibel.
    private const propertyActive = 'Active';
    private const propertySourceLanguage = 'SourceLanguage';
    private const propertyTargetLanguages = 'TargetLanguages';
    private const propertyGoogleTranslateAPIKey = 'GoogleTranslateAPIKey';
    private const propertyDeepLAPIKey = 'DeepLAPIKey';
    // Kontaktadresse fuer die kostenfreie MyMemory-API (Parameter "de") - komplett
    // optional, hebt aber das anonyme 5.000-Zeichen/Tag-Limit auf 50.000 an (siehe
    // TranslateSingleFree). Kein Account, keine Anmeldung - nur eine Anlaufstelle
    // fuer MyMemory bei Problemen mit dem Kontingent.
    private const propertyFreeTranslateContactEmail = 'FreeTranslateContactEmail';
    // 'google' oder 'deepl' - welcher der beiden BEZAHLTEN Anbieter zuerst versucht
    // wird, wenn beide konfiguriert sind (siehe GetProviderChain). Der kostenfreie
    // Anbieter (MyMemory) ist immer aktiv und steht immer als letztes Glied der
    // Kette - kein eigenes Freischalten noetig, kein Account, kein Key.
    private const propertyPreferredPaidProvider = 'PreferredPaidProvider';
    private const propertyAutoRescanInterval = 'AutoRescanInterval';
    private const propertyObjectNames = 'ObjectNames';
    private const propertyObjectTexts = 'ObjectTexts';

    // Beschriftungen (Caption/Prefix/Suffix) von Variablen-Präsentationen im Root-Baum
    // (Legacy-Profil-Assoziationen, Enumeration, oder jede andere Präsentationsart -
    // generisch per Feldname erkannt, siehe ExtractTranslatableFields) - eine Zeile je
    // (ObjectID, Feld-Pfad). Wird beim Sprachwechsel NIE auf das ggf. geteilte Profil/
    // Template selbst zurückgeschrieben, sondern immer als eigene Custom Presentation
    // nur auf die eine getrackte Variable (siehe ApplyLanguage) - andere Variablen, die
    // zufällig dasselbe Profil/Template nutzen, bleiben unangetastet.
    private const propertyEnumerationOptions = 'EnumerationOptions';

    // Kachel-Visualisierungs-Instanz (WebFront-Kernmodul, nicht Teil von Simple
    // Locale) - EINZIGE Quelle des Root der Visualisierung (bewusst NICHT
    // "Root-Kategorie" genannt - das bezeichnet in Symcon selbst die echte Wurzel
    // des gesamten Objektbaums, Objekt-ID 0; siehe GetEffectiveRootCategoryID,
    // liest deren "BaseID"-Property = die dort als "Startkategorie" bezeichnete
    // Kategorie) sowie der Automations/Favoriten/Begrüßung-Übersetzung. Kein
    // manuelles Rückfall-Feld mehr (Stand vor Build 24 gab es zusätzlich
    // RootCategoryID als eigene Property) - ohne gewählte Instanz bzw. ohne deren
    // BaseID bleibt die Instanz im Status STATUS_ROOT_CATEGORY_MISSING. Automations:
    // deren Property "Automations" traegt neben der Automation-Referenz einen frei
    // vergebenen Anzeigetext ("Name"), der komplett unabhaengig vom Root-Baum lebt
    // (aehnlich einer Verknuepfung mit eigenem Namensfeld) und daher separat
    // gescannt/uebersetzt werden muss - siehe ScanAutomations/ApplyAutomationsLanguage.
    private const propertyWebFrontVisuInstanceID = 'WebFrontVisuInstanceID';
    private const propertyObjectAutomations = 'ObjectAutomations';

    // Begrüßungstext der Kachel-Visualisierung ("Show Greeting" = "Automatic" oder
    // "Static" in der Instanzkonfiguration, Property "GreetingName") - exakt dasselbe
    // Muster wie Automations: ein frei vergebener Text, unabhängig vom Root-Baum,
    // der separat gescannt/übersetzt werden muss. Bei "Show Greeting" = "Variable"
    // kommt der Text stattdessen aus einer echten Symcon-Variable (Property
    // "GreetingVariableID") - deren Wert wird bereits automatisch mitübersetzt,
    // sofern die Variable im Root der Visualisierung liegt (siehe WalkTree); liegt
    // sie außerhalb, wird sie wie Favoriten außerhalb des Root-Baums zusätzlich
    // erfasst (siehe ScanGreetingVariableOutsideRootTree). Bei "Show Greeting" =
    // "Automatic" stellt Symcon zusätzlich noch eine tageszeitabhängige Anrede
    // ("Good Morning"/"Good Evening" etc.) VOR den Namen - diese wird laut
    // Nutzertest rein clientseitig anhand der Spracheinstellung des Besucher-
    // Browsers erzeugt, unabhängig von der in Simple Locale aktiven Sprache, und
    // ist daher NICHT beeinflussbar (siehe README Abschnitt 2, bekannte
    // Einschränkungen).
    private const propertyObjectGreeting = 'ObjectGreeting';

    // Build 108 (Nutzer-Wunsch): Legenden-Beschriftungen von Symcons eingebautem
    // Chart-Element (ObjectType 5 "Media", MediaType 4 "MEDIATYPE_CHART", z.B.
    // aus "Add Chart" im Kachel-Visualisierungs-Editor). Anders als "Beschriftungen"
    // (Variablen-Profile/-Presentations) sitzen diese Titel NICHT in einer
    // Variablen-Presentation, sondern direkt in der Chart-eigenen Konfiguration
    // (IPS_GetMediaContent/IPS_SetMediaContent, base64-kodiertes JSON mit einem
    // "datasets"-Array, je Eintrag "variableID" + "title") - eigener Lese-/
    // Schreibmechanismus, siehe ScanChartsByKey/ApplyChartsLanguage. Ein Chart
    // sitzt (anders als Automations) normal im Root-Baum und wird daher direkt
    // von WalkTree() mit erfasst, nicht separat wie Automations/Begrüßung.
    // Eindeutiger Schlüssel je Zeile ist die Kombination ChartID+VariableID (ein
    // Chart kann mehrere Datenreihen/Titel gleichzeitig haben).
    private const propertyObjectCharts = 'ObjectCharts';

    // Build 78 (Nutzer-Wunsch): die festen, im PHP-Code hart hinterlegten
    // Gast-Oberflächentexte (siehe GetOwnUiTextDefinitions - "Übersetzung pausiert
    // bis", die Statistik-Beschriftungen, der Info-Popup-Hinweistext, ...) werden
    // jetzt genau wie Objektnamen/Automations beim Rescan EINMALIG in alle
    // konfigurierten Zielsprachen übersetzt und dauerhaft in dieser Property
    // gespeichert - NICHT mehr live bei jedem Kachel-Aufruf über einen 24h-
    // Attribut-Cache (siehe EnsureGuestLanguageNamesFresh), der wegen genau dieses
    // Live-Aufrufs während einer Anbieter-Pause leer blieb (siehe Build 77). Da
    // diese Texte für immer feststehen (bis ein künftiges Modul-Update den
    // deutschen Quelltext ändert), liegt die Übersetzung damit VOR jeder Pause
    // bereits vor und ist von ihr komplett unabhängig - klassischer Fall von
    // "einmal übersetzen, für immer nutzen" statt "bei jedem Bedarf neu anfragen".
    // Zeilen-Schlüssel ist bewusst KEIN ObjectID (diese Texte gehören zu keinem
    // Symcon-Objekt), sondern ein fester, im Code vergebener String (siehe
    // fieldOwnUiTextKey) - dadurch nutzt "Aufräumen" (siehe identCleanupOrphanedRows)
    // diese Property gar nicht erst an, sie enthaelt strukturell nichts
    // "Verwaistes".
    private const propertyOwnUiTexts = 'OwnUiTexts';

    // Build 89 (Nutzer-Wunsch, "Eigene Übersetzungstabelle"): admin-gepflegtes
    // Glossar, das JEDER automatischen Übersetzung (Google/DeepL/MyMemory)
    // vorgezogen wird - siehe GetManualTranslation/TranslateBatch. Zeilenform wie
    // Objektnamen (fieldRowSourceLanguage + langOriginalImport als Quelltext-Feld +
    // eine Spalte je Zielsprache), aber vollständig admin-editierbar (kein Scan,
    // keine ObjectID) - der Admin fügt Zeilen selbst über den "Hinzufügen"-Button
    // der Liste hinzu.
    private const propertyManualTranslations = 'ManualTranslations';

    // Feldnamen, die IPS_GetVariablePresentation()/IPS_GetTemplate() für
    // menschenlesbaren Anzeigetext verwendet (siehe IsTranslatableFieldName) -
    // Groß-/Kleinschreibung wird beim Vergleich ignoriert. Symcon ist hier leider
    // uneinheitlich: dieselbe Bedeutung heißt je nach Präsentationsart/Kontext anders
    // ('Caption' bei Enumeration-Optionen, 'Constant' bzw. 'ConstantValue' bei der
    // intervallbasierten Numeric-Presentation - live gegen Kai's Controme-
    // Heizungsmodus geprüft, 'Prefix'/'Suffix' bzw. 'PrefixValue'/'SuffixValue' je nach
    // Ebene). An zentraler Stelle gepflegt, damit ein von IPS künftig neu erfundener
    // Name (z.B. ein hypothetisches "ValueConstant") nur hier ergänzt werden muss.
    private const TRANSLATABLE_PRESENTATION_FIELD_NAMES = [
        'CAPTION', 'PREFIX', 'SUFFIX', 'CONSTANT',
        'CONSTANTVALUE', 'PREFIXVALUE', 'SUFFIXVALUE',
    ];

    // Aktuell aktive Gast-Sprache - bewusst eine Property (instanzgebunden wie ein
    // Attribut, aber zusätzlich im Konfigurationsformular sicht- und änderbar), nicht
    // ein Variablenprofil (das wäre in Symcon global über alle Instanzen hinweg geteilt).
    private const propertyCurrentLanguage = 'CurrentLanguage';

    // Sichtbarkeit von Globus- und Info-Symbol in der Kachel - dem Admin überlassen,
    // falls er ein schlankeres/eigenes Design ohne diese Elemente möchte.
    private const propertyShowGlobeIcon = 'ShowGlobeIcon';
    private const propertyShowInfoIcon = 'ShowInfoIcon';

    // Blendet den kleinen Statistik-Hinweis ("X Übersetzungen/h, Y Zeichen/h", siehe
    // BuildTranslationStatsNoticeHtml) unter dem Dropdown in der Kachel ein/aus -
    // rein informativ, standardmäßig aus (die meisten Gäste interessiert das nicht).
    // Die zugrunde liegende Statistik selbst (siehe attributeStatsSince/
    // RequestCount/CharacterCount) läuft unabhängig von diesem Schalter immer mit,
    // auch die beiden Platzhalter <!--COUNT_TRANSLATIONS-->/<!--COUNT_SIGNES--> für
    // eigene Kacheln (siehe ApplyTilePlaceholders) funktionieren unabhängig davon.
    private const propertyShowTranslationStats = 'ShowTranslationStats';

    // Pro-Feature "custom_tile": schaltet die eingebaute <select>-Dropdown-
    // Kachel (siehe GetVisualizationTile) auf den vom Nutzer editierbaren
    // HTML-Code aus propertyCustomTileHtml um - die Instanz liefert also
    // weiterhin SELBST die Kachel aus, nur eben mit angepasstem HTML/CSS statt
    // der eingebauten Optik. Nur wirksam mit dem Feature-Flag - siehe
    // HasLicenseFeature in GetVisualizationTile, exakt analog zu
    // propertyAutoRescanInterval/"auto_rescan".
    private const propertyUseCustomTile = 'UseCustomTile';

    // Editierbarer HTML-Code fuer die Kachel im Modus "Eigene Sprachauswahl-
    // Kachel" (siehe propertyUseCustomTile) - Standardwert ist eine 1:1-Kopie
    // von module.html (siehe GetDefaultCustomTileHtml), damit der Nutzer eine
    // funktionierende Vorlage zum Anpassen hat statt bei Null anzufangen. Die
    // Platzhalter <!--WRAPPER_ID--> und <!--LANGUAGE_SELECT--> sowie die JS-
    // Funktion handleMessage() muessen dabei erhalten bleiben, siehe README
    // Abschnitt 7. Bewusst NICHT dasselbe wie eine komplett eigenstaendige,
    // separat gebaute Kachel (siehe GetAvailableLanguages/SetLanguage) - hier
    // liefert weiterhin diese Instanz selbst die Kachel aus.
    private const propertyCustomTileHtml = 'CustomTileHtml';

    // Optionaler, vom Nutzer editierbarer HTML-Code, der <!--LANGUAGE_SELECT--> in
    // propertyCustomTileHtml ersetzt (siehe ResolveLanguageSelectHtml). Default
    // ist EIN FUNKTIONIERENDES BEISPIEL (siehe GetDefaultCustomLanguageSelectHtml,
    // zwei Flaggen statt Dropdown) statt einer Kopie der eingebauten <select>-
    // Zeile - damit nach dem Umschalten auf "Eigene Sprachauswahl-Kachel" sofort
    // etwas Sichtbares/Funktionierendes im Bearbeiten-Fenster steht, an dem sich
    // der Nutzer orientieren kann. Leeres Feld (bewusst geleert) = stattdessen
    // die automatisch generierte eingebaute Sprachauswahl wird verwendet
    // (BuildLanguageSelectHtml, live aus der aktuellen Sprachliste/Auswahl
    // berechnet - anders als module.html KEIN statisches Template, eine Kopie
    // waere sofort veraltet). Nicht-leeres Feld = wird UNVERAENDERT verwendet,
    // sowohl beim ersten Laden der Kachel als auch bei jeder Live-Aktualisierung
    // (siehe PushVisualizationUpdate) - bewusst KEIN Platzhalter-Mechanismus fuer
    // einzelne Sprachen darin (z.B. "eine Zeile pro Sprache"): der Nutzer traegt
    // die gewuenschten Sprachcodes selbst fest ein und muss sein HTML selbst
    // anpassen, falls sich die konfigurierten Zielsprachen spaeter aendern -
    // bewusste Vereinfachung statt einer generischen Wiederholungs-Vorlage,
    // siehe README Abschnitt 7.
    private const propertyCustomLanguageSelectHtml = 'CustomLanguageSelectHtml';

    // Lizenzschlüssel (deckt sowohl Einmalkauf als auch Abo ab, siehe
    // ValidateLicenseKey) - eingetragen, aber erst nach Klick auf "Lizenz
    // aktivieren" tatsächlich geprüft/übernommen.
    private const propertyLicenseKey = 'LicenseKey';

    // Attribute
    private const attributeAvailableLanguagesCache = 'AvailableLanguagesCache';
    private const attributeAvailableLanguagesFetchedAt = 'AvailableLanguagesFetchedAt';

    // Rein informativer, intern gepflegter Cache des zuletzt aus der
    // Kachel-Visualisierungs-Instanz übernommenen Root der Visualisierung (siehe
    // GetEffectiveRootCategoryID) - kein Formularfeld, wird bei jedem
    // ApplyChanges/Rescan aus deren "BaseID"-Property neu geschrieben. Erlaubt
    // Fehlersuche (sichtbar im "Attribute"-Reiter der Instanz), ohne dass der Nutzer
    // ihn versehentlich manuell auf einen anderen Baum umstellen kann.
    private const attributeEffectiveRootCategoryID = 'EffectiveRootCategoryID';

    // Zuletzt erfolgreich geprüfte Lizenzinformationen (JSON, siehe
    // ValidateLicenseKey) - Cache, damit nicht bei jedem Aufruf neu geprüft werden
    // muss; wird bei jeder Statusabfrage trotzdem gegen den aktuellen Schlüssel neu
    // validiert (siehe GetLicenseInfo), nicht blind übernommen.
    private const attributeLicenseInfo = 'LicenseInfo';

    // Zeitpunkt (Unix-Timestamp) des ersten ApplyChanges dieser Instanz in einem
    // Testversion-Build - Start der Testphase. 0 = noch nicht gestartet.
    private const attributeTrialStartedAt = 'TrialStartedAt';

    // Lokales Protokoll erfolgreicher Lizenzaktivierungen (JSON-Array, siehe
    // TrackLicenseActivationIfNew) - Hash des Schlüssels + IPS_GetLicensee() + Zeitpunkt,
    // auf die letzten 20 Einträge begrenzt. Hilft beim Erkennen von Weiterverkauf/
    // Weitergabe eines Lizenzschlüssels (derselbe Schlüssel-Hash mit mehreren
    // unterschiedlichen Licensee-Adressen).
    private const attributeActivationLog = 'ActivationLog';

    // SHA-256-Hash des aktuell als "geblockt" bekannten Lizenzschlüssels (leer =
    // keiner) - gesetzt, wenn der Aktivierungs-Report-Server beim erstmaligen
    // Aktivieren eines Schlüssels meldet, dieser sei bereits gegen eine
    // hoeherwertige Edition eingetauscht worden (siehe RecordLicenseActivation/
    // GetLicenseInfo). Rein lokaler Cache eines EINMALIGEN Online-Checks, kein
    // laufender Server-Abgleich - siehe README Abschnitt 8. Wird ein ANDERER
    // Schluessel eingetragen, greift dieser Hash nicht mehr (Vergleich ist
    // schluesselgenau).
    private const attributeBlockedLicenseKeyHash = 'BlockedLicenseKeyHash';

    // SHA-256-Hash des Schlüssels, der beim letzten TrackLicenseActivationIfNew()-
    // Durchlauf tatsächlich beim Aktivierungs-Report-Server gemeldet wurde (leer =
    // noch nie). Entscheidet dort, ob ein erneutes "Übernehmen" den Server erneut
    // fragen muss: nur wenn der AKTUELL eingetragene Schlüssel von DIESEM Hash
    // abweicht. Bewusst NICHT mehr über attributeActivationLog (Verlauf der letzten
    // 20 Aktivierungen) geprüft - sonst ließe sich ein bereits einmal aktivierter,
    // zwischenzeitlich z.B. per Upgrade verbrauchter/geblockter Schlüssel beliebig
    // oft wieder eintragen: der Log-Scan fand den alten Eintrag und brach die
    // Prüfung ab, ohne den Server je erneut zu fragen, ob der Schlüssel inzwischen
    // geblockt wurde.
    private const attributeLastCheckedLicenseKeyHash = 'LastCheckedLicenseKeyHash';

    // SHA-256-Hash des aktuell als WIDERRUFEN bekannten Lizenzschluessels (leer =
    // keiner) - gesetzt von der taeglichen Statuspruefung (siehe
    // CheckLicenseStatus/ApplyActivationReportResponse), wenn der Meldeserver
    // {"revoked": true} liefert (Admin hat die Lizenz im Shop deaktiviert, z.B.
    // nach einem Widerruf/einer Rueckerstattung - siehe Synergetix-Website-Repo,
    // shop/admin/order.php). Bewusst ein EIGENES Attribut statt
    // attributeBlockedLicenseKeyHash mitzubenutzen: anders als ein Upgrade-Block
    // setzt "revoked" die Testphase NICHT auf frische 30 Tage zurueck - siehe
    // README Abschnitt 8.
    private const attributeRevokedLicenseKeyHash = 'RevokedLicenseKeyHash';

    // Vom Meldeserver ueber die taegliche Statuspruefung zurueckgemeldetes,
    // ggf. AKTUALISIERTES Ablaufdatum (Unix-Timestamp, 0 = kein Override
    // bekannt) fuer genau den Schluessel in attributeLicenseExpiresAtOverrideKeyHash
    // - ueberschreibt in GetLicenseInfo() das im Schluessel selbst signierte
    // expiresAt, OHNE dass ein neuer Schluessel ausgestellt/eingetragen werden
    // muss (siehe shop/admin/order.php's "Ablaufdatum ueberschreiben"-Feld). Der
    // Schluessel-Hash wird mitgespeichert, damit ein spaeter eingetragener
    // ANDERER Schluessel diesen Override nicht versehentlich erbt.
    private const attributeLicenseExpiresAtOverride = 'LicenseExpiresAtOverride';
    private const attributeLicenseExpiresAtOverrideKeyHash = 'LicenseExpiresAtOverrideKeyHash';

    // Zeitpunkt (Unix-Timestamp) der letzten taeglichen Statuspruefung, rein
    // informativ/fuer Debugging - keine Fachlogik haengt daran.
    private const attributeLastDailyLicenseCheckAt = 'LastDailyLicenseCheckAt';

    // Zeitpunkt (Unix-Timestamp) des letzten TATSAECHLICHEN Sprachwechsels (zu einer
    // anderen als der bis dahin aktiven Sprache) - nur relevant ohne das Feature
    // "unlimited_language_switch" (siehe IsLanguageSwitchRateLimited), z.B. bei der
    // "Light"-Edition: dort ist ein Wechsel auf max. einen pro rollierendem 24h-
    // Fenster begrenzt. 0 = noch nie gewechselt.
    private const attributeLastLanguageSwitchAt = 'LastLanguageSwitchAt';

    // Sprachcode, fuer den ApplyLanguage() zuletzt TATSAECHLICH die Umbenennungen/
    // Wertaenderungen durchgefuehrt hat (siehe ApplyChanges) - NICHT dasselbe wie
    // propertyCurrentLanguage: die Property allein aendert sich auch, wenn der Admin
    // im Konfigurationsformular nur das Auswahlfeld "Aktuell aktive Sprache" umstellt
    // und "Uebernehmen" klickt (ein reines ApplyChanges liest/speichert Properties,
    // loest aber fuer sich genommen keine Umbenennungen aus - das tat bisher nur der
    // RequestAction-Pfad ueber die Kachel/IPSSL_SetLanguage). ApplyChanges vergleicht
    // beide Werte und holt die Umbenennung ueber ApplyLanguage() nach, falls sie noch
    // aussteht - ohne dieses Attribut wuerde der Vergleich sonst immer "gleich" sehen,
    // sobald ApplyLanguage() selbst per IPS_SetProperty+IPS_ApplyChanges erneut in
    // ApplyChanges() hineinlaeuft.
    private const attributeLastAppliedLanguage = 'LastAppliedLanguage';

    // ValueObjectIDs (JSON-Array), für die aktuell VM_UPDATE-Nachrichten registriert
    // sind (siehe SyncValueUpdateRegistrations) - nötig, um bei jedem ApplyChanges
    // sauber ab-/neu zu registrieren, wenn sich "Eigene Texte" ändert (neue/gelöschte
    // Zeilen), ohne verwaiste Registrierungen auf inzwischen nicht mehr getrackten
    // Variablen zurückzulassen.
    private const attributeRegisteredValueObjectIDs = 'RegisteredValueObjectIDs';

    // Letzter von der Instanz SELBST geschriebener Wert je ValueObjectID (JSON-Map
    // ValueObjectID => Wert) - verhindert, dass der eigene SetValueString-Aufruf
    // (Sprachwechsel oder automatische Neuübersetzung, siehe HandleTrackedVariableUpdate)
    // die zugehörige VM_UPDATE-Nachricht erneut auslöst und sich selbst in eine
    // Endlosschleife übersetzt.
    private const attributeLastSelfWrittenValues = 'LastSelfWrittenValues';

    // Build 71: gepufferte, noch NICHT persistierte Zeilen-Feld-Aenderungen aus
    // ApplyTrackedVariableUpdate (JSON-Map Property => ValueObjectID => Feld => Wert) -
    // siehe BufferPendingTrackedRowUpdate/StagePendingTrackedRowUpdates/
    // FlushPendingTrackedRowUpdates. Live gemeldet (2026-08-19): eine haeufig
    // (mehrmals pro Minute) aktualisierte "Eigene Texte"-Variable hat bei JEDEM
    // externen Schreibvorgang sofort IPS_SetProperty+IPS_ApplyChanges auf genau die
    // Property ausgeloest, die im GERADE OFFENEN Konfigurationsformular als
    // bearbeitbare Liste angezeigt wird - der Admin konnte dadurch praktisch nie
    // eine eigene Aenderung speichern, bevor die Liste unter ihm neu geschrieben
    // wurde. Der GAST sieht die neue Uebersetzung weiterhin sofort (WriteTrackedValueString
    // bleibt unveraendert/unverzoegert) - nur die Buchfuehrung fuer einen SPAETEREN,
    // seltenen Sprachwechsel wird jetzt per Debounce-Timer erst nach einer Ruhephase
    // committet.
    private const attributePendingTrackedRowUpdates = 'PendingTrackedRowUpdates';

    // Build 71: Unix-Timestamp, wann der Debounce-Timer (siehe
    // BufferPendingTrackedRowUpdate) den aktuell gepufferten Stand voraussichtlich
    // schreibt - 0, solange nichts gepuffert ist. Rein informativ fuers
    // Konfigurationsformular (siehe PopulateFormElements/PendingRowUpdateNoticeRow),
    // damit der Admin sieht, bis wann er in Ruhe editieren kann bzw. wann die
    // Property als naechstes automatisch aktualisiert wird - unabhaengig davon,
    // welchen Wert eine EINZELNE Zeile gerade puffert.
    private const attributePendingRowUpdateFlushAt = 'PendingRowUpdateFlushAt';

    // Build 88: aktuell laufender Rescan-Verarbeitungsschritt (siehe
    // SetRescanProgress/RescanProgressBar) - '' = kein Rescan aktiv.
    private const attributeRescanProgressMessage = 'RescanProgressMessage';

    // Zustand der VariableCustomPresentation (JSON, roh - genau das Array, das
    // IPS_GetVariable($id)['VariableCustomPresentation'] vor dem allerersten eigenen
    // Fork-Schreibvorgang zurückgegeben hat) je ValueObjectID, unmittelbar bevor
    // SimpleLocale zum ersten Mal IPS_SetVariableCustomPresentation() auf diese
    // Variable anwendet (siehe ApplyEnumerationOptionsToVariable). Wird beim Wechsel
    // zurück auf die Original-/Basissprache exakt wiederhergestellt (statt nur
    // denselben Text erneut inline zu schreiben) - damit greift wieder live das
    // zugrunde liegende, ggf. geteilte Profil/Template (inkl. künftiger dortiger
    // Änderungen), und ein davor bereits vorhandener eigener Custom-Presentation-Stand
    // (von einem anderen Modul oder manuell vom Admin gesetzt) geht nicht verloren.
    private const attributeEnumerationPresentationBackup = 'EnumerationPresentationBackup';

    // Objekte ohne Namen, die beim letzten Rescan im Root-Baum gefunden wurden (JSON-
    // Array aus ObjectID+Path) - ein Rescan bricht ab, sobald welche existieren, bevor
    // irgendetwas übersetzt wird (leerer Name lässt sich sonst nicht sinnvoll übersetzen
    // und würde als leere Beschriftung in der Gäste-Visualisierung landen).
    private const attributeUnnamedObjects = 'UnnamedObjects';

    // Build 76: Ergebnis des letzten "Aufräumen"-Laufs (siehe CleanupOrphanedRows) -
    // Anzahl entfernter, verwaister Zeilen, NUR fürs einmalige Anzeigen im
    // CleanupResultPopup direkt nach dem zugehörigen ReloadForm() gedacht (analog zum
    // etablierten attributeUnnamedObjects-Muster, aber "zeig einmal und vergiss"
    // statt dauerhaft sichtbar) - wird von PopulateFormElements() sofort nach dem
    // Anzeigen wieder auf -1 zurückgesetzt, damit ein SPÄTERES, unabhängiges Öffnen
    // des Formulars nicht denselben alten Treffer erneut zeigt. -1 = nichts
    // anzuzeigen, >= 0 = genau einmal anzuzeigende Anzahl.
    private const attributeLastCleanupRemovedCount = 'LastCleanupRemovedCount';

    // Sprachnamen (+ das Label der "Original"-Pseudo-Sprache) live in die gerade
    // aktive Gast-Sprache übersetzt - fuer die Kachel-Visualisierung (GetVisualizationTile),
    // getrennt vom AvailableLanguagesCache oben, der an der Admin-Konsolensprache haengt.
    private const attributeGuestLanguageNamesCache = 'GuestLanguageNamesCache';

    // JSON-Map Anbieter => Unix-Timestamp, bis wann dieser Anbieter als "pausiert"
    // gilt (siehe DetectRateLimitCooldown/RecordProviderPaused) - ein bei einem
    // erkannten Rate-Limit/Tageskontingent-Fehler eingetragener Anbieter wird bis zu
    // diesem Zeitpunkt in TranslateChunk()/FetchLanguageNames() uebersprungen, OHNE
    // ihn erneut anzufragen (spart unnoetige, ohnehin aussichtslose Aufrufe waehrend
    // der Sperre). Sind ALLE Anbieter der aktuellen Kette gleichzeitig pausiert,
    // gilt die gesamte Instanz als pausiert (siehe GetGlobalPauseUntil) - dann wird
    // ueberhaupt kein Uebersetzungsversuch mehr unternommen, bis der fruehste der
    // eingetragenen Zeitpunkte erreicht ist.
    private const attributeProviderPausedUntil = 'ProviderPausedUntil';

    // JSON-Map Anbieter => SHA-256-Hash der zuletzt gesehenen Zugangsdaten (Google-/
    // DeepL-API-Key bzw. Kontakt-E-Mail beim kostenfreien Anbieter) - reine
    // Aenderungserkennung (siehe ClearPauseOnCredentialChange), speichert bewusst nur
    // einen Hash statt der Werte selbst (wie attributeLastCheckedLicenseKeyHash).
    // Aendert sich der Hash eines Anbieters seit dem letzten ApplyChanges(), wird
    // dessen laufende Pause (siehe attributeProviderPausedUntil) sofort beendet -
    // ein neuer/anderer Key bzw. eine andere Kontakt-E-Mail kann die Ursache der
    // Sperre direkt beheben.
    private const attributeLastSeenProviderCredentialsHash = 'LastSeenProviderCredentialsHash';

    // Statistik ueber tatsaechliche Uebersetzungs-API-Aufrufe seit Inbetriebnahme
    // dieser Instanz (siehe RecordTranslationRequestStats/BuildTranslationStatsText) -
    // attributeStatsSince wird EINMALIG beim allerersten ApplyChanges()-Durchlauf
    // gesetzt (Zeitpunkt der Inbetriebnahme, nicht der ersten tatsaechlichen
    // Uebersetzung), attributeStatsRequestCount zaehlt JEDEN einzelnen HTTP-Aufruf an
    // einen Anbieter (Google/DeepL: ein Aufruf pro Chunk, unabhaengig von der Anzahl
    // gebuendelter Texte; MyMemory: ein Aufruf PRO TEXT, da kein Batch-Endpoint
    // existiert - siehe TranslateChunkFree), erfolgreich oder nicht (ein
    // fehlgeschlagener Versuch verbraucht ebenfalls Kontingent/zaehlt als Last).
    // attributeStatsCharacterCount summiert die dabei tatsaechlich zur Uebersetzung
    // eingereichte Zeichenzahl (roh, vor jeder HTML-Text-Knoten-Zerlegung o.ae.).
    private const attributeStatsSince = 'StatsSince';
    // Build 132 (Nutzer-Wunsch, gemeinsam hergeleitet): IP-Symcons "Integer"-
    // Attributtyp ist ein klassischer 32-Bit-Integer (Bereich bis
    // 2.147.483.647) - unabhaengig davon, dass PHP selbst auf jedem
    // 64-Bit-System einen 64-Bit-Integer verwendet. Bei sehr langer Laufzeit
    // (Jahre) haette das die reinen Zaehl-Attribute unten irgendwann zum
    // Ueberlaufen/Wraparound bringen koennen, ganz ohne dass PHPs eigene
    // Rechenoperation je ueberlaeuft - der Fehler waere ausschliesslich beim
    // Schreiben in Symcons 32-Bit-Speicher entstanden. Auf String umgestellt
    // (praktisch unbegrenzt, Rechnen weiterhin ueber normale PHP-Ints, nur die
    // Persistierung aendert sich) - "V2"-Suffix im Attributnamen, damit
    // Symcon nicht denselben Attributnamen mit geaendertem Typ wiederverwendet
    // (unklares/riskantes Verhalten) - stattdessen ein sauberer, einmaliger
    // Migrations-Schritt in Create() von den alten Integer-Attributen (siehe
    // *LegacyInt-Konstanten unten) in die neuen String-Attribute, bevor die
    // alten Namen als reine, fortan nie mehr aktualisierte Altlast liegen
    // bleiben (harmlos, da Attribute nicht im sichtbaren Objektbaum stehen).
    private const attributeStatsRequestCount = 'StatsRequestCountV2';
    private const attributeStatsCharacterCount = 'StatsCharacterCountV2';
    private const attributeStatsRequestCountLegacyInt = 'StatsRequestCount';
    private const attributeStatsCharacterCountLegacyInt = 'StatsCharacterCount';

    // Zaehlt separat, wie viele Uebersetzungsanfragen/Zeichen NICHT an einen
    // Anbieter geschickt werden mussten, weil ein Cache-Treffer vorlag (siehe
    // TranslateBatch/RecordCacheSavingsStats) - laeuft parallel zu
    // attributeStatsRequestCount/attributeStatsCharacterCount oben (die zaehlen
    // nur TATSAECHLICH gestellte Anfragen), ebenfalls seit attributeStatsSince,
    // nie zurueckgesetzt. Build 132: dieselbe Integer->String-Migration wie oben.
    private const attributeStatsCacheSavedRequestCount = 'StatsCacheSavedRequestCountV2';
    private const attributeStatsCacheSavedCharacterCount = 'StatsCacheSavedCharacterCountV2';
    private const attributeStatsCacheSavedRequestCountLegacyInt = 'StatsCacheSavedRequestCount';
    private const attributeStatsCacheSavedCharacterCountLegacyInt = 'StatsCacheSavedCharacterCount';

    // Uebersetzungs-Cache (JSON-Map "Quellsprache|Zielsprache|SHA-256(Text)" =>
    // uebersetzter Text), siehe TranslateBatch/GetCachedTranslation/
    // StoreCachedTranslation - vermeidet wiederholte, identische API-Aufruf bei
    // Texten, die sich wiederholen (z.B. eine tageszeitabhaengige
    // Begruessungsvariable mit nur einer Handvoll fester Werte). Auf die letzten
    // TRANSLATION_CACHE_MAX_ENTRIES Eintraege begrenzt, analog zu
    // attributeActivationLog.
    private const attributeTranslationCache = 'TranslationCache';

    // Build 133 (Nutzer-Wunsch): JSON-Map der deutschen Quelltexte (Einheiten-
    // Kuerzel + Kompass-Punkte), die MergeBundledManualTranslations() dem Admin
    // bereits einmal als Vorschlagszeile in propertyManualTranslations
    // angeboten hat (Wert ist immer nur `true`, reine Existenzpruefung). Anders
    // als propertyOwnUiTexts (siehe MergeOwnUiTextRows) sind diese Zeilen fuer
    // den Admin frei loeschbar - ohne dieses Merkzettel-Attribut wuerde ein
    // geloeschter Vorschlag (Nutzer-Beispiel: "SSW" kollidiert mit einem
    // Personen-Kuerzel in seiner Installation) beim naechsten Rescan sofort
    // wieder auftauchen, statt geloescht zu bleiben.
    private const attributeSeededManualTranslationKeys = 'SeededManualTranslationKeys';

    // Idents (RequestAction) - kein zugehöriges Variablen-/Profilobjekt mehr, siehe
    // propertyCurrentLanguage oben. "Language" bleibt nur noch als reiner Aktions-Ident.
    private const identLanguage = 'Language';
    // Nur noch fuer die einmalige Bereinigung alter Installationen (siehe Create()) -
    // die Sprachauswahl ist jetzt eine echte Modul-Kachel (GetVisualizationTile),
    // keine HTMLBox-Variable mehr.
    private const identLanguageDropdown = 'LanguageDropdown';
    private const identRescan = 'Rescan';
    private const identShowApiKeyWarning = 'ShowApiKeyWarning';
    private const identActivateLicense = 'ActivateLicense';
    private const identClearTranslationCache = 'ClearTranslationCache';
    private const identCheckProviders = 'CheckProviders';
    private const identCleanupOrphanedRows = 'CleanupOrphanedRows';

    // Reservierter Pseudo-Sprachcode für den unangetasteten Rohtext beim ersten Scan
    // (Tippfehler inklusive). Nicht vom Gast über die Sprachauswahl erreichbar.
    // Objektnamen: ein Feld (Name des Objekts). Eigene Texte: der eigentliche
    // Variablen-Inhalt (der Name kommt ausschließlich aus "Objektnamen" - siehe
    // Build 115, entfernt die vormals zusätzliche, redundante Namens-Übersetzung
    // je "Eigene Texte"-Zeile).
    private const langOriginalImport = 'ORIGINAL_IMPORT';
    private const langOriginalImportText = 'ORIGINAL_IMPORT_Text';

    // Build 78: Zeilen-Schlüssel für propertyOwnUiTexts (siehe dort) - ein fester,
    // im Code vergebener String (z.B. "pausedNoticePrefix"), kein ObjectID.
    private const fieldOwnUiTextKey = 'Key';

    // Präfix für die Text-Übersetzungsspalten von "Eigene Texte" (z.B. "Text_es") -
    // seit Build 115 das einzige Präfix dort, bewusst trotzdem als Präfix belassen
    // (nicht der rohe Sprachcode), damit bestehende gespeicherte Zeilen kompatibel
    // bleiben und sich "Eigene Texte" strukturell nicht von den übrigen
    // Präfix-basierten Zeilen-Properties unterscheidet.
    private const fieldTextPrefix = 'Text_';

    // Quellsprache PRO ZEILE (nicht mehr nur instanzweit über propertySourceLanguage,
    // die jetzt "Scan-Sprache" heißt und nur noch den Startwert fürs Erfassen NEUER
    // Zeilen liefert) - macht gemischtsprachige Installationen sicher möglich (z.B.
    // ein Fremdmodul, das Objektnamen/-werte auf Englisch schreibt, während der Rest
    // der Visualisierung Deutsch ist) und schützt bestehende Zeilen davor, bei einer
    // späteren Änderung der Scan-Sprache falsch neu interpretiert zu werden. Editierbar
    // im Formular (siehe BuildListColumns). fieldTranslatedAgainstSourceLanguage ist
    // NICHT im Formular sichtbar - reines internes Bookkeeping, welche Sprache beim
    // letzten Mal tatsächlich für die Übersetzung dieser Zeile verwendet wurde, um eine
    // Änderung von fieldRowSourceLanguage zu erkennen (siehe
    // ReconcileRowSourceLanguageChanges).
    private const fieldRowSourceLanguage = 'Quellsprache';
    private const fieldTranslatedAgainstSourceLanguage = 'UebersetztGegen';

    // NICHT im Formular sichtbar - reines internes Bookkeeping fuer die
    // aktiv-Sprache-only-Uebersetzung + Nachhol-beim-Sprachwechsel-Mechanik (Build 70,
    // siehe IsRowLanguageTranslationCurrent): fieldSourceChangedAt ist ein Unix-Timestamp,
    // der IMMER dann neu gesetzt wird, wenn sich der Rohtext einer Zeile inhaltlich
    // aendert (VM_UPDATE-Live-Schreibvorgang) oder ihre fieldRowSourceLanguage
    // umgestellt wird (macht alle bisherigen Zielsprachen-Spalten ungueltig, da sie
    // gegen die FALSCHE Ausgangssprache berechnet wurden). fieldTranslatedAtByLanguage
    // ist eine verschachtelte Map (Sprachcode => Unix-Timestamp), die je Sprache
    // festhaelt, WANN diese Zeile zuletzt tatsaechlich (neu) uebersetzt wurde - eine
    // Sprache gilt als aktuell, wenn ihr Zeitstempel >= fieldSourceChangedAt ist. Beide
    // Felder fehlen bei alten, vor Build 70 gespeicherten Zeilen komplett (bzw. sind 0) -
    // das wird bewusst NICHT als "muss neu uebersetzt werden" gewertet (siehe
    // IsRowLanguageTranslationCurrent), sonst wuerde ein Modul-Update auf einer
    // bestehenden Installation eine Massen-Neuuebersetzung des kompletten Bestands
    // auslösen, obwohl inhaltlich nichts geaendert wurde.
    private const fieldSourceChangedAt = 'QuelleGeaendertAm';
    private const fieldTranslatedAtByLanguage = 'UebersetztAm';

    // Fingerprint (md5 ueber alle fieldRowSourceLanguage-Werte aller 5 Zeilen-Properties,
    // siehe ComputeRowSourceLanguageFingerprint) - guenstige Kurzschluss-Pruefung VOR
    // ReconcileRowSourceLanguageChanges: die teure Zeilen-fuer-Zeilen-Neuuebersetzung
    // laeuft nur, wenn sich seit dem letzten ApplyChanges() TATSAECHLICH irgendeine
    // Quellsprache geaendert hat, nicht bei JEDEM ApplyChanges()-Aufruf (der auch durch
    // haeufige VM_UPDATE-Live-Nachuebersetzungen re-entrant ausgeloest wird - siehe
    // ApplyTrackedVariableUpdate). Ohne diesen Schutz wuerde ein latenter Fehler in der
    // Abgleichs-Logik (oder auch nur ihre schiere Aufrufhaeufigkeit) das
    // API-Kontingent in kuerzester Zeit aufbrauchen koennen.
    private const attributeLastRowSourceLanguageFingerprint = 'LastRowSourceLanguageFingerprint';

    // Build 104 (Nutzer-Wunsch): guenstiger Kurzschluss-Vergleich (kein API-Aufruf,
    // reiner md5() ueber die bereits gespeicherten Zellwerte), damit ApplyChanges()
    // erkennt, ob sich der fuer die AKTUELL AKTIVE Gast-Sprache relevante Zellinhalt
    // seit dem letzten Durchlauf geaendert hat - z.B. weil der Admin eine
    // Uebersetzungszelle manuell im Formular korrigiert und "Uebernehmen" geklickt
    // hat. Ohne diesen Abgleich blieb so eine Korrektur zwar gespeichert, aber
    // unsichtbar: ApplyLanguage() (das den Namen/Wert tatsaechlich ans lebende
    // Objekt schreibt) lief bisher NUR bei einem tatsaechlichen Sprachwechsel oder
    // einer Zeilen-Quellsprachen-Aenderung erneut, nicht bei einer reinen
    // Zellkorrektur.
    private const attributeLastActiveLanguageContentFingerprint = 'LastActiveLanguageContentFingerprint';

    // Timer: Präfix als Salt auf den Namen, falls im jeweiligen IPS-System
    // bereits ein Timer/Objekt mit demselben Basisnamen existieren sollte.
    private const timerPrefix = 'IPSSL_TIMER_';
    private const timerIdentAutoRescan = 'AutoRescan';
    private const timerIdentTranslationStats = 'TranslationStats';
    private const timerIdentPendingRowUpdateFlush = 'PendingRowUpdateFlush';
    private const timerIdentCleanupReload = 'CleanupReload';
    private const timerIdentLicenseCheck = 'LicenseCheck';

    // Intervall der taeglichen Lizenz-Statuspruefung (siehe CheckLicenseStatus) -
    // bewusst 24h, wie von Kai gewuenscht (kein Push, nur einmal taeglich).
    private const LICENSE_CHECK_INTERVAL_SECONDS = 86400;

    // Build 71: Debounce-Fenster fuer BufferPendingTrackedRowUpdate - erst wenn eine
    // extern getrackte "Eigene Texte"-Variable fuer diese Zeitspanne RUHIG bleibt
    // (keine weitere VM_UPDATE-Nachricht), wird die Zeilen-Buchfuehrung tatsaechlich
    // in die Property geschrieben. Bewusst grosszuegig: ein Sprachwechsel (der
    // JEDERZEIT sofort den neuesten Rohtext braucht, siehe FlushPendingTrackedRowUpdates
    // in ApplyLanguage) ist ein seltenes Ereignis, waehrend das ungestoerte manuelle
    // Editieren des Formulars haeufig und die eigentliche Beschwerde ist.
    private const PENDING_ROW_UPDATE_DEBOUNCE_SECONDS = 720;

    // Statuscodes
    private const STATUS_ROOT_CATEGORY_MISSING = 201;
    private const STATUS_UNNAMED_OBJECTS = 202;
    private const STATUS_TRANSLATE_ERROR = 203;
    private const STATUS_TRIAL_EXPIRED = 204;
    // Milder als STATUS_TRANSLATE_ERROR: kein echter Fehler, sondern ein erkanntes,
    // selbstheilendes Rate-Limit/Tageskontingent bei ALLEN konfigurierten Anbietern
    // gleichzeitig (siehe GetGlobalPauseUntil) - die Instanz bleibt "Aktiv", pausiert
    // aber bis zum fruehesten Anbieter-Reset, statt bei jedem Versuch erneut gegen
    // eine Wand zu laufen.
    private const STATUS_TRANSLATE_PAUSED = 205;

    // Dient als Fallback, solange keine dynamische Liste im Cache liegt (z.B. Google/
    // DeepL noch nie erfolgreich abgefragt) UND als die einzige Liste, wenn nur der
    // kostenfreie Anbieter aktiv ist (der hat selbst keinen Sprachlisten-Endpunkt,
    // siehe FetchLanguageNames) - deshalb bewusst breiter als frueher, nicht nur ein
    // Mini-Platzhalter. MyMemory akzeptiert praktisch jeden ISO-639-1-Code, diese
    // Auswahl deckt die in der Praxis haeufigsten Zielsprachen ab. Enthaelt bewusst
    // auch die 5 TRIAL_LANGUAGE_CODES (is/cy/zu/mi/la, siehe unten) - sonst waere in
    // der Testphase ganz ohne Google/DeepL-Key (nur kostenfreier Anbieter) ueberhaupt
    // keine Zielsprache waehlbar, da BuildTargetLanguageOptions() im Testphase-Build
    // auf genau diese 5 Codes filtert.
    private const DEFAULT_LANGUAGES = [
        ['code' => 'de', 'name' => 'Deutsch'],
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'fr', 'name' => 'Français'],
        ['code' => 'es', 'name' => 'Español'],
        ['code' => 'it', 'name' => 'Italiano'],
        ['code' => 'nl', 'name' => 'Nederlands'],
        ['code' => 'pt', 'name' => 'Português'],
        ['code' => 'pl', 'name' => 'Polski'],
        ['code' => 'cs', 'name' => 'Čeština'],
        ['code' => 'da', 'name' => 'Dansk'],
        ['code' => 'sv', 'name' => 'Svenska'],
        ['code' => 'no', 'name' => 'Norsk'],
        ['code' => 'fi', 'name' => 'Suomi'],
        ['code' => 'el', 'name' => 'Ελληνικά'],
        ['code' => 'ro', 'name' => 'Română'],
        ['code' => 'hu', 'name' => 'Magyar'],
        ['code' => 'tr', 'name' => 'Türkçe'],
        ['code' => 'ru', 'name' => 'Русский'],
        ['code' => 'uk', 'name' => 'Українська'],
        ['code' => 'ar', 'name' => 'العربية'],
        ['code' => 'zh', 'name' => '中文'],
        ['code' => 'ja', 'name' => '日本語'],
        ['code' => 'ko', 'name' => '한국어'],
        ['code' => 'hi', 'name' => 'हिन्दी'],
        ['code' => 'id', 'name' => 'Bahasa Indonesia'],
        // TRIAL_LANGUAGE_CODES (siehe unten) - siehe Kommentar oben, warum die hier
        // mit aufgefuehrt sind.
        ['code' => 'is', 'name' => 'Íslenska'],
        ['code' => 'cy', 'name' => 'Cymraeg'],
        ['code' => 'zu', 'name' => 'isiZulu'],
        ['code' => 'mi', 'name' => 'Māori'],
        ['code' => 'la', 'name' => 'Latina'],
    ];

    // Isländisch, Walisisch, Zulu, Maori, Latein - alle von Google Cloud Translate
    // unterstützt, aber für die allermeisten Gäste-Visualisierungen (Ferienwohnung,
    // Showroom) kaum praxisrelevant. Voll funktionsfähig zum Testen des kompletten
    // Mechanismus, aber ohne die Sprachen, die man in der Praxis tatsächlich braucht.
    private const TRIAL_LANGUAGE_CODES = ['is', 'cy', 'zu', 'mi', 'la'];

    // Öffentlicher Ed25519-Schlüssel zur Prüfung von Lizenzschlüsseln (asymmetrische
    // Signatur, siehe ValidateLicenseKey) - base64-kodiert, 32 Rohbytes.
    //
    // WICHTIG, Unterschied zum früheren HMAC-Ansatz: dieser Schlüssel darf öffentlich
    // sein (er steckt zwangsläufig in jeder installierten Kopie von module.php - auch
    // ohne öffentliches Repo könnte ihn jeder Nutzer aus seiner eigenen Installation
    // auslesen). Er kann NUR prüfen, nicht signieren - im Gegensatz zu einem HMAC-
    // Geheimnis (dieselbe Zeichenkette signiert UND prüft) lässt sich mit ihm KEIN
    // gültiger Lizenzschlüssel erzeugen. Der dazugehörige PRIVATE Schlüssel (zum
    // tatsächlichen Ausstellen von Lizenzen) gehört NIEMALS in dieses - oder
    // irgendein - Repo, sondern nur in ein eigenes, privates Verkaufs-/Signier-Tool.
    private const LICENSE_PUBLIC_KEY = 't+eCtcgi3e7U09kNO4vpqNpeSsLkYApMgyHYz4lVp4M=';

    // "Permalink" zum Lizenzerwerb - zeigt auf den Preisvergleich im echten Shop.
    private const LICENSE_PURCHASE_URL = 'https://www.synergetix.de/simplelocale/pricing.php';

    // Meldeserver fürs Erkennen von Lizenzmissbrauch (z.B. ein Schlüssel wird als
    // "gebraucht" mehrfach weiterverkauft) - siehe TrackLicenseActivationIfNew.
    // Nimmt {licenseKeyHash, licensee, activatedAt} entgegen, siehe
    // Synergetix-Website/shop/license-activation-report.php.
    // WICHTIG: IPS_GetLicensee() liefert eine echte, personenbezogene E-Mail-Adresse -
    // muss vor dem produktiven Release in den eigenen Lizenzbedingungen/
    // Datenschutzhinweisen offengelegt sein.
    private const LICENSE_ACTIVATION_REPORT_URL = 'https://www.synergetix.de/shop/license-activation-report.php';
}

class GUIDs
{
    // --- Modul GUIDs (Instanzen) ---
    public const IPSSL_SimpleLocale = '{1A2E3892-FE35-9E4E-A3A8-B983B0C41F64}';
}
