<?php

namespace EscolaLms\TemplatesEmail\Tests\Feature;

use EscolaLms\Settings\Models\Setting;
use EscolaLms\Templates\Core\SettingsVariables;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Models\TemplateSection;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Core\EmailVariables;
use EscolaLms\TemplatesEmail\Tests\Mocks\TestEvent;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class SettingObserverTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\EscolaLms\Settings\EscolaLmsSettingsServiceProvider::class)) {
            $this->markTestSkipped('Settings package not installed');
        }
    }

    public function testShouldUpdateTemplateWhenGlobalVariableIsChanged(): void
    {
        $template = Template::factory()->create([
            'channel' => EmailChannel::class,
            'event' => TestEvent::class,
        ]);

        TemplateSection::factory(['key' => 'title', 'template_id' => $template->getKey()])->create();
        TemplateSection::factory([
            'key' => 'content',
            'template_id' => $template->getKey(),
            'content' => EmailVariables::wrapWithMjml('@GlobalSettingsHeaderText'),
        ])
            ->create();

        SettingsVariables::clearSettings();
        Setting::firstOrCreate([
            'group' => 'mail',
            'key' => 'header',
            'value' => 'Header Test',
            'public' => true,
            'enumerable' => true,
            'type' => 'text',
        ]);

        $template->refresh();
        $contentHtmlSection = $template->sections->where('key', 'contentHtml')->first();
        $this->assertStringContainsString('Header Test', $contentHtmlSection->content);
    }
}
