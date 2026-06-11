<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Integration;

use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;

final class MigrationsTest extends I18nTestCase
{
    public function testI18nTablesExist(): void
    {
        self::assertTrue($this->connection()->getSchemaBuilder()->hasTable('i18n_locales'));
        self::assertTrue($this->connection()->getSchemaBuilder()->hasTable('i18n_translations'));
        self::assertTrue($this->connection()->getSchemaBuilder()->hasTable('i18n_missing_translations'));
    }
}
