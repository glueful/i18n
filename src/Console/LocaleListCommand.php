<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:locales', description: 'List configured locales')]
final class LocaleListCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = array_map(
            static fn(array $locale): array => [
                (string) $locale['code'],
                (string) $locale['name'],
                (bool) $locale['enabled'] ? 'yes' : 'no',
                (bool) $locale['is_default'] ? 'yes' : 'no',
            ],
            $this->getService(LocaleManager::class)->all()
        );

        $this->table(['Code', 'Name', 'Enabled', 'Default'], $rows);

        return self::SUCCESS;
    }
}
