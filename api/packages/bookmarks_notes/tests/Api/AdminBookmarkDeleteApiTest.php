<?php

namespace Ulams\Bookmarks\Tests\Api;

use Ulams\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use Ulams\Bookmarks\Models\Bookmark;
use Ulams\Bookmarks\Tests\BookmarkTesting;
use Ulams\Bookmarks\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

class AdminBookmarkDeleteApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    public function testDeleteBookmark(): void
    {
        $admin = $this->makeAdmin();
        $bookmark = Bookmark::factory()->create();

        $this->actingAs($admin, 'api')
            ->deleteJson('/api/admin/bookmarks/' . $bookmark->getKey())
            ->assertOk();
    }

    public function testDeleteBookmarkNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('/api/admin/bookmarks/123')
            ->assertNotFound();
    }

    public function testDeleteBookmarkNotOwner(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->deleteJson('/api/admin/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertOk();
    }

    public function testDeleteBookmarkForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->deleteJson('/api/admin/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testDeleteBookmarkUnauthorized(): void
    {
        $this->deleteJson('/api/admin/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertUnauthorized();
    }
}
