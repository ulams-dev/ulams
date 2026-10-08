<?php

namespace EscolaLms\Templates\Tests\Api;

use EscolaLms\Templates\Facades\Template as FacadesTemplate;
use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Models\TemplateSection;
use EscolaLms\Templates\Tests\Mock\TestChannel;
use EscolaLms\Templates\Tests\Mock\TestEventWithGetters;
use EscolaLms\Templates\Tests\Mock\TestVariables;
use EscolaLms\Templates\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TemplatesUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FacadesTemplate::register(TestEventWithGetters::class, TestChannel::class, TestVariables::class);
    }

    private function uri(int $id): string
    {
        return sprintf('/api/admin/templates/%s', $id);
    }

    public function testAdminCanUpdateExistingTemplate(): void
    {
        $this->authenticateAsAdmin();

        $template = Template::factory()->createOne([
            'name' => 'old name',
            'event' => TestEventWithGetters::class,
            'channel' => TestChannel::class,
        ]);

        TemplateSection::factory(['key' => 'title', 'template_id' => $template->getKey()])->create();
        TemplateSection::factory(['key' => 'content', 'template_id' => $template->getKey(), 'content' => TestVariables::VAR_USER_EMAIL . '_' . TestVariables::VAR_FRIEND_EMAIL])->create();


        $response = $this->actingAs($this->user, 'api')->patchJson(
            $this->uri($template->id),
            [
                'name' => 'new name',
            ]
        );
        $response->assertOk();

        $template->refresh();
        $this->assertEquals('new name', $template->name);
        $this->assertTrue($template->is_valid);
    }

    public function testAdminCanUpdateExistingTemplateWithMissingName(): void
    {
        $this->authenticateAsAdmin();

        $template = Template::factory()->createOne([
            'event' => TestEventWithGetters::class,
            'channel' => TestChannel::class,
        ]);
        TemplateSection::factory(['key' => 'title', 'template_id' => $template->getKey()])->create();
        $templateSection = TemplateSection::factory(['key' => 'content', 'template_id' => $template->getKey(), 'content' => TestVariables::VAR_USER_EMAIL . '_' . TestVariables::VAR_FRIEND_EMAIL])->create();

        $newSections = [
            TemplateSection::factory(['key' => 'title', 'content' => 'new content'])->makeOne()->toArray(),
            $templateSection->toArray(),
        ];

        $response = $this->actingAs($this->user, 'api')->patchJson(
            $this->uri($template->id),
            [
                'sections' => $newSections,
            ]
        );
        $response->assertStatus(200);

        $template->refresh();
        $this->assertTrue($template->sections()->where('key', 'title')->where('content', 'new content')->exists());
        $this->assertTrue($template->is_valid);
    }

    public function testAdminCannotUpdateMissingTemplate(): void
    {
        $this->authenticateAsAdmin();

        $template = Template::factory()->makeOne();

        $response = $this->actingAs($this->user, 'api')->patchJson(
            $this->uri(99999999),
            [
                'name' => $template->name,
                'content' => $template->content,
            ]
        );

        $response->assertStatus(404);
        $this->assertEquals(0, $template->newQuery()->where('id', $template->id)->count());
    }

    public function testGuestCannotUpdateExistingTemplate(): void
    {
        $template = Template::factory()->createOne();
        $templateNew = Template::factory()->makeOne();

        $oldName = $template->name;
        $oldContent = $template->content;

        $response = $this->patchJson(
            $this->uri($template->id),
            [
                'name' => $templateNew->name,
                'content' => $templateNew->content,
            ]
        );
        $response->assertUnauthorized();
        $template->refresh();

        $this->assertEquals($oldName, $template->name);
        $this->assertEquals($oldContent, $template->content);
    }
}
