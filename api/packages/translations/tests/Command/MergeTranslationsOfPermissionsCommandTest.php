<?php

namespace Ulams\Translations\Tests\Command;

use Ulams\Translations\Console\Command\MergeTranslationsOfPermissionsCommand;
use Ulams\Translations\Enum\TranslationsPermissionsEnum;
use Ulams\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class MergeTranslationsOfPermissionsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function testCreateFileWithTranslations(): void
    {
        $this->artisan(MergeTranslationsOfPermissionsCommand::class);

        $languages = Config::get('ulams_translations.languages');
        $keys = TranslationsPermissionsEnum::getValues();

        foreach ($languages as $langKey) {
            $file = resource_path("lang/$langKey/permissions.php");
            $this->assertFileExists($file);
            $content = include $file;
            $this->assertEqualsCanonicalizing(array_intersect($keys, array_keys($content)), $keys);
        }
    }
}