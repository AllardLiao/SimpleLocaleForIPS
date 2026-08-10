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

    // Überschrift für den Info-Alert - alert() kennt keinen eigenen Titel-Parameter,
    // daher als erste Zeile des Texts selbst (siehe BuildInfoAlertJs).
    private const INFO_HEADING_TEXT = 'Hinweise';

    // ===== Lizenz / Testversion =====
    // Für den Vollversion-Build vor dem echten Release auf false setzen (siehe
    // README) - dann entfallen alle Einschränkungen unten unabhängig vom
    // Lizenzschlüssel. Für die Testversion: volle Funktionalität, aber nur die
    // unten gelisteten (bewusst wenig praxisrelevanten) Sprachen wählbar, und nach
    // TRIAL_DURATION_DAYS ab der ersten Einrichtung blockiert ein Rescan.
    private const IS_TRIAL_BUILD = true;
    private const TRIAL_DURATION_DAYS = 30;

    // Isländisch, Walisisch, Zulu, Maori, Latein - alle von Google Cloud Translate
    // unterstützt, aber für die allermeisten Gäste-Visualisierungen (Ferienwohnung,
    // Showroom) kaum praxisrelevant. Voll funktionsfähig zum Testen des kompletten
    // Mechanismus, aber ohne die Sprachen, die man in der Praxis tatsächlich braucht.
    private const TRIAL_LANGUAGE_CODES = ['is', 'cy', 'zu', 'mi', 'la'];

    // Marketing: zeitlich begrenzte Aktionen, die für ALLE Installationen
    // gleichzeitig zusätzliche Sprachen kostenfrei freischalten (zusätzlich zu
    // TRIAL_LANGUAGE_CODES, siehe GetFreeLanguageCodes) - bewusst hart im
    // Modul-Code hinterlegt statt irgendwo konfigurierbar, damit "für alle
    // Installationen gleich" auch wirklich stimmt: jede Instanz bekommt die Aktion
    // automatisch mit dem nächsten Update, ganz ohne eigenes Zutun des Nutzers.
    // Wirkt zweifach: (1) admin-seitig als zusätzlich wählbare Zielsprache
    // während der Aktion (auch für noch laufende Testphasen), (2) gast-seitig
    // darf ein Sprachwechsel in eine gerade aktive Aktionssprache sogar dann
    // stattfinden, wenn die eigene 30-Tage-Testphase der Instanz längst
    // abgelaufen ist (siehe IsLanguageBlockedByTrial) - ein netter Anlass,
    // testweise nochmal vorbeizuschauen. Frisch generiert werden Übersetzungen
    // dafür trotzdem nur, wenn ohnehin schon rescannt werden darf (aktive
    // Testphase oder Lizenz) - eine abgelaufene, unlizenzierte Instanz liefert
    // für noch nie übersetzte Zeilen weiterhin nur den Rohtext (siehe
    // ResolveRowValue), keinen Absturz und keine leere Anzeige.
    //
    // Ideen für sympathische, zum Schmunzeln anregende Aktionen (jeweils als
    // eigener Array-Eintrag unten aktivierbar):
    // - Fußball-/Sport-Großereignis: Sprachen der teilnehmenden Nationen für die
    //   Turnierlaufzeit ("Hurra, Weltmeisterschaft! ...").
    // - Europäischer Tag der Sprachen (26. September): 24h alle EU-Amtssprachen.
    // - Hieronymustag/Internationaler Übersetzertag (30. September): Dank an alle
    //   Übersetzer der Welt, 24h alle Sprachen kostenfrei.
    // - Dezember/Nikolaus: Finnisch ("die Sprache des Weihnachtsmanns") den ganzen
    //   Monat gratis.
    // - Tag der Deutschen Einheit (3. Oktober): die Sprachen aller neun deutschen
    //   Nachbarländer für diesen einen Tag.
    // - Esperanto-Tag (26. Juli): Esperanto - "die Sprache, die alle verbindet".
    private const PROMOTIONAL_LANGUAGE_CAMPAIGNS = [
        // Beispiel, bis zu einer echten Aktion auskommentiert:
        // [
        //     'name'     => 'Fußball-WM 2026',
        //     'codes'    => ['en', 'es', 'fr', 'pt', 'ja', 'ko', 'ar'],
        //     'startsAt' => 1749600000, // 11.06.2026
        //     'endsAt'   => 1752960000, // 19.07.2026 (exklusiv)
        // ],
    ];

    // Geheimnis zur Prüfung von Lizenzschlüsseln (HMAC-Signatur, siehe
    // ValidateLicenseKey). PLATZHALTER - vor dem echten Release durch ein
    // echtes, nur dem eigenen Verkaufssystem bekanntes Geheimnis ersetzen. Mit
    // diesem Platzhalter lässt sich kein echter Lizenzschlüssel gültig signieren,
    // der Mechanismus ist aber vollständig testbar (siehe smoke-Tests).
    private const LICENSE_SIGNING_SECRET = 'CHANGE_ME_BEFORE_RELEASE';

    // "Permalink" zum Lizenzerwerb - aktuell nur ein Verweis auf das GitHub-Repo,
    // da es noch keinen eigenen Shop gibt. Eine einzige zentrale Konstante, damit
    // später nur hier eine echte Verkaufs-URL eingetragen werden muss.
    private const LICENSE_PURCHASE_URL = 'https://github.com/AllardLiao/SimpleLocaleForIPS';

    // Rohtext (nicht über $this->Translate()!) für den Gast-Hinweis nach Ablauf der
    // Testphase - wie INFO_LIMITATION_TEXTS live per Google in die vom Gast gerade
    // gewünschte Sprache übersetzt, siehe PushTrialExpiredAlert. $this->Translate()
    // würde stattdessen die Admin-Konsolensprache treffen, nicht die Gast-Sprache.
    private const TRIAL_EXPIRED_ALERT_TEXT = 'Die Testversion ist abgelaufen. Bitte eine Lizenz erwerben:';

    // Meldeserver fürs Erkennen von Lizenzmissbrauch (z.B. ein Schlüssel wird als
    // "gebraucht" mehrfach weiterverkauft) - siehe TrackLicenseActivationIfNew.
    // PLATZHALTER (leer): solange hier keine echte URL eingetragen ist, wird nichts
    // verschickt, Aktivierungen landen nur lokal in attributeActivationLog/SendDebug.
    // WICHTIG: IPS_GetLicensee() liefert eine echte, personenbezogene E-Mail-Adresse -
    // die Erhebung/Übermittlung gehört vor dem Eintragen einer echten URL in die
    // eigenen Lizenzbedingungen/Datenschutzhinweise.
    private const LICENSE_ACTIVATION_REPORT_URL = '';

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

        // Dem Admin überlassen, ob Globus- und Info-Symbol in der Kachel angezeigt
        // werden sollen (z.B. falls er ein eigenes, schlankeres Design möchte).
        $this->RegisterPropertyBoolean(self::propertyShowGlobeIcon, true);
        $this->RegisterPropertyBoolean(self::propertyShowInfoIcon, true);

        $this->RegisterPropertyString(self::propertyLicenseKey, '');

        $this->RegisterAttributeString(self::attributeAvailableLanguagesCache, '[]');
        $this->RegisterAttributeInteger(self::attributeAvailableLanguagesFetchedAt, 0);
        $this->RegisterAttributeString(self::attributeGuestLanguageNamesCache, '{}');
        $this->RegisterAttributeString(self::attributeUnnamedObjects, '[]');
        $this->RegisterAttributeString(self::attributeLicenseInfo, '{}');
        $this->RegisterAttributeInteger(self::attributeTrialStartedAt, 0);
        $this->RegisterAttributeString(self::attributeActivationLog, '[]');

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

        // Testphase startet mit der allerersten Einrichtung der Instanz, nicht erst
        // beim ersten Rescan - sonst könnte man den Ablauf durch einfaches Nichtstun
        // beliebig hinauszögern.
        if (self::IS_TRIAL_BUILD && $this->ReadAttributeInteger(self::attributeTrialStartedAt) === 0) {
            $this->WriteAttributeInteger(self::attributeTrialStartedAt, time());
        }

        if (self::IS_TRIAL_BUILD) {
            $this->TrackLicenseActivationIfNew();
            $this->EnforceLicensedLanguageLimit();
        }

        $rootID = $this->ReadPropertyInteger(self::propertyRootCategoryID);
        if ($rootID === 0 || !@IPS_ObjectExists($rootID)) {
            $this->SetStatus(self::STATUS_ROOT_CATEGORY_MISSING);
        } elseif ($this->IsTrialLocked()) {
            $this->SetStatus(self::STATUS_TRIAL_EXPIRED);
            $this->ResetToOriginalLanguageIfNeeded();
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
                $language = (string) $Value;
                if ($this->IsLanguageBlockedByTrial($language)) {
                    // Statt der gewünschten Sprache zurück auf die Original-Importe
                    // (verhindert dauerhaft eingefrorene/unvollständige Übersetzungen)
                    // und ein Hinweis-Popup mit Link zum Lizenzerwerb, live übersetzt
                    // in die eigentlich gewünschte Sprache. Aktuell kostenfreie Sprachen
                    // (Testphase-Sprachen + laufende Marketing-Aktionen) landen dagegen
                    // im else-Zweig, auch wenn die eigene Testphase längst abgelaufen ist.
                    $this->ResetToOriginalLanguageIfNeeded();
                    $this->PushVisualizationUpdate();
                    $this->PushTrialExpiredAlert($language);
                } else {
                    $this->ApplyLanguage($language);
                }
                break;

            case self::identRescan:
                $this->Rescan();
                break;

            case self::identActivateLicense:
                $this->ActivateLicense();
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
        $this->PopulateFormElements($form['elements']);

        return json_encode($form);
    }

    // Läuft rekursiv durch alle Formularelemente, auch verschachtelt innerhalb der
    // ExpansionPanel-"items" (Konfiguration/Übersetzung/Lizenz-Panel im Formular) -
    // die dynamisch befüllten Felder stecken inzwischen alle in einem dieser Panels,
    // nicht mehr direkt auf oberster Ebene von $form['elements'].
    private function PopulateFormElements(array &$Elements): void
    {
        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
        $targetLanguages = $this->GetSelectedTargetLanguages();
        $unnamedObjects = json_decode($this->ReadAttributeString(self::attributeUnnamedObjects), true);
        if (!is_array($unnamedObjects)) {
            $unnamedObjects = [];
        }

        foreach ($Elements as &$element) {
            if (isset($element['items']) && is_array($element['items'])) {
                $this->PopulateFormElements($element['items']);
            }

            switch ($element['name'] ?? '') {
                case self::propertySourceLanguage:
                    $element['options'] = $this->BuildLanguageOptions();
                    break;

                case self::propertyTargetLanguages:
                    $element['columns'][0]['edit']['options'] = $this->BuildTargetLanguageOptions($sourceLanguage);
                    $element['values'] = $this->DecodeRows(self::propertyTargetLanguages);
                    $element['add'] = true;

                    // Ohne geladene Sprachliste (oder bei erreichtem Sprachlimit einer
                    // "Spezialversion"-Lizenz, siehe GetLicensedLanguageLimit) die ganze
                    // Liste (inkl. "Hinzufügen"-Button) sichtbar, aber ausgegraut lassen
                    // ("enabled": false) statt den Button komplett verschwinden zu lassen -
                    // macht auf einen Blick klar, dass hier etwas fehlt/ausgeschöpft ist,
                    // statt es einfach wegzulassen. Verhindert außerdem strukturell, dass
                    // der eingebaute Zeilen-Editor-Popup nur den Platzhalter zur Auswahl
                    // anbietet und dessen "OK" eine Fake-Zeile in die Liste einträgt.
                    $languageLimit = $this->GetLicensedLanguageLimit();
                    $limitReached = $languageLimit > 0 && count($targetLanguages) >= $languageLimit;

                    if (!$this->HasCachedLanguages()) {
                        $element['enabled'] = false;
                        $element['caption'] .= ' (' . $this->Translate('bitte zuerst gültigen API-Key speichern und Formular neu öffnen') . ')';
                    } elseif ($limitReached) {
                        $element['enabled'] = false;
                        $element['caption'] .= ' (' . $this->Translate('Sprachlimit dieser Lizenz erreicht, max.') . " $languageLimit)";
                    } else {
                        $element['enabled'] = true;
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

                case 'UnnamedObjectsLabel':
                case 'UnnamedObjects':
                    $element['visible'] = $unnamedObjects !== [];
                    if (($element['name'] ?? '') === 'UnnamedObjects') {
                        $element['values'] = $unnamedObjects;
                    }
                    break;

                case 'TrialInfoLabel':
                    $element['visible'] = self::IS_TRIAL_BUILD && !$this->HasFullLicense();
                    $element['caption'] = $this->BuildTrialInfoText();
                    break;

                // Übersetzung-Panel klappt automatisch auf, sobald ein gültiger API-Key
                // eine echte Sprachliste geladen hat - vorher gibt es dort ohnehin nichts
                // sinnvoll zu tun (Zielsprachen-Auswahl ist deaktiviert, siehe oben).
                case 'TranslationPanel':
                    $element['expanded'] = $this->HasCachedLanguages();
                    break;

                // Lizenz-Panel nur im Testversion-Build relevant; klappt automatisch auf,
                // wenn gerade etwas Aufmerksamkeit braucht (Testphase abgelaufen oder
                // bereits ein Schlüssel eingetragen) statt es standardmäßig zu verstecken.
                case 'LicensePanel':
                    $element['visible'] = self::IS_TRIAL_BUILD;
                    $element['expanded'] = $this->IsTrialLocked() || $this->ReadPropertyString(self::propertyLicenseKey) !== '';
                    break;
            }
        }
        unset($element);
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
                    $sourceLanguage,
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

    // Prüft/übernimmt den in propertyLicenseKey eingetragenen Schlüssel per
    // RequestAction (Button "Lizenz aktivieren"). Zeigt nur ein Popup an, die
    // eigentliche Property wurde schon beim "Übernehmen" des Formulars gespeichert -
    // hier wird nur der bereits gespeicherte Schlüssel geprüft und das Ergebnis
    // (für die schnelle Anzeige im Formular) in attributeLicenseInfo gecacht.
    private function ActivateLicense(): void
    {
        $info = $this->GetLicenseInfo();
        $this->WriteAttributeString(self::attributeLicenseInfo, json_encode($info));

        if ($info['valid']) {
            $this->TrackLicenseActivationIfNew();
            $this->UpdateFormField('LicenseValidPopup', 'visible', true);
        } else {
            $this->UpdateFormField('LicenseInvalidPopup', 'visible', true);
        }

        $this->ReloadForm();
    }

    // Protokolliert eine Aktivierung auch dann, wenn der Lizenzschlüssel nur eingetragen
    // und über "Übernehmen" gespeichert wurde, ohne extra auf "Lizenz aktivieren" zu
    // klicken (die Lizenz wirkt bereits ab dem Speichern, siehe GetLicenseInfo/
    // HasFullLicense) - sonst ließe sich die Protokollierung fürs Erkennen von
    // Weiterverkauf/Weitergabe einfach umgehen. Loggt nur beim ERSTEN Erkennen einer
    // neuen Schlüssel+Licensee-Kombination (Vergleich gegen attributeActivationLog),
    // nicht bei jedem Aufruf erneut.
    private function TrackLicenseActivationIfNew(): void
    {
        $info = $this->GetLicenseInfo();
        if (!($info['valid'] ?? false)) {
            return;
        }

        $keyHash = hash('sha256', $this->ReadPropertyString(self::propertyLicenseKey));
        $licensee = $this->GetLicenseeIdentifier();

        $log = json_decode($this->ReadAttributeString(self::attributeActivationLog), true);
        if (!is_array($log)) {
            $log = [];
        }

        foreach ($log as $entry) {
            if (($entry['licenseKeyHash'] ?? '') === $keyHash && ($entry['licensee'] ?? '') === $licensee) {
                return;
            }
        }

        $this->RecordLicenseActivation($keyHash, $licensee, $log);
    }

    // Eigener Wrapper um IPS_GetLicensee() (wie CallGoogleTranslateAPI/
    // CallActivationReportAPI) - so bleibt die Identität in Tests mockbar, ohne den
    // globalen Symcon-Stub-Rückgabewert (fest 'max@mustermann.de') ändern zu müssen.
    private function GetLicenseeIdentifier(): string
    {
        return IPS_GetLicensee();
    }

    // Erzeugt den eigentlichen Log-Eintrag (lokal, auf die letzten 20 begrenzt) und
    // meldet ihn zusätzlich an LICENSE_ACTIVATION_REPORT_URL, sofern dort eine echte
    // URL eingetragen ist. Taucht derselbe licenseKeyHash irgendwann mit mehreren
    // unterschiedlichen licensee-Werten auf, ist das ein Hinweis auf Weiterverkauf/
    // Weitergabe des Schlüssels (z.B. als "gebraucht" im Ebay).
    private function RecordLicenseActivation(string $KeyHash, string $Licensee, array $Log): void
    {
        $entry = [
            'licenseKeyHash' => $KeyHash,
            'licensee'       => $Licensee,
            'activatedAt'    => time(),
        ];

        $log = array_slice([...$Log, $entry], -20);
        $this->WriteAttributeString(self::attributeActivationLog, json_encode($log));
        $this->SendDebug('LicenseActivation', json_encode($entry), 0);

        if (self::LICENSE_ACTIVATION_REPORT_URL !== '') {
            $this->CallActivationReportAPI(self::LICENSE_ACTIVATION_REPORT_URL, json_encode($entry));
        }
    }

    // Eigene, überschreibbare Methode fürs HTTP-POST (wie CallGoogleTranslateAPI) - so
    // bleibt der Netzwerkaufruf in Tests mockbar. Ein nicht erreichbarer Meldeserver
    // darf die Aktivierung selbst nicht verhindern, daher wird der Rückgabewert/Fehler
    // bewusst ignoriert.
    private function CallActivationReportAPI(string $Url, string $JsonBody): void
    {
        $ch = curl_init($Url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $JsonBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        @curl_exec($ch);
        curl_close($ch);
    }

    // Lizenzschlüssel-Format: "<base64url(JSON-Payload)>.<base64url(HMAC-SHA256)>".
    // Payload deckt sowohl Einmalkauf als auch Abo mit demselben Feld ab:
    // {"type": "one_time"|"subscription", "expiresAt": 0|<Unix-Timestamp>, "languageLimit": 0|N}.
    // expiresAt=0 bedeutet "läuft nie ab" (Einmalkauf) - Abo-Schlüssel tragen den
    // Zeitpunkt, bis zu dem bezahlt wurde (vom Verkaufssystem bei jeder Verlängerung
    // neu ausgestellt). languageLimit=0 bedeutet "unbegrenzt viele Zielsprachen"
    // (normale Vollversion) - N>0 kennzeichnet eine günstigere "Spezialversion" mit nur
    // N frei wählbaren Zielsprachen (z.B. eine Rabattaktion "eine Sprache für 50%
    // Rabatt"), siehe GetLicensedLanguageLimit. Fehlt das Feld (ältere Schlüssel), gilt
    // 0 = unbegrenzt. Rein offline prüfbar, kein Server-Roundtrip nötig.
    private function ValidateLicenseKey(string $Key): ?array
    {
        $parts = explode('.', $Key);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadPart, $signaturePart] = $parts;
        $payloadJson = base64_decode(strtr($payloadPart, '-_', '+/'), true);
        $signature = base64_decode(strtr($signaturePart, '-_', '+/'), true);
        if ($payloadJson === false || $signature === false) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $payloadJson, self::LICENSE_SIGNING_SECRET, true);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['type'], $payload['expiresAt'])) {
            return null;
        }
        $payload['languageLimit'] = (int) ($payload['languageLimit'] ?? 0);

        return $payload;
    }

    // Validiert den aktuell gespeicherten Lizenzschlüssel neu gegen die Uhrzeit (statt
    // den Cache blind zu übernehmen) - ein Abo kann zwischen zwei Aufrufen ablaufen,
    // ohne dass sich der gespeicherte Schlüssel selbst ändert.
    private function GetLicenseInfo(): array
    {
        $key = $this->ReadPropertyString(self::propertyLicenseKey);
        if ($key === '') {
            return ['valid' => false];
        }

        $payload = $this->ValidateLicenseKey($key);
        if ($payload === null) {
            return ['valid' => false];
        }

        $expiresAt = (int) $payload['expiresAt'];
        if ($expiresAt !== 0 && $expiresAt < time()) {
            return ['valid' => false, 'expired' => true, 'type' => $payload['type'], 'expiresAt' => $expiresAt, 'languageLimit' => $payload['languageLimit']];
        }

        return ['valid' => true, 'type' => $payload['type'], 'expiresAt' => $expiresAt, 'languageLimit' => $payload['languageLimit']];
    }

    private function HasFullLicense(): bool
    {
        if (!self::IS_TRIAL_BUILD) {
            return true;
        }

        return $this->GetLicenseInfo()['valid'];
    }

    // 0 = unbegrenzt (Vollversion-Build, keine/eine unbegrenzte Lizenz, oder gar keine
    // gültige Lizenz - Sprachauswahl regelt sich in dem Fall über GetFreeLanguageCodes).
    // N>0 = "Spezialversion"-Lizenz mit nur N frei wählbaren Zielsprachen, siehe
    // ValidateLicenseKey.
    private function GetLicensedLanguageLimit(): int
    {
        if (!self::IS_TRIAL_BUILD) {
            return 0;
        }

        $info = $this->GetLicenseInfo();
        if (!($info['valid'] ?? false)) {
            return 0;
        }

        return (int) ($info['languageLimit'] ?? 0);
    }

    // Defensive Absicherung gegen ein Downgrade (z.B. eine zeitlich befristete
    // "Spezialversion"-Lizenz läuft ab und der Schlüssel wird gegen eine mit
    // kleinerem languageLimit ausgetauscht) oder eine von Hand editierte
    // Konfiguration: kappt bei jedem ApplyChanges auf die ersten N bereits
    // konfigurierten Zielsprachen, statt mehr zuzulassen als lizenziert. Die
    // Admin-Oberfläche verhindert das Hinzufügen weiterer Sprachen zusätzlich schon
    // vorher (siehe PopulateFormElements), das hier ist nur das serverseitige Netz.
    private function EnforceLicensedLanguageLimit(): void
    {
        $limit = $this->GetLicensedLanguageLimit();
        if ($limit <= 0) {
            return;
        }

        $rows = json_decode($this->ReadPropertyString(self::propertyTargetLanguages), true);
        if (!is_array($rows) || count($rows) <= $limit) {
            return;
        }

        IPS_SetProperty($this->InstanceID, self::propertyTargetLanguages, json_encode(array_slice($rows, 0, $limit)));
        IPS_ApplyChanges($this->InstanceID);
    }

    private function IsTrialExpired(): bool
    {
        $expiresAt = $this->GetTrialExpiresAt();

        return $expiresAt !== 0 && $expiresAt < time();
    }

    // Zentrale Bedingung für "Testphase abgelaufen und kein gültiger Lizenzschlüssel" -
    // wird an mehreren Stellen gebraucht (ApplyChanges, ScanRootTree, Sprachwechsel),
    // daher als eigener Helfer statt dreifach ausgeschrieben.
    private function IsTrialLocked(): bool
    {
        return self::IS_TRIAL_BUILD && !$this->HasFullLicense() && $this->IsTrialExpired();
    }

    // Sprachcodes aus PROMOTIONAL_LANGUAGE_CAMPAIGNS, deren Zeitraum gerade läuft
    // (startsAt inklusive, endsAt exklusiv).
    private function GetActivePromotionalLanguageCodes(): array
    {
        $now = time();
        $codes = [];
        foreach (self::PROMOTIONAL_LANGUAGE_CAMPAIGNS as $campaign) {
            if ($now >= $campaign['startsAt'] && $now < $campaign['endsAt']) {
                $codes = array_merge($codes, $campaign['codes']);
            }
        }

        return $codes;
    }

    // Alle aktuell ohne Lizenz nutzbaren Sprachen: die dauerhaften TRIAL_LANGUAGE_CODES
    // plus gerade laufende Marketing-Aktionen (siehe PROMOTIONAL_LANGUAGE_CAMPAIGNS).
    private function GetFreeLanguageCodes(): array
    {
        return array_values(array_unique(array_merge(
            self::TRIAL_LANGUAGE_CODES,
            $this->GetActivePromotionalLanguageCodes()
        )));
    }

    // Ob ein Sprachwechsel-Versuch des Gasts an der abgelaufenen Testphase scheitert.
    // Die Basissprache und "Original" sind nie blockiert (das ist ja gerade der
    // Rückfall-Zustand), ebenso jede aktuell kostenfreie Sprache (siehe
    // GetFreeLanguageCodes) - auch dann, wenn die eigene 30-Tage-Testphase dieser
    // Instanz für sich genommen längst abgelaufen ist (Marketing-Aktionen wirken
    // unabhängig vom individuellen Testphase-Ablauf, siehe PROMOTIONAL_LANGUAGE_CAMPAIGNS).
    private function IsLanguageBlockedByTrial(string $Language): bool
    {
        if (!$this->IsTrialLocked()) {
            return false;
        }
        if ($Language === self::langOriginalImport || $Language === $this->ReadPropertyString(self::propertySourceLanguage)) {
            return false;
        }

        return !in_array($Language, $this->GetFreeLanguageCodes(), true);
    }

    // Schwenkt bei abgelaufener Testphase auf die unbearbeiteten Original-Importe
    // zurück, statt eine ggf. längst veraltete/unvollständige Übersetzung dauerhaft
    // eingefroren stehen zu lassen (führt sonst zu Beschwerden). Wird bei jeder
    // Gelegenheit aufgerufen, bei der ohnehin schon Code der Instanz läuft
    // (ApplyChanges, automatischer Rescan-Timer, Sprachwechsel-Versuch) - kein
    // eigener Timer nötig.
    private function ResetToOriginalLanguageIfNeeded(): void
    {
        // Absichtlich IsLanguageBlockedByTrial() statt nur "!= ORIGINAL_IMPORT": eine
        // gerade aktive, kostenfreie Sprache (Testphase-Sprache oder laufende
        // Marketing-Aktion, siehe GetFreeLanguageCodes) soll bei abgelaufener
        // Testphase bestehen bleiben, nicht bei jedem ApplyChanges/Rescan erneut
        // zurückgesetzt werden.
        $currentLanguage = $this->ReadPropertyString(self::propertyCurrentLanguage);
        if ($this->IsLanguageBlockedByTrial($currentLanguage)) {
            $this->ApplyLanguage(self::langOriginalImport);
        }
    }

    // 0 = Testphase wurde noch nicht gestartet (erstes ApplyChanges steht noch aus,
    // siehe ApplyChanges).
    private function GetTrialExpiresAt(): int
    {
        $startedAt = $this->ReadAttributeInteger(self::attributeTrialStartedAt);
        if ($startedAt === 0) {
            return 0;
        }

        return $startedAt + self::TRIAL_DURATION_DAYS * 24 * 60 * 60;
    }

    private function BuildTrialInfoText(): string
    {
        $expiresAt = $this->GetTrialExpiresAt();
        if ($expiresAt === 0) {
            return $this->Translate('Testversion - läuft ab, sobald diese Instanz zum ersten Mal übernommen wurde.');
        }

        $daysLeft = (int) ceil(($expiresAt - time()) / (24 * 60 * 60));
        $dateText = date('d.m.Y', $expiresAt);

        if ($daysLeft > 0) {
            return $this->Translate('Testversion - läuft ab am') . " $dateText ($daysLeft " . $this->Translate('Tag(e) verbleibend') . '). '
                . $this->Translate('Bis dahin voller Funktionsumfang, aber nur mit den 5 testweise freigeschalteten Sprachen (Isländisch, Walisisch, Zulu, Maori, Latein).');
        }

        return $this->Translate('Testversion abgelaufen am') . " $dateText. "
            . $this->Translate('Die Kachel zeigt Gästen ab jetzt wieder den unbearbeiteten Original-Text, ein weiterer Rescan ist blockiert, bis ein gültiger Lizenzschlüssel aktiviert wurde.')
            . ' ' . $this->Translate('Lizenz erwerben:') . ' ' . self::LICENSE_PURCHASE_URL;
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
                    $sourceLanguage,
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
                $sourceLanguage,
                self::langOriginalImportText
            ));
        }
    }

    // Fallback-Kette: gewünschte Sprache -> Rohtext ($RawField). $SelectedLanguage ist
    // der vom Gast tatsächlich gewählte Sprachcode (unpräfixiert, z.B. "en" oder die
    // Pseudo-Sprache "ORIGINAL_IMPORT"), $LanguageField der (ggf. präfixierte)
    // Property-Feldname dazu. $SourceLanguage dient hier nur dem Vergleich, nicht als
    // Feldname: es gibt keine separate "bereinigte" Basissprachspalte (mehr) - Google
    // lehnt eine Übersetzung von einer Sprache in sich selbst ohnehin ab (siehe
    // FillMissingTranslations) - daher liefert die Basissprache direkt den Rohtext,
    // genau wie "Original".
    private function ResolveRowValue(array $Row, string $SelectedLanguage, string $LanguageField, string $SourceLanguage, string $RawField): string
    {
        // "Original" und die Basissprache setzen beide auf den unbearbeiteten Rohtext
        // zurück (Tippfehler inklusive) - eine Art Werkseinstellung, unabhängig von
        // allen Übersetzungen.
        if ($SelectedLanguage === self::langOriginalImport || $SelectedLanguage === $SourceLanguage) {
            return $Row[$RawField] ?? '';
        }
        if (($Row[$LanguageField] ?? '') !== '') {
            return $Row[$LanguageField];
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

        // Testphase abgelaufen und kein gültiger Lizenzschlüssel: kein weiterer Rescan
        // (also keine neuen/geänderten Objekte mehr übersetzen), und die Kachel schwenkt
        // auf die unbearbeiteten Original-Importe zurück statt eingefroren in der zuletzt
        // aktiven Sprache stehen zu bleiben (siehe ResetToOriginalLanguageIfNeeded).
        if ($this->IsTrialLocked()) {
            $this->SetStatus(self::STATUS_TRIAL_EXPIRED);
            $this->ResetToOriginalLanguageIfNeeded();

            return;
        }

        $scannedNames = [];
        $scannedTexts = [];
        $this->WalkTree($rootID, $scannedNames, $scannedTexts, []);

        // Vorab-Check, bevor überhaupt übersetzt wird: ein Objekt ohne echten Namen
        // lässt sich nicht sinnvoll übersetzen und würde als Platzhalter-Text in der
        // Gäste-Visualisierung landen. Bricht den kompletten Rescan ab (kein Merge,
        // keine Übersetzung, kein Speichern), bis der Admin alle betroffenen Objekte
        // benannt hat.
        $unnamedObjects = [];
        foreach ($scannedNames as $objectID => $row) {
            if ($this->IsUnnamedObject($objectID, $row[self::langOriginalImport])) {
                $unnamedObjects[] = ['ObjectID' => $objectID, 'Path' => $row['Path']];
            }
        }
        $this->WriteAttributeString(self::attributeUnnamedObjects, json_encode($unnamedObjects));

        if ($unnamedObjects !== []) {
            $this->SetStatus(self::STATUS_UNNAMED_OBJECTS);
            $this->ReloadForm();

            return;
        }

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

    // Symcon lässt einen wirklich leeren Namen nicht zu - IPS_SetName('') (bzw. ein
    // Objekt, dem nie explizit ein Name gegeben wurde) erhält stattdessen automatisch
    // den Kernel-Platzhalter "Unnamed Object (ID: <id>)" (lokalisiert je nach
    // Konsolensprache, z.B. "Unbenanntes Objekt (ID: <id>)" auf Deutsch). Ein reiner
    // Leerstring-Vergleich würde also nie greifen. Das "(ID: <id>)"-Suffix mit der
    // exakt passenden Objekt-ID ist dagegen unabhängig von der Konsolensprache
    // erkennbar - dass ein Objekt zufällig genau so (mit seiner eigenen ID) manuell
    // benannt wurde, ist praktisch ausgeschlossen.
    private function IsUnnamedObject(int $ObjectID, string $Name): bool
    {
        return $Name === '' || preg_match('/\(ID:\s*' . $ObjectID . '\)\s*$/', $Name) === 1;
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
    // Übersetzt direkt von roh in jede ausgewählte Zielsprache, mit fester Quellsprache.
    // Es gibt bewusst KEINE separate "bereinigte" Basissprachspalte (mehr): Google
    // lehnt eine Übersetzung von einer Sprache in sich selbst komplett ab (HTTP 400
    // "Bad language pair"), und der frühere Versuch, das über eine offene
    // Spracherkennung als Tippfehlerkorrektur zu nutzen, ging bei kurzen Wörtern (z.B.
    // "Haus") real schief (von Google fälschlich als Hmong erkannt und dadurch völlig
    // falsch "übersetzt"). Die Basissprache liefert stattdessen direkt den Rohtext
    // (siehe ResolveRowValue) - identisch zu "Original".
    // $FieldGroups[]['capitalizeFirst']: Google großschreibt den ersten Buchstaben bei
    // kurzen Einzelwörtern/Titeln (im Gegensatz zu vollständigen Sätzen) nicht
    // zuverlässig - für Namen/Titel wird das Ergebnis daher nachträglich korrigiert.
    // Nicht für freien Inhaltstext (kann HTML enthalten, erster Buchstabe ist dort
    // nicht zwangsläufig ein Satzanfang).
    private function FillMissingTranslations(array $Rows, array $FieldGroups, string $SourceLanguage, array $TargetLanguages): array
    {
        foreach ($FieldGroups as $group) {
            $rawField = $group['raw'];
            $capitalizeFirst = $group['capitalizeFirst'] ?? false;

            foreach ($TargetLanguages as $language) {
                if ($language === $SourceLanguage) {
                    continue;
                }
                $Rows = $this->FillLanguageColumn($Rows, $rawField, $group['prefix'] . $language, $SourceLanguage, $language, $capitalizeFirst);
            }
        }

        return $Rows;
    }

    // Übersetzt für alle Zeilen, bei denen $ToField noch leer ist, den Text aus
    // $FromField nach $ToField (gebatcht in einem API-Aufruf).
    // $ToField ist der Property-Feldname zum Speichern (kann präfixiert sein, z.B.
    // "Text_de"), $TargetLanguageCode der reine Sprachcode, der an Google geht.
    private function FillLanguageColumn(array $Rows, string $FromField, string $ToField, string $ForceSource, string $TargetLanguageCode, bool $CapitalizeFirst): array
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

        $debugContext = sprintf('%s->%s (target=%s)', $FromField, $ToField, $TargetLanguageCode);

        // Debug: welche ObjectID/welcher Ausgangstext liegt an welcher Batch-Position -
        // zum Abgleich mit den GoogleTranslate_Request/_Response-Logs bei Verdacht auf
        // einen Zeilen-Verrutscher (Position hier entspricht der Position in q[], solange
        // der Text keine <style>/<script>-Blöcke enthält, die separat behandelt werden).
        $debugMapping = [];
        $batchPosition = 0;
        foreach ($pending as $rowIndex => $text) {
            $debugMapping[] = sprintf('[%d] ObjectID=%s: "%s"', $batchPosition, $Rows[$rowIndex]['ObjectID'] ?? '?', $text);
            $batchPosition++;
        }
        $this->SendDebug('GoogleTranslate_Mapping', $debugContext . "\n" . implode("\n", $debugMapping), 0);

        $translated = $this->TranslateBatch(array_values($pending), $ForceSource, $TargetLanguageCode, $debugContext);

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

    // Google Cloud Translate lehnt Anfragen mit mehr als 128 Texten in einem
    // Aufruf komplett ab ("Too many text segments") - größere Batches werden
    // daher in mehrere Aufrufe aufgeteilt.
    private const translateMaxTextsPerRequest = 128;

    private function TranslateBatch(array $Texts, string $Source, string $Target, string $DebugContext = ''): array
    {
        if ($Texts === []) {
            return [];
        }

        // Google lehnt identische Quell-/Zielsprache komplett ab (HTTP 400 "Bad
        // language pair: de|de") - der frühere Versuch, das als Rechtschreib-/
        // Tippfehlerkorrektur zu nutzen, scheitert also schon an der API selbst, nicht
        // erst an der (in einem früheren Fix bereits verworfenen) Fehlerkennungsgefahr.
        // Es gibt in diesem Fall ohnehin nichts zu übersetzen - Text unverändert zurück.
        if ($Source === $Target) {
            return $Texts;
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
            $translatedFlat = array_merge($translatedFlat, $this->TranslateChunk($chunk, $Source, $Target, $apiKey, $DebugContext));
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

    private function TranslateChunk(array $Texts, string $Source, string $Target, string $ApiKey, string $DebugContext = ''): array
    {
        if ($Texts === []) {
            return [];
        }

        $body = [
            'q'      => $Texts,
            'source' => $Source,
            'target' => $Target,
            // "html" statt "text": Google übersetzt dann nur den Text zwischen Tags,
            // nicht die Tags/Attribute selbst - wichtig für "Eigene Texte", die
            // vollständige HTML-Widgets (Symcon-HTMLBox-Inhalte) sein können.
            'format' => 'html',
        ];
        $payload = json_encode($body);

        // Vollständiger Request-Payload, positionsgleich mit dem GoogleTranslate_Mapping-
        // Log aus FillLanguageColumn (solange keine <style>/<script>-Blöcke vorkommen) -
        // damit sich pro Zeile nachvollziehen lässt, was Google wirklich gesendet und
        // zurückgegeben wurde, bei Verdacht auf einen Zeilen-Verrutscher oder eine
        // Fehlübersetzung durch Google selbst.
        $this->SendDebug('GoogleTranslate_Request', $DebugContext . ' | ' . $payload, 0);

        $response = $this->CallGoogleTranslateAPI(
            'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($ApiKey),
            $payload
        );

        $this->SendDebug('GoogleTranslate_Response', $DebugContext . ' | ' . ($response ?? '(keine Antwort)'), 0);

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

    // Gewählte Zielsprachen + die "Original"-Werkseinstellung, in dieser Reihenfolge -
    // gemeinsam genutzt von der Kachel und vom Konfigurationsformular. Die Basissprache
    // erscheint bewusst NICHT zusätzlich als eigener Eintrag: ihr Inhalt ist ohnehin
    // identisch mit "Original" (siehe ResolveRowValue), ein separater Eintrag wäre
    // nur eine verwirrende Dopplung im Dropdown.
    private function GetSelectableLanguageCodes(): array
    {
        $languages = $this->GetSelectedTargetLanguages();
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

    // Hinweis-Popup nach einem Sprachwechsel-Versuch bei abgelaufener Testphase -
    // eigene "ALERT"-Nachricht statt der sonstigen "REFRESH" (siehe module.html),
    // damit ein echter Browser-Dialog erscheint statt nur die Kachel neu zu zeichnen.
    // TRIAL_EXPIRED_ALERT_TEXT ist bewusst in die tatsächlich gewünschte Sprache
    // übersetzt (nicht die gerade aktive, die ja gerade auf Original zurückgesetzt
    // wurde) - der Gast klickte ja genau diese Sprache an.
    private function PushTrialExpiredAlert(string $RequestedLanguage): void
    {
        $sourceLanguage = $this->ReadPropertyString(self::propertySourceLanguage);
        $text = self::TRIAL_EXPIRED_ALERT_TEXT;

        if ($RequestedLanguage !== $sourceLanguage && $RequestedLanguage !== self::langOriginalImport) {
            $translated = $this->TranslateBatch([$text], $sourceLanguage, $RequestedLanguage);
            if (($translated[0] ?? '') !== '') {
                $text = $translated[0];
            }
        }

        $payload = json_encode(['action' => 'ALERT', 'payload' => ['text' => $text . "\n" . self::LICENSE_PURCHASE_URL]]);
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

        $codes = $this->GetSelectableLanguageCodes();
        $names = [];
        foreach ($codes as $code) {
            $names[$code] = $this->GetGuestLanguageName($code, $guestCache);
        }
        $this->SortCodesByLocalizedName($codes, $names, $this->ResolveDisplayLanguageCode($currentLanguage));

        $optionsHtml = '';
        foreach ($codes as $code) {
            $selected = $code === $currentLanguage ? ' selected' : '';
            $value = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($this->GetGuestLanguageLabel($code, $guestCache), ENT_QUOTES, 'UTF-8');
            $optionsHtml .= "<option value=\"{$value}\"{$selected}>{$label}</option>";
        }

        $globeIconHtml = $this->ReadPropertyBoolean(self::propertyShowGlobeIcon)
            ? '<span class="ipssl-globe" aria-hidden="true">🌐</span>'
            : '';

        $infoIconHtml = $this->ReadPropertyBoolean(self::propertyShowInfoIcon)
            ? '<span class="ipssl-info-icon" aria-hidden="true"'
                . ' onclick="alert(' . $this->BuildInfoAlertJs($guestCache) . ');">ⓘ</span>'
            : '';

        return '<div class="ipssl-select-row">'
            . $globeIconHtml
            . '<select onchange="requestAction(\'' . self::identLanguage . '\', this.value);">'
            . $optionsHtml
            . '</select>'
            . $infoIconHtml
            . '</div>';
    }

    // Sortiert $Codes anhand von $Names (ObjectID-Code => angezeigter Name) alphabetisch
    // "in der jeweiligen Sprache", also nach den sprachspezifischen Sortierregeln von
    // $Locale (z.B. Umlaute/Akzente an der richtigen Stelle), nicht nach rohen
    // Byte-/Codepoint-Werten. Nutzt Collator (PHP-intl-Erweiterung), falls installiert -
    // sonst einen einfachen, sprachneutralen Fallback (immer noch alphabetisch, nur
    // ohne locale-spezifische Sonderregeln).
    private function SortCodesByLocalizedName(array &$Codes, array $Names, string $Locale): void
    {
        if (class_exists('Collator')) {
            $collator = new Collator($Locale);
            usort($Codes, function (string $a, string $b) use ($Names, $collator): int {
                return $collator->compare($Names[$a], $Names[$b]);
            });

            return;
        }

        usort($Codes, function (string $a, string $b) use ($Names): int {
            return strnatcasecmp($Names[$a], $Names[$b]);
        });
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
        $heading = $GuestCache['infoHeading'] ?? self::INFO_HEADING_TEXT;
        $texts = $GuestCache['infoTexts'] ?? self::INFO_LIMITATION_TEXTS;

        // alert() zeigt reinen Text, kein HTML - Absätze also nur per Leerzeile
        // trennen, keine Tags/Aufzählungszeichen (beides würde wörtlich erscheinen
        // bzw. wirkte unpassend). Die Überschrift ist einfach die erste Zeile,
        // da alert() keinen eigenen Titel-Parameter kennt.
        $alertText = $heading . "\n\n" . implode("\n\n", $texts);

        return htmlspecialchars(json_encode($alertText, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    }

    // "Original" liefert seit dem Wegfall der separaten Basissprachspalte exakt
    // denselben Rohtext wie die Basissprache selbst (siehe ResolveRowValue) - für
    // Anzeigezwecke (Name/Flagge/Sortierung) daher immer die Basissprache selbst
    // auflösen, keine eigene "Original"-Beschriftung mehr, die fälschlich einen
    // Unterschied suggerieren würde.
    private function ResolveDisplayLanguageCode(string $Code): string
    {
        return $Code === self::langOriginalImport ? $this->ReadPropertyString(self::propertySourceLanguage) : $Code;
    }

    // Nur der Name, live in die aktuell aktive Gast-Sprache übersetzt - eigene Methode
    // (statt Teil von GetGuestLanguageLabel), da BuildLanguageSelectHtml() danach
    // sortiert, ohne die vorangestellte Flagge/den Code mit einzubeziehen.
    private function GetGuestLanguageName(string $Code, array $GuestCache): string
    {
        $resolvedCode = $this->ResolveDisplayLanguageCode($Code);

        return $GuestCache['names'][$resolvedCode] ?? $this->GetLanguageDisplayName($resolvedCode);
    }

    // "Name - code" mit vorangestellter Flagge (z.B. "🇬🇧 English - en").
    private function GetGuestLanguageLabel(string $Code, array $GuestCache): string
    {
        $resolvedCode = $this->ResolveDisplayLanguageCode($Code);
        $flag = self::LANGUAGE_FLAGS[$resolvedCode] ?? '';
        $name = $this->GetGuestLanguageName($Code, $GuestCache);
        $prefix = $flag === '' ? '' : $flag . ' ';

        return $prefix . $name . ' - ' . $resolvedCode;
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

        // Die Basissprache selbst braucht ebenfalls einen live übersetzten Namen: das
        // Dropdown zeigt sie für "Original" an (siehe GetGuestLanguageLabel), ist aber
        // seit dem Wegfall der separaten Basissprachspalte kein eigener Eintrag in
        // GetSelectableLanguageCodes() mehr.
        $neededCodes = array_diff(
            array_merge($this->GetSelectableLanguageCodes(), [$this->ReadPropertyString(self::propertySourceLanguage)]),
            [self::langOriginalImport]
        );
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

        // Info-Überschrift + Info-Hinweistexte in einem gemeinsamen Aufruf übersetzen
        // (statt je einem eigenen) - alles feste, kurze Texte, die ohnehin nur bei
        // Sprachwechsel/Cache-Ablauf einmal aktualisiert werden.
        $ownTexts = array_merge([self::INFO_HEADING_TEXT], self::INFO_LIMITATION_TEXTS);
        if ($language === 'de') {
            $translatedOwnTexts = $ownTexts;
        } else {
            $translatedOwnTexts = $this->TranslateBatch($ownTexts, 'de', $language);
        }

        $infoHeading = $translatedOwnTexts[0] ?? self::INFO_HEADING_TEXT;
        $infoTexts = [
            $translatedOwnTexts[1] ?? self::INFO_LIMITATION_TEXTS[0],
            $translatedOwnTexts[2] ?? self::INFO_LIMITATION_TEXTS[1],
        ];

        $cache = [
            'language'    => $language,
            'names'       => $names,
            'infoHeading' => $infoHeading,
            'infoTexts'   => $infoTexts,
            'fetchedAt'   => time(),
        ];

        $this->WriteAttributeString(self::attributeGuestLanguageNamesCache, json_encode($cache));

        return $cache;
    }

    // Baut die Spalten für die "ObjectNames"/"ObjectTexts"-Listen dynamisch anhand
    // der Ziel-Sprachen zusammen (Symcon-Formulare kennen keine Spalten mit
    // dynamischer Anzahl, daher wird das Formular bei jedem Öffnen neu erzeugt).
    // "Eigene Texte" bekommt zusätzlich eine Name-Kontextspalte (welche Kachel ist
    // das?). Es gibt bewusst KEINE eigene Basissprachen-Spalte (mehr) - deren Inhalt
    // wäre ohnehin identisch mit "Original-Import" (siehe ResolveRowValue).
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

    // Baut die editierbaren Sprachspalten für eine Feldgruppe: eine Spalte je
    // ausgewählter Zielsprache (direkt aus Original-Import übersetzt, siehe
    // FillMissingTranslations - keine eigene Basissprachen-Spalte). $Label
    // unterscheidet bei "Eigene Texte" zwischen Name- und Text-Spalten (leer für
    // Objektnamen, die nur eine Feldgruppe haben).
    private function BuildLanguageColumnSet(string $Prefix, string $Label, string $SourceLanguage, array $TargetLanguages): array
    {
        $withLabel = function (string $Text) use ($Label): string {
            return $Label !== '' ? sprintf('%s %s', $Label, $Text) : $Text;
        };

        $columns = [];

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

        // Testversion: nur die bewusst wenig praxisrelevanten TRIAL_LANGUAGE_CODES
        // plus gerade laufende Marketing-Aktionen anbieten (siehe GetFreeLanguageCodes),
        // damit der komplette Mechanismus testbar bleibt, ohne die Vollversion
        // vorwegzunehmen.
        $restrictToTrialLanguages = self::IS_TRIAL_BUILD && !$this->HasFullLicense();
        $freeLanguageCodes = $this->GetFreeLanguageCodes();

        $options = [];
        foreach ($this->BuildLanguageOptions() as $option) {
            if ($option['value'] === $SourceLanguage) {
                continue;
            }
            if ($restrictToTrialLanguages && !in_array($option['value'], $freeLanguageCodes, true)) {
                continue;
            }
            $options[] = $option;
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

    // "Original" ist im Admin-Formular (z.B. "Aktuell aktive Sprache") ebenfalls kein
    // eigener Eintrag mehr - zeigt stattdessen den Namen der Basissprache, deren
    // Rohtext "Original" tatsächlich liefert (siehe ResolveRowValue).
    private function GetLanguageDisplayName(string $Code): string
    {
        if ($Code === self::langOriginalImport) {
            $Code = $this->ReadPropertyString(self::propertySourceLanguage);
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
