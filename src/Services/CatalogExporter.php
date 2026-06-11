<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

use Glueful\Extensions\I18n\Repositories\TranslationRepository;

final class CatalogExporter
{
    public function __construct(private TranslationRepository $translations)
    {
    }

    public function toJson(?string $locale = null, ?string $domain = null): string
    {
        $rows = $this->translations->list($locale, $domain);

        return json_encode(['translations' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}
