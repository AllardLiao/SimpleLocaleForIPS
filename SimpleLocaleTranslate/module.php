<?php

declare(strict_types=1);

// Gemeinsame Konstanten mit Simple Locale (siehe SimpleLocaleForIPS/libs) - deckt
// GoogleTranslateAPIKey, SourceLanguage, den Sprachlisten-Cache, den
// ShowApiKeyWarning-Ident und STATUS_TRANSLATE_ERROR ab, die in beiden Modulen exakt
// dieselbe Bedeutung haben. Nur das, was es wirklich nur hier gibt (TargetLanguage im
// Singular statt der Liste TargetLanguages, der Test-Button), bleibt lokal.
require_once __DIR__ . '/../libs/SimpleLocaleConstants.php';

use SimpleLocaleConstants\SimpleLocaleConstants;

// Ganz schlankes Schwester-Modul zu Simple Locale (siehe SimpleLocaleForIPS): kein
// Objektbaum-Scan, keine Gast-Kachel, keine Lizenzprüfung - nur API-Key, Quell-/
// Zielsprache und eine einzige Funktion. Zweck: Modulentwickler, die ihre eigenen
// dynamischen Inhalte künftig live über Simple Locale übersetzen lassen wollen (statt
// sie beim Sprachwechsel überschrieben zu bekommen), können ihre Integration hiermit
// gegen die echte Google-Übersetzung testen, ohne selbst schon eine volle, lizenzierte
// Simple-Locale-Instanz beim Kunden zu benötigen. Welche Zielsprache dabei konkret
// eingestellt ist, spielt für den Test keine Rolle - Hauptsache sie unterscheidet sich
// von der konfigurierten Quellsprache (Google lehnt eine Übersetzung von einer Sprache
// in sich selbst ab).
class SimpleLocaleTranslate extends IPSModuleStrict
{
    use SimpleLocaleConstants;

    // Einzige Zielsprache statt einer Liste wie propertyTargetLanguages im Hauptmodul -
    // gibt es dort nicht, daher eigener Name (Singular).
    private const propertyTargetLanguage = 'TargetLanguage';

    private const identTestTranslate = 'TestTranslate';

    // Fest verdrahteter Testsatz für den Button "Testübersetzung ausführen" - immer auf
    // Deutsch, unabhängig von der konfigurierten Quellsprache: der Nutzer kann diesen
    // Text nicht selbst anpassen, daher wäre es falsch, ihn als in der (ggf. anderen)
    // konfigurierten Quellsprache verfasst an Google zu melden.
    private const TEST_SENTENCE = 'Willkommen in der Welt einfacher Visualisierungsübersetzung!';
    private const TEST_SENTENCE_LANGUAGE = 'de';

    // Wie im Hauptmodul: höchstens 1x/Tag automatisch neu von Google abrufen.
    private const availableLanguagesMaxAgeSeconds = 86400;

    // Fallback, solange noch keine Sprachliste von Google geladen wurde (wie im
    // Hauptmodul) - wichtig nicht nur fürs UI, sondern strukturell: Symcon lehnt
    // "Übernehmen" mit "Current value ... is not available" ab, sobald der aktuelle
    // Property-Wert eines Select-Felds nicht unter dessen "options" auftaucht. Ohne
    // diesen Fallback wären die Standardwerte "de"/"en" beim allerersten Speichern
    // (bevor je ein gültiger API-Key existierte) nicht in den Optionen enthalten.
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

        $this->RegisterPropertyString(self::propertyGoogleTranslateAPIKey, '');
        $this->RegisterPropertyString(self::propertySourceLanguage, 'de');
        $this->RegisterPropertyString(self::propertyTargetLanguage, 'en');

