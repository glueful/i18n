<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Integration;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Container\Container;
use Glueful\Container\Definition\AliasDefinition;
use Glueful\Container\Definition\ValueDefinition;
use Glueful\Container\Loader\DefaultServicesLoader;
use Glueful\Database\Connection;
use Glueful\Extensions\I18n\Contracts\LocaleManagerInterface;
use Glueful\Extensions\I18n\Contracts\LocaleResolverInterface;
use Glueful\Extensions\I18n\Contracts\TranslationRepositoryInterface;
use Glueful\Extensions\I18n\Contracts\TranslatorInterface;
use Glueful\Extensions\I18n\Http\RequireI18nPermission;
use Glueful\Extensions\I18n\I18nServiceProvider;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Extensions\I18n\Services\LocaleResolver;
use Glueful\Extensions\I18n\Services\TranslationManager;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;

final class ServiceProviderWiringTest extends I18nTestCase
{
    public function testServiceAliasesLiveOnConcreteDefinitions(): void
    {
        $services = I18nServiceProvider::services();

        self::assertContains(TranslationRepositoryInterface::class, $services[TranslationRepository::class]['alias']);
        self::assertContains(LocaleManagerInterface::class, $services[LocaleManager::class]['alias']);
        self::assertContains(LocaleResolverInterface::class, $services[LocaleResolver::class]['alias']);
        self::assertContains(TranslatorInterface::class, $services[TranslationManager::class]['alias']);
        self::assertContains('translation.manager', $services[TranslationManager::class]['alias']);
        self::assertArrayNotHasKey(TranslationRepositoryInterface::class, $services);
        self::assertArrayNotHasKey(LocaleManagerInterface::class, $services);
        self::assertArrayNotHasKey(LocaleResolverInterface::class, $services);
    }

    public function testServicesLoadThroughRealDefaultServicesLoaderInProductionMode(): void
    {
        $definitions = (new DefaultServicesLoader())->load(
            I18nServiceProvider::services(),
            I18nServiceProvider::class,
            prod: true
        );

        self::assertInstanceOf(AliasDefinition::class, $definitions[TranslationRepositoryInterface::class] ?? null);
        self::assertInstanceOf(AliasDefinition::class, $definitions[LocaleManagerInterface::class] ?? null);
        self::assertInstanceOf(AliasDefinition::class, $definitions[LocaleResolverInterface::class] ?? null);
        self::assertInstanceOf(AliasDefinition::class, $definitions[TranslatorInterface::class] ?? null);
        self::assertInstanceOf(AliasDefinition::class, $definitions['translation.manager'] ?? null);
        self::assertArrayHasKey(RequireI18nPermission::class, $definitions);
    }

    public function testProviderVersionMatchesComposerManifest(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $expected = $manifest['extra']['glueful']['version'];

        self::assertIsString($expected);
        self::assertSame($expected, I18nServiceProvider::composerVersion());
        self::assertSame(
            $expected,
            (new I18nServiceProvider($this->appContext()->getContainer()))->getVersion()
        );
    }

    public function testAliasesResolveThroughRealContainer(): void
    {
        $definitions = (new DefaultServicesLoader())->load(
            I18nServiceProvider::services(),
            I18nServiceProvider::class,
            prod: true
        );
        $definitions[ApplicationContext::class] = new ValueDefinition(ApplicationContext::class, $this->appContext());
        $definitions[Connection::class] = new ValueDefinition(Connection::class, $this->connection());
        $definitions['database'] = new ValueDefinition('database', $this->connection());
        $container = new Container($definitions);

        self::assertInstanceOf(TranslationRepository::class, $container->get(TranslationRepositoryInterface::class));
        self::assertInstanceOf(LocaleManager::class, $container->get(LocaleManagerInterface::class));
        self::assertInstanceOf(LocaleResolver::class, $container->get(LocaleResolverInterface::class));
        self::assertInstanceOf(TranslationManager::class, $container->get(TranslatorInterface::class));
        self::assertInstanceOf(TranslationManager::class, $container->get('translation.manager'));
    }
}
