<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Http;

use Glueful\Extensions\I18n\Http\Controllers\TranslationController;
use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\CatalogExporter;
use Glueful\Extensions\I18n\Services\CatalogImporter;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;
use Symfony\Component\HttpFoundation\Request;

final class TranslationControllerTest extends I18nTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function controller(): TranslationController
    {
        $translations = new TranslationRepository($this->connection());

        return new TranslationController(
            $translations,
            new MissingTranslationRepository($this->connection()),
            new CatalogImporter($translations),
            new CatalogExporter($translations),
            new I18nPayloadValidator()
        );
    }

    /** @param array<string,mixed> $payload */
    private function jsonRequest(array $payload): Request
    {
        return Request::create(
            '/i18n/translations',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode($payload)
        );
    }

    private function tempFile(string $contents, string $extension = 'json'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'i18n') . '.' . $extension;
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function testStoreUpsertsTranslation(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['key' => 'hello', 'value' => 'Hello']));

        self::assertSame(201, $response->getStatusCode());
        $row = $this->connection()->table('i18n_translations')->where('key', '=', 'hello')->first();
        self::assertSame('Hello', $row['value']);
    }

    public function testStoreMissingKeyReturns422(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['value' => 'Hello']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreMissingValueReturns422(): void
    {
        $response = $this->controller()->store($this->jsonRequest(['key' => 'hello']));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testStoreMalformedLocaleReturns422(): void
    {
        $response = $this->controller()->store(
            $this->jsonRequest(['key' => 'hello', 'value' => 'Hello', 'locale' => 'not a locale!'])
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUpdateUnknownUuidReturns404(): void
    {
        $response = $this->controller()->update($this->jsonRequest(['value' => 'Hi']), 'missing-uuid');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testUpdateUpdatesValue(): void
    {
        $this->seedTranslation();
        $row = $this->connection()->table('i18n_translations')->where('key', '=', 'hello')->first();

        $response = $this->controller()->update($this->jsonRequest(['value' => 'Hi']), (string) $row['uuid']);

        self::assertSame(200, $response->getStatusCode());
        $updated = $this->connection()->table('i18n_translations')->where('key', '=', 'hello')->first();
        self::assertSame('Hi', $updated['value']);
    }

    public function testUpdateMissingValueReturns422(): void
    {
        $this->seedTranslation();
        $row = $this->connection()->table('i18n_translations')->where('key', '=', 'hello')->first();

        $response = $this->controller()->update($this->jsonRequest([]), (string) $row['uuid']);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testImportMissingCatalogReturns422(): void
    {
        $response = $this->controller()->import($this->jsonRequest([]));

        self::assertSame(422, $response->getStatusCode());
    }

    public function testImportRejectsPathOnlyPayload(): void
    {
        $response = $this->controller()->import(
            $this->jsonRequest(['path' => sys_get_temp_dir() . '/i18n-definitely-missing.json'])
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testImportDoesNotExecutePhpPathPayload(): void
    {
        $marker = sys_get_temp_dir() . '/i18n-http-import-rce-' . bin2hex(random_bytes(4));
        $path = $this->tempFile(
            "<?php\nfile_put_contents(" . var_export($marker, true) . ", 'hit');\nreturn [];\n",
            'php'
        );

        $response = $this->controller()->import($this->jsonRequest(['path' => $path]));

        self::assertSame(422, $response->getStatusCode());
        self::assertFileDoesNotExist($marker);
    }

    public function testImportHappyPathReturnsCount(): void
    {
        $response = $this->controller()->import($this->jsonRequest([
            'catalog' => [
                ['domain' => 'messages', 'locale' => 'en', 'key' => 'hello', 'value' => 'Hello'],
                ['domain' => 'messages', 'locale' => 'fr', 'key' => 'hello', 'value' => 'Bonjour'],
            ],
        ]));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        self::assertSame(2, $body['data']['imported']);
    }

    public function testIndexListsTranslations(): void
    {
        $this->seedTranslation();

        $response = $this->controller()->index(Request::create('/i18n/translations'));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        self::assertCount(1, $body['data']['translations']);
    }

    public function testExportReturnsCatalog(): void
    {
        $this->seedTranslation();

        $response = $this->controller()->export(Request::create('/i18n/export'));

        self::assertSame(200, $response->getStatusCode());
    }
}
