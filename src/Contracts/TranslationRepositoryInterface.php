<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Contracts;

interface TranslationRepositoryInterface
{
    public function get(string $domain, string $locale, string $key): ?string;

    public function put(string $domain, string $locale, string $key, string $value): void;

    /** @return list<array<string,mixed>> */
    public function missing(string $domain, string $locale): array;
}
