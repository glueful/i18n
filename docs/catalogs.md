# Catalogs

File catalogs are loaded through Glueful's existing
`ServiceProvider::loadMessageCatalogs($dir, $domain)` behavior. The extension
accepts those messages through `translation.manager::addMessages()`.

Persisted translations are stored by `domain + locale + key`. By default, DB
translations override file catalogs. This lets deployments ship default strings
in code while admins or tooling override strings from persistence.

Catalog import/export supports JSON and PHP-array payloads and preserves:

- domain
- locale
- key
- value

Large asynchronous import/export orchestration belongs in `glueful/import-export`;
this extension only owns catalog-shaped adapters and round trips.
