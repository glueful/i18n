<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Services;

use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Extensions\I18n\Services\LocaleResolver;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;

final class LocaleResolverTest extends I18nTestCase
{
    public function testDisabledStoredLocaleDoesNotBypassThroughConfig(): void
    {
        $this->seedLocale(['code' => 'en', 'enabled' => true, 'is_default' => true]);
        $this->seedLocale(['code' => 'fr', 'name' => 'French', 'enabled' => false, 'is_default' => false]);
        $locales = new LocaleManager(new LocaleRepository($this->connection()), $this->appContext());

        self::assertSame('en', (new LocaleResolver($this->appContext(), $locales))->resolveLocale('fr'));
    }
}
