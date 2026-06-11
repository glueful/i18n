<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Repositories;

use Glueful\Database\Connection;
use Glueful\Helpers\Utils;

final class LocaleRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        $code = $this->string($data, 'code');
        $fallback = isset($data['fallback_locale']) && $data['fallback_locale'] !== ''
            ? (string) $data['fallback_locale']
            : null;
        if ($fallback !== null) {
            $this->assertNoFallbackCycle($code, $fallback);
        }

        $now = $this->now();
        $row = array_merge([
            'uuid' => Utils::generateNanoID(12),
            'native_name' => null,
            'enabled' => true,
            'is_default' => false,
            'fallback_locale' => $fallback,
            'direction' => 'ltr',
            'region' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $data, ['fallback_locale' => $fallback]);

        if ((bool) $row['is_default']) {
            $this->connection->table('i18n_locales')->executeModification(
                'UPDATE i18n_locales SET is_default = ?',
                [false]
            );
        }

        $this->connection->table('i18n_locales')->insert($row);

        return $row;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function update(string $code, array $data): array
    {
        if (array_key_exists('fallback_locale', $data)) {
            $fallback = $data['fallback_locale'] !== null && $data['fallback_locale'] !== ''
                ? (string) $data['fallback_locale']
                : null;
            if ($fallback !== null) {
                $this->assertNoFallbackCycle($code, $fallback);
            }
            $data['fallback_locale'] = $fallback;
        }

        if (($data['is_default'] ?? false) === true) {
            $this->connection->table('i18n_locales')->executeModification(
                'UPDATE i18n_locales SET is_default = ?',
                [false]
            );
        }

        $data['updated_at'] = $this->now();
        $this->connection->table('i18n_locales')->where('code', '=', $code)->update($data);

        $row = $this->find($code);
        if ($row === null) {
            throw new \RuntimeException(sprintf('Locale "%s" was not found.', $code));
        }

        return $row;
    }

    /** @return array<string,mixed>|null */
    public function find(string $code): ?array
    {
        return $this->connection->table('i18n_locales')->where('code', '=', $code)->first();
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->connection->table('i18n_locales')->orderBy('code', 'ASC')->get();
    }

    /** @return list<array<string,mixed>> */
    public function enabled(): array
    {
        return $this->connection
            ->table('i18n_locales')
            ->where('enabled', '=', true)
            ->orderBy('code', 'ASC')
            ->get();
    }

    public function defaultCode(string $fallback = 'en'): string
    {
        $row = $this->connection->table('i18n_locales')->where('is_default', '=', true)->first();

        return $row !== null ? (string) $row['code'] : $fallback;
    }

    /** @return list<string> */
    public function fallbackChain(string $locale, string $globalFallback): array
    {
        $chain = [];
        $seen = [];
        $current = $locale;

        while ($current !== '' && !isset($seen[$current])) {
            $seen[$current] = true;
            $chain[] = $current;
            $row = $this->find($current);
            $parent = is_array($row) ? (string) ($row['fallback_locale'] ?? '') : '';
            $current = $parent;
        }

        $parent = $this->parentLocale($locale);
        if ($parent !== null && !in_array($parent, $chain, true)) {
            $chain[] = $parent;
        }

        if ($globalFallback !== '' && !in_array($globalFallback, $chain, true)) {
            $chain[] = $globalFallback;
        }

        return $chain;
    }

    private function assertNoFallbackCycle(string $code, string $fallback): void
    {
        $seen = [$code => true];
        $current = $fallback;

        while ($current !== '') {
            if (isset($seen[$current])) {
                throw new \InvalidArgumentException('Locale fallback cycle detected.');
            }
            $seen[$current] = true;
            $row = $this->find($current);
            $current = is_array($row) ? (string) ($row['fallback_locale'] ?? '') : '';
        }
    }

    private function parentLocale(string $locale): ?string
    {
        return str_contains($locale, '-') ? explode('-', $locale, 2)[0] : null;
    }

    /** @param array<string,mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_scalar($value) || (string) $value === '') {
            throw new \InvalidArgumentException(sprintf('"%s" is required.', $key));
        }

        return (string) $value;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
