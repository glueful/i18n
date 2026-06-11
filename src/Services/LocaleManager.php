<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\I18n\Contracts\LocaleManagerInterface;
use Glueful\Extensions\I18n\Repositories\LocaleRepository;

final class LocaleManager implements LocaleManagerInterface
{
    public function __construct(
        private LocaleRepository $locales,
        private ApplicationContext $context,
    ) {
    }

    public function all(): array
    {
        return $this->locales->all();
    }

    /** @return array<string,mixed>|null */
    public function find(string $code): ?array
    {
        return $this->locales->find($code);
    }

    public function enabled(): array
    {
        return $this->locales->enabled();
    }

    public function default(): string
    {
        return $this->locales->defaultCode((string) \config($this->context, 'i18n.default_locale', 'en'));
    }

    public function fallbackChain(string $locale): array
    {
        return $this->locales->fallbackChain(
            $locale,
            (string) \config($this->context, 'i18n.fallback_locale', $this->default())
        );
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        return $this->locales->create($data);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function update(string $code, array $data): array
    {
        return $this->locales->update($code, $data);
    }
}
