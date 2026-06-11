<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Contracts;

interface LocaleResolverInterface
{
    public function resolveLocale(mixed $context = null): string;
}
