<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http\Controllers;

use Glueful\Extensions\I18n\Http\I18nPayloadValidator;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Http\Response;
use Glueful\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Request;

final class LocaleController
{
    public function __construct(
        private LocaleManager $locales,
        private I18nPayloadValidator $validator,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::success(['locales' => $this->locales->all()], 'Locales retrieved.');
    }

    public function store(Request $request): Response
    {
        try {
            $data = $this->validator->validateLocaleCreate($this->body($request));

            return Response::created(['locale' => $this->locales->create($data)], 'Locale created.');
        } catch (ValidationException $e) {
            return Response::validation($e->errors(), $e->getMessage());
        }
    }

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
