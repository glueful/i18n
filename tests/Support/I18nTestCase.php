<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Support;

use Glueful\Bootstrap\ApplicationContext;
use Glueful\Database\Connection;
use Glueful\Extensions\I18n\Database\Migrations\CreateI18nTables;
use Glueful\Helpers\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

abstract class I18nTestCase extends TestCase
{
    protected ApplicationContext $context;
    protected Connection $connection;

    /** @var array<string,mixed> */
    protected array $bindings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = new Connection([
            'engine' => 'sqlite',
            'sqlite' => ['primary' => ':memory:'],
            'pooling' => ['enabled' => false],
        ]);

        (new CreateI18nTables())->up($this->connection->getSchemaBuilder());

        $connection = $this->connection;
        $bindings = &$this->bindings;
        $container = new class ($connection, $bindings) implements ContainerInterface {
            /** @param array<string,mixed> $bindings */
            public function __construct(private Connection $connection, private array &$bindings)
            {
            }

            public function get(string $id): mixed
            {
                if ($id === 'database' || $id === Connection::class || $id === ApplicationContext::class) {
                    return $id === ApplicationContext::class ? $this->bindings[$id] : $this->connection;
                }
                if (array_key_exists($id, $this->bindings)) {
                    return $this->bindings[$id];
                }

                throw new \RuntimeException("Unknown service: {$id}");
            }

            public function has(string $id): bool
            {
                return $id === 'database'
                    || $id === Connection::class
                    || $id === ApplicationContext::class
                    || array_key_exists($id, $this->bindings);
            }
        };

        $this->context = new ApplicationContext(basePath: sys_get_temp_dir(), environment: 'testing');
        $this->context->setContainer($container);
        $this->context->mergeConfigDefaults('i18n', require __DIR__ . '/../../config/i18n.php');
        $this->bindings[ApplicationContext::class] = $this->context;
    }

    protected function appContext(): ApplicationContext
    {
        return $this->context;
    }

    protected function connection(): Connection
    {
        return $this->connection;
    }

    protected function bind(string $id, mixed $service): void
    {
        $this->bindings[$id] = $service;
    }

    protected function setConfig(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $root = array_shift($parts);
        $nested = $value;
        foreach (array_reverse($parts) as $part) {
            $nested = [$part => $nested];
        }
        $this->context->mergeConfigDefaults((string) $root, $nested);
    }

    /** @param array<string,mixed> $overrides */
    protected function seedLocale(array $overrides = []): array
    {
        $row = array_merge([
            'uuid' => Utils::generateNanoID(12),
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'enabled' => true,
            'is_default' => true,
            'fallback_locale' => null,
            'direction' => 'ltr',
            'region' => 'US',
        ], $overrides);

        $this->connection->table('i18n_locales')->insert($row);

        return $row;
    }

    protected function seedTranslation(string $locale = 'en', string $key = 'hello', string $value = 'Hello'): void
    {
        $this->connection->table('i18n_translations')->insert([
            'uuid' => Utils::generateNanoID(12),
            'domain' => 'messages',
            'locale' => $locale,
            'key' => $key,
            'value' => $value,
            'status' => 'active',
        ]);
    }
}
