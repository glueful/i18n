# Glueful I18n

Platform localization for Glueful apps and extensions: a locale registry with
single-parent fallback chains, persisted translation catalogs that overlay
file catalogs, parameterized message formatting with pluralization, optional
missing-key tracking, catalog import/export, an HTTP management API, and CLI
tooling.

The headline integration: this extension binds the `translation.manager`
service id that the framework's `ServiceProvider::loadMessageCatalogs()` hook
looks for -- installing it is what makes every extension's `messages.{locale}.php`
catalogs actually load.

## Install

```bash
composer require glueful/i18n
php glueful extensions:enable i18n
php glueful migrate:run
```

Requires `glueful/framework >= 1.55.0`. `ext-intl` is optional: with it,
pluralization goes through ICU MessageFormat; without it, a simple built-in
plural parser is used (see "Pluralization").

## The `translation.manager` seam

Framework core ships `ServiceProvider::loadMessageCatalogs($dir, $domain)` on
every extension service provider, but it is a silent no-op unless something
binds the `translation.manager` container id. This extension binds its
`TranslationManager` to that id (and to its typed
`Glueful\Extensions\I18n\Contracts\TranslatorInterface`), satisfying the
expected contract:

```php
$manager = app($context, 'translation.manager');
$manager->addMessages('en', 'messages', ['hello' => 'Hello {name}']);
```

With `glueful/i18n` installed, any extension can ship translations using the
framework's `messages.{locale}.php` convention -- no dependency on this
package needed:

```php
// In your extension's ServiceProvider:
public function boot(ApplicationContext $context): void
{
    // Loads resources/lang/messages.en.php, messages.fr.php, ... into the
    // "my-extension" domain via translation.manager (no-op when i18n is absent).
    $this->loadMessageCatalogs(__DIR__ . '/../resources/lang', 'my-extension');
}
```

```php
// resources/lang/messages.en.php
return [
    'welcome.title' => 'Welcome, {name}',
    'inbox.count'   => '{count, plural, one {# message} other {# messages}}',
];
```

## Translating

Resolve the typed contract (or `translation.manager`) and call `trans()`:

```php
use Glueful\Extensions\I18n\Contracts\TranslatorInterface;

$translator = app($context, TranslatorInterface::class);

$translator->trans('welcome.title', ['name' => 'Ada'], 'fr');
$translator->trans('inbox.count', ['count' => 3], 'en', 'my-extension');
```

- `{param}` placeholders are replaced from the parameters array.
- The lookup walks the locale's fallback chain (see below); the first bundle
  containing the key wins.
- A complete miss returns the key itself (and optionally records it, see
  "Missing-key tracking").
- When no locale is passed, `trans()` uses the application default locale --
  it does not inspect the current request by itself. To translate per-request,
  resolve the locale first and pass it explicitly:

```php
use Glueful\Extensions\I18n\Contracts\LocaleResolverInterface;

$locale = app($context, LocaleResolverInterface::class)->resolveLocale($request);
$translator->trans('welcome.title', ['name' => 'Ada'], $locale);
```

### Pluralization

There is no hard `ext-intl` dependency; both paths understand the same
`{count, plural, one {...} other {...}}` message shape:

- **With `ext-intl`:** messages using ICU argument syntax for a `plural`,
  `select`, or `selectordinal` block (`{name, plural, ...}`,
  `{name, select, ...}`, `{name, selectordinal, ...}`) are formatted by ICU
  `MessageFormatter` with full CLDR rules for the target locale. Plain
  `{param}` messages -- including ones that merely contain the word "plural"
  outside such a block -- keep the cheap substitution path.
- **Without `ext-intl`:** a built-in fallback handles only the two-branch
  `one`/`other` plural form in place (`#` is replaced with the count), then
  simple `{param}` substitution runs. `select`/`selectordinal` blocks and
  locale-specific plural categories beyond `one`/`other` require `ext-intl`.

## Locale resolution

`LocaleResolver::resolveLocale($context)` accepts a locale string, a Symfony
`Request`, or a `Glueful\Extensions\I18n\Support\LocaleContext`. Candidates are
tried in order; the first enabled locale wins:

