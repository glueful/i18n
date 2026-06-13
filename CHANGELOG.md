# Changelog

All notable changes to `glueful/i18n` will be documented in this file.

The format is based on Keep a Changelog, and this project adheres to Semantic Versioning.

## [Unreleased]

### Fixed

- Harden HTTP catalog import so `POST /i18n/import` accepts only inline JSON catalog payloads and never executes caller-supplied server-side file paths; trusted file imports remain available through the CLI.
- Fall back to simple substitution when a stored ICU message pattern is malformed instead of throwing an uncaught formatter exception.
- Cap translation values at 65,535 bytes in HTTP validation and repository writes.
- Add `i18n.missing_max_rows` to cap novel missing-key recording while still allowing existing missing rows to increment.
- Add locale repository column allow-lists so direct repository calls cannot write unknown or immutable columns.
- Preserve a stored default locale by forcing the first locale to enabled/default and rejecting updates that clear or disable the only default.

## [1.0.0] - 2026-06-11

First release. **Platform localization** for Glueful: a locale registry with
fallback chains, persisted translation catalogs layered over file catalogs,
message formatting with pluralization, optional missing-key tracking, catalog
import/export, an HTTP management API, and CLI tooling. Requires
**`glueful/framework >= 1.55.0`**.

### Added

- **`translation.manager` binding (the core seam):** `TranslationManager` is
  registered under the `translation.manager` service id consumed by framework
  core's `ServiceProvider::loadMessageCatalogs($dir, $domain)`, implementing
  the expected `addMessages($locale, $domain, $messages)` contract. Installing
  this extension activates the `messages.{locale}.php` catalog convention for
  every extension; nothing else needs to depend on this package.
- **Typed contracts** for consumers: `TranslatorInterface` (`trans()`),
  `LocaleResolverInterface`, `LocaleManagerInterface`, and
  `TranslationRepositoryInterface`, each aliased from the concrete service.
- **Locale registry** (`i18n_locales`): unique codes, enabled/default flags,
  text direction, region, and a single-parent `fallback_locale`. Setting a
  locale as default clears the previous default; fallback **cycles are
  rejected at write time** so read-time resolution never loops.
- **Fallback chains:** requested locale, its stored parent chain, the implicit
  language parent (`en-GB -> en`), then the global `i18n.fallback_locale`; a
  complete miss returns the key itself.
- **Locale resolution** (`LocaleResolver`): explicit locale, then request
  override (`?locale=` / `X-Locale`, config-gated via
  `i18n.request_override`), then the identity's `preferred_locale` claim, then
  tenant/app locale context, then the default locale. Only enabled locales are
  accepted; once stored locales exist, **disabled stored locales are excluded
  even if config-listed** (`i18n.enabled_locales` applies only while the
  table is empty). A `LocaleContext` value object supports resolution outside
  an HTTP request.
- **DB-over-file catalog merge:** per `(locale, domain)`, persisted
  translations override file catalog entries when
  `i18n.db_overrides_catalogs` is true (default); flip it to let files win.
- **Pluralization without a hard intl dependency:** ICU `MessageFormatter` is
  used when `ext-intl` is present, gated on real ICU argument syntax for
  `plural`, `select`, and `selectordinal` blocks (`{name, plural, ...}` etc.;
  plain `{param}` messages keep the cheap substitution path). Without
  `ext-intl`, a built-in fallback handles the two-branch `one`/`other` plural
  form with `#` substitution plus simple `{param}` replacement;
  `select`/`selectordinal` require `ext-intl`.
- **Request-scoped bundle cache:** merged bundles are memoized in memory per
  `locale:domain:version`; writes bump the version and invalidate. No backend
  cache in this release.
- **Missing-key tracking (default OFF):** when `i18n.missing_tracking` is
  enabled, misses are upserted into `i18n_missing_translations` with hit
  counts, rate-limited per key by `i18n.missing_rate_limit_seconds`
  (limiter state is per recorder instance, i.e. effectively per request under
  PHP-FPM).
- **Catalog import/export:** JSON and PHP-array catalog files with
  `domain` / `locale` / `key` / `value` rows, exposed over HTTP and CLI.
- **HTTP management API** under `/i18n` (config-gated via
  `i18n.routes_enabled`): locales list/create/update, translations
  list/upsert/update, missing-key listing, catalog import and export -- all
  behind `auth` plus the extension-owned `i18n_permission` middleware. Routes
  carry OpenAPI docblock annotations.
- **422/404 error envelopes:** write payloads go through
  `I18nPayloadValidator` (whitelisted fields, locale-code format checks,
  required key/value, import payload shape). Invalid input -- including
  fallback-locale cycles and duplicate locale codes -- returns HTTP 422 with
  field-keyed details; unknown locale codes and translation UUIDs return
  HTTP 404. Input errors never render as 500.
- **Permissions:** `i18n.view`, `i18n.manage`, `i18n.import`, and
  `i18n.export` registered in the framework permission catalog. The
  `i18n_permission` middleware calls `PermissionManager::can()` directly and
  fails closed (missing identity or missing permission manager both deny).
- **CLI:** `i18n:locales`, `i18n:missing`, `i18n:sync-catalogs <dir> [domain]`
  (upserts `messages.*.php` file catalogs into the DB), `i18n:validate`
  (reports stored keys missing per enabled locale; non-zero exit on gaps),
  `i18n:import <file>`, and `i18n:export [locale]`.
- **Schema:** one migration creating `i18n_locales`, `i18n_translations`
  (unique per `(domain, locale, key)`), and `i18n_missing_translations`.
- **Config** (`config/i18n.php`): `default_locale`, `fallback_locale`,
  `enabled_locales`, `request_override`, `missing_tracking`,
  `missing_rate_limit_seconds`, `db_overrides_catalogs`, `routes_enabled`.
  Every shipped key is consumed; a backend-cache TTL will be added together
  with a real backend cache.

### Boundaries

- Platform localization only: UI strings, supported locales, fallback rules,
  and catalogs. Content localization (localized content fields, slugs, routes,
  editorial translation workflow) belongs to content platforms such as Lemma.
- Regional formatting helpers (dates/numbers/currency) are **not** part of
  this release.
