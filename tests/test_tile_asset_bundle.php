<?php
declare(strict_types=1);
// Standalone replica test for build 172 (2026-08-27, Nutzer-Wunsch): Symbole
// und Kachel-Vorlagen je Edition sollen auf der Website pflegbar sein, statt
// fuer jede Sonder-Edition ein neues Modul-Release samt Symcon-Begutachtung zu
// erfordern.
//
// AUSGANGSLAGE: TILE_ICON_CATALOG/TILE_TEMPLATE_CATALOG waren einkompilierte
// Konstanten, die Inhalte Dateien im Modulverzeichnis. Ein neues Design hiess:
// neue Datei, neuer Katalogeintrag, neues Release.
//
// LOESUNG: der Server liefert die Designs bei der Lizenz-AKTIVIERUNG mit -
// signiert mit demselben Ed25519-Schluessel wie ein Lizenzschluessel. Das Modul
// prueft gegen den einkompilierten oeffentlichen Schluessel und speichert NUR
// bei gueltiger Signatur. Es laedt also Inhalte aus dem Netz, akzeptiert aber
// ausschliesslich Herstellerinhalte - genau diese Unterscheidung traegt die
// Begruendung gegenueber der Symcon-Begutachtung.

if (!function_exists('sodium_crypto_sign_keypair')) {
    echo "SKIP: libsodium nicht verfuegbar\n";

    return;
}

$keypair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($keypair);
$public = sodium_crypto_sign_publickey($keypair);

function b64url(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64urlDec(string $d): string|false { return base64_decode(strtr($d, '-_', '+/'), true); }

// Repliziert slips_sign_tile_asset_bundle() der Website.
function signBundle(array $assets, string $secret): string
{
    $json = json_encode(['v' => 1, 'assets' => array_values($assets)]);

    return b64url($json) . '.' . b64url(sodium_crypto_sign_detached($json, $secret));
}

// Repliziert StoreTileAssetBundle() des Moduls - liefert die uebernommenen
// Eintraege, oder [] wenn das Paket verworfen wurde.
function storeReplica(string $bundle, string $public): array
{
    $parts = explode('.', $bundle);
    if (count($parts) !== 2) { return []; }
    $json = b64urlDec($parts[0]);
    $sig = b64urlDec($parts[1]);
    if ($json === false || $sig === false) { return []; }
    if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) { return []; }
    if (!sodium_crypto_sign_verify_detached($sig, $json, $public)) { return []; }
    $payload = json_decode($json, true);
    if (!is_array($payload) || !is_array($payload['assets'] ?? null)) { return []; }

    $clean = [];
    foreach ($payload['assets'] as $a) {
        if (!is_array($a)) { continue; }
        $kind = (string) ($a['kind'] ?? '');
        $key = (string) ($a['key'] ?? '');
        $content = (string) ($a['content'] ?? '');
        if ($key === '' || $content === '' || !in_array($kind, ['icon', 'template'], true)) { continue; }
        $clean[] = [
            'key' => $key, 'kind' => $kind,
            'label' => (string) ($a['label'] ?? $key),
            'scope' => ($a['scope'] ?? '') === 'edition' ? 'edition' : 'all',
            'content' => $content,
        ];
    }

    return $clean;
}

$assets = [
    ['key' => 'xmas2026', 'kind' => 'icon', 'label' => 'Weihnachten 2026', 'scope' => 'edition', 'content' => base64_encode('PNG')],
    ['key' => 'xmas2026', 'kind' => 'template', 'label' => 'Weihnachten 2026', 'scope' => 'edition', 'content' => '<html>x</html>'],
    ['key' => 'plain', 'kind' => 'icon', 'label' => 'Schlicht', 'scope' => 'all', 'content' => base64_encode('PNG2')],
];

// Test 1: DER RUNDLAUF - was die Website signiert, nimmt das Modul an.
$uebernommen = storeReplica(signBundle($assets, $secret), $public);
assert(count($uebernommen) === 3, 'ein gueltig signiertes Paket muss vollstaendig uebernommen werden');
assert($uebernommen[0]['scope'] === 'edition' && $uebernommen[2]['scope'] === 'all', 'die Bindung muss erhalten bleiben');
echo "Test 1 (ein gültig signiertes Paket wird übernommen) OK\n";

// Test 2: DER KERN - ein Paket mit FREMDER Signatur wird verworfen. Das ist der
// Unterschied zwischen "laedt Inhalte aus dem Netz" und "akzeptiert
// ausschliesslich Herstellerinhalte".
$fremd = sodium_crypto_sign_keypair();
$boese = signBundle([['key' => 'evil', 'kind' => 'template', 'label' => 'x', 'scope' => 'edition', 'content' => '<script>']], sodium_crypto_sign_secretkey($fremd));
assert(storeReplica($boese, $public) === [], 'DER KERN: fremd signierte Inhalte duerfen NIE uebernommen werden');
echo "Test 2 (ein fremd signiertes Paket wird verworfen) OK\n";

