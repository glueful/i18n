<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Http;

use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class I18nPayloadValidatorTest extends TestCase
{
    private I18nPayloadValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new I18nPayloadValidator();
    }

    public function testLocaleCreateReturnsWhitelistedFields(): void
    {
        $validated = $this->validator->validateLocaleCreate([
            'code' => 'en-GB',
            'name' => 'English (UK)',
            'native_name' => 'English',
            'enabled' => true,
            'is_default' => false,
            'fallback_locale' => 'en',
            'direction' => 'ltr',
            'region' => 'GB',
            'bogus' => 'ignored',
        ]);

        self::assertSame('en-GB', $validated['code']);
        self::assertSame('English (UK)', $validated['name']);
        self::assertSame('en', $validated['fallback_locale']);
        self::assertArrayNotHasKey('bogus', $validated);
    }

    public function testLocaleCreateRequiresCode(): void
    {
        try {
            $this->validator->validateLocaleCreate(['name' => 'English']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('code'));
        }
    }

    public function testLocaleCreateRequiresName(): void
    {
        try {
            $this->validator->validateLocaleCreate(['code' => 'en']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('name'));
        }
    }

    /** @return list<array{0:string}> */
    public static function malformedCodes(): array
    {
        return [
            ['EN US'],
            ['!!'],
            ['-en'],
            ['english-language-code-way-too-long'],
        ];
    }

    /** @dataProvider malformedCodes */
    public function testLocaleCreateRejectsMalformedCode(string $code): void
    {
        try {
            $this->validator->validateLocaleCreate(['code' => $code, 'name' => 'Broken']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('code'));
        }
    }

    public function testLocaleCreateRejectsInvalidDirection(): void
    {
        try {
            $this->validator->validateLocaleCreate(['code' => 'en', 'name' => 'English', 'direction' => 'up']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('direction'));
        }
    }

    public function testLocaleCreateRejectsNonBooleanEnabled(): void
    {
        try {
            $this->validator->validateLocaleCreate(['code' => 'en', 'name' => 'English', 'enabled' => 'maybe']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('enabled'));
        }
    }

    public function testLocaleCreateCollectsMultipleErrors(): void
    {
        try {
            $this->validator->validateLocaleCreate(['direction' => 'up']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('code'));
            self::assertTrue($e->hasError('name'));
            self::assertTrue($e->hasError('direction'));
        }
    }

    public function testLocaleUpdateRejectsEmptyPayload(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateLocaleUpdate([]);
    }

    public function testLocaleUpdateRejectsCodeChange(): void
    {
        try {
            $this->validator->validateLocaleUpdate(['code' => 'fr']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('code'));
        }
    }

    public function testLocaleUpdateAllowsClearingFallback(): void
    {
        $validated = $this->validator->validateLocaleUpdate(['fallback_locale' => null]);

        self::assertArrayHasKey('fallback_locale', $validated);
        self::assertNull($validated['fallback_locale']);
    }

    public function testLocaleUpdateRejectsMalformedFallback(): void
    {
        try {
            $this->validator->validateLocaleUpdate(['fallback_locale' => 'not a code!']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('fallback_locale'));
        }
    }

    public function testTranslationUpsertRequiresKeyAndValue(): void
    {
        try {
            $this->validator->validateTranslationUpsert([]);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('key'));
            self::assertTrue($e->hasError('value'));
        }
    }

    public function testTranslationUpsertDefaultsDomainAndLocale(): void
    {
        $validated = $this->validator->validateTranslationUpsert(['key' => 'hello', 'value' => 'Hello']);

        self::assertSame('messages', $validated['domain']);
        self::assertSame('en', $validated['locale']);
        self::assertSame('hello', $validated['key']);
        self::assertSame('Hello', $validated['value']);
    }

    public function testTranslationUpsertRejectsMalformedLocale(): void
    {
        try {
            $this->validator->validateTranslationUpsert(['key' => 'hello', 'value' => 'Hello', 'locale' => '!!']);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('locale'));
        }
    }

    public function testTranslationValueRequired(): void
    {
        try {
            $this->validator->validateTranslationValue([]);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('value'));
        }
    }

    public function testImportRequiresPath(): void
    {
        try {
            $this->validator->validateImport([]);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('path'));
        }
    }

    public function testImportRejectsNonStringPath(): void
    {
        try {
            $this->validator->validateImport(['path' => ['nested']]);
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertTrue($e->hasError('path'));
        }
    }
}
