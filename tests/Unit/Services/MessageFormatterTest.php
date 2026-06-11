<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Services;

use Glueful\Extensions\I18n\Services\MessageFormatter;
use PHPUnit\Framework\TestCase;

final class MessageFormatterTest extends TestCase
{
    public function testFallbackPluralPreservesSurroundingText(): void
    {
        $formatter = new MessageFormatter();

        self::assertSame(
            'Cart: 2 items ready',
            $formatter->format('Cart: {count, plural, one {# item} other {# items}} ready', ['count' => 2], 'en')
        );
    }
}
