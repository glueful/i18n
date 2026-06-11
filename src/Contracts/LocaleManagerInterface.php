<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Contracts;

interface LocaleManagerInterface
{
    /** @return list<array<string,mixed>> */
    public function all(): array;

    /** @return list<array<string,mixed>> */
    public function enabled(): array;

    public function default(): string;

    /** @return list<string> */
    public function fallbackChain(string $locale): array;
}
