<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\I18n\Services\CatalogImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:import', description: 'Import a translation catalog')]
final class TranslationImportCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'JSON or PHP catalog file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->getService(CatalogImporter::class)->importFile((string) $input->getArgument('file'));
        $this->success(sprintf('Imported %d translations.', $count));

        return self::SUCCESS;
    }
}
