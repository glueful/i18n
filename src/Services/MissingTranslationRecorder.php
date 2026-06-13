<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;

final class MissingTranslationRecorder
{
    /** @var array<string,int> */
    private array $lastRecordedAt = [];

    public function __construct(
        private MissingTranslationRepository $missing,
        private ApplicationContext $context,
    ) {
    }

    public function record(string $domain, string $locale, string $key): void
    {
        if ((bool) \config($this->context, 'i18n.missing_tracking', false) !== true) {
            return;
        }

        $cacheKey = $domain . ':' . $locale . ':' . $key;
        $now = time();
        $limit = (int) \config($this->context, 'i18n.missing_rate_limit_seconds', 60);
        if (($this->lastRecordedAt[$cacheKey] ?? 0) + $limit > $now) {
            return;
        }

        $maxRows = (int) \config($this->context, 'i18n.missing_max_rows', 10000);
        if (
            $maxRows > 0
            && !$this->missing->exists($domain, $locale, $key)
            && $this->missing->count() >= $maxRows
        ) {
            return;
        }

        $this->lastRecordedAt[$cacheKey] = $now;
        $this->missing->record($domain, $locale, $key);
    }
}
