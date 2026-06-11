<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Http;

use Glueful\Extensions\I18n\Http\Controllers\LocaleController;
use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;
use Symfony\Component\HttpFoundation\Request;

final class LocaleControllerTest extends I18nTestCase
{
    private function controller(): LocaleController
    {
        return new LocaleController(
            new LocaleManager(new LocaleRepository($this->connection()), $this->appContext()),
            new I18nPayloadValidator()
        );
    }

    /** @param array<string,mixed> $payload */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            '/i18n/locales',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode($payload)
        );
    }

    public function testStoreCreatesLocale(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['code' => 'fr', 'name' => 'French']));

        self::assertSame(201, $response->getStatusCode());
        $row = $this->connection()->table('i18n_locales')->where('code', '=', 'fr')->first();
        self::assertNotNull($row);
        self::assertSame('French', $row['name']);
    }

    public function testStoreIgnoresUnknownFields(): void
    {
        $response = $this->controller()->store(
            $this->jsonRequest(['code' => 'fr', 'name' => 'French', 'bogus' => 'nope'])
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testStoreMissingCodeReturns422(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['name' => 'French']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreMalformedCodeReturns422(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['code' => 'not a code!', 'name' => 'Broken']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreMissingNameReturns422(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['code' => 'fr']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreDuplicateCodeReturns422(): void
    {
        $this->seedLocale();

        $response = $this->controller()->store($this->jsonRequest(['code' => 'en', 'name' => 'English']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreSelfFallbackCycleReturns422(): void
    {
        $response = $this->controller()->store(
            $this->jsonRequest(['code' => 'fr', 'name' => 'French', 'fallback_locale' => 'fr'])
        );

        self::assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        self::assertFalse($body['success']);
        self::assertArrayHasKey('fallback_locale', $body['error']['details']);
    }

    public function testUpdateUnknownLocaleReturns404(): void
    {
        $response = $this->controller()->update($this->jsonRequest(['name' => 'Ghost']), 'zz');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpdateUpdatesLocale(): void
    {
        $this->seedLocale();

        $response = $this->controller()->update($this->jsonRequest(['name' => 'British English']), 'en');

        self::assertSame(200, $response->getStatusCode());
        $row = $this->connection()->table('i18n_locales')->where('code', '=', 'en')->first();
        self::assertSame('British English', $row['name']);
    }

    public function testUpdateFallbackCycleReturns422(): void
    {
        $this->seedLocale();
        $this->seedLocale(['code' => 'fr', 'name' => 'French', 'is_default' => false, 'fallback_locale' => 'en']);

        $response = $this->controller()->update($this->jsonRequest(['fallback_locale' => 'fr']), 'en');

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateEmptyPayloadReturns422(): void
    {
        $this->seedLocale();

        $response = $this->controller()->update($this->jsonRequest([]), 'en');

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateCodeChangeReturns422(): void
    {
        $this->seedLocale();

        $response = $this->controller()->update($this->jsonRequest(['code' => 'fr']), 'en');

        self::assertSame(422, $response->getStatusCode());
    }

    public function testIndexListsLocales(): void
    {
        $this->seedLocale();

        $response = $this->controller()->index(Request::create('/i18n/locales'));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        self::assertCount(1, $body['data']['locales']);
    }
}
