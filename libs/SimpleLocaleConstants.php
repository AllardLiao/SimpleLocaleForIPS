<?php

declare(strict_types=1);

namespace SimpleLocaleConstants;

trait SimpleLocaleConstants
{
    // Properties
    private const propertyRootCategoryID = 'RootCategoryID';
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

    // Lizenzschlüssel (deckt sowohl Einmalkauf als auch Abo ab, siehe
    // ValidateLicenseKey) - eingetragen, aber erst nach Klick auf "Lizenz
    // aktivieren" tatsächlich geprüft/übernommen.
    private const propertyLicenseKey = 'LicenseKey';

    // Attribute
    private const attributeAvailableLanguagesCache = 'AvailableLanguagesCache';
    private const attributeAvailableLanguagesFetchedAt = 'AvailableLanguagesFetchedAt';

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

    // Zeitpunkt (Unix-Timestamp) des letzten TATSAECHLICHEN Sprachwechsels (zu einer
    // anderen als der bis dahin aktiven Sprache) - nur relevant ohne das Feature
    // "unlimited_language_switch" (siehe IsLanguageSwitchRateLimited), z.B. bei der
    // "Light"-Edition: dort ist ein Wechsel auf max. einen pro rollierendem 24h-
    // Fenster begrenzt. 0 = noch nie gewechselt.
    private const attributeLastLanguageSwitchAt = 'LastLanguageSwitchAt';

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

    // Sprachnamen (+ das Label der "Original"-Pseudo-Sprache) live in die gerade
    // aktive Gast-Sprache übersetzt - fuer die Kachel-Visualisierung (GetVisualizationTile),
    // getrennt vom AvailableLanguagesCache oben, der an der Admin-Konsolensprache haengt.
    private const attributeGuestLanguageNamesCache = 'GuestLanguageNamesCache';

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

    // Reservierter Pseudo-Sprachcode für den unangetasteten Rohtext beim ersten Scan
    // (Tippfehler inklusive). Nicht vom Gast über die Sprachauswahl erreichbar.
    // Objektnamen: ein Feld (Name des Objekts). Eigene Texte: zwei getrennte Felder
    // (Objektname als Kontext + eigentlicher Inhalt).
    private const langOriginalImport = 'ORIGINAL_IMPORT';
    private const fieldOriginalImportName = 'ORIGINAL_IMPORT_Name';
    private const langOriginalImportText = 'ORIGINAL_IMPORT_Text';

    // Präfixe für die Übersetzungsspalten von "Eigene Texte" - dort gibt es sowohl
    // Name- als auch Inhalts-Übersetzungen, die Sprachcodes allein wären sonst
    // mehrdeutig (z.B. "en" für Name UND Inhalt gleichzeitig).
    private const fieldNamePrefix = 'Name_';
    private const fieldTextPrefix = 'Text_';

    // Timer: Präfix als Salt auf den Namen, falls im jeweiligen IPS-System
    // bereits ein Timer/Objekt mit demselben Basisnamen existieren sollte.
    private const timerPrefix = 'IPSSL_TIMER_';
    private const timerIdentAutoRescan = 'AutoRescan';

    // Statuscodes
    private const STATUS_ROOT_CATEGORY_MISSING = 201;
    private const STATUS_UNNAMED_OBJECTS = 202;
    private const STATUS_TRANSLATE_ERROR = 203;
    private const STATUS_TRIAL_EXPIRED = 204;

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
