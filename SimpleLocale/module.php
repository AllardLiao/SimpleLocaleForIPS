<?php

declare(strict_types=1);

// Include Helper classes/traits.
require_once __DIR__ . '/../libs/SimpleLocaleConstants.php';

use SimpleLocaleConstants\GUIDs;

class SimpleLocale extends IPSModuleStrict
{
    use SimpleLocaleConstants\SimpleLocaleConstants;

    // Fallback-Liste, solange noch keine Sprachliste von Google geladen wurde
    private const DEFAULT_LANGUAGES = [
        ['code' => 'de', 'name' => 'Deutsch'],
        ['code' => 'en', 'name' => 'English'],
        ['code' => 'fr', 'name' => 'Français'],
        ['code' => 'es', 'name' => 'Español'],
        ['code' => 'it', 'name' => 'Italiano'],
        ['code' => 'nl', 'name' => 'Nederlands'],
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(self::propertyRootCategoryID, 0);
        $this->RegisterPropertyString(self::propertySourceLanguage, 'de');
        $this->RegisterPropertyString(self::propertyTargetLanguages, '[]');
        $this->RegisterPropertyString(self::propertyGoogleTranslateAPIKey, '');
        $this->RegisterPropertyInteger(self::propertyAutoRescanInterval, 0);
        $this->RegisterPropertyString(self::propertyObjectNames, '[]');
        $this->RegisterPropertyString(self::propertyObjectTexts, '[]');

        $this->RegisterAttributeString(self::attributeCurrentLanguage, '');
        $this->RegisterAttributeString(self::attributeAvailableLanguagesCache, '[]');

        // Profil muss existieren, bevor die Variable damit registriert werden kann
        $this->EnsureLanguageProfileExists();

        $this->RegisterVariableString(self::identLanguage, $this->Translate('Sprache'), self::profileLanguage);
        $this->EnableAction(self::identLanguage);

        $this->RegisterTimer($this->GetAutoRescanTimerIdent(), 0, 'IPSSL_Rescan($_IPS[\'TARGET\']);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $rootID = $this->ReadPropertyInteger(self::propertyRootCategoryID);
        if ($rootID === 0 || !@IPS_ObjectExists($rootID)) {
            $this->SetStatus(self::STATUS_ROOT_CATEGORY_MISSING);
        } else {
            $this->SetStatus(102);
        }

        $this->UpdateLanguageProfile();

        // Beim allerersten Aufbau: aktive Sprache auf die Basissprache setzen
        if ($this->ReadAttributeString(self::attributeCurrentLanguage) === '') {
            $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
            $this->WriteAttributeString(self::attributeCurrentLanguage, $sourceLanguage);
            $this->SetValue(self::identLanguage, $sourceLanguage);
        }

        $interval = $this->ReadPropertyInteger(self::propertyAutoRescanInterval);
        $this->SetTimerInterval($this->GetAutoRescanTimerIdent(), $interval > 0 ? $interval * 60 * 1000 : 0);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case self::identLanguage:
                $this->ApplyLanguage((string) $Value);
                break;

            case self::identRescan:
                $this->Rescan();
                break;

            case self::identRefreshLanguageList:
                $this->FetchSupportedLanguages();
                $this->ReloadForm();
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $targetLanguages = $this->GetSelectedTargetLanguages();
        $languageOptions = $this->BuildLanguageOptions();

        foreach ($form['elements'] as &$element) {
            switch ($element['name'] ?? '') {
                case self::propertySourceLanguage:
                    $element['options'] = $languageOptions;
                    break;

                case self::propertyTargetLanguages:
                    $element['values'] = $this->BuildTargetLanguageRows();
                    break;

                case self::propertyObjectNames:
                    $element['columns'] = $this->BuildListColumns('SourceName', $this->Translate('Objektname'), $targetLanguages);
                    break;

                case self::propertyObjectTexts:
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
    public function TranslateText(int $ObjectID): string
    {
        $currentLanguage = $this->ReadAttributeString(self::attributeCurrentLanguage);

        foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {
            if (($row['ObjectID'] ?? null) === $ObjectID) {
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
        $this->SetValue(self::identLanguage, $Language);
        $this->WriteAttributeString(self::attributeCurrentLanguage, $Language);

        foreach ($this->DecodeRows(self::propertyObjectNames) as $row) {
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID === 0 || !@IPS_ObjectExists($objectID)) {
                continue;
            }

            $name = $row[$Language] ?? '';
            IPS_SetName($objectID, $name !== '' ? $name : ($row['SourceName'] ?? ''));
        }

        foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {
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
        $rootID = $this->ReadPropertyInteger(self::propertyRootCategoryID);
        if ($rootID === 0 || !@IPS_ObjectExists($rootID)) {
            $this->SetStatus(self::STATUS_ROOT_CATEGORY_MISSING);
            return;
        }

        $scannedNames = [];
        $scannedTexts = [];
        $this->WalkTree($rootID, $scannedNames, $scannedTexts);

        $objectNames = $this->MergeRows($this->DecodeRows(self::propertyObjectNames), $scannedNames, 'SourceName');
        $objectTexts = $this->MergeRows($this->DecodeRows(self::propertyObjectTexts), $scannedTexts, 'SourceContent');

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
        $targetLanguages = $this->GetSelectedTargetLanguages();

        $objectNames = $this->FillMissingTranslations($objectNames, 'SourceName', $sourceLanguage, $targetLanguages);
        $objectTexts = $this->FillMissingTranslations($objectTexts, 'SourceContent', $sourceLanguage, $targetLanguages);

        IPS_SetProperty($this->InstanceID, self::propertyObjectNames, json_encode(array_values($objectNames)));
        IPS_SetProperty($this->InstanceID, self::propertyObjectTexts, json_encode(array_values($objectTexts)));
        IPS_ApplyChanges($this->InstanceID);

        $this->UpdateFormField(self::propertyObjectNames, 'values', json_encode(array_values($objectNames)));
        $this->UpdateFormField(self::propertyObjectTexts, 'values', json_encode(array_values($objectTexts)));
    }

    private function WalkTree(int $ID, array &$ScannedNames, array &$ScannedTexts): void
    {
        foreach (IPS_GetChildrenIDs($ID) as $childID) {
            $object = IPS_GetObject($childID);

            // Objekt-ID ist der eindeutige, stabile Schlüssel - Idents sind bei
            // handangelegten Objekten (Kategorien/Variablen über die Konsole) meist gar
            // nicht gesetzt.
            $ScannedNames[$childID] = [
                'ObjectID'   => $childID,
                'SourceName' => IPS_GetName($childID),
            ];

            if ($object['ObjectType'] === OBJECTTYPE_VARIABLE) {
                $variable = IPS_GetVariable($childID);
                if ($variable['VariableType'] === VARIABLETYPE_STRING) {
                    $ScannedTexts[$childID] = [
                        'ObjectID'      => $childID,
                        'SourceContent' => GetValueString($childID),
                    ];
                }
            }

            $this->WalkTree($childID, $ScannedNames, $ScannedTexts);
        }
    }

    // Merged bereits gespeicherte Zeilen (inkl. manueller Übersetzungen) mit frisch gescannten Objekt-IDs
    private function MergeRows(array $ExistingRows, array $ScannedByObjectID, string $SourceField): array
    {
        $result = [];
        foreach ($ExistingRows as $row) {
            $objectID = $row['ObjectID'] ?? null;
            if ($objectID !== null && isset($ScannedByObjectID[$objectID])) {
                $row[$SourceField] = $ScannedByObjectID[$objectID][$SourceField];
                unset($ScannedByObjectID[$objectID]);
            }
            $result[] = $row;
        }

        // verbleibende, bisher unbekannte Objekt-IDs neu anhängen
        foreach ($ScannedByObjectID as $newRow) {
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
        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
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
        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return;
        }

        $target = $this->ReadPropertyString(self::propertySourceLanguage);
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

        $this->WriteAttributeString(self::attributeAvailableLanguagesCache, json_encode($result));
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

    private function EnsureLanguageProfileExists(): void
    {
        if (!IPS_VariableProfileExists(self::profileLanguage)) {
            IPS_CreateVariableProfile(self::profileLanguage, VARIABLETYPE_STRING);
        }
    }

    private function UpdateLanguageProfile(): void
    {
        $profileName = self::profileLanguage;
        $this->EnsureLanguageProfileExists();

        foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
            IPS_SetVariableProfileAssociation($profileName, $association['Value'], '', '', -1);
        }

        $languages = array_merge(
            [$this->ReadPropertyString(self::propertySourceLanguage)],
            $this->GetSelectedTargetLanguages()
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
            ['caption' => 'Objekt-ID', 'name' => 'ObjectID', 'width' => '100px'],
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

    // "TargetLanguages" ist eine List mit einer CheckBox-Spalte (Mehrfachauswahl-Ersatz,
    // da form.json keinen "SelectMultiple"-Typ kennt) - Property speichert Zeilen
    // [{"code": "en", "name": "English", "enabled": true}, ...].
    private function GetSelectedTargetLanguages(): array
    {
        $rows = json_decode($this->ReadPropertyString(self::propertyTargetLanguages), true);
        if (!is_array($rows)) {
            return [];
        }

        $codes = [];
        foreach ($rows as $row) {
            if (($row['enabled'] ?? false) === true && isset($row['code'])) {
                $codes[] = $row['code'];
            }
        }

        return $codes;
    }

    private function BuildTargetLanguageRows(): array
    {
        $enabledByCode = [];
        foreach ($this->GetSelectedTargetLanguages() as $code) {
            $enabledByCode[$code] = true;
        }

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
        $rows = [];
        foreach ($this->GetKnownLanguages() as $language) {
            if ($language['code'] === $sourceLanguage) {
                continue;
            }
            $rows[] = [
                'code'    => $language['code'],
                'name'    => $language['name'],
                'enabled' => $enabledByCode[$language['code']] ?? false,
            ];
        }

        return $rows;
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
        $cached = json_decode($this->ReadAttributeString(self::attributeAvailableLanguagesCache), true);
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

    // Salt auf den Timer-Namen (Präfix + Instanz-ID), falls im System bereits
    // ein Timer/Objekt mit demselben Basisnamen existieren sollte.
    private function GetAutoRescanTimerIdent(): string
    {
        return self::timerPrefix . $this->InstanceID . self::timerIdentAutoRescan;
    }
}
