<?php

namespace EscolaLms\Translations\Tests\TranslationLoader;

use EscolaLms\Auth\Models\User;
use EscolaLms\Translations\Tests\TestCase;
use EscolaLms\Translations\Translations\TranslationLoaderManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;

class TranslationManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function testTranslationManager(): void
    {
        Event::fake();
        Config::set('translation-loader.translation_manager', TranslationLoaderManager::class);

        $validationContent = <<<PHP
            <?php
            return [
                'unique' => 'Test unique translated',
            ];
        PHP;

        $filesystem = new Filesystem;
        $translationDirectory = resource_path('lang/pl');
        if (!$filesystem->exists($translationDirectory)) {
            $filesystem->makeDirectory($translationDirectory, 0755, true);
        }

        $filesystem->put($translationDirectory . '/validation.php', $validationContent);

        User::factory()->create([
            'email' => 'test@test.test'
        ]);

        App::setLocale('pl-PL');

        $validator = Validator::make(['email' => 'test@test.test'], [
            'email' => 'unique:users,email',
        ]);

        $this->assertEquals('Test unique translated', $validator->errors()->first('email'));

        $filesystem->delete($translationDirectory . '/validation.php');
    }
}
