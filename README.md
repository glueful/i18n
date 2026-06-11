# Glueful i18n

Localization primitives for Glueful apps and extensions. It provides locale
management, persisted translation catalogs, fallback chains, missing-key
tracking, catalog import/export, and compatibility with Glueful's
`translation.manager` message-catalog hook.

## Usage

Resolve the translator contract or the framework-compatible service id:

```php
$translator = app($context, \Glueful\Extensions\I18n\Contracts\TranslatorInterface::class);
$label = $translator->trans('nav.publish', ['name' => 'Lemma'], 'en');

$manager = app($context, 'translation.manager');
$manager->addMessages('en', 'messages', ['hello' => 'Hello {name}']);
```

DB translations override file catalogs by default. Missing tracking is disabled
by default and rate-limited when enabled.

## Boundaries

Use this extension for platform localization: UI strings, supported locales,
fallback rules, validation messages, and regional formatting helpers. Domain
packages such as Lemma should still own localized content fields, slugs, routes,
publishing state, and editorial translation workflow.

## Commands

- `i18n:locales`
- `i18n:missing`
- `i18n:sync-catalogs`
- `i18n:validate`
- `i18n:import <file>`
- `i18n:export [locale]`
