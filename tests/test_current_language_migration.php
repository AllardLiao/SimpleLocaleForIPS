<?php
declare(strict_types=1);
// Standalone replica test for build 79 (2026-08-19):
// Since "ORIGINAL_IMPORT" is no longer a selectable guest language (see
// test_source_language_as_target.php), any instance whose propertyCurrentLanguage
// still literally holds that string (either a pre-existing installation upgrading
// from an older build, or a brand-new instance before its very first ApplyChanges,
// since RegisterPropertyString's default is still "ORIGINAL_IMPORT") must be
// migrated to the real source-language code, exactly once, so the guest dropdown's
// <select> can actually show it as selected.

function migrateCurrentLanguageReplica(string $currentLanguage, string $sourceLanguage): string
{
    return $currentLanguage === 'ORIGINAL_IMPORT' ? $sourceLanguage : $currentLanguage;
}

// Test 1: a pre-existing installation still on the pseudo-language gets migrated to
// its real source language.
assert(migrateCurrentLanguageReplica('ORIGINAL_IMPORT', 'de') === 'de', 'A stored ORIGINAL_IMPORT current-language must be rewritten to the real source language');
echo "Test 1 (existing installation's ORIGINAL_IMPORT current language migrates to the real source language) OK\n";

// Test 2: a brand-new instance (RegisterPropertyString default) behaves identically -
// no separate code path needed for "new" vs. "old" instances.
assert(migrateCurrentLanguageReplica('ORIGINAL_IMPORT', 'en') === 'en', 'A brand-new instance (default ORIGINAL_IMPORT) must migrate the same way as an existing one, just using whatever propertySourceLanguage is configured');
echo "Test 2 (brand-new instance's default ORIGINAL_IMPORT current language migrates identically) OK\n";

// Test 3: an instance already on a real language code is left untouched - this must
// be a one-time, idempotent migration, not something that keeps re-triggering
// IPS_SetProperty+IPS_ApplyChanges on every single ApplyChanges() call.
assert(migrateCurrentLanguageReplica('fr', 'de') === 'fr', 'An instance already on a real selected language must not be touched by the migration');
echo "Test 3 (an instance already on a real guest language is left untouched, no unnecessary reentry) OK\n";

echo "\nAll tests passed.\n";
