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

    // Attribute
    private const attributeCurrentLanguage = 'CurrentLanguage';
    private const attributeAvailableLanguagesCache = 'AvailableLanguagesCache';

    // Idents (Variablen / RequestAction)
    private const identLanguage = 'Language';
    private const identRescan = 'Rescan';
    private const identRefreshLanguageList = 'RefreshLanguageList';

    // Variablenprofil
    private const profileLanguage = '~IPSSL.Language';

    // Timer: Präfix als Salt auf den Namen, falls im jeweiligen IPS-System
    // bereits ein Timer/Objekt mit demselben Basisnamen existieren sollte.
    private const timerPrefix = 'IPSSL_TIMER_';
    private const timerIdentAutoRescan = 'AutoRescan';

    // Statuscodes
    private const STATUS_ROOT_CATEGORY_MISSING = 201;
    private const STATUS_TRANSLATE_ERROR = 203;
}

class GUIDs
{
    // --- Modul GUIDs (Instanzen) ---
    public const IPSSL_SimpleLocale = '{1A2E3892-FE35-9E4E-A3A8-B983B0C41F64}';
}
