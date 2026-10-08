<?php

namespace EscolaLms\Templates\Tests\Api;

use EscolaLms\Templates\Models\Template;
use EscolaLms\Templates\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TemplatesDeleteTest extends TestCase
{
    use DatabaseTransactions;

    private function uri(int $id): string
    {
        return sprintf('/api/admin/templates/%s', $id);
    }

    public function testAdminCanDeleteExistingTemplate(): void
    {
        $this->authenticateAsAdmin();

        $template = Template::factory()->createOne();
        $response = $this->actingAs($this->user, 'api')->delete($this->uri($template->id));
        $response->assertOk();
        $this->assertEquals(0, Template::factory()->make()->newQuery()->where('id', $template->id)->count());
    }

    public function testAdminCannotDeleteMissingTemplate(): void
    {
        $this->authenticateAsAdmin();

        $template = Template::factory()->makeOne();
        $template->id = 999999;

        $response = $this->actingAs($this->user, 'api')->delete($this->uri($template->id));

        $response->assertStatus(404);
    }

    public function testGuestCannotDeleteExistingTemplate(): void
    {
        $template = Template::factory()->createOne();
        $response = $this->json('delete', $this->uri($template->id));
        $response->assertUnauthorized();
    }
}