1. Explicit locale (string argument or `LocaleContext::$explicitLocale`).
2. Request override: `?locale=` query parameter or `X-Locale` header
   (only when `i18n.request_override` is true).
3. The authenticated identity's `preferred_locale` claim
   (`auth.user` request attribute or `LocaleContext::$claims`).
4. Tenant locale (`tenant.locale` request attribute or
   `LocaleContext::$tenantLocale`), then `LocaleContext::$appLocale`.
5. The default locale (the stored locale flagged `is_default`, falling back to
   `i18n.default_locale`).

A candidate only wins if it is enabled. When any stored locales are enabled,
the stored set is authoritative: a locale that is disabled in the database is
excluded even if it appears in `i18n.enabled_locales`. The config list only
applies while the `i18n_locales` table is empty.

## Fallback chains

Each stored locale may declare a single parent via `fallback_locale`. For a
requested locale the chain is:

1. The locale itself, then its stored parent chain (`fr-CA -> fr -> ...`).
2. The implicit language parent derived from the code (`en-GB -> en`), if not
   already in the chain.
3. The global `i18n.fallback_locale`.
4. On a complete miss, the key itself is returned.

Cycles are rejected at write time: creating or updating a locale whose
`fallback_locale` would close a loop throws, so resolution never has to break
a cycle at read time.

## DB translations vs file catalogs

Bundles are merged from two sources per `(locale, domain)`:

- File catalogs registered through `translation.manager::addMessages()`
  (typically via `loadMessageCatalogs()`).
- Persisted rows in `i18n_translations`.

With `i18n.db_overrides_catalogs` set to `true` (the default), DB rows win per
key: ship default strings in code, override them from the database via the API
or CLI without a deploy. Set it to `false` to let file catalogs win instead.

## Caching

Bundle caching is request-scoped memoization: merged bundles are kept in
memory keyed by `locale:domain:version`, and every write through the manager
bumps the version and drops the stale entry. There is **no backend cache**
(Redis or otherwise) in this release; each request/process rebuilds bundles
on first use.

## Missing-key tracking

Off by default (`i18n.missing_tracking`). When enabled, a translation miss is
recorded in `i18n_missing_translations` (first/last seen, hit count) and
surfaced via `GET /i18n/missing` and `i18n:missing`.

Recording is rate-limited per `(domain, locale, key)` by
`i18n.missing_rate_limit_seconds`, but the limiter state lives in the recorder
instance -- in a typical PHP-FPM deployment that means **per request**, so the
limit mainly dedupes repeat misses within a single request (long-running
workers get the full window). Each recorded miss is 1-2 queries; leave
tracking off in production unless you are actively auditing.
Novel missing keys stop recording once `i18n.missing_max_rows` is reached,
while existing rows can still increment their hit count.

## Configuration

`config/i18n.php` (merged under the `i18n` key):

| Key | Default | Meaning |
| --- | --- | --- |
| `default_locale` | `'en'` | App default; used when no stored locale is flagged `is_default`. |
| `fallback_locale` | `'en'` | Global last step of every fallback chain. |
| `enabled_locales` | `['en']` | Allowed locales **only while the `i18n_locales` table is empty**; stored enabled locales take over once any exist. |
| `request_override` | `true` | Honor `?locale=` / `X-Locale` request overrides. |
| `missing_tracking` | `false` | Record translation misses to the database. |
| `missing_rate_limit_seconds` | `60` | Per-key re-record window (per-request state; see above). |
| `missing_max_rows` | `10000` | Maximum missing-key rows; existing rows can still increment. |
| `db_overrides_catalogs` | `true` | DB translations win over file catalogs per key. |
| `routes_enabled` | `true` | Register the `/i18n` HTTP routes. |

The first stored locale is forced to enabled/default. Once a stored default
exists, the repository rejects updates that would clear or disable the only
default locale.

## HTTP API

All routes are mounted under `/i18n`, require `auth`, and are gated by the
`i18n_permission` middleware (see "Permissions"). Disable the whole surface
with `i18n.routes_enabled = false`.

