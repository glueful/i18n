<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:sync-catalogs', description: 'Sync file catalogs into persistence')]
final class TranslationSyncCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->info('Catalog sync is available through ServiceProvider::loadMessageCatalogs().');

        return self::SUCCESS;
    }
}
