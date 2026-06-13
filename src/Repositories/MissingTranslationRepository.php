<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Repositories;

use Glueful\Database\Connection;
use Glueful\Helpers\Utils;

final class MissingTranslationRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function record(string $domain, string $locale, string $key): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->connection
            ->table('i18n_missing_translations')
            ->where('domain', '=', $domain)
            ->where('locale', '=', $locale)
            ->where('key', '=', $key)
            ->first();

        if ($existing === null) {
            $this->connection->table('i18n_missing_translations')->insert([
                'uuid' => Utils::generateNanoID(12),
                'domain' => $domain,
                'locale' => $locale,
                'key' => $key,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'hits' => 1,
            ]);
            return;
        }

        $this->connection->table('i18n_missing_translations')->executeModification(
            'UPDATE i18n_missing_translations SET hits = hits + 1, last_seen_at = ? WHERE uuid = ?',
            [$now, (string) $existing['uuid']]
        );
    }

    /** @return list<array<string,mixed>> */
    public function list(?string $locale = null, ?string $domain = null): array
    {
        $query = $this->connection->table('i18n_missing_translations')->orderBy('last_seen_at', 'DESC');
        if ($locale !== null && $locale !== '') {
            $query->where('locale', '=', $locale);
        }
        if ($domain !== null && $domain !== '') {
            $query->where('domain', '=', $domain);
        }

        return $query->get();
    }

    public function exists(string $domain, string $locale, string $key): bool
    {
        return $this->connection
            ->table('i18n_missing_translations')
            ->where('domain', '=', $domain)
            ->where('locale', '=', $locale)
            ->where('key', '=', $key)
            ->exists();
    }

    public function count(): int
    {
        return $this->connection->table('i18n_missing_translations')->count();
    }
}
