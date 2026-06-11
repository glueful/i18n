<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Console;

use Glueful\Console\BaseCommand;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'i18n:validate', description: 'Validate translation catalogs')]
final class TranslationValidateCommand extends BaseCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $translations = $this->getService(TranslationRepository::class)->list();
        $locales = array_map(
            static fn(array $locale): string => (string) $locale['code'],
            $this->getService(LocaleManager::class)->enabled()
        );
        $keys = [];
        $present = [];
        foreach ($translations as $row) {
            $identity = (string) $row['domain'] . ':' . (string) $row['key'];
            $keys[$identity] = [(string) $row['domain'], (string) $row['key']];
            $present[$identity][(string) $row['locale']] = true;
        }

        $missing = [];
        foreach ($keys as $identity => [$domain, $key]) {
            foreach ($locales as $locale) {
                if (!isset($present[$identity][$locale])) {
                    $missing[] = [$locale, $domain, $key];
                }
            }
        }

        if ($missing !== []) {
            $this->table(['Locale', 'Domain', 'Key'], $missing);
            $this->error(sprintf('Found %d missing translations.', count($missing)));
            return self::FAILURE;
        }

        $this->success('Translation catalogs validated.');
        return self::SUCCESS;
    }
}
