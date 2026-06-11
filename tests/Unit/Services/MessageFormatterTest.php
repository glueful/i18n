<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Services;

use Glueful\Extensions\I18n\Services\MessageFormatter;
use PHPUnit\Framework\TestCase;

final class MessageFormatterTest extends TestCase
{
    private MessageFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new MessageFormatter();
    }

    public function testFallbackPluralPreservesSurroundingText(): void
    {
        self::assertSame(
            'Cart: 2 items ready',
            $this->formatter->format('Cart: {count, plural, one {# item} other {# items}} ready', ['count' => 2], 'en')
        );
    }

    public function testTwoBranchPluralSingularForm(): void
    {
        self::assertSame(
            '1 item',
            $this->formatter->format('{count, plural, one {# item} other {# items}}', ['count' => 1], 'en')
        );
    }

    public function testPlainParameterSubstitution(): void
    {
        self::assertSame(
            'Welcome, Ada',
            $this->formatter->format('Welcome, {name}', ['name' => 'Ada'], 'en')
        );
    }

    public function testSelectMessageFormatsViaIcuWhenIntlPresent(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl is required for ICU select formatting.');
        }

        self::assertSame(
            'She replied',
            $this->formatter->format(
                '{gender, select, female {She} male {He} other {They}} replied',
                ['gender' => 'female'],
                'en'
            )
        );
    }

    public function testSelectordinalFormatsViaIcuWhenIntlPresent(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl is required for ICU selectordinal formatting.');
        }

        self::assertSame(
            '3rd',
            $this->formatter->format(
                '{n, selectordinal, one {#st} two {#nd} few {#rd} other {#th}}',
                ['n' => 3],
                'en'
            )
        );
    }

    public function testLiteralWordPluralOutsideBracesDoesNotTriggerIcuPath(): void
    {
        // ICU MessageFormatter collapses doubled apostrophes ('' -> '); the cheap
        // substitution path must leave them intact, proving the gate did not fire
        // for a message that merely contains the word "plural" outside braces.
        self::assertSame(
            "A so-called ''plural'' note for Ada",
            $this->formatter->format("A so-called ''plural'' note for {name}", ['name' => 'Ada'], 'en')
        );
    }
}