| Method | Path | Permission | Purpose |
| --- | --- | --- | --- |
| GET | `/i18n/locales` | `i18n.view` | List stored locales. |
| POST | `/i18n/locales` | `i18n.manage` | Create a locale (`code` + `name` required; cycle-checked `fallback_locale`). |
| PATCH | `/i18n/locales/{code}` | `i18n.manage` | Update a locale; `is_default: true` clears the previous default. |
| GET | `/i18n/translations` | `i18n.view` | List translations (`?locale=`, `?domain=` filters). |
| POST | `/i18n/translations` | `i18n.manage` | Upsert a translation on `(domain, locale, key)`. |
| PATCH | `/i18n/translations/{uuid}` | `i18n.manage` | Update one translation's value. |
| GET | `/i18n/missing` | `i18n.view` | List recorded missing keys (`?locale=`, `?domain=` filters). |
| POST | `/i18n/import` | `i18n.import` | Import an inline JSON catalog from the `catalog` payload field. |
| GET | `/i18n/export` | `i18n.export` | Export translations as a JSON catalog (`?locale=`, `?domain=` filters). |

Error envelopes: write payloads are validated by `I18nPayloadValidator`
(unknown fields are stripped). Invalid input -- missing or malformed fields,
duplicate locale codes, fallback cycles, empty update payloads, attempts to
change a locale `code`, and malformed import catalogs -- returns **HTTP 422**
with the standard error envelope, field-keyed messages under
`error.details`. An unknown locale `{code}` or translation `{uuid}` returns
**HTTP 404**. Input errors never surface as 500.

## CLI

| Command | What it actually does |
| --- | --- |
| `i18n:locales` | Tables all stored locales (code, name, enabled, default). |
| `i18n:missing` | Tables all recorded missing translations with hit counts. |
| `i18n:sync-catalogs <directory> [domain]` | Globs `messages.*.php` files in the directory, derives the locale from each filename, and upserts every scalar key/value into `i18n_translations` under the given domain (default `messages`). One-way: files into DB. |
| `i18n:validate` | Builds the set of known `(domain, key)` identities from **stored** translations and reports, per enabled locale, every identity that locale is missing. Exits non-zero when gaps exist. DB-only: file catalogs are not inspected. |
| `i18n:import <file>` | Imports a JSON or PHP catalog file from a trusted operator-supplied path. |
| `i18n:export [locale]` | Prints the JSON catalog for all or one locale. |

HTTP catalog import accepts an inline JSON `catalog` object or array. CLI
catalog import accepts JSON and PHP-array files from trusted operator-supplied
paths. Both forms round-trip four fields per row: `domain`, `locale`, `key`,
`value` -- an exported catalog can be re-imported losslessly.
Translation values are capped at 65,535 bytes across HTTP, CLI import, and
direct repository writes.

## Permissions

The extension registers four permissions in the framework permission catalog
and ships its own `i18n_permission` route middleware:

- `i18n.view` -- read locales, translations, and missing keys.
- `i18n.manage` -- create/update locales and translations.
- `i18n.import` -- import catalogs.
- `i18n.export` -- export catalogs.

The middleware calls `PermissionManager::can()` directly and **fails closed**:
no authenticated identity, no resolvable `PermissionManager`, or a denied
check all return 403.

## Boundaries

This extension owns **platform localization**: UI strings, supported locales,
fallback rules, and translation catalogs for app/extension surfaces. Content
platforms such as Lemma own **content localization** -- localized content
fields, slugs, routes, publishing state, and editorial translation workflow.
Lemma should consume this extension's locale registry and translator rather
than re-implement them, but its content models stay on its side of the line.

Likewise, large asynchronous import/export orchestration (batched jobs,
progress tracking, validation reports) belongs to `glueful/import-export`;
this extension only owns catalog-shaped payloads and their synchronous
round-trips.

## Schema

One migration creates three tables:

- `i18n_locales` -- locale registry (`code` unique, `enabled`, `is_default`,
  `fallback_locale`, `direction`, `region`).
- `i18n_translations` -- persisted catalog rows, unique per
  `(domain, locale, key)`, with `status` and `source` columns.
- `i18n_missing_translations` -- recorded misses, unique per
  `(domain, locale, key)`, with first/last seen and hit count.
