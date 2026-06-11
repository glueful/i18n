<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Http\Controllers;

use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Http\Response;
use Symfony\Component\HttpFoundation\Request;

final class LocaleController
{
    public function __construct(private LocaleManager $locales)
    {
    }

    public function index(Request $request): Response
    {
        return Response::success(['locales' => $this->locales->all()], 'Locales retrieved.');
    }

    public function store(Request $request): Response
    {
        return Response::created(['locale' => $this->locales->create($this->body($request))], 'Locale created.');
    }

    public function update(Request $request, string $code): Response
    {
        return Response::success(['locale' => $this->locales->update($code, $this->body($request))], 'Locale updated.');
    }

    /** @return array<string,mixed> */
    private function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return array_merge($request->request->all(), is_array($data) ? $data : []);
    }
}
