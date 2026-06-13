<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Repositories;

use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;
use Glueful\Validation\ValidationException;

final class LocaleRepositoryTest extends I18nTestCase
{
    public function testCreateUpdateDefaultAndFallbackCycleRejection(): void
    {
        $repo = new LocaleRepository($this->connection());
        $repo->create(['code' => 'en', 'name' => 'English', 'is_default' => true]);
        $repo->create(['code' => 'fr', 'name' => 'French', 'fallback_locale' => 'en']);

        self::assertSame('en', $repo->defaultCode());
        self::assertSame(['fr', 'en'], $repo->fallbackChain('fr', 'en'));

        $this->expectException(ValidationException::class);
        $repo->update('en', ['fallback_locale' => 'fr']);
    }

    public function testCreateRejectsDuplicateCodeAsValidationError(): void
    {
        $repo = new LocaleRepository($this->connection());
        $repo->create(['code' => 'en', 'name' => 'English']);

        $this->expectException(ValidationException::class);
        $repo->create(['code' => 'en', 'name' => 'English again']);
    }

    public function testCreateStripsUnknownFields(): void
    {
        $row = (new LocaleRepository($this->connection()))->create([
            'code' => 'en',
            'name' => 'English',
            'unexpected_column' => 'ignored',
        ]);

        self::assertArrayNotHasKey('unexpected_column', $row);
        self::assertSame('English', $row['name']);
    }

    public function testUpdateAllowsOnlyMutableLocaleColumns(): void
    {
        $repo = new LocaleRepository($this->connection());
        $repo->create(['code' => 'en', 'name' => 'English']);

        $row = $repo->update('en', [
            'code' => 'fr',
            'name' => 'English Updated',
            'uuid' => 'attackeruuid',
            'unexpected_column' => 'ignored',
        ]);

        self::assertSame('en', $row['code']);
        self::assertSame('English Updated', $row['name']);
        self::assertNotSame('attackeruuid', $row['uuid']);
        self::assertNull($repo->find('fr'));
    }
}
