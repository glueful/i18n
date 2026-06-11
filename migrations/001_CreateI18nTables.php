<?php

declare(strict_types=1);

namespace Glueful\Extensions\I18n\Database\Migrations;

use Glueful\Database\Migrations\MigrationInterface;
use Glueful\Database\Schema\Interfaces\SchemaBuilderInterface;

final class CreateI18nTables implements MigrationInterface
{
    public function up(SchemaBuilderInterface $schema): void
    {
        if (!$schema->hasTable('i18n_locales')) {
            $schema->createTable('i18n_locales', function ($table): void {
                $table->bigInteger('id')->primary()->autoIncrement();
                $table->string('uuid', 12);
                $table->string('code', 16);
                $table->string('name', 255);
                $table->string('native_name', 255)->nullable();
                $table->boolean('enabled')->default(true);
                $table->boolean('is_default')->default(false);
                $table->string('fallback_locale', 16)->nullable();
                $table->string('direction', 3)->default('ltr');
                $table->string('region', 16)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('uuid');
                $table->unique('code');
                $table->index('enabled');
                $table->index('is_default');
            });
        }

        if (!$schema->hasTable('i18n_translations')) {
            $schema->createTable('i18n_translations', function ($table): void {
                $table->bigInteger('id')->primary()->autoIncrement();
                $table->string('uuid', 12);
                $table->string('domain', 120);
                $table->string('locale', 16);
                $table->string('key', 255);
                $table->text('value');
                $table->string('status', 20)->default('active');
                $table->string('source', 40)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->unique('uuid');
                $table->unique(['domain', 'locale', 'key'], 'uniq_i18n_translation_key');
                $table->index(['locale', 'domain'], 'idx_i18n_translation_bundle');
                $table->index('status');
            });
        }

        if (!$schema->hasTable('i18n_missing_translations')) {
            $schema->createTable('i18n_missing_translations', function ($table): void {
                $table->bigInteger('id')->primary()->autoIncrement();
                $table->string('uuid', 12);
                $table->string('domain', 120);
                $table->string('locale', 16);
                $table->string('key', 255);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->integer('hits')->default(1);
                $table->unique('uuid');
                $table->unique(['domain', 'locale', 'key'], 'uniq_i18n_missing_key');
                $table->index(['locale', 'domain'], 'idx_i18n_missing_bundle');
            });
        }
    }

    public function down(SchemaBuilderInterface $schema): void
    {
        $schema->dropTableIfExists('i18n_missing_translations');
        $schema->dropTableIfExists('i18n_translations');
        $schema->dropTableIfExists('i18n_locales');
    }

    public function getDescription(): string
    {
        return 'Create i18n locale, translation, and missing translation tables.';
    }
}
