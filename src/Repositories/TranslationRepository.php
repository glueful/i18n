<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Repositories;

use Glueful\Database\Connection;
use Glueful\Extensions\I18n\Contracts\TranslationRepositoryInterface;
use Glueful\Helpers\Utils;

final class TranslationRepository implements TranslationRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function get(string $domain, string $locale, string $key): ?string
    {
        $row = $this->connection
            ->table('i18n_translations')
            ->where('domain', '=', $domain)
            ->where('locale', '=', $locale)
            ->where('key', '=', $key)
            ->where('status', '=', 'active')
            ->first();

        return $row !== null ? (string) $row['value'] : null;
    }

    public function put(string $domain, string $locale, string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->connection
            ->table('i18n_translations')
            ->where('domain', '=', $domain)
            ->where('locale', '=', $locale)
            ->where('key', '=', $key)
            ->first();

        if ($existing !== null) {
            $this->connection
                ->table('i18n_translations')
                ->where('uuid', '=', (string) $existing['uuid'])
                ->update(['value' => $value, 'status' => 'active', 'updated_at' => $now]);
            return;
        }

        $this->connection->table('i18n_translations')->insert([
            'uuid' => Utils::generateNanoID(12),
            'domain' => $domain,
            'locale' => $locale,
            'key' => $key,
            'value' => $value,
            'status' => 'active',
            'source' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string,string> */
    public function bundle(string $locale, string $domain): array
    {
        $rows = $this->connection
            ->table('i18n_translations')
            ->where('locale', '=', $locale)
            ->where('domain', '=', $domain)
            ->where('status', '=', 'active')
            ->orderBy('key', 'ASC')
            ->get();

        $bundle = [];
        foreach ($rows as $row) {
            $bundle[(string) $row['key']] = (string) $row['value'];
        }

        return $bundle;
    }

    /** @return list<array<string,mixed>> */
    public function list(?string $locale = null, ?string $domain = null): array
    {
        $query = $this->connection->table('i18n_translations')->orderBy('key', 'ASC');
        if ($locale !== null && $locale !== '') {
            $query->where('locale', '=', $locale);
        }
        if ($domain !== null && $domain !== '') {
            $query->where('domain', '=', $domain);
        }

        return $query->get();
    }

    /** @return list<array<string,mixed>> */
    public function missing(string $domain, string $locale): array
    {
        return $this->connection
            ->table('i18n_missing_translations')
            ->where('domain', '=', $domain)
            ->where('locale', '=', $locale)
            ->orderBy('last_seen_at', 'DESC')
            ->get();
    }

    /** @return array<string,mixed>|null */
    public function findByUuid(string $uuid): ?array
    {
        return $this->connection->table('i18n_translations')->where('uuid', '=', $uuid)->first();
    }

    /** @return array<string,mixed> */
    public function updateByUuid(string $uuid, string $value): array
    {
        $this->connection
            ->table('i18n_translations')
            ->where('uuid', '=', $uuid)
            ->update(['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);

        $row = $this->connection->table('i18n_translations')->where('uuid', '=', $uuid)->first();
        if ($row === null) {
            throw new \RuntimeException(sprintf('Translation "%s" was not found.', $uuid));
        }

        return $row;
    }
}
