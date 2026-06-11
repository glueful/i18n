<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\I18n\Services\CatalogExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:export', description: 'Export translation catalogs')]
final class TranslationExportCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('locale', InputArgument::OPTIONAL, 'Optional locale');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $locale = $input->getArgument('locale');
        $output->writeln(
            $this->getService(CatalogExporter::class)->toJson(is_scalar($locale) ? (string) $locale : null)
        );

        return self::SUCCESS;
    }
}
