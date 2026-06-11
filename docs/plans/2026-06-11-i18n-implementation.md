# i18n Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `glueful/i18n` as the first-party localization extension for locales, translation catalogs, fallback rules, missing-translation reporting, import/export of catalogs, and regional formatting helpers.

**Architecture:** The extension satisfies the framework's existing `ServiceProvider::loadMessageCatalogs()` hook by binding string service id `translation.manager`. Typed translator and locale resolver contracts live inside the extension namespace in v1. The extension owns persisted locales/translations and exposes services, CLI, and optional admin APIs. Lemma and other domain packages can depend on it for platform localization while keeping domain content localization in their own packages.

**Tech Stack:** PHP 8.3+, Glueful Framework 1.54.0+, PHPUnit 10.5, PHPStan level 6, Glueful extension `ServiceProvider`, Glueful migrations, Glueful container DSL, PHP `intl` when available with graceful fallback, SQLite/temp database for repository tests.

**Spec:** `docs/specs/2026-06-11-i18n-design.md` (read it first).

**Conventions used throughout:**
- Namespace `Glueful\Extensions\I18n\` maps to `src/`; tests namespace `Glueful\Extensions\I18n\Tests\` maps to `tests/`.
- Run commands from `/Users/michaeltawiahsowah/Sites/glueful/extensions/i18n`.
- Bind `translation.manager` for framework compatibility.
- Do not add a framework-core typed translator contract in v1.
- Do not create an `i18n_domains` table in v1.
- Every implementation task is red/green: write the named failing test, run the exact `--filter`, implement the smallest passing code, rerun the same filter, then commit.
- Put controllers, guards, managers, repositories, commands, and aliases in `I18nServiceProvider::services()`. The container compiles before `boot()`, so `boot()` is only for loading routes, migrations, catalogs, and commands.
- DB translations override file catalogs by default. This is locked behavior, not a config afterthought.
- Missing tracking defaults off and must be rate-limited when enabled.
- Permission guard tests must cover the four failure/success cases named in Task 7.

---

## File Structure

- `composer.json`: package metadata, one PSR-4 root, `autoload.classmap: ["migrations/"]`, `extra.glueful`, scripts.
- `phpunit.xml`: Unit and Integration suites.
- `phpstan.neon`: level 6.
- `CHANGELOG.md`: `0.1.0` entry.
- `config/i18n.php`: default/fallback locale, enabled locales, missing tracking default false, `db_overrides_catalogs` true, cache TTL.
- `migrations/001_CreateI18nTables.php`: locales/translations/missing rows.
- `src/Contracts/*`: translator, locale resolver, locale manager, translation repository interfaces.
- `src/Services/*`: translation manager, locale manager/resolver, cache, formatter, missing recorder.
- `src/Repositories/*`: locale, translation, missing repositories.
- `src/Http/*`: permission guard and controllers.
- `src/Console/*`: locales, missing, sync-catalogs, import, export.
- `tests/Support/I18nTestCase.php`: in-memory SQLite harness, migrations, tiny container.

---

### Task 1: Package Scaffold, Tooling, And Test Harness

**Files:**
- Create or update: `composer.json`
- Create: `phpunit.xml`
- Create: `phpstan.neon`
- Create: `tests/bootstrap.php`
- Create: `tests/Support/I18nTestCase.php`
- Create: `CHANGELOG.md`
- Review existing: `.gitignore`

- [ ] Create `composer.json` with package name `glueful/i18n`, type `glueful-extension`, require php only, require-dev `glueful/framework:^1.54.0`, `phpunit/phpunit:^10.5`, `squizlabs/php_codesniffer:^3.6`, `phpstan/phpstan:^1.0`, PSR-4 autoload for `Glueful\Extensions\I18n\`, `autoload.classmap: ["migrations/"]`, and `extra.glueful` provider `Glueful\Extensions\I18n\I18nServiceProvider`, version `0.1.0`, requires `{"glueful": ">=1.54.0", "extensions": []}`.
- [ ] Require only hard runtime dependencies needed for v1; keep `ext-intl` suggested rather than mandatory unless implementation chooses to require it.
- [ ] Create `phpunit.xml` with Unit (`tests/Unit`) and Integration (`tests/Integration`) suites and `tests/bootstrap.php` as bootstrap.
- [ ] Create `phpstan.neon` with `level: 6`, `paths: [src]`, and bootstrap through Composer autoload if needed.
- [ ] Create `tests/bootstrap.php` requiring `vendor/autoload.php`.
- [ ] Create `tests/Support/I18nTestCase.php` modeled on `glueful/subscriptions`' SQLite harness: create a `Glueful\Database\Connection` against in-memory SQLite, run `CreateI18nTables` once it exists, expose `connection()` and `appContext()`, and provide helpers `seedLocale()` and `seedTranslation()`.
- [ ] Preserve the existing `.gitignore` if present; only add missing ignores for `/vendor/`, `/composer.lock`, `.phpunit.cache/`, and `.DS_Store`.
- [ ] Create `CHANGELOG.md` with an Unreleased section and an initial `0.1.0` planning entry.
- [ ] Run `composer install`.
- [ ] Run `vendor/bin/phpunit --filter=I18nTestCase`.
- [ ] Expected: FAIL until migration class exists; keep this known failure for Task 3.
- [ ] Commit: `git add composer.json phpunit.xml phpstan.neon tests/bootstrap.php tests/Support/I18nTestCase.php CHANGELOG.md .gitignore && git commit -m "chore: scaffold i18n extension tooling"`

---

### Task 2: Translator Contracts And Message Catalog Compatibility

**Files:**
- Create: `src/Contracts/TranslatorInterface.php`
- Create: `src/Contracts/LocaleResolverInterface.php`
- Create: `src/Contracts/LocaleManagerInterface.php`
- Create: `src/Contracts/TranslationRepositoryInterface.php`
- Create: `src/Services/TranslationManager.php`
- Test: `tests/Services/TranslationManagerTest.php`

- [ ] Write failing `TranslationManagerTest` proving `addMessages('en', 'messages', ['hello' => 'Hello {name}'])` is returned by `trans('hello', ['name' => 'Ada'], 'en', 'messages')`.
- [ ] Run `vendor/bin/phpunit --filter=TranslationManagerTest`.
- [ ] Expected: FAIL because contracts/services are missing.
- [ ] Define `TranslatorInterface::trans(string $key, array $parameters = [], ?string $locale = null, string $domain = 'messages'): string`.
- [ ] Define `LocaleResolverInterface::resolveLocale(mixed $context = null): string`.
- [ ] Define `LocaleManagerInterface` with `all()`, `enabled()`, `default()`, and `fallbackChain(string $locale): array`.
- [ ] Define `TranslationRepositoryInterface` with `get()`, `put()`, and `missing()` exactly as the spec public API.
- [ ] Implement `TranslationManager` with `addMessages(string $locale, string $domain, array $messages): void` so framework `loadMessageCatalogs()` can call it.
- [ ] Implement in-memory catalog lookup for messages loaded from files before persistence/caching is added.
- [ ] Support placeholder interpolation using named placeholders from the parameters array.
- [ ] Unit test `addMessages()` followed by `trans()` for multiple locales and domains.
- [ ] Unit test missing keys return the translation key itself when no fallback is available.
- [ ] Run `vendor/bin/phpunit --filter=TranslationManagerTest`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Contracts src/Services tests/Services && git commit -m "feat(i18n): bind translator contracts and catalog manager"`

---

### Task 3: Database Migrations And Repositories

**Files:**
- Create: `migrations/001_CreateI18nTables.php`
- Create: `src/Repositories/LocaleRepository.php`
- Create: `src/Repositories/TranslationRepository.php`
- Create: `src/Repositories/MissingTranslationRepository.php`
- Test: `tests/Repositories/LocaleRepositoryTest.php`
- Test: `tests/Repositories/TranslationRepositoryTest.php`

- [ ] Write failing `tests/Integration/MigrationsTest.php::testI18nTablesExist` using `I18nTestCase`.
- [ ] Run `vendor/bin/phpunit --filter=MigrationsTest`.
- [ ] Expected: FAIL because `CreateI18nTables` is missing.
- [ ] Add migrations for `i18n_locales`, `i18n_translations`, and `i18n_missing_translations` exactly as defined in the spec.
- [ ] Keep domains as string namespaces on `i18n_translations`; do not create an `i18n_domains` table.
- [ ] Update `I18nTestCase` to run `CreateI18nTables`.
- [ ] Run `vendor/bin/phpunit --filter=MigrationsTest`.
- [ ] Expected: PASS.
- [ ] Write failing repository tests for locale create/update/default/enabled/fallback cycle rejection, translation upsert, unique `domain + locale + key`, and missing hit increments.
- [ ] Run `vendor/bin/phpunit --filter='LocaleRepositoryTest|TranslationRepositoryTest'`.
- [ ] Expected: FAIL because repositories are missing.
- [ ] Implement locale create/update/enable/default/fallback operations.
- [ ] Reject fallback cycles on write, including direct cycles and longer chains.
- [ ] Implement translation upsert and bundle loading by `locale + domain`.
- [ ] Implement optional missing-translation recording with hit increments and first/last seen timestamps.
- [ ] Test unique `domain + locale + key` behavior through repository methods.
- [ ] Test fallback cycle detection.
- [ ] Run `vendor/bin/phpunit --filter='MigrationsTest|LocaleRepositoryTest|TranslationRepositoryTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add migrations src/Repositories tests/Integration tests/Repositories tests/Support && git commit -m "feat(i18n): add schema and repositories"`

---

### Task 4: Locale Resolution And Fallback Chains

**Files:**
- Create: `src/Services/LocaleResolver.php`
- Create: `src/Services/LocaleManager.php`
- Create: `src/Support/LocaleContext.php`
- Test: `tests/Services/LocaleResolverTest.php`
- Test: `tests/Services/LocaleManagerTest.php`

- [ ] Write failing locale resolver tests for explicit locale, request override, identity claim `preferred_locale`, soft tenant/app context, configured default, disabled locale exclusion, and fallback chain.
- [ ] Run `vendor/bin/phpunit --filter='LocaleResolverTest|LocaleManagerTest'`.
- [ ] Expected: FAIL because resolver/manager are missing.
- [ ] Implement locale resolution order: explicit locale, request override if enabled, identity claim `preferred_locale`, tenant/app locale if available through soft context, configured default locale.
- [ ] Keep users optional; read preferred locale from identity claims/context, not from a hard users table field.
- [ ] Implement fallback chain order: requested locale, locale parent chain, global fallback locale, key itself.
- [ ] Ensure disabled locales are not selected unless explicitly allowed by internal/admin operations.
- [ ] Unit test explicit locale wins over context and default.
- [ ] Unit test identity claim `preferred_locale` is used when no explicit locale exists.
- [ ] Unit test fallback chain stops and reports clearly if a cycle somehow exists in stored data.
- [ ] Run `vendor/bin/phpunit --filter='LocaleResolverTest|LocaleManagerTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Services src/Support tests/Services && git commit -m "feat(i18n): resolve locales and fallback chains"`

---

### Task 5: Persistence-Backed Translator, Cache, And Plurals

**Files:**
- Update: `src/Services/TranslationManager.php`
- Create: `src/Services/TranslationCache.php`
- Create: `src/Services/MessageFormatter.php`
- Test: `tests/Services/PersistedTranslationManagerTest.php`
- Test: `tests/Services/MessageFormatterTest.php`

- [ ] Write failing `PersistedTranslationManagerTest` proving DB translations override file catalog values by default when both define the same `locale + domain + key`.
- [ ] Write failing cache tests proving two translation lookups for the same `locale + domain + version` do not perform a second repository read, and `put()` invalidates/reloads the bundle.
- [ ] Write failing missing-tracking tests proving missing tracking defaults off and rate limits writes when enabled.
- [ ] Run `vendor/bin/phpunit --filter='PersistedTranslationManagerTest|MessageFormatterTest|MissingTranslationRecorderTest'`.
- [ ] Expected: FAIL because cache/formatter/missing recorder are incomplete.
- [ ] Extend `TranslationManager` to load persisted bundles through `TranslationRepository`.
- [ ] Merge file catalog bundles first and DB bundles second when `db_overrides_catalogs` is true. Keep the config default true.
- [ ] Cache bundles by `locale + domain + version`; avoid per-call database reads.
- [ ] Add cache invalidation/version bump hooks on translation writes and imports.
- [ ] Implement `MissingTranslationRecorder` with config default off and a rate-limit key based on `locale + domain + key`.
- [ ] Implement plural/message formatting using ICU MessageFormat when `intl` support is present.
- [ ] Provide a simple deterministic fallback formatter when `intl` is not available.
- [ ] Unit test cache hit avoids a second repository read.
- [ ] Unit test cache invalidation reloads changed translations.
- [ ] Unit test plural formatting with the `intl` path when available and fallback behavior otherwise.
- [ ] Run `vendor/bin/phpunit --filter='PersistedTranslationManagerTest|MessageFormatterTest|MissingTranslationRecorderTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add src/Services tests/Services && git commit -m "feat(i18n): merge DB overrides, cache bundles, and record misses safely"`

---

### Task 6: Service Provider, Config, Permissions, And Guards

**Files:**
- Create: `src/I18nServiceProvider.php`
- Create: `config/i18n.php`
- Create: `src/Http/RequireI18nPermission.php`
- Test: `tests/I18nServiceProviderTest.php`
- Test: `tests/Http/RequireI18nPermissionTest.php`

- [ ] Write failing `I18nServiceProviderTest` asserting services include aliases/bindings for `translation.manager`, `TranslatorInterface`, `LocaleResolverInterface`, `LocaleManagerInterface`, `TranslationRepositoryInterface`, controllers, commands, and guard before `boot()` runs.
- [ ] Write failing guard tests named `testPermissionMiddlewareReturns403WithoutAuthenticatedUser`, `testPermissionMiddlewareReturns403WhenManagerUnavailable`, `testPermissionMiddlewareReturns403WithRealManagerAndNoProvider`, `testPermissionMiddlewareReturns403WhenPermissionDenied`, and `testPermissionMiddlewareCallsNextOnlyWhenAllowed`.
- [ ] Run `vendor/bin/phpunit --filter='I18nServiceProviderTest|RequireI18nPermissionTest'`.
- [ ] Expected: FAIL because provider/guard wiring is missing.
- [ ] Implement `I18nServiceProvider extends Glueful\Extensions\ServiceProvider`.
- [ ] Register `translation.manager` as a string service id for framework compatibility.
- [ ] Bind `TranslatorInterface`, `LocaleResolverInterface`, and locale/translation managers.
- [ ] Register controllers, guard/middleware, and console commands in `services()`; do not register these in `boot()`.
- [ ] Register migrations with source `glueful/i18n`.
- [ ] Declare permissions for locale read/write, translation read/write, imports, exports, and missing-translation reports.
- [ ] Implement an extension-owned guard/middleware that calls `Glueful\Permissions\PermissionManager::can()` directly for management routes.
- [ ] Unit test that `translation.manager` is present and has `addMessages()`.
- [ ] Unit test that permission denial blocks management endpoints.
- [ ] Run `vendor/bin/phpunit --filter='I18nServiceProviderTest|RequireI18nPermissionTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add config src/I18nServiceProvider.php src/Http tests && git commit -m "feat(i18n): wire provider and permission guard"`

---

### Task 7: HTTP API, CLI, And Catalog Import/Export

**Files:**
- Create: `routes/routes.php`
- Create: `src/Http/Controllers/LocaleController.php`
- Create: `src/Http/Controllers/TranslationController.php`
- Create: `src/Console/LocaleListCommand.php`
- Create: `src/Console/TranslationSyncCommand.php`
- Create: `src/Console/TranslationValidateCommand.php`
- Create: `src/Console/TranslationImportCommand.php`
- Create: `src/Console/TranslationExportCommand.php`
- Create: `src/Services/CatalogImporter.php`
- Create: `src/Services/CatalogExporter.php`
- Test: `tests/Http/LocaleControllerTest.php`
- Test: `tests/Http/TranslationControllerTest.php`
- Test: `tests/Console/TranslationCommandsTest.php`
- Test: `tests/Services/CatalogRoundTripTest.php`

- [ ] Write failing controller tests for all locked routes: `GET /i18n/locales`, `POST /i18n/locales`, `PATCH /i18n/locales/{code}`, `GET /i18n/translations`, `POST /i18n/translations`, `PATCH /i18n/translations/{uuid}`, `GET /i18n/missing`, `POST /i18n/import`, and `GET /i18n/export`.
- [ ] Write failing `CatalogRoundTripTest`: export a catalog to PHP-array or JSON artifact, import it into an empty database, and assert the same `domain + locale + key + value` rows return.
- [ ] Write failing command tests for `i18n:locales`, `i18n:missing`, `i18n:sync-catalogs`, `i18n:import <file>`, and `i18n:export [locale]`.
- [ ] Run `vendor/bin/phpunit --filter='LocaleControllerTest|TranslationControllerTest|TranslationCommandsTest|CatalogRoundTripTest'`.
- [ ] Expected: FAIL because routes/controllers/import-export services are missing.
- [ ] Add routes for listing locales, creating/updating locales, listing translations, upserting translations, and viewing missing translations.
- [ ] Add CLI sync command for loading `messages.{locale}.php` catalogs into persistence.
- [ ] Add CLI validation command for missing keys across enabled locales/domains.
- [ ] Support basic PHP-array and JSON catalog import/export in v1, with round-trip preservation of domain, locale, key, and value.
- [ ] Leave generic large job orchestration to `glueful/import-export`; do not embed the import-export engine here.
- [ ] Test catalog sync writes translation rows.
- [ ] Test validation reports missing locale/domain keys.
- [ ] Run `vendor/bin/phpunit --filter='LocaleControllerTest|TranslationControllerTest|TranslationCommandsTest|CatalogRoundTripTest'`.
- [ ] Expected: PASS.
- [ ] Commit: `git add routes src/Http/Controllers src/Console src/Services tests && git commit -m "feat(i18n): add admin API, CLI, and catalog round trips"`

---

### Task 8: Documentation And Verification

**Files:**
- Update: `README.md`
- Create: `docs/usage.md`
- Create: `docs/catalogs.md`
- Update: `CHANGELOG.md`

- [ ] Document binding `translation.manager` and usage through `TranslatorInterface`.
- [ ] Document locale resolution order, fallback behavior, DB-overrides-catalog precedence, missing-translation tracking default-off/rate-limited behavior, and optional `intl` support.
- [ ] Document how Lemma should use this extension for platform localization while keeping localized content models in Lemma.
- [ ] Run `composer validate --strict`.
- [ ] Run `vendor/bin/phpunit`.
- [ ] Run `vendor/bin/phpstan analyse src --level=6`.
- [ ] Run `vendor/bin/phpcs --standard=PSR12 src` if phpcs is installed.
- [ ] Commit: `git add README.md docs CHANGELOG.md && git commit -m "docs(i18n): document localization contracts and catalog behavior"`
