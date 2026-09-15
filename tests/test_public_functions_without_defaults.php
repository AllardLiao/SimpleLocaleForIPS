<?php
// Build 214 (live gefunden bei der Anbindung von da8ters Room-Kachel): Symcon
// erzeugt aus jeder oeffentlichen Modulmethode eine SLOC_-Funktion - aber OHNE
// optionale Parameter. SLOC_TranslateExternalTexts($id, $texts) scheiterte mit
// ArgumentCountError, obwohl die Methode "string $SourceLanguage = ''" hatte. Die
// fremde Kachel fing den Fehler ab, und die Texte blieben stillschweigend
// unuebersetzt.
//
// Symmetrie-Check: keine oeffentliche Modulfunktion darf einen Standardwert
// haben - er taeuscht nur vor, man duerfe den Parameter weglassen. Ausgenommen
// sind die Methoden, die Symcon selbst aufruft und nicht als SLOC_ anbietet.
declare(strict_types=1);

require_once dirname(__DIR__) . '/.ips_stubs/autoload.php';
require_once dirname(__DIR__) . '/libs/SimpleLocaleConstants.php';
require_once dirname(__DIR__) . '/SimpleLocale/module.php';

$notExported = ['__construct', 'Create', 'Destroy', 'ApplyChanges', 'MessageSink', 'RequestAction', 'GetConfigurationForm', 'Migrate', 'GetVisualizationTile', 'ReceiveData', 'ForwardData', 'Translate'];

$offenders = [];
foreach ((new ReflectionClass(SimpleLocale::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
    if ($method->getDeclaringClass()->getName() !== SimpleLocale::class || in_array($method->getName(), $notExported, true)) {
        continue;
    }
    foreach ($method->getParameters() as $parameter) {
        if ($parameter->isDefaultValueAvailable()) {
            $offenders[] = $method->getName() . '($' . $parameter->getName() . ')';
        }
    }
}

assert($offenders === [], 'DER BUG: diese oeffentlichen Funktionen haben Standardwerte, die als SLOC_-Funktion nicht gelten: ' . implode(', ', $offenders));
echo "Test 1 (keine oeffentliche Modulfunktion mit Standardwert) OK\n";

echo "\nAll tests passed.\n";
