<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:sync-catalogs', description: 'Sync file catalogs into persistence')]
final class TranslationSyncCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Directory containing messages.{locale}.php files');
        $this->addArgument('domain', InputArgument::OPTIONAL, 'Translation domain', 'messages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory');
        $domain = (string) $input->getArgument('domain');
        if (!is_dir($directory)) {
            $this->error(sprintf('Catalog directory "%s" was not found.', $directory));
            return self::FAILURE;
        }

        $count = 0;
        $repository = $this->getService(TranslationRepository::class);
        foreach (glob(rtrim($directory, '/') . '/messages.*.php') ?: [] as $file) {
            $locale = $this->localeFromFile((string) $file);
            $messages = require $file;
            if (!is_array($messages)) {
                continue;
            }

            foreach ($messages as $key => $value) {
                if (is_scalar($key) && is_scalar($value)) {
                    $repository->put($domain, $locale, (string) $key, (string) $value);
                    $count++;
                }
            }
        }

        $this->success(sprintf('Synced %d translations.', $count));
        return self::SUCCESS;
    }

    private function localeFromFile(string $file): string
    {
        $name = basename($file, '.php');

        return substr($name, strlen('messages.'));
    }
}
