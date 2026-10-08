<?php

namespace EscolaLms\Translations\Tests\TranslationLoader;

use EscolaLms\Translations\Models\LanguageLine;
use EscolaLms\Translations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\App;

class TranslationLoaderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languageLine = LanguageLine::create([
            'group' => 'validation',
            'key' => 'attributes.last_name',
            'text' => [
                'en' => 'last name',
                'pl' => 'nazwisko',
            ],
        ]);
    }

    public function testTranslationLoaderByKey(): void
    {
        App::setLocale('en');
        $this->assertEquals('last name', __('validation.attributes.last_name'));
        $this->assertEquals('last name', trans('validation.attributes.last_name'));

        App::setLocale('pl');
        $this->assertEquals('nazwisko', __('validation.attributes.last_name'));
        $this->assertEquals('nazwisko', trans('validation.attributes.last_name'));
    }
}
