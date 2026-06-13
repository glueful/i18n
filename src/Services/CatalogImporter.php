<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Services;

use Glueful\Extensions\I18n\Repositories\TranslationRepository;

final class CatalogImporter
{
    public function __construct(private TranslationRepository $translations)
    {
    }

    public function importFile(string $path): int
    {
        if (!is_file($path)) {
            throw new \InvalidArgumentException(sprintf('Catalog file "%s" was not found.', $path));
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $payload = $extension === 'php'
            ? require $path
            : json_decode((string) file_get_contents($path), true);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Catalog payload must be a JSON or PHP array.');
        }

        return $this->importPayload($payload);
    }

    /** @param array<mixed> $payload */
    public function importPayload(array $payload): int
    {
        $rows = isset($payload['translations']) && is_array($payload['translations'])
            ? $payload['translations']
            : $payload;

        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $this->translations->put(
                (string) ($row['domain'] ?? 'messages'),
                (string) ($row['locale'] ?? 'en'),
                (string) ($row['key'] ?? ''),
                (string) ($row['value'] ?? '')
            );
            $count++;
        }

        return $count;
    }
}
