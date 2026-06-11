<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Tests\Unit\Console;

use Glueful\Extensions\I18n\Console\TranslationSyncCommand;
use Glueful\Extensions\I18n\Console\TranslationValidateCommand;
use Glueful\Extensions\I18n\Repositories\LocaleRepository;
use Glueful\Extensions\I18n\Repositories\TranslationRepository;
use Glueful\Extensions\I18n\Services\LocaleManager;
use Glueful\Extensions\I18n\Tests\Support\I18nTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class TranslationCommandsTest extends I18nTestCase
{
    public function testSyncCatalogsImportsPhpMessageFiles(): void
    {
        $dir = sys_get_temp_dir() . '/i18n-catalog-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/messages.en.php', "<?php\nreturn ['hello' => 'Hello'];\n");
        $repository = new TranslationRepository($this->connection());
        $this->bind(TranslationRepository::class, $repository);
        $command = new TranslationSyncCommand($this->appContext()->getContainer(), $this->appContext());
        $command->setName('i18n:sync-catalogs');

        $result = (new CommandTester($command))->execute(['directory' => $dir]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertSame('Hello', $repository->get('messages', 'en', 'hello'));
    }

    public function testValidateReportsMissingEnabledLocaleKeys(): void
    {
        $this->seedLocale(['code' => 'en', 'is_default' => true]);
        $this->seedLocale(['code' => 'fr', 'name' => 'French', 'is_default' => false]);
        $repository = new TranslationRepository($this->connection());
        $repository->put('messages', 'en', 'hello', 'Hello');
        $this->bind(TranslationRepository::class, $repository);
        $this->bind(LocaleManager::class, new LocaleManager(new LocaleRepository($this->connection()), $this->appContext()));
        $command = new TranslationValidateCommand($this->appContext()->getContainer(), $this->appContext());
        $command->setName('i18n:validate');

        self::assertSame(Command::FAILURE, (new CommandTester($command))->execute([]));
    }
}
