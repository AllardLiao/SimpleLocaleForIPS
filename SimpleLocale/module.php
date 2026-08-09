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

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
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
                    $element['columns'] = $this->BuildListColumns($sourceLanguage, $targetLanguages, false);
                    $element['values'] = $this->DecodeRows(self::propertyObjectNames);
                    break;

                case self::propertyObjectTexts:
                    $element['columns'] = $this->BuildListColumns($sourceLanguage, $targetLanguages, true);
                    $element['values'] = $this->DecodeRows(self::propertyObjectTexts);
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
        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);

        foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {
            if (($row['ObjectID'] ?? null) === $ObjectID) {
                return $this->ResolveRowValue($row, $currentLanguage, $sourceLanguage, self::langOriginalImportText);
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

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);

        foreach ($this->DecodeRows(self::propertyObjectNames) as $row) {
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID === 0 || !@IPS_ObjectExists($objectID)) {
                continue;
            }

            IPS_SetName($objectID, $this->ResolveRowValue($row, $Language, $sourceLanguage, self::langOriginalImport));
        }

        foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {
            // Bei Links auf eine String-Variable ist ValueObjectID die Zielvariable,
            // die den eigentlichen Wert hält - sonst identisch mit ObjectID.
            $valueObjectID = (int) ($row['ValueObjectID'] ?? $row['ObjectID'] ?? 0);
            if ($valueObjectID === 0 || !@IPS_ObjectExists($valueObjectID)) {
                continue;
            }

            SetValueString($valueObjectID, $this->ResolveRowValue($row, $Language, $sourceLanguage, self::langOriginalImportText));
        }
    }

    // Fallback-Kette: gewünschte Sprache -> Quellsprache (bereinigt) -> Rohtext ($RawField)
    private function ResolveRowValue(array $Row, string $Language, string $SourceLanguage, string $RawField): string
    {
        if (($Row[$Language] ?? '') !== '') {
            return $Row[$Language];
        }
        if (($Row[$SourceLanguage] ?? '') !== '') {
            return $Row[$SourceLanguage];
        }

        return $Row[$RawField] ?? '';
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
        $this->WalkTree($rootID, $scannedNames, $scannedTexts, []);

        $objectNames = $this->MergeRows($this->DecodeRows(self::propertyObjectNames), $scannedNames);
        $objectTexts = $this->MergeRows($this->DecodeRows(self::propertyObjectTexts), $scannedTexts);

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
        $targetLanguages = $this->GetSelectedTargetLanguages();

        $objectNames = $this->FillMissingTranslations($objectNames, self::langOriginalImport, $sourceLanguage, $targetLanguages, false);
        $objectTexts = $this->FillMissingTranslations($objectTexts, self::langOriginalImportText, $sourceLanguage, $targetLanguages, true);

        IPS_SetProperty($this->InstanceID, self::propertyObjectNames, json_encode(array_values($objectNames)));
        IPS_SetProperty($this->InstanceID, self::propertyObjectTexts, json_encode(array_values($objectTexts)));
        IPS_ApplyChanges($this->InstanceID);

        // Kompletter Formular-Neuaufbau statt UpdateFormField: ein offenes Formular hat
        // sonst noch den alten (leeren) Stand im Speicher und würde ihn bei "Übernehmen"
        // über die gerade gespeicherten Scan-Ergebnisse zurückschreiben.
        $this->ReloadForm();
    }

    // $ParentPath enthält die Namen der Vorfahren ab der Root-Kategorie (ohne den
    // Namen des Objekts selbst), damit gleichnamige Texte an unterschiedlichen
    // Stellen im Baum unterscheidbar bleiben.
    private function WalkTree(int $ID, array &$ScannedNames, array &$ScannedTexts, array $ParentPath): void
    {
        foreach (IPS_GetChildrenIDs($ID) as $childID) {
            $object = IPS_GetObject($childID);
            $name = IPS_GetName($childID);
            $path = implode(' > ', $ParentPath);

            // Objekt-ID ist der eindeutige, stabile Schlüssel - Idents sind bei
            // handangelegten Objekten (Kategorien/Variablen über die Konsole) meist gar
            // nicht gesetzt.
            $ScannedNames[$childID] = [
                'ObjectID'                 => $childID,
                'Path'                     => $path,
                self::langOriginalImport   => $name,
            ];

            // Viele "Hinweis"-Objekte in der Kachel-Visualisierung sind Verknüpfungen
            // (Links) auf eine String-Variable an anderer Stelle im Baum, nicht die
            // Variable selbst - deren Wert (und beim Sprachwechsel: Schreibziel) ist
            // dann die verlinkte Zielvariable, nicht das Link-Objekt.
            $stringVariableID = $this->ResolveStringVariableID($childID, $object);
            if ($stringVariableID !== null) {
                $ScannedTexts[$childID] = [
                    'ObjectID'                       => $childID,
                    'ValueObjectID'                  => $stringVariableID,
                    'Path'                           => $path,
                    // Name des Objekts als Kontext (z.B. "Hinweis:"), damit gleichnamige
                    // Inhalte an unterschiedlichen Stellen unterscheidbar bleiben. Wie
                    // der Inhalt beim ersten Fund eingefroren, nicht die Live-Anzeige.
                    self::fieldOriginalImportName    => $name,
                    self::langOriginalImportText     => GetValueString($stringVariableID),
                ];
            }

            $this->WalkTree($childID, $ScannedNames, $ScannedTexts, array_merge($ParentPath, [$name]));
        }
    }

    // Ermittelt die tatsächliche String-Variablen-ID für ein Scan-Objekt: entweder das
    // Objekt selbst (wenn es eine String-Variable ist) oder - falls es eine Verknüpfung
    // ist - deren Zielvariable, sofern diese ebenfalls vom Typ String ist. Liefert null,
    // wenn das Objekt keine (verlinkte) String-Variable ist.
    private function ResolveStringVariableID(int $ObjectID, array $Object): ?int
    {
        $variableID = null;

        if ($Object['ObjectType'] === OBJECTTYPE_VARIABLE) {
            $variableID = $ObjectID;
        } elseif ($Object['ObjectType'] === OBJECTTYPE_LINK) {
            $targetID = IPS_GetLink($ObjectID)['TargetID'];
            if ($targetID > 0 && IPS_VariableExists($targetID)) {
                $variableID = $targetID;
            }
        }

        if ($variableID === null) {
            return null;
        }

        return IPS_GetVariable($variableID)['VariableType'] === VARIABLETYPE_STRING ? $variableID : null;
    }

    // Merged bereits gespeicherte Zeilen mit frisch gescannten Objekt-IDs. ORIGINAL_IMPORT
    // und alle Übersetzungen bleiben für bereits bekannte Objekte unangetastet (nur Pfad
    // und Ziel-Variablen-ID werden aktualisiert, falls sich der Baum geändert hat) - so
    // bleibt der roh vorgefundene Text auch dann erhalten, wenn die Live-Anzeige gerade
    // in einer anderen Sprache steht.
    private function MergeRows(array $ExistingRows, array $ScannedByObjectID): array
    {
        $result = [];
        foreach ($ExistingRows as $row) {
            $objectID = $row['ObjectID'] ?? null;
            if ($objectID !== null && isset($ScannedByObjectID[$objectID])) {
                $row['Path'] = $ScannedByObjectID[$objectID]['Path'];
                if (isset($ScannedByObjectID[$objectID]['ValueObjectID'])) {
                    $row['ValueObjectID'] = $ScannedByObjectID[$objectID]['ValueObjectID'];
                }
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

    // Objektnamen sind meist kurz (Kategorie-/Kachel-Titel) - dort lohnt sich die
    // separate Google-Bereinigungsrunde nicht, Original-Import dient direkt als
    // Quellsprachen-Basis. Eigene Texte (oft längere Hinweistexte) durchlaufen
    // zusätzlich einen ersten Durchlauf ORIGINAL_IMPORT -> Quellsprache ohne
    // erzwungene Quellsprache bei Google - dadurch erkennt Google die tatsächliche
    // Sprache selbst und poliert nebenbei Tippfehler im rohen Originaltext.
    private function FillMissingTranslations(array $Rows, string $RawField, string $SourceLanguage, array $TargetLanguages, bool $CleanupSourceFromOriginal): array
    {
        $baseField = $RawField;

        if ($CleanupSourceFromOriginal) {
            $Rows = $this->FillLanguageColumn($Rows, $RawField, $SourceLanguage, null);
            $baseField = $SourceLanguage;
        }

        foreach ($TargetLanguages as $language) {
            if ($language === $SourceLanguage) {
                continue;
            }
            $Rows = $this->FillLanguageColumn($Rows, $baseField, $language, $SourceLanguage);
        }

        return $Rows;
    }

    // Übersetzt für alle Zeilen, bei denen $ToField noch leer ist, den Text aus
    // $FromField nach $ToField (gebatcht in einem API-Aufruf). $ForceSource = null
    // lässt Google die Quellsprache selbst erkennen.
    private function FillLanguageColumn(array $Rows, string $FromField, string $ToField, ?string $ForceSource): array
    {
        $pending = [];
        foreach ($Rows as $index => $row) {
            $fromText = $row[$FromField] ?? '';
            if ($fromText !== '' && ($row[$ToField] ?? '') === '') {
                $pending[$index] = $fromText;
            }
        }

        if ($pending === []) {
            return $Rows;
        }

        $translated = $this->TranslateBatch(array_values($pending), $ForceSource, $ToField);

        $i = 0;
        foreach (array_keys($pending) as $index) {
            $Rows[$index][$ToField] = $translated[$i] ?? '';
            $i++;
        }

        return $Rows;
    }

    // $Source = null lässt Google die Quellsprache des Texts selbst erkennen.
    private function TranslateBatch(array $Texts, ?string $Source, string $Target): array
    {
        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '' || $Texts === []) {
            return array_fill(0, count($Texts), '');
        }

        $body = [
            'q'      => $Texts,
            'target' => $Target,
            'format' => 'text',
        ];
        if ($Source !== null) {
            $body['source'] = $Source;
        }
        $payload = json_encode($body);

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
    // der Quell-/Zielsprachen zusammen (Symcon-Formulare kennen keine Spalten mit
    // dynamischer Anzahl, daher wird das Formular bei jedem Öffnen neu erzeugt).
    // "Eigene Texte" bekommt zusätzlich eine Name-Kontextspalte (welche Kachel ist
    // das?) und die editierbare Quellsprachen-Spalte (Basis für die Zielsprachen,
    // da Inhalte oft länger sind und von der Google-Bereinigung profitieren).
    // "Objektnamen" verzichtet auf beides - Original-Import dient dort direkt als
    // Quellsprachen-Basis.
    private function BuildListColumns(string $SourceLanguage, array $TargetLanguages, bool $IsObjectTexts): array
    {
        $columns = [
            ['caption' => 'Objekt-ID', 'name' => 'ObjectID', 'width' => '80px'],
            ['caption' => $this->Translate('Pfad'), 'name' => 'Path', 'width' => '200px'],
        ];

        if ($IsObjectTexts) {
            $columns[] = [
                'caption' => $this->Translate('Original-Import (Name)'),
                'name'    => self::fieldOriginalImportName,
                'width'   => '150px',
            ];
            $columns[] = [
                'caption' => $this->Translate('Original-Import (Text)'),
                'name'    => self::langOriginalImportText,
                'width'   => '200px',
            ];
        } else {
            $columns[] = ['caption' => $this->Translate('Original-Import'), 'name' => self::langOriginalImport, 'width' => '200px'];
        }

        if ($IsObjectTexts) {
            $columns[] = [
                'caption' => sprintf('%s (%s)', $this->GetLanguageDisplayName($SourceLanguage), $this->Translate('Quelle')),
                'name'    => $SourceLanguage,
                'width'   => '200px',
                'add'     => '',
                'edit'    => ['type' => 'ValidationTextBox'],
            ];
        }

        foreach ($TargetLanguages as $language) {
            if ($language === $SourceLanguage) {
                continue;
            }
            $columns[] = [
                'caption' => $this->GetLanguageDisplayName($language),
                'name'    => $language,
                'width'   => '200px',
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
