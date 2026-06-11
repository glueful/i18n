<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Services;

use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Extensions\I18n\Services\LocaleResolver;
use Glueful\Extensions\I18n\Services\MessageFormatter;
use Glueful\Extensions\I18n\Services\MissingTranslationRecorder;
use Glueful\Extensions\I18n\Services\TranslationCache;
use Glueful\Extensions\I18n\Services\TranslationManager;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;

final class TranslationManagerTest extends I18nTestCase
{
    public function testCatalogMessagesCanBeTranslatedWithParameters(): void
    {
        $this->seedLocale();
        $manager = $this->manager();
        $manager->addMessages('en', 'messages', ['hello' => 'Hello {name}']);

        self::assertSame('Hello Ada', $manager->trans('hello', ['name' => 'Ada'], 'en'));
    }

    public function testDbTranslationsOverrideCatalogMessagesByDefault(): void
    {
        $this->seedLocale();
        $this->seedTranslation('en', 'hello', 'Hello from DB');
        $manager = $this->manager();
        $manager->addMessages('en', 'messages', ['hello' => 'Hello from file']);

        self::assertSame('Hello from DB', $manager->trans('hello', [], 'en'));
    }

    public function testMissingKeysReturnTheKey(): void
    {
        $this->seedLocale();

        self::assertSame('missing.key', $this->manager()->trans('missing.key', [], 'en'));
    }

    private function manager(): TranslationManager
    {
        $locales = new LocaleManager(new LocaleRepository($this->connection()), $this->appContext());

        return new TranslationManager(
            new LocaleResolver($this->appContext(), $locales),
            $locales,
            new TranslationRepository($this->connection()),
            new TranslationCache(),
            new MessageFormatter(),
            new MissingTranslationRecorder(new MissingTranslationRepository($this->connection()), $this->appContext()),
            $this->appContext(),
        );
    }
}
