<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Contracts;

interface TranslatorInterface
{
    /** @param array<string, scalar|null> $parameters */
    public function trans(
        string $key,
        array $parameters = [],
        ?string $locale = null,
        string $domain = 'messages'
    ): string;
}
