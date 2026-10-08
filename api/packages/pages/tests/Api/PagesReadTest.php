<?php

namespace EscolaLms\Pages\Tests\Api;

use EscolaLms\Pages\Models\Page;
use EscolaLms\Pages\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PagesReadTest extends TestCase
{
    use DatabaseTransactions;

    private function uri(string $slug): string
    {
        return sprintf('/api/pages/%s', $slug);
    }

    public function testCanReadExisting(): void
    {
        $page = Page::factory()->createOne();

        $response = $this->getJson($this->uri($page->slug));
        $response->assertOk();
        $response->assertJsonFragment(collect($page->getAttributes())->except('id', 'slug')->toArray());
    }

    public function testCannotFindMissing(): void
    {
        $response = $this->getJson($this->uri('non-existing-page'));
        $response->assertNotFound();
    }

    public function testAdminCanReadExistingById(): void
    {
        $this->authenticateAsAdmin();

        $page = Page::factory()->createOne();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/pages/' . $page->getKey());
        $response->assertOk();
        $response->assertJsonFragment(collect($page->getAttributes())->except('id', 'slug')->toArray());
    }
}
