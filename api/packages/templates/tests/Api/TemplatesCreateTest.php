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
use Illuminate\Testing\TestResponse;

class TemplatesCreateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FacadesTemplate::register(TestEventWithGetters::class, TestChannel::class, TestVariables::class);
    }

    private function convertToAlternativeFormat(string $varname): string
    {
        return '$' . "{" . str_replace("@", "", $varname) . "}";
    }

    private function uri(string $suffix): string
    {
        return sprintf('/api/admin/templates%s', $suffix);
    }

    public function testAdminCanCreateTemplate(): void
    {
        $this->authenticateAsAdmin();
        $template = Template::factory()->makeOne();
        $template->event = TestEventWithGetters::class;
        $template->channel = TestChannel::class;

        $sections = [
            TemplateSection::factory(['key' => 'title'])->makeOne()->toArray(),
            TemplateSection::factory(['key' => 'content', 'content' => TestVariables::VAR_USER_EMAIL . '_' . TestVariables::VAR_FRIEND_EMAIL])->makeOne()->toArray(),
        ];

        $data = array_merge($template->toArray(), ['sections' => $sections]);

        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/templates',
            $data
        );

        $response->assertStatus(201);

        $response2 = $this->actingAs($this->user, 'api')->getJson(
            '/api/admin/templates/' . $template->id,
        );

        $response2->assertOk();
    }



    public function testAdminCanCreateTemplateAlternativeFormat(): void
    {
        $this->authenticateAsAdmin();
        $template = Template::factory()->makeOne();
        $template->event = TestEventWithGetters::class;
        $template->channel = TestChannel::class;

        $sections = [
            TemplateSection::factory(['key' => 'title'])->makeOne()->toArray(),
            TemplateSection::factory(['key' => 'content', 'content' => $this->convertToAlternativeFormat(TestVariables::VAR_USER_EMAIL) . '_'])->makeOne()->toArray(),
        ];

        $data = array_merge($template->toArray(), ['sections' => $sections]);

        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/templates',
            $data
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('sections');
        $response->assertJsonValidationErrors(['sections' => 'Required variables in section: content [@VarFriendEmail]']);
    }

    public function testAdminCannotCreateTemplateWithoutTitle(): void
    {
        $this->authenticateAsAdmin();

        $template = Template::factory()->makeOne();
        $template->event = TestEventWithGetters::class;
        $template->channel = TestChannel::class;

        $sections = [
            TemplateSection::factory(['key' => 'title'])->makeOne()->toArray(),
            TemplateSection::factory(['key' => 'content', 'content' => TestVariables::VAR_USER_EMAIL . '_' . TestVariables::VAR_FRIEND_EMAIL])->makeOne()->toArray(),
        ];

        $data = array_merge(collect($template->getAttributes())->except('id', 'name', 'type')->toArray(), ['sections' => $sections]);

        /** @var TestResponse $response */
        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/templates',
            $data
        );
        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('name');

        $response = $this->getJson(
            '/api/templates/' . $template->id,
        );

        $response->assertNotFound();
    }

    public function testAdminCannotCreateTemplateWithMissingSection(): void
    {
        $this->authenticateAsAdmin();
        $template = Template::factory()->makeOne();
        $template->event = TestEventWithGetters::class;
        $template->channel = TestChannel::class;

        $sections = [
            TemplateSection::factory(['key' => 'title'])->makeOne()->toArray(),
        ];

        $data = array_merge($template->toArray(), ['sections' => $sections]);

        /** @var TestResponse $response */
        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/templates',
            $data
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('sections');
        $response->assertJsonValidationErrors(['sections' => 'Required section: content']);
    }

    public function testAdminCannotCreateTemplateWithMissingVariablesInSection(): void
    {
        $this->authenticateAsAdmin();
        $template = Template::factory()->makeOne();
        $template->event = TestEventWithGetters::class;
        $template->channel = TestChannel::class;

        $sections = [
            TemplateSection::factory(['key' => 'title'])->makeOne()->toArray(),
            TemplateSection::factory(['key' => 'content'])->makeOne()->toArray(),
        ];

        $data = array_merge($template->toArray(), ['sections' => $sections]);

        /** @var TestResponse $response */
        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/templates',
            $data
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('sections');
        $response->assertJsonValidationErrors(['sections' => 'Required variables in section: content [@VarUserEmail, @VarFriendEmail]']);
    }

    public function testAdminCannotCreateTemplateWithMissingVariablesInSectionAlterativeFormatting(): void
    {
        $this->authenticateAsAdmin();
        $template = Template::factory()->makeOne();
        $template->event = TestEventWithGetters::class;
        $template->channel = TestChannel::class;

        $sections = [
            TemplateSection::factory(['key' => 'title'])->makeOne()->toArray(),
            TemplateSection::factory(['key' => 'content'])->makeOne()->toArray(),
        ];

        $data = array_merge($template->toArray(), ['sections' => $sections]);

        /** @var TestResponse $response */
        $response = $this->actingAs($this->user, 'api')->postJson(
            '/api/admin/templates',
            $data
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrorFor('sections');
        $response->assertJsonValidationErrors(['sections' => 'Required variables in section: content [@VarUserEmail, @VarFriendEmail]']);
    }

    public function testGuestCannotCreateTemplate(): void
    {
        $template = Template::factory()->makeOne();
        $response = $this->postJson(
            '/api/admin/templates',
            collect($template->getAttributes())->except('id', 'name')->toArray()
        );
        $response->assertUnauthorized();
    }
}
