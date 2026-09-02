<?php
declare(strict_types=1);
// Standalone replica test for build 143 (2026-08-24, Nutzer-Wunsch mit
// Screenshot):
//
// Bei Visualisierungs-Hoehe "1" war die eingebaute Kachel nur wenige Pixel zu
// hoch und zeigte deshalb rechts einen Scrollbalken - optisch stoerend, und
// Hoehe "1" ist fuer eine reine Sprachauswahl die naheliegende Einstellung.
//
// Der Platz UNTER dem Dropdown ist genau dann ungenutzt, wenn keine der drei
// optionalen Hinweiszeilen (Testphase / Anbieter-Pause / Statistik) angezeigt
// wird. Nur dann bekommt die Zeile die Zusatzklasse "sloc-compact", die sich
// diesen Platz per negativem unteren Rand zurueckholt. Sind Hinweise sichtbar,
// braucht die Kachel die Hoehe ohnehin - dann darf nichts zusammengezogen
// werden, sonst wuerde der Hinweistext abgeschnitten.

// Repliziert die Klassenwahl aus BuildLanguageSelectHtml().
function rowClassReplica(string $trialNotice, string $pausedNotice, string $statsNotice): string
{
    $notices = $trialNotice . $pausedNotice . $statsNotice;

    return $notices === '' ? 'sloc-select-row sloc-compact' : 'sloc-select-row';
}

$NOTICE = '<div class="sloc-stats-notice" style="font-size:11px;">30 Übersetzungen/h</div>';

// Test 1: DER GEMELDETE FALL - keine Hinweiszeile aktiv (Normalzustand einer
// lizenzierten Instanz ohne eingeschaltete Statistik) -> kompakt.
assert(rowClassReplica('', '', '') === 'sloc-select-row sloc-compact', 'DER FALL AUS DEM REPORT: ohne jede Hinweiszeile muss die Kachel kompakt werden, sonst erzwingen die ungenutzten Pixel unter dem Dropdown bei Hoehe "1" einen Scrollbalken');
echo "Test 1 (ohne Hinweiszeilen wird die Zeile kompakt gesetzt) OK\n";

// Test 2: eingeschaltete Statistik -> NICHT kompakt. Genau der vom Nutzer
// benannte Kompromiss ("wenn der User die Statistiken sehen will, laesst sich
// das nicht aendern").
assert(rowClassReplica('', '', $NOTICE) === 'sloc-select-row', 'mit sichtbarer Statistik darf NICHT kompakt gesetzt werden - die Kachel braucht die Hoehe fuer den Hinweistext');
echo "Test 2 (mit eingeschalteter Statistik bleibt die volle Höhe erhalten) OK\n";

// Test 3: die beiden anderen Hinweisarten verhindern den Kompaktmodus ebenso -
// ein abgeschnittener Testphasen- oder Pausenhinweis waere schlimmer als ein
// Scrollbalken.
assert(rowClassReplica($NOTICE, '', '') === 'sloc-select-row', 'ein sichtbarer Testphasen-Hinweis muss den Kompaktmodus verhindern');
assert(rowClassReplica('', $NOTICE, '') === 'sloc-select-row', 'ein sichtbarer Anbieter-Pause-Hinweis muss den Kompaktmodus verhindern');
assert(rowClassReplica($NOTICE, $NOTICE, $NOTICE) === 'sloc-select-row', 'mehrere gleichzeitige Hinweise erst recht');
echo "Test 3 (auch Testphasen- und Pausenhinweise verhindern den Kompaktmodus) OK\n";

// Test 4: die Basisklasse bleibt IMMER erhalten - das gesamte uebrige Layout
// (Flex-Zeile, Hoehe der Bedienelemente, Icon-Groessen) haengt daran, und
// bestehende eigene Kachel-HTMLs von Nutzern binden ebenfalls daran.
foreach ([['', '', ''], ['', '', $NOTICE]] as $case) {
    assert(strpos(rowClassReplica(...$case), 'sloc-select-row') === 0, 'die Basisklasse "sloc-select-row" muss in JEDEM Fall erhalten bleiben - das restliche Layout und ggf. eigenes Nutzer-CSS haengen daran');
}
echo "Test 4 (die Basisklasse bleibt in jedem Fall erhalten) OK\n";

// Test 5: Symmetrie-Check gegen module.php - die Hinweise muessen VOR der
// Klassenwahl gebaut werden, sonst koennte die Klasse nicht von ihnen abhaengen.
$moduleSource = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.php');
assert(strpos($moduleSource, "\$rowClass = \$noticesHtml === '' ? 'sloc-select-row sloc-compact' : 'sloc-select-row';") !== false, 'module.php muss die Kompaktklasse genau dann setzen, wenn keine Hinweiszeile vorliegt');
$noticesPos = strpos($moduleSource, '$noticesHtml = $this->BuildTrialNoticeHtml(');
$classPos = strpos($moduleSource, '$rowClass = $noticesHtml');
assert($noticesPos !== false && $noticesPos < $classPos, 'die Hinweiszeilen muessen VOR der Klassenwahl gebaut werden');
// Die Hinweise duerfen nur noch EINMAL zusammengebaut werden - vor Build 143
// standen sie direkt in der return-Verkettung; blieben sie dort zusaetzlich
// stehen, wuerden sie doppelt gerendert.
assert(substr_count($moduleSource, '$this->BuildTranslationStatsNoticeHtml($ownUiTextRows, $currentLanguage)') === 1, 'die Hinweiszeilen duerfen nur an EINER Stelle zusammengebaut werden, sonst erscheinen sie doppelt in der Kachel');
echo "Test 5 (module.php baut die Hinweise genau einmal und vor der Klassenwahl) OK\n";

// Test 6: Symmetrie-Check gegen module.html - die CSS-Regel muss existieren und
// ausschliesslich unten wirken. Ein negativer Rand OBEN wuerde das Dropdown
// unter Symcons Titel-/Vergroessern-Overlay schieben.
$tileHtml = file_get_contents(dirname(__DIR__) . '/SimpleLocale/module.html');
assert(preg_match('/\.sloc-select-row\.sloc-compact\s*\{([^}]*)\}/', $tileHtml, $m) === 1, 'module.html muss eine Regel fuer .sloc-select-row.sloc-compact enthalten');
$rule = $m[1];
assert(preg_match('/margin-bottom:\s*-\d+px/', $rule) === 1, 'die Kompaktregel muss den unteren Rand negativ setzen - nur so verschwindet der ungenutzte Platz');
assert(preg_match('/margin-top\s*:/', $rule) !== 1, 'DER RISIKOFALL: die Kompaktregel darf den OBEREN Rand nicht antasten - dort liegt Symcons Titel-/Vergroessern-Overlay, das Dropdown wuerde darunter rutschen');
echo "Test 6 (die CSS-Regel wirkt ausschließlich nach unten, nicht in den Titelbereich) OK\n";

echo "\nAll tests passed.\n";
