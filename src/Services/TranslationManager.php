<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\I18n\Contracts\TranslatorInterface;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;

final class TranslationManager implements TranslatorInterface
{
    /** @var array<string,array<string,array<string,string>>> */
    private array $catalogs = [];

    /** @var array<string,int> */
    private array $versions = [];

    public function __construct(
        private LocaleResolver $resolver,
        private LocaleManager $locales,
        private TranslationRepository $translations,
        private TranslationCache $cache,
        private MessageFormatter $formatter,
        private MissingTranslationRecorder $missing,
        private ApplicationContext $context,
    ) {
    }

    /** @param array<string,string> $messages */
    public function addMessages(string $locale, string $domain, array $messages): void
    {
        $this->catalogs[$locale][$domain] = array_merge($this->catalogs[$locale][$domain] ?? [], $messages);
        $this->bump($locale, $domain);
    }

    public function trans(
        string $key,
        array $parameters = [],
        ?string $locale = null,
        string $domain = 'messages'
    ): string {
        $resolved = $locale ?? $this->resolver->resolveLocale();

        foreach ($this->locales->fallbackChain($resolved) as $candidate) {
            $bundle = $this->bundle($candidate, $domain);
            if (array_key_exists($key, $bundle)) {
                return $this->formatter->format($bundle[$key], $parameters, $candidate);
            }
        }

        $this->missing->record($domain, $resolved, $key);

        return $key;
    }

    public function put(string $domain, string $locale, string $key, string $value): void
    {
        $this->translations->put($domain, $locale, $key, $value);
        $this->bump($locale, $domain);
    }

    /** @return array<string,string> */
    private function bundle(string $locale, string $domain): array
    {
        $version = $this->versions[$locale . ':' . $domain] ?? 1;
        $cached = $this->cache->get($locale, $domain, $version);
        if ($cached !== null) {
            return $cached;
        }

        $file = $this->catalogs[$locale][$domain] ?? [];
        $db = $this->translations->bundle($locale, $domain);
        $bundle = (bool) \config($this->context, 'i18n.db_overrides_catalogs', true)
            ? array_merge($file, $db)
            : array_merge($db, $file);

        $this->cache->put($locale, $domain, $version, $bundle);

        return $bundle;
    }

    private function bump(string $locale, string $domain): void
    {
        $key = $locale . ':' . $domain;
        $this->versions[$key] = ($this->versions[$key] ?? 1) + 1;
        $this->cache->clear($locale, $domain);
    }
}
