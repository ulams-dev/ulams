<?php

namespace EscolaLms\Bookmarks\Tests\Api;

use EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Tests\BookmarkTesting;
use EscolaLms\Bookmarks\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class BookmarkDeleteApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    public function testDeleteBookmark(): void
    {
        $user = $this->makeStudent();
        $bookmark = Bookmark::factory()->create(['user_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->deleteJson('/api/bookmarks/' . $bookmark->getKey())
            ->assertOk();
    }

    public function testDeleteBookmarkNotFound(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('/api/bookmarks/123')
            ->assertNotFound();
    }

    public function testDeleteBookmarkNotOwner(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->deleteJson('/api/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testDeleteBookmarkForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->deleteJson('/api/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testDeleteBookmarkUnauthorized(): void
    {
        $this->deleteJson('/api/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertUnauthorized();
    }
}
