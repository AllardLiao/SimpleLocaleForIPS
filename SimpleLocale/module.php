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
        $this->RegisterAttributeInteger(self::attributeAvailableLanguagesFetchedAt, 0);

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

            case self::identShowApiKeyWarning:
                // Prüft die tatsächliche Ursache serverseitig nach, statt sich allein
                // auf den (nur indirekten) Hinweis "hinzugefügte Zeile hat leeren Code"
                // aus form.json zu verlassen.
                if ($this->ReadPropertyString(self::propertyGoogleTranslateAPIKey) === '') {
                    $this->UpdateFormField('ApiKeyMissingPopup', 'visible', true);
                } elseif (!$this->HasCachedLanguages()) {
                    $this->UpdateFormField('ApiKeyInvalidPopup', 'visible', true);
                }
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    public function GetConfigurationForm(): string
    {
        // Sprachliste höchstens 1x/Tag automatisch aktualisieren, immer nur beim
        // (frischen) Öffnen des Formulars - die Zielsprachen-Liste selbst wird dadurch
        // nie mehr angefasst (sie wächst nur noch über den eingebauten "Hinzufügen"-
        // Button, Zeile für Zeile), nur die Dropdown-Optionen für neue Zeilen.
        $this->RefreshAvailableLanguagesIfStale();

        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
        $targetLanguages = $this->GetSelectedTargetLanguages();
        $languageOptions = $this->BuildLanguageOptions();
        $targetLanguageOptions = $this->BuildTargetLanguageOptions($sourceLanguage);

        foreach ($form['elements'] as &$element) {
            switch ($element['name'] ?? '') {
                case self::propertySourceLanguage:
                    $element['options'] = $languageOptions;
                    break;

                case self::propertyTargetLanguages:
                    $element['columns'][0]['edit']['options'] = $targetLanguageOptions;
                    $element['values'] = $this->DecodeRows(self::propertyTargetLanguages);
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
                return $this->ResolveRowValue(
                    $row,
                    self::fieldTextPrefix . $currentLanguage,
                    self::fieldTextPrefix . $sourceLanguage,
                    self::langOriginalImportText
                );
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
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID !== 0 && @IPS_ObjectExists($objectID)) {
                IPS_SetName($objectID, $this->ResolveRowValue(
                    $row,
                    self::fieldNamePrefix . $Language,
                    self::fieldNamePrefix . $sourceLanguage,
                    self::fieldOriginalImportName
                ));
            }

            // Bei Links auf eine String-Variable ist ValueObjectID die Zielvariable,
            // die den eigentlichen Wert hält - sonst identisch mit ObjectID.
            $valueObjectID = (int) ($row['ValueObjectID'] ?? $objectID);
            if ($valueObjectID === 0 || !@IPS_ObjectExists($valueObjectID)) {
                continue;
            }

            SetValueString($valueObjectID, $this->ResolveRowValue(
                $row,
                self::fieldTextPrefix . $Language,
                self::fieldTextPrefix . $sourceLanguage,
                self::langOriginalImportText
            ));
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

        $objectNames = $this->FillMissingTranslations($objectNames, [
            ['raw' => self::langOriginalImport, 'prefix' => '', 'capitalizeFirst' => true],
        ], $sourceLanguage, $targetLanguages);

        $objectTexts = $this->FillMissingTranslations($objectTexts, [
            ['raw' => self::fieldOriginalImportName, 'prefix' => self::fieldNamePrefix, 'capitalizeFirst' => true],
            ['raw' => self::langOriginalImportText, 'prefix' => self::fieldTextPrefix, 'capitalizeFirst' => false],
        ], $sourceLanguage, $targetLanguages);

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

    // Übersetzt eine oder mehrere Feldgruppen einer Zeilenliste. Jede Gruppe hat einen
    // rohen Ausgangstext ('raw', z.B. ORIGINAL_IMPORT) und ein Präfix für die daraus
    // abgeleiteten Sprachspalten ('prefix', z.B. "Text_" -> "Text_de", "Text_en", ...;
    // leeres Präfix für Objektnamen, die nur eine Feldgruppe haben).
    // Ablauf je Gruppe: (1) roh -> Quellsprache, ohne erzwungene Quellsprache bei
    // Google - dadurch erkennt Google die tatsächliche Sprache selbst und poliert
    // nebenbei Tippfehler im rohen Originaltext. (2) Quellsprache -> jede ausgewählte
    // Zielsprache, jetzt mit bekannter, korrekter Quellsprache.
    // $FieldGroups[]['capitalizeFirst']: Google großschreibt den ersten Buchstaben bei
    // kurzen Einzelwörtern/Titeln (im Gegensatz zu vollständigen Sätzen) nicht
    // zuverlässig - für Namen/Titel wird das Ergebnis daher nachträglich korrigiert.
    // Nicht für freien Inhaltstext (kann HTML enthalten, erster Buchstabe ist dort
    // nicht zwangsläufig ein Satzanfang).
    private function FillMissingTranslations(array $Rows, array $FieldGroups, string $SourceLanguage, array $TargetLanguages): array
    {
        foreach ($FieldGroups as $group) {
            $rawField = $group['raw'];
            $sourceField = $group['prefix'] . $SourceLanguage;
            $capitalizeFirst = $group['capitalizeFirst'] ?? false;

            $Rows = $this->FillLanguageColumn($Rows, $rawField, $sourceField, null, $SourceLanguage, $capitalizeFirst);

            foreach ($TargetLanguages as $language) {
                if ($language === $SourceLanguage) {
                    continue;
                }
                $Rows = $this->FillLanguageColumn($Rows, $sourceField, $group['prefix'] . $language, $SourceLanguage, $language, $capitalizeFirst);
            }
        }

        return $Rows;
    }

    // Übersetzt für alle Zeilen, bei denen $ToField noch leer ist, den Text aus
    // $FromField nach $ToField (gebatcht in einem API-Aufruf). $ForceSource = null
    // lässt Google die Quellsprache selbst erkennen.
    // $ToField ist der Property-Feldname zum Speichern (kann präfixiert sein, z.B.
    // "Text_de"), $TargetLanguageCode der reine Sprachcode, der an Google geht.
    private function FillLanguageColumn(array $Rows, string $FromField, string $ToField, ?string $ForceSource, string $TargetLanguageCode, bool $CapitalizeFirst): array
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

        $translated = $this->TranslateBatch(array_values($pending), $ForceSource, $TargetLanguageCode);

        $i = 0;
        foreach (array_keys($pending) as $index) {
            $value = $translated[$i] ?? '';
            if ($CapitalizeFirst && $value !== '') {
                $value = $this->CapitalizeFirstLetter($value);
            }
            $Rows[$index][$ToField] = $value;
            $i++;
        }

        return $Rows;
    }

    private function CapitalizeFirstLetter(string $Text): string
    {
        return mb_strtoupper(mb_substr($Text, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($Text, 1, null, 'UTF-8');
    }

    // $Source = null lässt Google die Quellsprache des Texts selbst erkennen.
    // Google Cloud Translate lehnt Anfragen mit mehr als 128 Texten in einem
    // Aufruf komplett ab ("Too many text segments") - größere Batches werden
    // daher in mehrere Aufrufe aufgeteilt.
    private const translateMaxTextsPerRequest = 128;

    private function TranslateBatch(array $Texts, ?string $Source, string $Target): array
    {
        if ($Texts === []) {
            return [];
        }

        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return array_fill(0, count($Texts), '');
        }

        $results = [];
        foreach (array_chunk($Texts, self::translateMaxTextsPerRequest) as $chunk) {
            $results = array_merge($results, $this->TranslateChunk($chunk, $Source, $Target, $apiKey));
        }

        return $results;
    }

    private function TranslateChunk(array $Texts, ?string $Source, string $Target, string $ApiKey): array
    {
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
            'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($ApiKey),
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

    private const availableLanguagesMaxAgeSeconds = 86400;

    private function RefreshAvailableLanguagesIfStale(): void
    {
        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return;
        }

        $fetchedAt = $this->ReadAttributeInteger(self::attributeAvailableLanguagesFetchedAt);
        if ((time() - $fetchedAt) < self::availableLanguagesMaxAgeSeconds) {
            return;
        }

        $this->FetchSupportedLanguages();
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
        $this->WriteAttributeInteger(self::attributeAvailableLanguagesFetchedAt, time());
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
            $columns = array_merge(
                $columns,
                $this->BuildLanguageColumnSet(self::fieldNamePrefix, $this->Translate('Name'), $SourceLanguage, $TargetLanguages)
            );

            $columns[] = [
                'caption' => $this->Translate('Original-Import (Text)'),
                'name'    => self::langOriginalImportText,
                'width'   => '200px',
            ];
            $columns = array_merge(
                $columns,
                $this->BuildLanguageColumnSet(self::fieldTextPrefix, $this->Translate('Text'), $SourceLanguage, $TargetLanguages)
            );
        } else {
            $columns[] = ['caption' => $this->Translate('Original-Import'), 'name' => self::langOriginalImport, 'width' => '200px'];
            $columns = array_merge($columns, $this->BuildLanguageColumnSet('', '', $SourceLanguage, $TargetLanguages));
        }

        return $columns;
    }

    // Baut die editierbaren Sprachspalten für eine Feldgruppe: erst die (bereinigte,
    // aus Original-Import übersetzte) Quellsprache, dann jede ausgewählte Zielsprache.
    // $Label unterscheidet bei "Eigene Texte" zwischen Name- und Text-Spalten
    // (leer für Objektnamen, die nur eine Feldgruppe haben).
    private function BuildLanguageColumnSet(string $Prefix, string $Label, string $SourceLanguage, array $TargetLanguages): array
    {
        $withLabel = function (string $Text) use ($Label): string {
            return $Label !== '' ? sprintf('%s %s', $Label, $Text) : $Text;
        };

        $columns = [
            [
                'caption' => $withLabel(sprintf('%s (%s)', $this->GetLanguageDisplayName($SourceLanguage), $this->Translate('übersetzt'))),
                'name'    => $Prefix . $SourceLanguage,
                'width'   => '200px',
                'add'     => '',
                'edit'    => ['type' => 'ValidationTextBox'],
            ],
        ];

        foreach ($TargetLanguages as $language) {
            if ($language === $SourceLanguage) {
                continue;
            }
            $columns[] = [
                'caption' => $withLabel($this->GetLanguageDisplayName($language)),
                'name'    => $Prefix . $language,
                'width'   => '200px',
                'add'     => '',
                'edit'    => ['type' => 'ValidationTextBox'],
            ];
        }

        return $columns;
    }

    // "TargetLanguages" ist eine List mit einer CheckBox-Spalte (Mehrfachauswahl-Ersatz,
    // da form.json keinen "SelectMultiple"-Typ kennt). Statt eine Zeile je bekannter
    // Sprache mit Checkbox anzuzeigen (die bei jedem Sprachlisten-Refresh komplett neu
    // aufgebaut werden müsste - siehe Git-Historie für die Probleme, die das gemacht
    // hat), funktioniert die Liste wie eine klassische Add/Delete-Liste: leer starten,
    // der Nutzer fügt über "Hinzufügen" gezielt einzelne Sprachen hinzu. Property
    // speichert nur die tatsächlich hinzugefügten Zeilen [{"code": "en"}, ...].
    private function GetSelectedTargetLanguages(): array
    {
        $rows = json_decode($this->ReadPropertyString(self::propertyTargetLanguages), true);
        if (!is_array($rows)) {
            return [];
        }

        $codes = [];
        foreach ($rows as $row) {
            if (isset($row['code']) && $row['code'] !== '') {
                $codes[] = $row['code'];
            }
        }

        return $codes;
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

    // Dropdown-Optionen für die "Hinzufügen"-Zeile der Zielsprachen-Liste. Ohne
    // gespeicherten API-Key ist noch keine echte Sprachliste bekannt - statt einer
    // leeren/irreführenden Auswahl gibt es dann einen erklärenden Platzhalter.
    private function BuildTargetLanguageOptions(string $SourceLanguage): array
    {
        if ($this->ReadPropertyString(self::propertyGoogleTranslateAPIKey) === '') {
            return [[
                'caption' => $this->Translate('Bitte zuerst Google Cloud Translate API-Key eintragen und übernehmen'),
                'value'   => '',
            ]];
        }

        // Ein API-Key ist gesetzt, aber noch nie erfolgreich eine echte Sprachliste
        // geladen worden (z.B. ungültiger Key) - dann NICHT still auf die 6 fest
        // eingebauten Standardsprachen zurückfallen, das sähe aus wie ein Erfolg.
        if (!$this->HasCachedLanguages()) {
            return [[
                'caption' => $this->Translate('Sprachliste konnte nicht von Google geladen werden - bitte API-Key prüfen'),
                'value'   => '',
            ]];
        }

        $options = [];
        foreach ($this->BuildLanguageOptions() as $option) {
            if ($option['value'] !== $SourceLanguage) {
                $options[] = $option;
            }
        }

        usort($options, function ($a, $b) {
            return strnatcasecmp($a['caption'], $b['caption']);
        });

        return $options;
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

    private function HasCachedLanguages(): bool
    {
        $cached = json_decode($this->ReadAttributeString(self::attributeAvailableLanguagesCache), true);

        return is_array($cached) && $cached !== [];
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
