<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Database\Migrations\MigrationPriority;
use Glueful\Extensions\I18n\Console\LocaleListCommand;
use Glueful\Extensions\I18n\Console\MissingListCommand;
use Glueful\Extensions\I18n\Console\TranslationExportCommand;
use Glueful\Extensions\I18n\Console\TranslationImportCommand;
use Glueful\Extensions\I18n\Console\TranslationSyncCommand;
use Glueful\Extensions\I18n\Console\TranslationValidateCommand;
use Glueful\Extensions\I18n\Contracts\LocaleManagerInterface;
use Glueful\Extensions\I18n\Contracts\LocaleResolverInterface;
use Glueful\Extensions\I18n\Contracts\TranslationRepositoryInterface;
use Glueful\Extensions\I18n\Contracts\TranslatorInterface;
use Glueful\Extensions\I18n\Http\Controllers\LocaleController;
use Glueful\Extensions\I18n\Http\Controllers\TranslationController;
use Glueful\Extensions\I18n\Http\RequireI18nPermission;
use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\CatalogExporter;
use Glueful\Extensions\I18n\Services\CatalogImporter;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Extensions\I18n\Services\LocaleResolver;
use Glueful\Extensions\I18n\Services\MessageFormatter;
use Glueful\Extensions\I18n\Services\MissingTranslationRecorder;
use Glueful\Extensions\I18n\Services\TranslationCache;
use Glueful\Extensions\I18n\Services\TranslationManager;
use Glueful\Extensions\ServiceProvider;
use Glueful\Permissions\Catalog\Permission;

final class I18nServiceProvider extends ServiceProvider
{
    /** @return array<string,mixed> */
    public static function services(): array
    {
        return [
            LocaleRepository::class => self::autowired(LocaleRepository::class),
            TranslationRepository::class => self::autowired(
                TranslationRepository::class,
                aliases: [TranslationRepositoryInterface::class]
            ),
            MissingTranslationRepository::class => self::autowired(MissingTranslationRepository::class),
            TranslationCache::class => self::autowired(TranslationCache::class),
            MessageFormatter::class => self::autowired(MessageFormatter::class),
            MissingTranslationRecorder::class => self::autowired(MissingTranslationRecorder::class),
            LocaleManager::class => self::autowired(LocaleManager::class, aliases: [LocaleManagerInterface::class]),
            LocaleResolver::class => self::autowired(LocaleResolver::class, aliases: [LocaleResolverInterface::class]),
            TranslationManager::class => [
                'class' => TranslationManager::class,
                'shared' => true,
                'autowire' => true,
                'alias' => ['translation.manager', TranslatorInterface::class],
            ],
            CatalogImporter::class => self::autowired(CatalogImporter::class),
            CatalogExporter::class => self::autowired(CatalogExporter::class),
            RequireI18nPermission::class => [
                'class' => RequireI18nPermission::class,
                'shared' => true,
                'autowire' => true,
                'alias' => ['i18n_permission'],
            ],
            LocaleController::class => self::autowired(LocaleController::class),
            TranslationController::class => self::autowired(TranslationController::class),
            LocaleListCommand::class => self::autowired(LocaleListCommand::class),
            MissingListCommand::class => self::autowired(MissingListCommand::class),
            TranslationSyncCommand::class => self::autowired(TranslationSyncCommand::class),
            TranslationValidateCommand::class => self::autowired(TranslationValidateCommand::class),
            TranslationImportCommand::class => self::autowired(TranslationImportCommand::class),
            TranslationExportCommand::class => self::autowired(TranslationExportCommand::class),
        ];
    }

    /**
     * @param list<string> $aliases
     * @return array{class:class-string,shared:bool,autowire:bool,alias?:list<string>}
     */
    private static function autowired(string $class, bool $shared = true, array $aliases = []): array
    {
        $definition = ['class' => $class, 'shared' => $shared, 'autowire' => true];
        if ($aliases !== []) {
            $definition['alias'] = $aliases;
        }

        return $definition;
    }

    public function getName(): string
    {
        return 'I18n';
    }

    public function getVersion(): string
    {
        return '0.1.0';
    }

    public function getDescription(): string
    {
        return 'Localization primitives for Glueful apps and extensions.';
    }

    public function register(ApplicationContext $context): void
    {
        $this->mergeConfig('i18n', require __DIR__ . '/../config/i18n.php');
        $this->loadMigrationsFrom(__DIR__ . '/../migrations', MigrationPriority::DEFAULT, 'glueful/i18n');
    }

    public function boot(ApplicationContext $context): void
    {
        $this->discoverCommands('Glueful\\Extensions\\I18n\\Console', __DIR__ . '/Console');
        if ((bool) \config($context, 'i18n.routes_enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');
        }
    }

    public function permissions(): array
    {
        return [
            Permission::define('i18n.view')
                ->label('View locales and translations')
                ->category('I18n')
                ->resource('i18n')
                ->managedBy('glueful/i18n'),
            Permission::define('i18n.manage')
                ->label('Manage locales and translations')
                ->category('I18n')
                ->resource('i18n')
                ->managedBy('glueful/i18n'),
            Permission::define('i18n.import')
                ->label('Import translation catalogs')
                ->category('I18n')
                ->resource('i18n')
                ->managedBy('glueful/i18n'),
            Permission::define('i18n.export')
                ->label('Export translation catalogs')
                ->category('I18n')
                ->resource('i18n')
                ->managedBy('glueful/i18n'),
        ];
    }
}
