<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Schema;

use Glueful\Database\Connection;
use Glueful\Extensions\Schema\StructuralVerifierInterface;

/**
 * Structural verifier for glueful/i18n (schema policy spec B7): each create migration proves
 * every table it creates with its load-bearing columns. Unknown basenames are never adoptable.
 */
final class I18nSchemaVerifier implements StructuralVerifierInterface
{
    public function source(): string
    {
        return 'glueful/i18n';
    }

    /** @return list<string> */
    public function migrationBasenames(): array
    {
        return [
            '001_CreateI18nTables.php',
        ];
    }

    public function verify(Connection $db, string $migrationBasename): bool
    {
        return match ($migrationBasename) {
            '001_CreateI18nTables.php' => $this->tablesWithColumns($db, [
                'i18n_locales' => [],
                'i18n_translations' => [],
                'i18n_missing_translations' => [],
            ]),
            default => false,
        };
    }

    /** @param array<string, list<string>> $expectations */
    private function tablesWithColumns(Connection $db, array $expectations): bool
    {
        $schema = $db->getSchemaBuilder();
        foreach ($expectations as $table => $columns) {
            if (!$schema->hasTable($table)) {
                return false;
            }
            foreach ($columns as $column) {
                if (!$schema->hasColumn($table, $column)) {
                    return false;
                }
            }
        }
        return true;
    }
}
