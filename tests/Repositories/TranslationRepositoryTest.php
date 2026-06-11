<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Repositories;

use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;

final class TranslationRepositoryTest extends I18nTestCase
{
    public function testPutUpsertsAndBundleLoadsByLocaleAndDomain(): void
    {
        $repo = new TranslationRepository($this->connection());
        $repo->put('messages', 'en', 'hello', 'Hello');
        $repo->put('messages', 'en', 'hello', 'Hi');

        self::assertSame('Hi', $repo->get('messages', 'en', 'hello'));
        self::assertSame(['hello' => 'Hi'], $repo->bundle('en', 'messages'));
    }

    public function testMissingHitIncrements(): void
    {
        $repo = new MissingTranslationRepository($this->connection());
        $repo->record('messages', 'en', 'missing');
        $repo->record('messages', 'en', 'missing');

        self::assertSame(2, (int) $repo->list('en', 'messages')[0]['hits']);
    }
}
