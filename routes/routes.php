<?php

declare(strict_types=1);

use Glueful\Extensions\I18n\Http\Controllers\LocaleController;
use Glueful\Extensions\I18n\Http\Controllers\TranslationController;
use Glueful\Routing\Router;

/** @var Router $router Router instance injected by RouteManifest::load() */

$router->group(['prefix' => '/i18n', 'middleware' => ['auth']], function (Router $router): void {
    /**
     * @route GET /i18n/locales
     * @summary List Locales
     * @description Lists all stored locales ordered by code, including disabled ones.
     *   Requires `i18n.view`.
     * @tag I18n
     * @response 200 application/json "Locales retrieved"
     * @response 403 "Forbidden"
     */
    $router->get('/locales', [LocaleController::class, 'index'])
        ->middleware('i18n_permission:i18n.view')
        ->name('i18n.locales.index');

    /**
     * @route POST /i18n/locales
     * @summary Create Locale
     * @description Creates a stored locale. Setting `is_default` clears the previous
     *   default. A `fallback_locale` that would create a fallback cycle is rejected.
     *   Requires `i18n.manage`.
     * @tag I18n
     * @requestBody
     *   code:string="Unique locale code (e.g. en, fr, en-GB)" {required=code}
     *   name:string="Display name (column is NOT NULL)" {required=name}
     *   native_name:string="Optional native display name"
     *   enabled:boolean="Whether the locale is selectable (default true)"
     *   is_default:boolean="Make this the default locale (default false)"
     *   fallback_locale:string="Parent locale code for the fallback chain"
     *   direction:string="Text direction: ltr|rtl (default ltr)"
     *   region:string="Optional region tag"
     * @response 201 application/json "Locale created"
     * @response 403 "Forbidden"
     * @response 500 "Missing code or fallback cycle detected"
     */
    $router->post('/locales', [LocaleController::class, 'store'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.locales.store');

    /**
     * @route PATCH /i18n/locales/{code}
     * @summary Update Locale
     * @description Partially updates a stored locale by code. All body fields are
     *   optional; `fallback_locale` is cycle-checked and `is_default: true` clears the
     *   previous default. Requires `i18n.manage`.
     * @tag I18n
     * @requestBody
     *   name:string="Display name"
     *   native_name:string="Native display name"
     *   enabled:boolean="Enable or disable the locale"
     *   is_default:boolean="Make this the default locale"
     *   fallback_locale:string="Parent locale code (null/empty clears it)"
     *   direction:string="Text direction: ltr|rtl"
     *   region:string="Region tag"
     * @response 200 application/json "Locale updated"
     * @response 403 "Forbidden"
     * @response 500 "Unknown locale code or fallback cycle detected"
     */
    $router->patch('/locales/{code}', [LocaleController::class, 'update'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.locales.update');

    /**
     * @route GET /i18n/translations
     * @summary List Translations
     * @description Lists persisted translations ordered by key, optionally filtered by
     *   locale and domain. Requires `i18n.view`.
     * @tag I18n
     * @queryParam locale:string="Filter by locale code"
     * @queryParam domain:string="Filter by translation domain"
     * @response 200 application/json "Translations retrieved"
     * @response 403 "Forbidden"
     */
    $router->get('/translations', [TranslationController::class, 'index'])
        ->middleware('i18n_permission:i18n.view')
        ->name('i18n.translations.index');

    /**
     * @route POST /i18n/translations
     * @summary Create or Update Translation
     * @description Upserts a translation on its `(domain, locale, key)` identity:
     *   an existing row is updated (and reactivated) in place, otherwise a new row is
     *   inserted. Requires `i18n.manage`.
     * @tag I18n
     * @requestBody
     *   key:string="Translation key (e.g. nav.publish)" {required=key}
     *   value:string="Translated message; may contain {param} placeholders" {required=value}
     *   domain:string="Translation domain (default: messages)"
     *   locale:string="Locale code (default: en)"
     * @response 201 application/json "Translation saved"
     * @response 403 "Forbidden"
     * @response 500 "Missing key or value"
     */
    $router->post('/translations', [TranslationController::class, 'store'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.translations.store');

    /**
     * @route PATCH /i18n/translations/{uuid}
     * @summary Update Translation Value
     * @description Updates the value of one persisted translation by UUID.
     *   Requires `i18n.manage`.
     * @tag I18n
     * @requestBody
     *   value:string="New translated message" {required=value}
     * @response 200 application/json "Translation updated"
     * @response 403 "Forbidden"
     * @response 500 "Missing value or unknown translation UUID"
     */
    $router->patch('/translations/{uuid}', [TranslationController::class, 'update'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.translations.update');

    /**
     * @route GET /i18n/missing
     * @summary List Missing Translations
     * @description Lists recorded missing translation keys with hit counts, most
     *   recently seen first. Rows only accumulate while `i18n.missing_tracking` is
     *   enabled. Requires `i18n.view`.
     * @tag I18n
     * @queryParam locale:string="Filter by locale code"
     * @queryParam domain:string="Filter by translation domain"
     * @response 200 application/json "Missing translations retrieved"
     * @response 403 "Forbidden"
     */
    $router->get('/missing', [TranslationController::class, 'missing'])
        ->middleware('i18n_permission:i18n.view')
        ->name('i18n.missing.index');

    /**
     * @route POST /i18n/import
     * @summary Import Translation Catalog
     * @description Imports a server-side JSON or PHP catalog file and upserts each row
     *   into the translation store. The payload is either a top-level list of rows or
     *   an object with a `translations` list; each row carries `domain`, `locale`,
     *   `key`, and `value`. Requires `i18n.import`.
     * @tag I18n
     * @requestBody
     *   path:string="Server-side path to a .json or .php catalog file" {required=path}
     * @response 200 application/json "Catalog imported (returns imported row count)"
     * @response 403 "Forbidden"
     * @response 500 "Missing path or catalog file not found"
     */
    $router->post('/import', [TranslationController::class, 'import'])
        ->middleware('i18n_permission:i18n.import')
        ->name('i18n.import');

    /**
     * @route GET /i18n/export
     * @summary Export Translation Catalog
     * @description Exports persisted translations as a JSON catalog, optionally
     *   filtered by locale and domain. Requires `i18n.export`.
     * @tag I18n
     * @queryParam locale:string="Filter by locale code"
     * @queryParam domain:string="Filter by translation domain"
     * @response 200 application/json "Catalog exported"
     * @response 403 "Forbidden"
     */
    $router->get('/export', [TranslationController::class, 'export'])
        ->middleware('i18n_permission:i18n.export')
        ->name('i18n.export');
});
