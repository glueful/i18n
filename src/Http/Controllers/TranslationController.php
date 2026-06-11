<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http\Controllers;

use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\CatalogExporter;
use Glueful\Extensions\I18n\Services\CatalogImporter;
use Glueful\Http\Response;
use Glueful\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Request;

final class TranslationController
{
    public function __construct(
        private TranslationRepository $translations,
        private MissingTranslationRepository $missing,
        private CatalogImporter $importer,
        private CatalogExporter $exporter,
        private I18nPayloadValidator $validator,
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
        try {
            $data = $this->validator->validateTranslationUpsert($this->body($request));
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        }

        $this->translations->put($data['domain'], $data['locale'], $data['key'], $data['value']);

        return Response::created([], 'Translation saved.');
    }

    public function update(Request $request, string $uuid): Response
    {
        if ($this->translations->findByUuid($uuid) === null) {
            return Response::notFound('Translation not found.');
        }

        try {
            $data = $this->validator->validateTranslationValue($this->body($request));
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        }

        return Response::success([
            'translation' => $this->translations->updateByUuid($uuid, $data['value']),
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
        try {
            $path = $this->validator->validateImport($this->body($request));
            $count = $this->importer->importFile($path);
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['path' => [$e->getMessage()]]);
        }

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
}
