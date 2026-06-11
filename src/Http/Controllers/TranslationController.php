<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http\Controllers;

use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\CatalogExporter;
use Glueful\Extensions\I18n\Services\CatalogImporter;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class TranslationController
{
    public function __construct(
        private TranslationRepository $translations,
        private MissingTranslationRepository $missing,
        private CatalogImporter $importer,
        private CatalogExporter $exporter,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::success([
            'translations' => $this->translations->list(
                $this->optional($request, 'locale'),
                $this->optional($request, 'domain')
            ),
        ], 'Translations retrieved.');
    }

    public function store(Request $request): Response
    {
        $data = $this->body($request);
        $this->translations->put(
            $this->required($data, 'domain', 'messages'),
            $this->required($data, 'locale', 'en'),
            $this->required($data, 'key'),
            $this->required($data, 'value')
        );

        return Response::created([], 'Translation saved.');
    }

    public function update(Request $request, string $uuid): Response
    {
        $data = $this->body($request);

        return Response::success([
            'translation' => $this->translations->updateByUuid($uuid, $this->required($data, 'value')),
        ], 'Translation updated.');
    }

    public function missing(Request $request): Response
    {
        return Response::success([
            'missing' => $this->missing->list($this->optional($request, 'locale'), $this->optional($request, 'domain')),
        ], 'Missing translations retrieved.');
    }

    public function import(Request $request): Response
    {
        $data = $this->body($request);
        $count = $this->importer->importFile($this->required($data, 'path'));

        return Response::success(['imported' => $count], 'Catalog imported.');
    }

    public function export(Request $request): Response
    {
        $json = $this->exporter->toJson($this->optional($request, 'locale'), $this->optional($request, 'domain'));

        return Response::success(['catalog' => json_decode($json, true)], 'Catalog exported.');
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }

    private function optional(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /** @param array<string,mixed> $data */
    private function required(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_scalar($value) || (string) $value === '') {
            throw new \InvalidArgumentException(sprintf('"%s" is required.', $key));
        }

        return (string) $value;
    }
}
