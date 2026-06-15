<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http\Controllers;

use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\CatalogExporter;
use Glueful\Extensions\I18n\Services\CatalogImporter;
use Glueful\Http\Response;
use Glueful\Routing\Attributes\ApiOperation;
use Glueful\Routing\Attributes\ApiResponse;
use Glueful\Routing\Attributes\QueryParam;
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

    /**
     * List persisted translations.
     */
    #[ApiOperation(
        summary: 'List Translations',
        description: 'Lists persisted translations ordered by key, optionally filtered by locale and domain. '
            . 'Requires the `i18n.view` permission.',
        tags: ['I18n'],
    )]
    #[QueryParam('locale', description: 'Filter by locale code')]
    #[QueryParam('domain', description: 'Filter by translation domain')]
    #[ApiResponse(200, description: 'Translations retrieved')]
    #[ApiResponse(403, description: 'Forbidden')]
    public function index(Request $request): Response
    {
        return Response::success([
            'translations' => $this->translations->list(
                $this->optional($request, 'locale'),
                $this->optional($request, 'domain')
            ),
        ], 'Translations retrieved.');
    }

    /**
     * Create or update a translation.
     */
    #[ApiOperation(
        summary: 'Create or Update Translation',
        description: 'Upserts a translation on its `(domain, locale, key)` identity: an existing row is '
            . 'updated (and reactivated) in place, otherwise a new row is inserted. '
            . 'Body: `key` (required), `value` (required; max 65,535 bytes, may contain {param} placeholders), '
            . '`domain` (default: messages), `locale` (default: en). Requires the `i18n.manage` permission.',
        tags: ['I18n'],
    )]
    #[ApiResponse(201, description: 'Translation saved')]
    #[ApiResponse(403, description: 'Forbidden')]
    #[ApiResponse(422, description: 'Validation failed (missing/oversized key/value or malformed locale)')]
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

    /**
     * Update the value of one translation by UUID.
     */
    #[ApiOperation(
        summary: 'Update Translation Value',
        description: 'Updates the value of one persisted translation by UUID. '
            . 'Body: `value` (required; new translated message, max 65,535 bytes). '
            . 'Requires the `i18n.manage` permission.',
        tags: ['I18n'],
    )]
    #[ApiResponse(200, description: 'Translation updated')]
    #[ApiResponse(403, description: 'Forbidden')]
    #[ApiResponse(404, description: 'Translation not found')]
    #[ApiResponse(422, description: 'Validation failed (missing or oversized value)')]
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

    /**
     * List recorded missing translation keys.
     */
    #[ApiOperation(
        summary: 'List Missing Translations',
        description: 'Lists recorded missing translation keys with hit counts, most recently seen first. '
            . 'Rows only accumulate while `i18n.missing_tracking` is enabled. '
            . 'Requires the `i18n.view` permission.',
        tags: ['I18n'],
    )]
    #[QueryParam('locale', description: 'Filter by locale code')]
    #[QueryParam('domain', description: 'Filter by translation domain')]
    #[ApiResponse(200, description: 'Missing translations retrieved')]
    #[ApiResponse(403, description: 'Forbidden')]
    public function missing(Request $request): Response
    {
        return Response::success([
            'missing' => $this->missing->list($this->optional($request, 'locale'), $this->optional($request, 'domain')),
        ], 'Missing translations retrieved.');
    }

    /**
     * Import an inline JSON translation catalog.
     */
    #[ApiOperation(
        summary: 'Import Translation Catalog',
        description: 'Imports an inline JSON catalog and upserts each row into the translation store. '
            . 'The `catalog` value is either a list of rows or an object with a `translations` list; each '
            . 'row carries `domain`, `locale`, `key`, and `value` (max 65,535 bytes). Server-side file '
            . 'imports are CLI-only. Body: `catalog` (required; catalog object or array of translation rows). '
            . 'Requires the `i18n.import` permission.',
        tags: ['I18n'],
    )]
    #[ApiResponse(200, description: 'Catalog imported (returns imported row count)')]
    #[ApiResponse(403, description: 'Forbidden')]
    #[ApiResponse(422, description: 'Validation failed (missing or malformed catalog payload)')]
    public function import(Request $request): Response
    {
        try {
            $catalog = $this->validator->validateImport($this->body($request));
            $count = $this->importer->importPayload($catalog);
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return Response::validation(['catalog' => [$e->getMessage()]]);
        }

        return Response::success(['imported' => $count], 'Catalog imported.');
    }

    /**
     * Export persisted translations as a JSON catalog.
     */
    #[ApiOperation(
        summary: 'Export Translation Catalog',
        description: 'Exports persisted translations as a JSON catalog, optionally filtered by locale '
            . 'and domain. Requires the `i18n.export` permission.',
        tags: ['I18n'],
    )]
    #[QueryParam('locale', description: 'Filter by locale code')]
    #[QueryParam('domain', description: 'Filter by translation domain')]
    #[ApiResponse(200, description: 'Catalog exported')]
    #[ApiResponse(403, description: 'Forbidden')]
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
