<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http\Controllers;

use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Http\Response;
use Glueful\Routing\Attributes\ApiOperation;
use Glueful\Routing\Attributes\ApiResponse;
use Glueful\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Request;

final class LocaleController
{
    public function __construct(
        private LocaleManager $locales,
        private I18nPayloadValidator $validator,
    ) {
    }

    /**
     * List stored locales.
     */
    #[ApiOperation(
        summary: 'List Locales',
        description: 'Lists all stored locales ordered by code, including disabled ones. '
            . 'Requires the `i18n.view` permission.',
        tags: ['I18n'],
    )]
    #[ApiResponse(200, description: 'Locales retrieved')]
    #[ApiResponse(403, description: 'Forbidden')]
    public function index(Request $request): Response
    {
        return Response::success(['locales' => $this->locales->all()], 'Locales retrieved.');
    }

    /**
     * Create a stored locale.
     */
    #[ApiOperation(
        summary: 'Create Locale',
        description: 'Creates a stored locale. The first stored locale is forced to enabled/default. '
            . 'Setting `is_default` clears the previous default. Missing/malformed fields, a duplicate '
            . '`code`, or a `fallback_locale` that would create a fallback cycle are rejected with 422. '
            . 'Body: `code` (required), `name` (required), `native_name`, `enabled`, `is_default`, '
            . '`fallback_locale`, `direction` (ltr|rtl), `region`. Requires the `i18n.manage` permission.',
        tags: ['I18n'],
    )]
    #[ApiResponse(201, description: 'Locale created')]
    #[ApiResponse(403, description: 'Forbidden')]
    #[ApiResponse(422, description: 'Validation failed (missing/malformed fields, duplicate code, or fallback cycle)')]
    public function store(Request $request): Response
    {
        try {
            $data = $this->validator->validateLocaleCreate($this->body($request));

            return Response::created(['locale' => $this->locales->create($data)], 'Locale created.');
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        }
    }

    /**
     * Partially update a stored locale.
     */
    #[ApiOperation(
        summary: 'Update Locale',
        description: 'Partially updates a stored locale by code. All body fields are optional; '
            . '`fallback_locale` is cycle-checked, `is_default: true` clears the previous default, and '
            . 'the only stored default locale cannot be cleared or disabled. '
            . 'Body: `name`, `native_name`, `enabled`, `is_default`, `fallback_locale`, `direction` (ltr|rtl), '
            . '`region`. Requires the `i18n.manage` permission.',
        tags: ['I18n'],
    )]
    #[ApiResponse(200, description: 'Locale updated')]
    #[ApiResponse(403, description: 'Forbidden')]
    #[ApiResponse(404, description: 'Locale not found')]
    #[ApiResponse(
        422,
        description: 'Validation failed (empty payload, code change, malformed fields, fallback cycle, '
            . 'or clearing/disabling the only default)'
    )]
    public function update(Request $request, string $code): Response
    {
        if ($this->locales->find($code) === null) {
            return Response::notFound('Locale not found.');
        }

        try {
            $data = $this->validator->validateLocaleUpdate($this->body($request));

            return Response::success(['locale' => $this->locales->update($code, $data)], 'Locale updated.');
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        }
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }
}
