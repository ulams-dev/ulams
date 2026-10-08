<?php

namespace EscolaLms\Templates\Tests\Api;

use EscolaLms\Settings\Models\Setting;
use EscolaLms\Templates\Enums\TemplateSectionTypeEnum;
use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Tests\Mock\TestChannel;
use EscolaLms\Templates\Tests\Mock\TestEventWithGetters;
use EscolaLms\Templates\Tests\Mock\TestVariables;
use EscolaLms\Templates\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TemplatesPreviewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FacadesTemplate::register(TestEventWithGetters::class, TestChannel::class, TestVariables::class);
    }

    public function testAdminCanListRegisteredEvents(): void
    {
        $this->authenticateAsAdmin();
        $response = $this->actingAs($this->user, 'api')->getJson(
            '/api/admin/templates/events'
        );

        $response->assertOk();

        $json = $response->json();
        $variables = $json['data'];

        $this->assertTrue(isset($variables[TestEventWithGetters::class]));
        $this->assertTrue(isset($variables[TestEventWithGetters::class][TestChannel::class]));
        $this->assertEquals(TestVariables::class, $variables[TestEventWithGetters::class][TestChannel::class]);
    }

    public function testAdminCanListVariables(): void
    {
        Setting::query()->delete();
        Setting::create([
            'key' => 'setting',
            'group' => 'test',
            'value' => 'Test value',
            'public' => true,
            'enumerable' => true,
            'type' => 'text'
        ]);
        Setting::create([
            'key' => 'global',
            'group' => 'test',
            'value' => 'Global value',
            'public' => true,
            'enumerable' => true,
            'type' => 'file'
        ]);

        $this->authenticateAsAdmin();
        $response = $this->actingAs($this->user, 'api')->getJson(
            '/api/admin/templates/variables'
        );

        $response->assertOk();

        $json = $response->json();
        $variables = $json['data'];

        $this->assertEquals([
            "class" => "EscolaLms\Templates\Tests\Mock\TestVariables",
            'assignable_class' => null,
            "variables" =>  [
                0 => "@GlobalSettingsSettingText",
                1 => "@GlobalSettingsGlobalFile",
                2 => "@VarUserEmail",
                3 => "@VarFriendEmail",
            ],
            "required_variables" =>  [
                0 => "@VarUserEmail",
                1 => "@VarFriendEmail"
            ],
            "sections" => [
                "title" => [
                    "type" => TemplateSectionTypeEnum::SECTION_TEXT,
                    "required" => true,
                    "readonly" => false,
                    "default_content" => "New friend request",
                    "required_variables" => []
                ],
                "content" =>  [
                    "type" => TemplateSectionTypeEnum::SECTION_HTML,
                    "required" => true,
                    "readonly" => false,
                    "default_content" => '<h1>Hello @VarUserEmail!</h1><br/>' . PHP_EOL . '<p>You have new friend request from @VarFriendEmail</p>',
                    "required_variables" => [
                        0 => "@VarUserEmail",
                        1 => "@VarFriendEmail"
                    ]
                ],
                "url" => [
                    "type" => TemplateSectionTypeEnum::SECTION_URL,
                    "required" => false,
                    "readonly" => false,
                    "default_content" => "",
                    "required_variables" => []
                ]
            ]
        ], $variables[TestEventWithGetters::class][TestChannel::class]);
        $this->assertEquals([
            "@GlobalSettingsSettingText" => "text",
            "@GlobalSettingsGlobalFile" => "file"
        ], $variables['user_settings']);
    }

    public function testAdminCanPreviewTemplateData(): void
    {
        FacadesTemplate::fake();

        FacadesTemplate::createDefaultTemplatesForChannel(TestChannel::class);
        $template = Template::whereDefault(true)->whereChannel(TestChannel::class)->whereEvent(TestEventWithGetters::class)->first();

        $this->authenticateAsAdmin();

        $response = $this->actingAs($this->user, 'api')->getJson(
            '/api/admin/templates/' . $template->getKey() . '/preview'
        );

        $response->assertOk();
        $response->assertJsonFragment([
            'sent' => true,
            'recipient' => $this->user->toArray(),
            'data' => [
                'title' => TestVariables::defaultSectionsContent()['title'],
                'content' => strtr(TestVariables::defaultSectionsContent()['content'], TestVariables::mockedVariables($this->user))
            ]
        ]);
    }
}
