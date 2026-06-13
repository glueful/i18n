<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Repositories;

use Glueful\Database\Connection;
use Glueful\Helpers\Utils;
use Glueful\Validation\ValidationException;

final class LocaleRepository
{
    private const CREATE_COLUMNS = [
        'code',
        'name',
        'native_name',
        'enabled',
        'is_default',
        'fallback_locale',
        'direction',
        'region',
    ];

    private const UPDATE_COLUMNS = [
        'name',
        'native_name',
        'enabled',
        'is_default',
        'fallback_locale',
        'direction',
        'region',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        $data = $this->only($data, self::CREATE_COLUMNS);
        $code = $this->string($data, 'code');
        if ($this->find($code) !== null) {
            throw ValidationException::forField('code', sprintf('Locale "%s" already exists.', $code));
        }

        $fallback = isset($data['fallback_locale']) && $data['fallback_locale'] !== ''
            ? (string) $data['fallback_locale']
            : null;
        if ($fallback !== null) {
            $this->assertNoFallbackCycle($code, $fallback);
        }

        $isFirstLocale = $this->count() === 0;
        $now = $this->now();
        $row = array_merge([
            'uuid' => Utils::generateNanoID(12),
            'native_name' => null,
            'enabled' => true,
            'is_default' => $isFirstLocale,
            'fallback_locale' => $fallback,
            'direction' => 'ltr',
            'region' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $data, ['fallback_locale' => $fallback]);
        if ($isFirstLocale) {
            $row['enabled'] = true;
            $row['is_default'] = true;
        }

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
        $data = $this->only($data, self::UPDATE_COLUMNS);
        if (array_key_exists('fallback_locale', $data)) {
            $fallback = $data['fallback_locale'] !== null && $data['fallback_locale'] !== ''
                ? (string) $data['fallback_locale']
                : null;
            if ($fallback !== null) {
                $this->assertNoFallbackCycle($code, $fallback);
            }
            $data['fallback_locale'] = $fallback;
        }

        if (
            (array_key_exists('is_default', $data) && (bool) $data['is_default'] === false)
            || (array_key_exists('enabled', $data) && (bool) $data['enabled'] === false)
        ) {
            $this->assertNotOnlyDefault($code);
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

    public function count(): int
    {
        return $this->connection->table('i18n_locales')->count();
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
                throw ValidationException::forField('fallback_locale', 'Locale fallback cycle detected.');
            }
            $seen[$current] = true;
            $row = $this->find($current);
            $current = is_array($row) ? (string) ($row['fallback_locale'] ?? '') : '';
        }
    }

    private function assertNotOnlyDefault(string $code): void
    {
        $row = $this->find($code);
        if ($row === null || (bool) ($row['is_default'] ?? false) !== true) {
            return;
        }

        $defaultCount = $this->connection->table('i18n_locales')->where('is_default', '=', true)->count();
        if ($defaultCount <= 1) {
            throw ValidationException::forField('is_default', 'At least one stored default locale is required.');
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

    /**
     * @param array<string,mixed> $data
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private function only(array $data, array $columns): array
    {
        return array_intersect_key($data, array_flip($columns));
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
