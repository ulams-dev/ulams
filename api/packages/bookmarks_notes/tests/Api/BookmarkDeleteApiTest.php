<?php

namespace Ulams\Bookmarks\Tests\Api;

use Ulams\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use Ulams\Bookmarks\Models\Bookmark;
use Ulams\Bookmarks\Tests\BookmarkTesting;
use Ulams\Bookmarks\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

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
