<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\I18n\Repositories\MissingTranslationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:missing', description: 'List missing translations')]
final class MissingListCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = array_map(
            static fn(array $row): array => [
                (string) $row['locale'],
                (string) $row['domain'],
                (string) $row['key'],
                (string) $row['hits'],
            ],
            $this->getService(MissingTranslationRepository::class)->list()
        );

        $this->table(['Locale', 'Domain', 'Key', 'Hits'], $rows);

        return self::SUCCESS;
    }
}