        $this->RegisterAttributeString(self::attributeAvailableLanguagesCache, '[]');
        $this->RegisterAttributeInteger(self::attributeAvailableLanguagesFetchedAt, 0);
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetStatus(102);
    }

    public function GetConfigurationForm(): string
    {
        // Wie im Hauptmodul: die Sprachliste braucht zuerst einen gültigen API-Key,
        // bevor sie überhaupt abgerufen werden kann - ohne Key bleibt die
        // Zielsprachen-Auswahl ausgegraut mit erklärendem Hinweis (siehe unten), statt
        // eine leere/irreführende Liste zu zeigen.
        $this->RefreshAvailableLanguagesIfStale();

        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        foreach ($form['elements'] as &$element) {
            switch ($element['name'] ?? '') {
                // Wie propertySourceLanguage im Hauptmodul: immer aktiv, Optionen fallen
                // auf DEFAULT_LANGUAGES zurück, solange keine echte Liste geladen wurde.
                case self::propertySourceLanguage:
                    $element['options'] = $this->BuildLanguageOptions($this->ReadPropertyString(self::propertySourceLanguage));
                    break;

                // Wie die Zielsprachen-Liste im Hauptmodul: ausgegraut mit erklärendem
                // Hinweis, solange kein gültiger API-Key eine echte Sprachliste geladen
                // hat - Optionen kommen trotzdem aus BuildLanguageOptions() (inkl.
                // Fallback), damit der aktuelle Wert immer unter den Optionen bleibt.
                case self::propertyTargetLanguage:
                    $element['options'] = $this->BuildLanguageOptions($this->ReadPropertyString(self::propertyTargetLanguage));
                    $element['enabled'] = $this->HasCachedLanguages();
                    if (!$element['enabled']) {
                        $element['caption'] .= ' (' . $this->Translate('bitte zuerst gültigen API-Key speichern und Formular neu öffnen') . ')';
                    }
                    break;
            }
        }
        unset($element);

        // Testübersetzung ausgegraut, solange der letzte Google-Aufruf fehlgeschlagen
        // ist (STATUS_TRANSLATE_ERROR) - verhindert, dass der Button nach einem
        // ungültigen API-Key beliebig oft anklickbar bleibt und dabei jedes Mal
        // stillschweigend den unübersetzten Text zurückliefert (siehe RunTestTranslate).
        // Wird erst durch "Übernehmen" wieder freigegeben (setzt Status zurück auf 102),
        // genau wie im Hauptmodul. Der Button steckt in einem RowLayout, daher eine Ebene
        // tiefer als die restlichen "actions"-Einträge.
        foreach ($form['actions'] as &$action) {
            if (($action['type'] ?? '') !== 'RowLayout') {
                continue;
            }
            foreach ($action['items'] as &$item) {
                if (($item['name'] ?? '') !== 'TestTranslateButton') {
                    continue;
                }
                $item['enabled'] = $this->GetStatus() !== self::STATUS_TRANSLATE_ERROR;
                if (!$item['enabled']) {
                    $item['caption'] .= ' (' . $this->Translate('letzter Test fehlgeschlagen - bitte API-Key prüfen und über "Übernehmen" speichern') . ')';
                }
            }
            unset($item);
        }
        unset($action);

        return json_encode($form);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case self::identTestTranslate:
                $this->RunTestTranslate();
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    // Die eigentliche Funktion, um die es hier geht - dieselbe Aufrufform ist für den
    // entsprechenden Pro-Funktionsumfang der echten Simple-Locale-Instanz vorgesehen
    // (Name analog zu deren bestehender IPSSL_TranslateText): beliebiger Text rein,
    // live von der konfigurierten Quell- in die konfigurierte Zielsprache übersetzt
    // zurück. Quell-/Zielsprache sind bewusst feste Properties statt Aufrufparameter
    // (wie propertySourceLanguage/propertyTargetLanguages in der echten Instanz) -
    // die Quellsprache ist die, in der der Modulentwickler seine eigenen Texte
    // normalerweise schreibt, muss also nicht bei jedem Aufruf erneut mitgegeben
    // werden. "Translate" (ohne Suffix) ist bereits von IPSModuleStrict selbst belegt
    // (Symcons eigene Konsolensprachen-Übersetzung, siehe $this->Translate() im Code),
    // daher TranslateText statt einer inkompatiblen Überschreibung. Kein Cache, keine
    // Objektbindung - reine Testfunktion für die eigene Modulentwicklung. Leerer Text
    // oder Quellsprache == Zielsprache liefert den Text unverändert zurück (Google
    // lehnt eine Übersetzung von einer Sprache in sich selbst ohnehin ab).
    public function TranslateText(string $Text): string
    {
        return $this->TranslateFromTo($Text, $this->ReadPropertyString(self::propertySourceLanguage), $this->ReadPropertyString(self::propertyTargetLanguage));
    }

    // Gemeinsamer Kern für TranslateText() (konfigurierte Quellsprache) und den
    // Test-Button (immer TEST_SENTENCE_LANGUAGE, siehe RunTestTranslate) - Quellsprache
    // hier bewusst als Parameter statt fest über propertySourceLanguage, da der
    // Testsatz unabhängig von der konfigurierten Quellsprache immer Deutsch ist.
    private function TranslateFromTo(string $Text, string $SourceLanguage, string $TargetLanguage): string
    {
        if ($Text === '' || $SourceLanguage === $TargetLanguage) {
            return $Text;
        }

        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return $Text;
        }

        $body = json_encode([
            'q'      => [$Text],
            'source' => $SourceLanguage,
            'target' => $TargetLanguage,
            'format' => 'text',
        ]);

        $response = $this->CallGoogleTranslateAPI(
            'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($apiKey),
            $body
        );

        $decoded = json_decode((string) $response, true);

        return $decoded['data']['translations'][0]['translatedText'] ?? $Text;
    }

    // Wie identShowApiKeyWarning im Hauptmodul: fehlender API-Key zeigt ein Popup statt
    // einfach nur den unübersetzten Text im Ergebnis-Label anzuzeigen (sonst wirkt ein
    // fehlender Key wie ein stiller Fehler).
    private function RunTestTranslate(): void
    {
        if ($this->ReadPropertyString(self::propertyGoogleTranslateAPIKey) === '') {
            $this->UpdateFormField('ApiKeyMissingPopup', 'visible', true);

            return;
        }

        $result = $this->TranslateFromTo(self::TEST_SENTENCE, self::TEST_SENTENCE_LANGUAGE, $this->ReadPropertyString(self::propertyTargetLanguage));

        // Ein fehlgeschlagener Google-Aufruf liefert aus TranslateFromTo() bewusst nur
        // den unveränderten Text zurück (kein Absturz) - ohne diesen Check würde das
        // Ergebnis-Label wie eine erfolgreiche (Nicht-)Übersetzung aussehen, obwohl der
        // Key falsch war (siehe CallGoogleTranslateAPI, setzt STATUS_TRANSLATE_ERROR).
        // ReloadForm() graut sofort auch den Button aus (siehe GetConfigurationForm),
        // statt erst beim nächsten manuellen Neuladen des Formulars.
        if ($this->GetStatus() === self::STATUS_TRANSLATE_ERROR) {
            $this->UpdateFormField('TranslateFailedPopup', 'visible', true);
            $this->ReloadForm();

            return;
        }

        $this->UpdateFormField('TestTranslateResult', 'caption', $result);
    }

    private function RefreshAvailableLanguagesIfStale(): void
    {
        if ($this->ReadPropertyString(self::propertyGoogleTranslateAPIKey) === '') {
            return;
        }

        $fetchedAt = $this->ReadAttributeInteger(self::attributeAvailableLanguagesFetchedAt);
        if ((time() - $fetchedAt) < self::availableLanguagesMaxAgeSeconds) {
            return;
        }

        $this->FetchSupportedLanguages();
    }

    // Sprachnamen kommen in der konfigurierten Quellsprache zurück (Google übersetzt
    // die Sprachnamen selbst mit) - für den Modulentwickler naheliegender als z.B. eine
    // feste Konsolensprache, da er sich mit dieser Quellsprache ohnehin schon
    // beschäftigt.
    private function FetchSupportedLanguages(): void
    {
        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return;
        }

        $url = 'https://translation.googleapis.com/language/translate/v2/languages'
            . '?key=' . urlencode($apiKey)
            . '&target=' . urlencode($this->ReadPropertyString(self::propertySourceLanguage));

        $response = $this->CallGoogleTranslateAPI($url, null);
        if ($response === null) {
            return;
        }

        $decoded = json_decode($response, true);
        $languages = $decoded['data']['languages'] ?? null;
        if (!is_array($languages)) {
            return;
        }

        $result = [];
        foreach ($languages as $entry) {
            $code = $entry['language'] ?? '';
            if ($code !== '') {
                $result[] = ['code' => $code, 'name' => $entry['name'] ?? $code];
            }
        }

        $this->WriteAttributeString(self::attributeAvailableLanguagesCache, json_encode($result));
        $this->WriteAttributeInteger(self::attributeAvailableLanguagesFetchedAt, time());
    }

    private function HasCachedLanguages(): bool
    {
        $cached = json_decode($this->ReadAttributeString(self::attributeAvailableLanguagesCache), true);

        return is_array($cached) && $cached !== [];
    }

    // $CurrentValue: der aktuell konfigurierte Property-Wert (Quell- oder Zielsprache) -
    // wird bei Bedarf zusätzlich in die Optionsliste aufgenommen, damit "Übernehmen"
    // nie an "Current value ... is not available" scheitert (siehe DEFAULT_LANGUAGES).
    private function BuildLanguageOptions(string $CurrentValue = ''): array
    {
        $cached = json_decode($this->ReadAttributeString(self::attributeAvailableLanguagesCache), true);
        if (!is_array($cached) || $cached === []) {
            $cached = self::DEFAULT_LANGUAGES;
        }

        $options = array_map(fn ($language) => ['caption' => $language['name'], 'value' => $language['code']], $cached);

        if ($CurrentValue !== '' && !in_array($CurrentValue, array_column($options, 'value'), true)) {
            $options[] = ['caption' => $CurrentValue, 'value' => $CurrentValue];
        }

        usort($options, fn ($a, $b) => strnatcasecmp($a['caption'], $b['caption']));

        return $options;
    }

    // Eigene, überschreibbare Methode fürs HTTP - so bleibt der Netzwerkaufruf in Tests
    // mockbar (siehe smoke-Tests). $JsonBody === null -> GET (Sprachliste abrufen),
    // sonst POST (übersetzen). Ein fehlgeschlagener Aufruf setzt STATUS_TRANSLATE_ERROR
    // (gleicher Code wie im Hauptmodul) statt den fehlenden API-Key selbst schon als
    // Fehlerstatus zu werten (siehe Klassenkommentar zu STATUS_TRANSLATE_ERROR oben).
    private function CallGoogleTranslateAPI(string $Url, ?string $JsonBody): ?string
    {
        $ch = curl_init($Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($JsonBody !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $JsonBody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400 || $error !== '') {
            $this->SendDebug('GoogleTranslate', sprintf('HTTP %s, Fehler: %s, Antwort: %s', $httpCode, $error, (string) $response), 0);
            $this->SetStatus(self::STATUS_TRANSLATE_ERROR);

            return null;
        }

        return $response;
    }
}
