<?php

namespace EscolaLms\Pages\Tests\Api;

use EscolaLms\Core\Models\User;
use EscolaLms\Pages\Models\Page;
use EscolaLms\Pages\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PagesListTest extends TestCase
{
    use DatabaseTransactions;

    private string $uri = '/api/pages';

    public function testAdminCanListEmpty(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/pages');
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
            'meta',
            'message'
        ]);
        $response->assertJsonCount(0, 'data');
    }

    public function testAdminCanList(): void
    {
        $this->authenticateAsAdmin();

        $pages = Page::factory()
            ->count(10)
            ->create();

        $pagesArr = $pages->map(function (Page $p) {
            return $p->toArray();
        })->toArray();

        $response = $this->actingAs($this->user, 'api')->getJson('/api/admin/pages');
        $response->assertOk();
        $response->assertJsonFragment(
            $pagesArr[0],
        );
    }

    public function testListWithFiltersAndSorts(): void
    {
        $this->authenticateAsAdmin();
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $pageOne = Page::factory()->create([
            'title' => 'One Page',
            'slug' => 'one-page',
            'author_id' => $userOne->id,
            'active' => true,
            'created_at' => now()->subDay()
        ]);

        $pageTwo = Page::factory()->create([
            'title' => 'Two Page',
            'slug' => 'two-page',
            'author_id' => $userTwo->id,
            'active' => true,
            'created_at' => now()
        ]);

        $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'title' => 'One'
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $pageOne->id,
            ]);

        $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'slug' => 'one'
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $pageOne->id,
            ]);

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'title',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageTwo->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'created_at',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageOne->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'created_at',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageTwo->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'title',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageOne->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'author_id',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageTwo->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'author_id',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageOne->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'slug',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageTwo->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'slug',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageOne->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'id',
                'order' => 'ASC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageTwo->getKey());

        $response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', '/api/admin/pages', [
                'order_by' => 'id',
                'order' => 'DESC'
            ]);

        $this->assertTrue($response->json('data.0.id') === $pageTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $pageOne->getKey());
    }

    public function testAnonymousCanListEmpty(): void
    {
        $this->authenticateAsAdmin();

        $response = $this->getJson('/api/pages');
        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
            'meta',
            'message'
        ]);
        $response->assertJsonCount(0, 'data');
    }

    public function testAnonymousCanList(): void
    {
        $this->authenticateAsAdmin();

        $pages = Page::factory()
            ->count(10)
            ->create(['active' => true]);

        $pagesArr = $pages->map(function (Page $p) {
            return $p->toArray();
        })->values()->toArray();


        $response = $this->getJson('/api/pages');
        $response->assertOk();
        $response->assertJsonFragment(
            $pagesArr[0]
        );
    }
}
