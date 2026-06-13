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

    public function testMissingTrackingStopsRecordingNovelKeysAtConfiguredRowCap(): void
    {
        $this->seedLocale();
        $this->setConfig('i18n.missing_tracking', true);
        $this->setConfig('i18n.missing_max_rows', 1);
        $this->setConfig('i18n.missing_rate_limit_seconds', 0);
        $manager = $this->manager();

        $manager->trans('missing.one', [], 'en');
        $manager->trans('missing.two', [], 'en');
        $manager->trans('missing.one', [], 'en');

        $rows = (new MissingTranslationRepository($this->connection()))->list('en', 'messages');

        self::assertCount(1, $rows);
        self::assertSame('missing.one', $rows[0]['key']);
        self::assertSame(2, (int) $rows[0]['hits']);
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
