<?php

declare(strict_types=1);

use Glueful\Extensions\I18n\Http\Controllers\LocaleController;
use Glueful\Extensions\I18n\Http\Controllers\TranslationController;
use Glueful\Routing\Router;

/** @var Router $router */

$router->group(['prefix' => '/i18n', 'middleware' => ['auth']], function (Router $router): void {
    $router->get('/locales', [LocaleController::class, 'index'])
        ->middleware('i18n_permission:i18n.view')
        ->name('i18n.locales.index');
    $router->post('/locales', [LocaleController::class, 'store'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.locales.store');
    $router->patch('/locales/{code}', [LocaleController::class, 'update'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.locales.update');

    $router->get('/translations', [TranslationController::class, 'index'])
        ->middleware('i18n_permission:i18n.view')
        ->name('i18n.translations.index');
    $router->post('/translations', [TranslationController::class, 'store'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.translations.store');
    $router->patch('/translations/{uuid}', [TranslationController::class, 'update'])
        ->middleware('i18n_permission:i18n.manage')
        ->name('i18n.translations.update');
    $router->get('/missing', [TranslationController::class, 'missing'])
        ->middleware('i18n_permission:i18n.view')
        ->name('i18n.missing.index');
    $router->post('/import', [TranslationController::class, 'import'])
        ->middleware('i18n_permission:i18n.import')
        ->name('i18n.import');
    $router->get('/export', [TranslationController::class, 'export'])
        ->middleware('i18n_permission:i18n.export')
        ->name('i18n.export');
});
