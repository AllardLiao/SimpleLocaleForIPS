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

    // Hinweistexte fürs Info-Symbol neben dem Dropdown - live in die aktive
    // Gast-Sprache übersetzt (siehe EnsureGuestLanguageNamesFresh), damit auch
    // dieser Text nicht die Konsolensprache des Admins mit der Gast-Sprache mischt.
    private const INFO_LIMITATION_TEXTS = [
        'Die gewählte Sprache gilt für alle Besucher dieser Seite gleichzeitig - nicht individuell für jede Person.',
        'Inhalte, die von anderen Modulen oder Skripten laufend automatisch aktualisiert werden (z. B. Messwerte oder Wetterdaten), erscheinen nach jeder Aktualisierung wieder in ihrer ursprünglichen Sprache.',
    ];

    // Rein dekorativ fürs Gast-Dropdown (GetVisualizationTile) - nicht erschöpfend,
    // unbekannte Sprachcodes bekommen einfach keine Flagge vorangestellt.
    private const LANGUAGE_FLAGS = [
        'de' => '🇩🇪', 'en' => '🇬🇧', 'fr' => '🇫🇷', 'es' => '🇪🇸', 'it' => '🇮🇹',
        'nl' => '🇳🇱', 'pt' => '🇵🇹', 'pl' => '🇵🇱', 'ru' => '🇷🇺', 'tr' => '🇹🇷',
        'ar' => '🇸🇦', 'zh' => '🇨🇳', 'zh-CN' => '🇨🇳', 'zh-TW' => '🇹🇼', 'ja' => '🇯🇵',
        'ko' => '🇰🇷', 'da' => '🇩🇰', 'sv' => '🇸🇪', 'no' => '🇳🇴', 'nb' => '🇳🇴',
        'fi' => '🇫🇮', 'cs' => '🇨🇿', 'sk' => '🇸🇰', 'hu' => '🇭🇺', 'ro' => '🇷🇴',
        'bg' => '🇧🇬', 'el' => '🇬🇷', 'uk' => '🇺🇦', 'he' => '🇮🇱', 'hi' => '🇮🇳',
        'th' => '🇹🇭', 'vi' => '🇻🇳', 'id' => '🇮🇩', 'ms' => '🇲🇾', 'hr' => '🇭🇷',
        'sl' => '🇸🇮', 'et' => '🇪🇪', 'lv' => '🇱🇻', 'lt' => '🇱🇹', 'sr' => '🇷🇸',
        'fa' => '🇮🇷', 'ur' => '🇵🇰', 'bn' => '🇧🇩', 'sw' => '🇰🇪', 'af' => '🇿🇦',
        'is' => '🇮🇸', 'ga' => '🇮🇪', 'mt' => '🇲🇹', 'ca' => '🇪🇸', 'eu' => '🇪🇸',
        'gl' => '🇪🇸',
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

        // Bewusst eine Property statt Variable/Profil für die aktive Sprache: Profile
        // sind in Symcon immer global, nicht instanzgebunden - bei mehreren Instanzen
        // mit unterschiedlichen Zielsprachen würde jede Instanz beim Übernehmen die
        // Assoziationen der jeweils anderen überschreiben. Als Property ist sie sowohl
        // instanzgebunden als auch direkt im Konfigurationsformular sicht-/änderbar
        // (siehe GetConfigurationForm). "Language" bleibt unten nur noch als reiner
        // RequestAction-Ident (String) bestehen, ohne zugehöriges Variablenobjekt - die
        // Kachel spricht ihn direkt per requestAction() an (siehe HTML-SDK).
        // Default bewusst "ORIGINAL_IMPORT", nicht "": Das Select-Formularfeld bietet
        // nur die Werte aus GetSelectableLanguageCodes() an (Basissprache, gewählte
        // Zielsprachen, "Original") - "" ist dort nie eine gültige Option. Bei der
        // allerersten Formularanzeige (vor dem ersten Übernehmen) war der Wert sonst
        // "", was zu keiner Option passte und einen Fehler auslöste. "ORIGINAL_IMPORT"
        // ist der einzige Code, der unabhängig von jeder Konfiguration immer gültig ist.
        $this->RegisterPropertyString(self::propertyCurrentLanguage, self::langOriginalImport);

        $this->RegisterAttributeString(self::attributeAvailableLanguagesCache, '[]');
        $this->RegisterAttributeInteger(self::attributeAvailableLanguagesFetchedAt, 0);
        $this->RegisterAttributeString(self::attributeGuestLanguageNamesCache, '{}');

        $this->SetVisualizationType(1);

        // Einmalige Bereinigung: die frühere HTMLBox-Dropdown-Variable sowie die
        // frühere "Sprache"-Variable (inkl. globalem Profil) existieren bei bereits
        // eingerichteten Installationen noch, werden aber nicht mehr benötigt.
        foreach ([self::identLanguageDropdown, self::identLanguage] as $staleIdent) {
            $staleID = @IPS_GetObjectIDByIdent($staleIdent, $this->InstanceID);
            if ($staleID !== false) {
                IPS_DeleteVariable($staleID);
            }
        }

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
                    $element['add'] = true;

                    // Ohne geladene Sprachliste die ganze Liste (inkl. "Hinzufügen"-Button)
                    // sichtbar, aber ausgegraut lassen ("enabled": false) statt den Button
                    // komplett verschwinden zu lassen - macht auf einen Blick klar, dass hier
                    // etwas fehlt, statt es einfach wegzulassen. Verhindert außerdem
                    // strukturell, dass der eingebaute Zeilen-Editor-Popup nur den
                    // Platzhalter zur Auswahl anbietet und dessen "OK" eine Fake-Zeile
                    // in die Liste einträgt.
                    if ($this->HasCachedLanguages()) {
                        $element['enabled'] = true;
                    } else {
                        $element['enabled'] = false;
                        $element['caption'] .= ' (' . $this->Translate('bitte zuerst gültigen API-Key speichern und Formular neu öffnen') . ')';
                    }
                    break;

                case self::propertyObjectNames:
                    $element['columns'] = $this->BuildListColumns($sourceLanguage, $targetLanguages, false);
                    $element['values'] = $this->DecodeRows(self::propertyObjectNames);
                    break;

                case self::propertyObjectTexts:
                    $element['columns'] = $this->BuildListColumns($sourceLanguage, $targetLanguages, true);
                    $element['values'] = $this->DecodeRows(self::propertyObjectTexts);
                    break;

                case self::propertyCurrentLanguage:
                    $element['options'] = $this->BuildCurrentLanguageOptions();
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
        $currentLanguage = $this->ReadPropertyString(self::propertyCurrentLanguage);
        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);

        foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {
            if (($row['ObjectID'] ?? null) === $ObjectID) {
                return $this->ResolveRowValue(
                    $row,
                    $currentLanguage,
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
        // Wie Rescan(): direktes IPS_SetProperty + IPS_ApplyChanges, damit die neue
        // Sprache sofort persistiert ist und im Konfigurationsformular korrekt
        // angezeigt wird, sobald es (neu) geöffnet wird.
        IPS_SetProperty($this->InstanceID, self::propertyCurrentLanguage, $Language);
        IPS_ApplyChanges($this->InstanceID);
        $this->PushVisualizationUpdate();

        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);

        foreach ($this->DecodeRows(self::propertyObjectNames) as $row) {
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID === 0 || !@IPS_ObjectExists($objectID)) {
                continue;
            }

            IPS_SetName($objectID, $this->ResolveRowValue($row, $Language, $Language, $sourceLanguage, self::langOriginalImport));
        }

        foreach ($this->DecodeRows(self::propertyObjectTexts) as $row) {
            $objectID = (int) ($row['ObjectID'] ?? 0);
            if ($objectID !== 0 && @IPS_ObjectExists($objectID)) {
                IPS_SetName($objectID, $this->ResolveRowValue(
                    $row,
                    $Language,
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
                $Language,
                self::fieldTextPrefix . $Language,
                self::fieldTextPrefix . $sourceLanguage,
                self::langOriginalImportText
            ));
        }
    }

    // Fallback-Kette: gewünschte Sprache -> Quellsprache (bereinigt) -> Rohtext ($RawField)
    // $SelectedLanguage ist der vom Gast tatsächlich gewählte Sprachcode
    // (unpräfixiert, z.B. "en" oder die Pseudo-Sprache "ORIGINAL_IMPORT"),
    // $LanguageField/$SourceField die (ggf. präfixierten) Property-Feldnamen dazu.
    private function ResolveRowValue(array $Row, string $SelectedLanguage, string $LanguageField, string $SourceField, string $RawField): string
    {
        // "Original" setzt bewusst auf den unbearbeiteten Rohtext zurück (Tippfehler
        // inklusive) - eine Art Werkseinstellung, unabhängig von allen Übersetzungen.
        if ($SelectedLanguage === self::langOriginalImport) {
            return $Row[$RawField] ?? '';
        }
        if (($Row[$LanguageField] ?? '') !== '') {
            return $Row[$LanguageField];
        }
        if (($Row[$SourceField] ?? '') !== '') {
            return $Row[$SourceField];
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

        // Jeder Text wird in abwechselnd übersetzbare/geschützte Segmente zerlegt -
        // nur die übersetzbaren Segmente werden überhaupt an Google geschickt (siehe
        // SplitProtectedSegments). Die Chunk-Grenze von 128 muss daher auf der
        // flachen Segmentliste liegen, nicht auf den ursprünglichen Zeilen-Texten,
        // sonst könnte ein einzelner Text mit mehreren Segmenten die Google-Grenze
        // pro Aufruf ("Too many text segments") trotzdem reißen.
        $segmentsPerText = array_map([$this, 'SplitProtectedSegments'], $Texts);

        $translatable = [];
        foreach ($segmentsPerText as $segments) {
            foreach ($segments as $segment) {
                if (!$segment['protected']) {
                    $translatable[] = $segment['text'];
                }
            }
        }

        $translatedFlat = [];
        foreach (array_chunk($translatable, self::translateMaxTextsPerRequest) as $chunk) {
            $translatedFlat = array_merge($translatedFlat, $this->TranslateChunk($chunk, $Source, $Target, $apiKey));
        }

        $result = [];
        $cursor = 0;
        foreach ($segmentsPerText as $segments) {
            $rebuilt = '';
            foreach ($segments as $segment) {
                $rebuilt .= $segment['protected'] ? $segment['text'] : ($translatedFlat[$cursor++] ?? '');
            }
            $result[] = $rebuilt;
        }

        return $result;
    }

    private function TranslateChunk(array $Texts, ?string $Source, string $Target, string $ApiKey): array
    {
        if ($Texts === []) {
            return [];
        }

        $body = [
            'q'      => $Texts,
            'target' => $Target,
            // "html" statt "text": Google übersetzt dann nur den Text zwischen Tags,
            // nicht die Tags/Attribute selbst - wichtig für "Eigene Texte", die
            // vollständige HTML-Widgets (Symcon-HTMLBox-Inhalte) sein können.
            'format' => 'html',
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

    // Zerlegt einen Text in abwechselnd übersetzbare und geschützte (<style>/<script>-
    // Block-)Segmente, in ursprünglicher Reihenfolge. Style-/Script-Inhalte gehen nie
    // an Google - dort würden CSS-Eigenschaften/JS-Code wie normaler Fließtext
    // "übersetzt" und das eingebettete HTML/CSS zerstören (z.B. bei HTMLBox-Widgets
    // als "Eigener Text", siehe Bugreport mit übersetztem "text-align"/"background-
    // color" in einem <style>-Block). Bewusst kein Platzhalter-Text anstelle des
    // Blocks, der die Übersetzung unbeschadet überstehen müsste - Segmente, die gar
    // nicht erst an Google gehen, können dabei auch nicht verändert werden.
    private function SplitProtectedSegments(string $Text): array
    {
        $segments = [];
        $offset = 0;

        if (preg_match_all('/<(style|script)\b[^>]*>.*?<\/\1>/is', $Text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$blockText, $blockOffset]) {
                if ($blockOffset > $offset) {
                    $segments[] = ['protected' => false, 'text' => substr($Text, $offset, $blockOffset - $offset)];
                }
                $segments[] = ['protected' => true, 'text' => $blockText];
                $offset = $blockOffset + strlen($blockText);
            }
        }

        if ($offset < strlen($Text)) {
            $segments[] = ['protected' => false, 'text' => substr($Text, $offset)];
        }

        return $segments === [] ? [['protected' => false, 'text' => $Text]] : $segments;
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
        if ($this->ReadPropertyString(self::propertyGoogleTranslateAPIKey) === '') {
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return;
        }

        $target = $this->ReadPropertyString(self::propertySourceLanguage);
        $names = $this->FetchLanguageNames($target);
        if ($names === null) {
            return;
        }

        $result = [];
        foreach ($names as $code => $name) {
            $result[] = ['code' => $code, 'name' => $name];
        }

        $this->WriteAttributeString(self::attributeAvailableLanguagesCache, json_encode($result));
        $this->WriteAttributeInteger(self::attributeAvailableLanguagesFetchedAt, time());
    }

    // Von Google unterstützte Sprachen, mit Namen in $Target - gemeinsam genutzt von
    // FetchSupportedLanguages() (Admin-Konsolensprache) und EnsureGuestLanguageNamesFresh()
    // (aktuell aktive Gast-Sprache). null bei fehlendem Key oder Fehler beim Abruf.
    private function FetchLanguageNames(string $Target): ?array
    {
        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return null;
        }

        $url = 'https://translation.googleapis.com/language/translate/v2/languages'
            . '?key=' . urlencode($apiKey)
            . '&target=' . urlencode($Target);

        $response = $this->CallGoogleTranslateAPI($url, null);
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        $languages = $decoded['data']['languages'] ?? null;
        if (!is_array($languages)) {
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return null;
        }

        $names = [];
        foreach ($languages as $entry) {
            $code = $entry['language'] ?? '';
            if ($code !== '') {
                $names[$code] = $entry['name'] ?? $code;
            }
        }

        return $names;
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

    // Basissprache + gewählte Zielsprachen + die "Original"-Werkseinstellung, in
    // dieser Reihenfolge - gemeinsam genutzt von der Kachel und vom Konfigurationsformular.
    private function GetSelectableLanguageCodes(): array
    {
        $languages = array_merge(
            [$this->ReadPropertyString(self::propertySourceLanguage)],
            $this->GetSelectedTargetLanguages()
        );
        $languages = array_unique($languages);
        $languages[] = self::langOriginalImport;

        return $languages;
    }

    // Symcon ruft diese Methode auf, sobald die Instanz selbst als Kachel in die
    // Visualisierung gezogen wird (aktiviert per SetVisualizationType in Create()).
    // Wird nur einmal beim Laden der Kachel aufgerufen - Aktualisierungen (z.B. nach
    // einem Sprachwechsel) laufen über UpdateVisualizationValue()/PushVisualizationUpdate().
    // HTML-Gerüst liegt in module.html (Standard-Symcon-Vorgehen, siehe HTML-SDK) -
    // hier wird nur der dynamische Teil per Platzhalter eingesetzt.
    public function GetVisualizationTile(): string
    {
        $html = file_get_contents(__DIR__ . '/module.html');
        // Instanz-eigene ID (nicht nur eine Klasse) - falls mehrere Instanzen jemals
        // im selben DOM landen sollten (statt jeweils eigenem iframe), verhindert das
        // eine ID-Kollision zwischen den Kacheln verschiedener Instanzen.
        $html = str_replace('<!--WRAPPER_ID-->', 'ipssl-select-wrapper-' . $this->InstanceID, $html);

        return str_replace('<!--LANGUAGE_SELECT-->', $this->BuildLanguageSelectHtml(), $html);
    }

    // Schickt bereits geöffneten Kacheln (z.B. andere Browser-Tabs/Geräte) die
    // neu aufgebaute Sprachauswahl - die Kachel selbst, die den Wechsel ausgelöst
    // hat, kennt ihre neue Auswahl bereits durch die native <select>-Interaktion.
    private function PushVisualizationUpdate(): void
    {
        $payload = json_encode(['action' => 'REFRESH', 'payload' => ['html' => $this->BuildLanguageSelectHtml()]]);
        $this->UpdateVisualizationValue($payload);
    }

    // Schlankes natives <select> statt der Symcon-Standarddarstellung (Buttons
    // untereinander) - ruft beim Ändern dieselbe RequestAction wie die
    // Profil-Variable auf. Kein Text-Label ("Sprache" o.ä.), damit nicht die
    // Konsolensprache des Admins mit der vom Gast gewählten Sprache gemischt wird -
    // stattdessen ein sprachneutrales Globus-Symbol. Die Sprachnamen selbst werden
    // live in die aktuell aktive Gast-Sprache übersetzt (siehe EnsureGuestLanguageNamesFresh).
    private function BuildLanguageSelectHtml(): string
    {
        $currentLanguage = $this->ReadPropertyString(self::propertyCurrentLanguage);
        $guestCache = $this->EnsureGuestLanguageNamesFresh();

        $optionsHtml = '';
        foreach ($this->GetSelectableLanguageCodes() as $code) {
            $selected = $code === $currentLanguage ? ' selected' : '';
            $value = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($this->GetGuestLanguageLabel($code, $guestCache), ENT_QUOTES, 'UTF-8');
            $optionsHtml .= "<option value=\"{$value}\"{$selected}>{$label}</option>";
        }

        $infoIconHtml = '<span class="ipssl-info-icon" aria-hidden="true"'
            . ' onclick="alert(' . $this->BuildInfoAlertJs($guestCache) . ');">ⓘ</span>';

        return '<div class="ipssl-select-row">'
            . '<span class="ipssl-globe" aria-hidden="true">🌐</span>'
            . '<select onchange="requestAction(\'' . self::identLanguage . '\', this.value);">'
            . $optionsHtml
            . '</select>'
            . $infoIconHtml
            . '</div>';
    }

    // alert() ist ein Browser-Chrome-Dialog, kein DOM-Element - anders als jedes per
    // CSS positionierte <div> (siehe .ipssl-select-row) ist er nicht an die Box des
    // eigenen iframes gebunden und kann daher unabhängig von der Kachelgröße immer
    // vollständig angezeigt werden. Achtung: manche eingebetteten WebViews (v.a.
    // native Mobile-Apps) unterdrücken window.alert() aus eingebettetem Fremd-Content
    // aus Sicherheitsgründen - das lässt sich ohne Live-Test auf dem Zielgerät nicht
    // zuverlässig verifizieren. Text live in die aktuell aktive Gast-Sprache
    // übersetzt, damit auch dieser Hinweis nicht die Admin-Konsolensprache mit der
    // Gast-Sprache mischt.
    private function BuildInfoAlertJs(array $GuestCache): string
    {
        $texts = $GuestCache['infoTexts'] ?? self::INFO_LIMITATION_TEXTS;
        $alertText = implode("\n\n", array_map(fn (string $text): string => '• ' . $text, $texts));

        return htmlspecialchars(json_encode($alertText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    }

    // "Name - code" mit vorangestellter Flagge (z.B. "🇬🇧 English - en"), Name live
    // in die aktuell aktive Gast-Sprache übersetzt. "Original" ist kein echter
    // Google-Sprachcode und bekommt daher weder Flagge noch Code-Suffix.
    private function GetGuestLanguageLabel(string $Code, array $GuestCache): string
    {
        if ($Code === self::langOriginalImport) {
            return '🔄 ' . ($GuestCache['originalLabel'] ?? $this->Translate('Original (unbearbeitet)'));
        }

        $flag = self::LANGUAGE_FLAGS[$Code] ?? '';
        $name = $GuestCache['names'][$Code] ?? $this->GetLanguageDisplayName($Code);
        $prefix = $flag === '' ? '' : $flag . ' ';

        return $prefix . $name . ' - ' . $Code;
    }

    private const guestLanguageNamesMaxAgeSeconds = 86400;

    // Stellt sicher, dass GuestLanguageNamesCache zur aktuell aktiven Gast-Sprache
    // passt (alle wählbaren Sprachcodes vorhanden, nicht älter als ein Tag) und
    // aktualisiert sie andernfalls per Google. Ohne API-Key oder bei Fehlern wird
    // der (ggf. leere) Cache unverändert zurückgegeben - GetGuestLanguageLabel()
    // fällt dann auf die admin-sprachige Anzeige zurück, nichts bricht dadurch ab.
    private function EnsureGuestLanguageNamesFresh(): array
    {
        $language = $this->ReadPropertyString(self::propertyCurrentLanguage);

        // "Original" ist kein echter Google-Sprachcode - für die Beschriftung der
        // *anderen* Dropdown-Einträge wird in diesem Fall die Basissprache verwendet
        // (der Baum steht ja gerade unübersetzt/roh in genau dieser Sprache da).
        if ($language === '' || $language === self::langOriginalImport) {
            $language = $this->ReadPropertyString(self::propertySourceLanguage);
        }

        $cache = json_decode($this->ReadAttributeString(self::attributeGuestLanguageNamesCache), true);
        if (!is_array($cache)) {
            $cache = [];
        }

        if ($language === '') {
            return $cache;
        }

        $neededCodes = array_diff($this->GetSelectableLanguageCodes(), [self::langOriginalImport]);
        $missingCodes = array_diff($neededCodes, array_keys($cache['names'] ?? []));
        $isFresh = ($cache['language'] ?? '') === $language
            && $missingCodes === []
            && (time() - ($cache['fetchedAt'] ?? 0)) < self::guestLanguageNamesMaxAgeSeconds;

        if ($isFresh) {
            return $cache;
        }

        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return $cache;
        }

        $names = $this->FetchLanguageNames($language) ?? ($cache['names'] ?? []);

        // "Original (unbearbeitet)" + die Info-Hinweistexte in einem gemeinsamen
        // Aufruf übersetzen (statt je einem eigenen) - alles feste, kurze Texte,
        // die ohnehin nur bei Sprachwechsel/Cache-Ablauf einmal aktualisiert werden.
        $ownTexts = array_merge(['Original (unbearbeitet)'], self::INFO_LIMITATION_TEXTS);
        if ($language === 'de') {
            $translatedOwnTexts = $ownTexts;
        } else {
            $translatedOwnTexts = $this->TranslateBatch($ownTexts, 'de', $language);
        }

        $originalLabel = $translatedOwnTexts[0] ?? ($cache['originalLabel'] ?? 'Original');
        $infoTexts = [
            $translatedOwnTexts[1] ?? self::INFO_LIMITATION_TEXTS[0],
            $translatedOwnTexts[2] ?? self::INFO_LIMITATION_TEXTS[1],
        ];

        $cache = [
            'language'      => $language,
            'names'         => $names,
            'originalLabel' => $originalLabel,
            'infoTexts'     => $infoTexts,
            'fetchedAt'     => time(),
        ];

        $this->WriteAttributeString(self::attributeGuestLanguageNamesCache, json_encode($cache));

        return $cache;
    }

    // Baut die Spalten für die "ObjectNames"/"ObjectTexts"-Listen dynamisch anhand
    // der Quell-/Zielsprachen zusammen (Symcon-Formulare kennen keine Spalten mit
    // dynamischer Anzahl, daher wird das Formular bei jedem Öffnen neu erzeugt).
    // "Eigene Texte" bekommt zusätzlich eine Name-Kontextspalte (welche Kachel ist
    // das?) und die editierbare Quellsprachen-Spalte (Basis für die Zielsprachen,
    // da Inhalte oft länger sind und von der Google-Bereinigung profitieren).
    // "Objektnamen" verzichtet auf beides - Original-Import dient dort direkt als
    // Quellsprachen-Basis.
    // Wichtig: Spalten ohne "edit"-Definition werden von Symcon beim normalen
    // "Übernehmen" NICHT als Property gespeichert, außer "save" ist explizit true
    // (nur Rescan schreibt direkt per IPS_SetProperty und umgeht das). Ohne "save"
    // wären ObjectID/Path/Original-Import bei jedem Übernehmen verloren.
    private function BuildListColumns(string $SourceLanguage, array $TargetLanguages, bool $IsObjectTexts): array
    {
        $columns = [
            ['caption' => 'Objekt-ID', 'name' => 'ObjectID', 'width' => '80px', 'save' => true],
            ['caption' => $this->Translate('Pfad'), 'name' => 'Path', 'width' => '200px', 'save' => true],
        ];

        if ($IsObjectTexts) {
            $columns[] = ['caption' => 'Wert-Objekt-ID', 'name' => 'ValueObjectID', 'width' => '90px', 'save' => true];

            $columns[] = [
                'caption' => $this->Translate('Original-Import (Name)'),
                'name'    => self::fieldOriginalImportName,
                'width'   => '150px',
                'save'    => true,
            ];
            $columns = array_merge(
                $columns,
                $this->BuildLanguageColumnSet(self::fieldNamePrefix, $this->Translate('Name'), $SourceLanguage, $TargetLanguages)
            );

            $columns[] = [
                'caption' => $this->Translate('Original-Import (Text)'),
                'name'    => self::langOriginalImportText,
                'width'   => '200px',
                'save'    => true,
            ];
            $columns = array_merge(
                $columns,
                $this->BuildLanguageColumnSet(self::fieldTextPrefix, $this->Translate('Text'), $SourceLanguage, $TargetLanguages)
            );
        } else {
            $columns[] = [
                'caption' => $this->Translate('Original-Import'),
                'name'    => self::langOriginalImport,
                'width'   => '200px',
                'save'    => true,
            ];
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
                'save'    => true,
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
                'save'    => true,
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

    // Dropdown-Optionen für "Aktuell aktive Sprache" im Konfigurationsformular:
    // Basissprache + gewählte Zielsprachen + "Original", mit Namen in der
    // Admin-Konsolensprache (dies ist das Admin-Formular, nicht die Gast-Kachel -
    // dort werden die Namen stattdessen live in die Gast-Sprache übersetzt).
    private function BuildCurrentLanguageOptions(): array
    {
        return array_map(function (string $code): array {
            return [
                'caption' => $this->GetLanguageDisplayName($code),
                'value'   => $code,
            ];
        }, $this->GetSelectableLanguageCodes());
    }

    private function GetLanguageDisplayName(string $Code): string
    {
        if ($Code === self::langOriginalImport) {
            return $this->Translate('Original (unbearbeitet)');
        }

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
