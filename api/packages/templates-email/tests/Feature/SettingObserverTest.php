<?php

namespace Ulams\TemplatesEmail\Tests\Feature;

use Ulams\Settings\Models\Setting;
use Ulams\Templates\Core\SettingsVariables;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\EmailVariables;
use Ulams\TemplatesEmail\Tests\Mocks\TestEvent;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;

class SettingObserverTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Ulams\Settings\UlamsSettingsServiceProvider::class)) {
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
