<?php

declare(strict_types=1);

// Ganz schlankes Schwester-Modul zu Simple Locale (siehe SimpleLocaleForIPS): kein
// Objektbaum-Scan, keine Gast-Kachel, keine Lizenzprüfung - nur API-Key + Zielsprache
// und eine einzige Funktion. Zweck: Modulentwickler, die ihre eigenen dynamischen
// Inhalte künftig live über Simple Locale übersetzen lassen wollen (statt sie beim
// Sprachwechsel überschrieben zu bekommen), können ihre Integration hiermit gegen die
// echte Google-Übersetzung testen, ohne selbst schon eine volle, lizenzierte
// Simple-Locale-Instanz beim Kunden zu benötigen. Welche Zielsprache dabei konkret
// eingestellt ist, spielt für den Test keine Rolle - Hauptsache sie unterscheidet sich
// von der Quellsprache des jeweiligen Texts (Google lehnt eine Übersetzung von einer
// Sprache in sich selbst ab).
class SimpleLocaleTranslate extends IPSModuleStrict
{
    private const propertyGoogleTranslateAPIKey = 'GoogleTranslateAPIKey';
    private const propertyTargetLanguage = 'TargetLanguage';

    private const identTestTranslate = 'TestTranslate';

    private const STATUS_API_KEY_MISSING = 201;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(self::propertyGoogleTranslateAPIKey, '');
        $this->RegisterPropertyString(self::propertyTargetLanguage, 'en');
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetStatus($this->ReadPropertyString(self::propertyGoogleTranslateAPIKey) === '' ? self::STATUS_API_KEY_MISSING : 102);
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
    // (Name analog zu deren bestehender IPSSL_TranslateText): beliebiger Text + dessen
    // Quellsprache rein, live in die hier konfigurierte Zielsprache übersetzt zurück.
    // "Translate" (ohne Suffix) ist bereits von IPSModuleStrict selbst belegt (Symcons
    // eigene Konsolensprachen-Übersetzung, siehe $this->Translate() im Code), daher
    // TranslateText mit abweichender Signatur statt einer inkompatiblen Überschreibung.
    // Kein Cache, keine Objektbindung - reine Testfunktion für die eigene
    // Modulentwicklung. Leerer Text oder Quellsprache == Zielsprache liefert den Text
    // unverändert zurück (Google lehnt eine Übersetzung von einer Sprache in sich
    // selbst ohnehin ab).
    public function TranslateText(string $Text, string $SourceLanguage): string
    {
        $targetLanguage = $this->ReadPropertyString(self::propertyTargetLanguage);
        if ($Text === '' || $SourceLanguage === $targetLanguage) {
            return $Text;
        }

        $apiKey = $this->ReadPropertyString(self::propertyGoogleTranslateAPIKey);
        if ($apiKey === '') {
            return $Text;
        }

        $body = json_encode([
            'q'      => [$Text],
            'source' => $SourceLanguage,
            'target' => $targetLanguage,
            'format' => 'text',
        ]);

        $response = $this->CallGoogleTranslateAPI(
            'https://translation.googleapis.com/language/translate/v2?key=' . urlencode($apiKey),
            $body
        );

        $decoded = json_decode((string) $response, true);

        return $decoded['data']['translations'][0]['translatedText'] ?? $Text;
    }

    private function RunTestTranslate(): void
    {
        $result = $this->TranslateText('Hallo Welt', 'de');
        $this->UpdateFormField('TestTranslateResult', 'caption', $result);
    }

    // Eigene, überschreibbare Methode fürs HTTP-POST - so bleibt der Netzwerkaufruf in
    // Tests mockbar (siehe smoke-Tests), ohne echte Google-API-Zugangsdaten zu
    // benötigen.
    private function CallGoogleTranslateAPI(string $Url, string $JsonBody): ?string
    {
        $ch = curl_init($Url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $JsonBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response === false ? null : $response;
    }
}
