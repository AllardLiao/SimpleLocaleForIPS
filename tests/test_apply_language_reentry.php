<?php
// Standalone simulation of the ApplyChanges()/ApplyLanguage() re-entrancy fix
// in SimpleLocaleForIPS/SimpleLocale/module.php. Mirrors the real control flow
// (copy-adapted, $this-> calls replaced by injected mutable state) since there
// is no live Symcon instance available here. Verifies: (a) the bug this fixes
// - changing "Aktuell aktive Sprache" via the config form now actually
// triggers the rename side effects - and (b) no infinite recursion / no
// wasted redundant full ApplyChanges() passes.
declare(strict_types=1);

function assertTrue(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: $msg\n");
        exit(1);
    }
    echo "OK: $msg\n";
}

final class ModuleSim
{
    public string $currentLanguageProperty = 'ORIGINAL_IMPORT';
    public string $lastAppliedLanguageAttr = 'ORIGINAL_IMPORT';
    public int $applyChangesCalls = 0;
    public int $applyLanguageCalls = 0;
    public int $renameSideEffectsRun = 0; // the IPS_SetName/SetValueString loops

    // Mirrors ApplyChanges()'s tail.
    public function applyChanges(): void
    {
        $this->applyChangesCalls++;

        $currentLanguage = $this->currentLanguageProperty;
        if ($currentLanguage !== $this->lastAppliedLanguageAttr) {
            $this->applyLanguage($currentLanguage);
        }
    }

    // Mirrors ApplyLanguage().
    private function applyLanguage(string $language): void
    {
        $this->applyLanguageCalls++;
        $this->lastAppliedLanguageAttr = $language;

        if ($this->currentLanguageProperty !== $language) {
            $this->currentLanguageProperty = $language;
            $this->applyChanges(); // IPS_ApplyChanges() reentry
        }

        $this->renameSideEffectsRun++;
    }

    // Simulates the admin editing the Select field in the config form and
    // clicking "Uebernehmen" - Symcon sets Current=Pending BEFORE calling our
    // ApplyChanges() override, so the property already reads the new value.
    public function simulateFormSave(string $newLanguage): void
    {
        $this->currentLanguageProperty = $newLanguage;
        $this->applyChanges();
    }

    // Simulates a guest clicking the tile dropdown (RequestAction path) -
    // property is NOT yet updated when ApplyLanguage() is first invoked.
    public function simulateGuestSwitch(string $newLanguage): void
    {
        $this->applyLanguagePublic($newLanguage);
    }

    public function applyLanguagePublic(string $language): void
    {
        $this->applyLanguage($language);
    }
}

// ---- Scenario 1 (THE BUG): admin changes "Aktuell aktive Sprache" via the
// config form. Before the fix, ApplyChanges() never called ApplyLanguage() at
// all - the property changed but no rename ever ran. -----------------------
$sim = new ModuleSim();
$sim->simulateFormSave('en');
assertTrue($sim->renameSideEffectsRun === 1, 'form-driven language change now runs the rename side effects exactly once (bug fix)');
assertTrue($sim->currentLanguageProperty === 'en', 'property ends up correctly set to the new language');
assertTrue($sim->lastAppliedLanguageAttr === 'en', 'tracking attribute matches after the change');
assertTrue($sim->applyChangesCalls === 1, 'form-driven change causes NO redundant reentrant ApplyChanges() pass - property already matched, so the IPS_SetProperty+IPS_ApplyChanges branch inside ApplyLanguage() is correctly skipped');

// ---- Scenario 2: no infinite recursion - a SECOND ApplyChanges() pass with
// an unchanged language must NOT call ApplyLanguage() again. ----------------
$callsBefore = $sim->applyLanguageCalls;
$sim->applyChanges(); // e.g. some unrelated property saved afterwards
assertTrue($sim->applyLanguageCalls === $callsBefore, 'a later ApplyChanges() pass with the language unchanged does not re-trigger ApplyLanguage()');

// ---- Scenario 3: guest-triggered switch (RequestAction path, unchanged
// behavior) still works exactly as before - property not yet set when
// ApplyLanguage() starts, so the IPS_SetProperty+IPS_ApplyChanges branch
// still runs (needed to persist for this path). ----------------------------
$sim2 = new ModuleSim();
$sim2->simulateGuestSwitch('fr');
assertTrue($sim2->renameSideEffectsRun === 1, 'guest-triggered switch runs rename side effects exactly once');
assertTrue($sim2->currentLanguageProperty === 'fr', 'guest switch persists the property');
assertTrue($sim2->lastAppliedLanguageAttr === 'fr', 'guest switch updates the tracking attribute too');

// ---- Scenario 4: switching to the SAME language twice in a row via the form
// must not do anything wasteful/wrong the second time. ----------------------
$sim3 = new ModuleSim();
$sim3->simulateFormSave('de');
$firstRunRenames = $sim3->renameSideEffectsRun;
$sim3->simulateFormSave('de'); // saved again, no actual change
assertTrue($sim3->renameSideEffectsRun === $firstRunRenames, 'resaving the same already-applied language does not redundantly rerun the rename');

echo "\nAll ApplyLanguage/ApplyChanges re-entrancy simulations passed.\n";
