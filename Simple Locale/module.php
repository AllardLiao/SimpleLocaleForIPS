<?php

declare(strict_types=1);

class SimpleLocale extends IPSModuleStrict
{
    private const STATUS_ROOT_CATEGORY_MISSING = 201;
    private const STATUS_TRANSLATE_ERROR = 203;

    // Fallback-Liste, solange noch keine Sprachliste von Google geladen wurde
    private const DEFAULT_LANGUAGES = [
        ['code' => 'de', 'name' => 'Deutsch'],
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'fr', 'name' => 'Français'],
        ['code' => 'es', 'name' => 'Español'],
        ['code' => 'it', 'name' => 'Italiano'],
        ['code' => 'nl', 'name' => 'Nederlands'],
    ];

    public function Create():void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('RootCategoryID', 0);
        $this->RegisterPropertyString('SourceLanguage', 'de');
        $this->RegisterPropertyString('TargetLanguages', '[]');
        $this->RegisterPropertyString('GoogleTranslateAPIKey', '');
        $this->RegisterPropertyInteger('AutoRescanInterval', 0);
        $this->RegisterPropertyString('ObjectNames', '[]');
        $this->RegisterPropertyString('ObjectTexts', '[]');

        $this->RegisterAttributeString('CurrentLanguage', '');
        $this->RegisterAttributeString('AvailableLanguagesCache', '[]');

        $this->RegisterVariableString('Language', $this->Translate('Sprache'), '~IPSSL.Language');
        $this->EnableAction('Language');

        $this->RegisterTimer('AutoRescan', 0, 'IPSSL_Rescan($_IPS[\'TARGET\']);');
    }

    public function Destroy():void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges():void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $rootID = $this->ReadPropertyInteger('RootCategoryID');
        if ($rootID === 0 || !@IPS_ObjectExists($rootID)) {
            $this->SetStatus(self::STATUS_ROOT_CATEGORY_MISSING);
        } else {
            $this->SetStatus(102);
        }

        $this->UpdateLanguageProfile();

        // Beim allerersten Aufbau: aktive Sprache auf die Basissprache setzen
        if ($this->ReadAttributeString('CurrentLanguage') === '') {
            $sourceLanguage = $this->ReadPropertyString('SourceLanguage');
            $this->WriteAttributeString('CurrentLanguage', $sourceLanguage);
            $this->SetValue('Language', $sourceLanguage);
        }

        $interval = $this->ReadPropertyInteger('AutoRescanInterval');
        $this->SetTimerInterval('AutoRescan', $interval > 0 ? $interval * 60 * 1000 : 0);
    }

    public function RequestAction($Ident, $Value):void
    {
        switch ($Ident) {
            case 'Language':
                $this->ApplyLanguage((string) $Value);
                break;

            case 'Rescan':
                $this->Rescan();
                break;

            case 'RefreshLanguageList':
                $this->FetchSupportedLanguages();
                $this->ReloadForm();
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    public function GetConfigurationForm():string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $targetLanguages = json_decode($this->ReadPropertyString('TargetLanguages'), true);
        if (!is_array($targetLanguages)) {
            $targetLanguages = [];
        }

        $languageOptions = $this->BuildLanguageOptions();

        foreach ($form['elements'] as &$element) {
            switch ($element['name'] ?? '') {
                case 'SourceLanguage':
                    $element['options'] = $languageOptions;
                    break;

                case 'TargetLanguages':
                    $element['options'] = $languageOptions;
                    break;

                case 'ObjectNames':
                    $element['columns'] = $this->BuildListColumns('SourceName', $this->Translate('Objektname'), $targetLanguages);
                    break;

                case 'ObjectTexts':
                    $element['columns'] = $this->BuildListColumns('SourceContent', $this->Translate('Inhalt'), $targetLanguages);
                    break;
            }
        }
        unset($element);

        return json_encode($form);
    }

    // Symcon registriert öffentliche Methoden automatisch als globale Funktion
    // "<prefix>_<Methodenname>" (Prefix "IPSSL" aus module.json) - daher genügt
    // hier die public-Methode, ein eigenes "function IPSSL_..." ist nicht nötig.
    public function TranslateText(string $Ident): string
    {
        $currentLanguage = $this->ReadAttributeString('CurrentLanguage');

        foreach ($this->DecodeRows('ObjectTexts') as $row) {
            if (($row['Ident'] ?? null) === $Ident) {
                $value = $row[$currentLanguage] ?? '';

                return $value !== '' ? $value : ($row['SourceContent'] ?? '');
            }
        }

        return '';
    }

    public function Rescan(): void
    {
        $this->ScanRootTree();
    }

    private function ApplyLanguage(string $Language): void
    {
        $this->SetValue('Language', $Language);
        $this->WriteAttributeString('CurrentLanguage', $Language);

        foreach ($this->DecodeRows('ObjectNames') as $row) {
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID === 0 || !@IPS_ObjectExists($objectID)) {
                continue;
            }

            $name = $row[$Language] ?? '';
            IPS_SetName($objectID, $name !== '' ? $name : ($row['SourceName'] ?? ''));
        }

        foreach ($this->DecodeRows('ObjectTexts') as $row) {
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID === 0 || !@IPS_ObjectExists($objectID)) {
                continue;
            }

            $content = $row[$Language] ?? '';
            SetValueString($objectID, $content !== '' ? $content : ($row['SourceContent'] ?? ''));
        }
    }

    private function ScanRootTree(): void
    {
        $rootID = $this->ReadPropertyInteger('RootCategoryID');
        if ($rootID === 0 || !@IPS_ObjectExists($rootID)) {
            $this->SetStatus(self::STATUS_ROOT_CATEGORY_MISSING);
            return;
        }

        $scannedNames = [];
        $scannedTexts = [];
        $this->WalkTree($rootID, $scannedNames, $scannedTexts);

        $objectNames = $this->MergeRows($this->DecodeRows('ObjectNames'), $scannedNames, 'SourceName');
        $objectTexts = $this->MergeRows($this->DecodeRows('ObjectTexts'), $scannedTexts, 'SourceContent');

        $sourceLanguage = $this->ReadPropertyString('SourceLanguage');
        $targetLanguages = json_decode($this->ReadPropertyString('TargetLanguages'), true);
        if (!is_array($targetLanguages)) {
            $targetLanguages = [];
        }

        $objectNames = $this->FillMissingTranslations($objectNames, 'SourceName', $sourceLanguage, $targetLanguages);
        $objectTexts = $this->FillMissingTranslations($objectTexts, 'SourceContent', $sourceLanguage, $targetLanguages);

        IPS_SetProperty($this->InstanceID, 'ObjectNames', json_encode(array_values($objectNames)));
        IPS_SetProperty($this->InstanceID, 'ObjectTexts', json_encode(array_values($objectTexts)));
        IPS_ApplyChanges($this->InstanceID);

        $this->UpdateFormField('ObjectNames', 'values', json_encode(array_values($objectNames)));
        $this->UpdateFormField('ObjectTexts', 'values', json_encode(array_values($objectTexts)));
    }

    private function WalkTree(int $ID, array &$ScannedNames, array &$ScannedTexts): void
    {
        foreach (IPS_GetChildrenIDs($ID) as $childID) {
            $object = IPS_GetObject($childID);
            $ident = $object['ObjectIdent'];

            if ($ident === '') {
                $this->SendDebug('ScanRootTree', sprintf('Objekt %d ("%s") hat keinen Ident und wird übersprungen', $childID, IPS_GetName($childID)), 0);
            } else {
                $ScannedNames[$ident] = [
                    'Ident'      => $ident,
                    'ObjectID'   => $childID,
                    'SourceName' => IPS_GetName($childID),
                ];

                if ($object['ObjectType'] === OBJECTTYPE_VARIABLE) {
                    $variable = IPS_GetVariable($childID);
                    if ($variable['VariableType'] === VARIABLETYPE_STRING) {
                        $ScannedTexts[$ident] = [
                            'Ident'         => $ident,
                            'ObjectID'      => $childID,
                            'SourceContent' => GetValueString($childID),
                        ];
                    }
                }
            }

            $this->WalkTree($childID, $ScannedNames, $ScannedTexts);
        }
    }

    // Merged bereits gespeicherte Zeilen (inkl. manueller Übersetzungen) mit frisch gescannten Idents
    private function MergeRows(array $ExistingRows, array $ScannedByIdent, string $SourceField): array
    {
        $result = [];
        foreach ($ExistingRows as $row) {
            $ident = $row['Ident'] ?? null;
            if ($ident !== null && isset($ScannedByIdent[$ident])) {
                $row['ObjectID'] = $ScannedByIdent[$ident]['ObjectID'];
                $row[$SourceField] = $ScannedByIdent[$ident][$SourceField];
                unset($ScannedByIdent[$ident]);
            }
            $result[] = $row;
        }

        // verbleibende, bisher unbekannte Idents neu anhängen
        foreach ($ScannedByIdent as $newRow) {
            $result[] = $newRow;
        }

        return $result;
    }

    private function FillMissingTranslations(array $Rows, string $SourceField, string $SourceLanguage, array $TargetLanguages): array
    {
        if ($TargetLanguages === []) {
            return $Rows;
        }

        foreach ($Rows as &$row) {
            $sourceText = $row[$SourceField] ?? '';
            if ($sourceText === '') {
                continue;
            }

            foreach ($TargetLanguages as $language) {
                if ($language === $SourceLanguage) {
                    continue;
                }
                if (($row[$language] ?? '') !== '') {
                    continue;
                }

                $translated = $this->TranslateBatch([$sourceText], $SourceLanguage, $language);
                $row[$language] = $translated[0] ?? '';
            }
        }
        unset($row);

        return $Rows;
    }

    private function TranslateBatch(array $Texts, string $Source, string $Target): array
    {
        $apiKey = $this->ReadPropertyString('GoogleTranslateAPIKey');
        if ($apiKey === '' || $Texts === []) {
            return array_fill(0, count($Texts), '');
        }

        $payload = json_encode([
            'q'      => $Texts,
            'source' => $Source,
            'target' => $Target,
            'format' => 'text',
        ]);

        $response = $this->CallGoogleTranslateAPI(
            'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($apiKey),
            $payload
        );

        if ($response === null) {
            return array_fill(0, count($Texts), '');
        }

        $decoded = json_decode($response, true);
        $translations = $decoded['data']['translations'] ?? null;
        if (!is_array($translations)) {
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return array_fill(0, count($Texts), '');
        }

        return array_map(function ($entry) {
            return $entry['translatedText'] ?? '';
        }, $translations);
    }

    private function FetchSupportedLanguages(): void
    {
        $apiKey = $this->ReadPropertyString('GoogleTranslateAPIKey');
        if ($apiKey === '') {
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return;
        }

        $target = $this->ReadPropertyString('SourceLanguage');
        $url = 'https://translation.googleapis.com/language/translate/v2/languages'
            . '?key=' . urlencode($apiKey)
            . '&target=' . urlencode($target);

        $response = $this->CallGoogleTranslateAPI($url, null);
        if ($response === null) {
            return;
        }

        $decoded = json_decode($response, true);
        $languages = $decoded['data']['languages'] ?? null;
        if (!is_array($languages)) {
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return;
        }

        $result = array_map(function ($entry) {
            return [
                'code' => $entry['language'] ?? '',
                'name' => $entry['name'] ?? ($entry['language'] ?? ''),
            ];
        }, $languages);

        $this->WriteAttributeString('AvailableLanguagesCache', json_encode($result));
    }

    // Gemeinsamer HTTP-Client für die Google Cloud Translate API (GET ohne Body, POST mit JSON-Body)
    private function CallGoogleTranslateAPI(string $Url, ?string $JsonBody): ?string
    {
        $curl = curl_init($Url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);

        if ($JsonBody !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $JsonBody);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $httpCode >= 400 || $error !== '') {
            $this->SendDebug('GoogleTranslate', sprintf('HTTP %s, Fehler: %s, Antwort: %s', $httpCode, $error, (string) $response), 0);
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return null;
        }

        return $response;
    }

    private function UpdateLanguageProfile(): void
    {
        $profileName = '~IPSSL.Language';
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, VARIABLETYPE_STRING);
        }

        foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
            IPS_SetVariableProfileAssociation($profileName, $association['Value'], '', '', -1);
        }

        $languages = array_merge(
            [$this->ReadPropertyString('SourceLanguage')],
            json_decode($this->ReadPropertyString('TargetLanguages'), true) ?: []
        );
        $languages = array_unique($languages);

        foreach ($languages as $code) {
            IPS_SetVariableProfileAssociation($profileName, $code, $this->GetLanguageDisplayName($code), '', -1);
        }
    }

    // Baut die Spalten für die "ObjectNames"/"ObjectTexts"-Listen dynamisch anhand
    // der aktuell ausgewählten Zielsprachen zusammen (Symcon-Formulare kennen keine
    // Spalten mit dynamischer Anzahl, daher wird das Formular bei jedem Öffnen neu erzeugt).
    private function BuildListColumns(string $SourceField, string $SourceCaption, array $TargetLanguages): array
    {
        $columns = [
            ['caption' => 'Ident', 'name' => 'Ident', 'width' => '150px'],
            ['caption' => $SourceCaption, 'name' => $SourceField, 'width' => '250px'],
        ];

        foreach ($TargetLanguages as $language) {
            $columns[] = [
                'caption' => $this->GetLanguageDisplayName($language),
                'name'    => $language,
                'width'   => '250px',
                'add'     => '',
                'edit'    => ['type' => 'ValidationTextBox'],
            ];
        }

        return $columns;
    }

    private function BuildLanguageOptions(): array
    {
        $languages = $this->GetKnownLanguages();

        return array_map(function ($language) {
            return [
                'caption' => $language['name'],
                'value'   => $language['code'],
            ];
        }, $languages);
    }

    private function GetLanguageDisplayName(string $Code): string
    {
        foreach ($this->GetKnownLanguages() as $language) {
            if ($language['code'] === $Code) {
                return $language['name'];
            }
        }

        return $Code;
    }

    private function GetKnownLanguages(): array
    {
        $cached = json_decode($this->ReadAttributeString('AvailableLanguagesCache'), true);
        if (!is_array($cached) || $cached === []) {
            return self::DEFAULT_LANGUAGES;
        }

        $byCode = [];
        foreach (array_merge(self::DEFAULT_LANGUAGES, $cached) as $language) {
            $byCode[$language['code']] = $language;
        }
        ksort($byCode);

        return array_values($byCode);
    }

    private function DecodeRows(string $PropertyName): array
    {
        $rows = json_decode($this->ReadPropertyString($PropertyName), true);

        return is_array($rows) ? $rows : [];
    }
}
