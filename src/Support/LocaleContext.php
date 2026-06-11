<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Support;

use Symfony\Component\HttpFoundation\Request;

final readonly class LocaleContext
{
    /** @param array<string,mixed> $claims */
    public function __construct(
        public ?string $explicitLocale = null,
        public ?Request $request = null,
        public array $claims = [],
        public ?string $tenantLocale = null,
        public ?string $appLocale = null,
    ) {
    }
}
