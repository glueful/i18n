<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Repositories;

use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;

final class LocaleRepositoryTest extends I18nTestCase
{
    public function testCreateUpdateDefaultAndFallbackCycleRejection(): void
    {
        $repo = new LocaleRepository($this->connection());
        $repo->create(['code' => 'en', 'name' => 'English', 'is_default' => true]);
        $repo->create(['code' => 'fr', 'name' => 'French', 'fallback_locale' => 'en']);

        self::assertSame('en', $repo->defaultCode());
        self::assertSame(['fr', 'en'], $repo->fallbackChain('fr', 'en'));

        $this->expectException(\InvalidArgumentException::class);
        $repo->update('en', ['fallback_locale' => 'fr']);
    }
}
