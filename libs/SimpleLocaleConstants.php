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
    private const propertyAutoRescanInterval = 'AutoRescanInterval';
    private const propertyObjectNames = 'ObjectNames';
    private const propertyObjectTexts = 'ObjectTexts';

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
}

class GUIDs
{
    // --- Modul GUIDs (Instanzen) ---
    public const IPSSL_SimpleLocale = '{1A2E3892-FE35-9E4E-A3A8-B983B0C41F64}';
}
