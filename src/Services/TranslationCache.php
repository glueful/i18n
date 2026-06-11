<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

final class TranslationCache
{
    /** @var array<string,array<string,string>> */
    private array $bundles = [];

    /** @return array<string,string>|null */
    public function get(string $locale, string $domain, int $version): ?array
    {
        return $this->bundles[$this->key($locale, $domain, $version)] ?? null;
    }

    /** @param array<string,string> $messages */
    public function put(string $locale, string $domain, int $version, array $messages): void
    {
        $this->bundles[$this->key($locale, $domain, $version)] = $messages;
    }

    public function clear(?string $locale = null, ?string $domain = null): void
    {
        if ($locale === null || $domain === null) {
            $this->bundles = [];
            return;
        }

        foreach (array_keys($this->bundles) as $key) {
            if (str_starts_with($key, $locale . ':' . $domain . ':')) {
                unset($this->bundles[$key]);
            }
        }
    }

    private function key(string $locale, string $domain, int $version): string
    {
        return $locale . ':' . $domain . ':' . $version;
    }
}