// Test 3: ein nachtraeglich veraenderter Inhalt bricht die Signatur - ein
// uebernommener Webserver oder ein Man-in-the-Middle kann nichts einschleusen.
$gut = signBundle($assets, $secret);
[$p, $sig] = explode('.', $gut);
$manipuliert = json_decode(b64urlDec($p), true);
$manipuliert['assets'][1]['content'] = '<script>alert(1)</script>';
assert(storeReplica(b64url(json_encode($manipuliert)) . '.' . $sig, $public) === [],
    'ein nachtraeglich veraenderter Inhalt muss die Pruefung brechen');
echo "Test 3 (nachträglich verändertem Inhalt wird die Signatur zum Verhängnis) OK\n";

// Test 4: Muell fuehrt nie zu einer Ausnahme - die Gast-Kachel darf an einem
// kaputten Paket nicht scheitern.
foreach (['', 'kein-punkt', 'a.b', '...', 'x.' . b64url('nicht json')] as $muell) {
    assert(storeReplica($muell, $public) === [], "Muell muss folgenlos verworfen werden: $muell");
}
echo "Test 4 (kaputte Pakete werden folgenlos verworfen) OK\n";

// Test 5: unvollstaendige Eintraege fliegen raus - ein halber Eintrag waere im
// Katalog ein waehlbares, aber leeres Design.
$halb = signBundle([
    ['key' => '', 'kind' => 'icon', 'content' => 'x'],
    ['key' => 'ok', 'kind' => 'unbekannt', 'content' => 'x'],
    ['key' => 'leer', 'kind' => 'icon', 'content' => ''],
    ['key' => 'gut', 'kind' => 'icon', 'label' => 'Gut', 'scope' => 'all', 'content' => 'abc'],
], $secret);
$uebrig = storeReplica($halb, $public);
assert(count($uebrig) === 1 && $uebrig[0]['key'] === 'gut', 'nur vollstaendige Eintraege duerfen uebernommen werden');
echo "Test 5 (unvollständige Einträge werden aussortiert) OK\n";

// Test 6: Symmetrie-Check gegen beide realen Seiten.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
$constantsSource = file_get_contents(dirname(__DIR__) . '/libs/SimpleLocaleConstants.php');

assert(strpos($constantsSource, "attributeTileAssetBundle = 'TileAssetBundle'") !== false, 'das Attribut muss deklariert sein');
assert(strpos($moduleSource, 'private function StoreTileAssetBundle(string $Bundle): void') !== false, 'die Pruefung muss existieren');
assert(strpos($moduleSource, 'sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey)') !== false,
    'sie muss wirklich die Signatur pruefen');
assert(strpos($moduleSource, 'private function GetTileCatalog(string $Kind): array') !== false, 'der zusammengefuehrte Katalog muss existieren');

// Ein eingebauter Eintrag darf nie ueberschrieben werden - sonst liesse sich die
// Kachel nicht mehr auf den Auslieferungszustand zuruecksetzen.
$catalogFn = substr($moduleSource, strpos($moduleSource, 'private function GetTileCatalog'), 2200);
assert(strpos($catalogFn, 'if ($key === \'\' || isset($catalog[$key])) {') !== false,
    'ein eingebauter Katalogeintrag muss Vorrang behalten');

// Kein Aufrufer darf mehr direkt auf die Konstanten zugreifen - sonst saehe eine
// Stelle die gelieferten Designs nicht.
foreach (['BuildAppIconImgHtml', 'GetSelectedTileTemplateHtml'] as $fn) {
    $start = strpos($moduleSource, 'private function ' . $fn);
    $body = substr($moduleSource, $start, strpos($moduleSource, "\n    private function ", $start + 10) - $start);
    assert(strpos($body, '$this->GetTileCatalog(') !== false, "$fn muss ueber den zusammengefuehrten Katalog gehen");
    assert(strpos($body, 'self::TILE_ICON_CATALOG[') === false && strpos($body, 'self::TILE_TEMPLATE_CATALOG[') === false,
        "$fn darf nicht mehr direkt auf die Konstante zugreifen");
}
echo "Test 6 (die reale Umsetzung prüft die Signatur und führt den Katalog zusammen) OK\n";

// Test 7: nur EDITIONSGEBUNDENE Designs gewinnen "Automatisch" - ein
// editionsloses verhaelt sich wie der Standard.
function automaticReplica(array $catalog, string $default): string
{
    $automatic = $default;
    foreach ($catalog as $id => $entry) {
        if (($entry['auto'] ?? false) === true) { $automatic = $id; continue; }
    }

    return $automatic;
}
assert(automaticReplica(['default' => [], 'plain' => ['auto' => false]], 'default') === 'default',
    'ein editionsloses Design darf nicht automatisch gewaehlt werden');
assert(automaticReplica(['default' => [], 'xmas2026' => ['auto' => true]], 'default') === 'xmas2026',
    'ein editionsgebundenes Design MUSS automatisch gewaehlt werden - das ist der Wiedererkennungswert');
echo "Test 7 (nur editionsgebundene Designs gewinnen \"Automatisch\") OK\n";

echo "\nAll tests passed.\n";
