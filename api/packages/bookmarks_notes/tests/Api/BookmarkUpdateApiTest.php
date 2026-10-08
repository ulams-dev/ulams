<?php

namespace EscolaLms\Bookmarks\Tests\Api;

use EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Tests\BookmarkTesting;
use EscolaLms\Bookmarks\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class BookmarkUpdateApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    public function testUpdateBookmark(): void
    {
        $user = $this->makeStudent();
        $bookmark = Bookmark::factory()->create(['user_id' => $user->getKey()]);
        $payload = $this->bookmarkPayload();

        $this->actingAs($user, 'api')
            ->patchJson('api/bookmarks/' . $bookmark->getKey(), $payload);

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'id' => $bookmark->getKey(),
            'user_id' => $user->getKey(),
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
        ]);
    }

    public function testUpdateBookmarkNullableValue(): void
    {
        $user = $this->makeStudent();
        $bookmark = Bookmark::factory()->create(['user_id' => $user->getKey()]);
        $payload = $this->bookmarkPayload();
        $payload['value'] = null;

        $this->actingAs($user, 'api')
            ->patchJson('api/bookmarks/' . $bookmark->getKey(), $payload);

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'id' => $bookmark->getKey(),
            'user_id' => $user->getKey(),
            'value' => null,
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
        ]);
    }

    public function testUpdateBookmarkExceptUser(): void
    {
        $user = $this->makeStudent();
        $bookmark = Bookmark::factory()->create(['user_id' => $user->getKey()]);
        $payload = $this->bookmarkPayload(['user_id' => 123]);

        $this->actingAs($user, 'api')
            ->patchJson('api/bookmarks/' . $bookmark->getKey(), $payload);

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'id' => $bookmark->getKey(),
            'user_id' => $user->getKey(),
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
        ]);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testUpdateBookmarkInvalidData(string $key, array $data): void
    {
        $user = $this->makeStudent();
        $bookmark = Bookmark::factory()->create(['user_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->patchJson('/api/bookmarks/' . $bookmark->getKey(), $this->bookmarkPayload($data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$key]);
    }

    public function testUpdateBookmarkNotFound(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/bookmarks/123')
            ->assertNotFound();
    }

    public function testUpdateBookmarkNotOwner(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUpdateBookmarkForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->patchJson('api/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUpdateBookmarkUnauthorized(): void
    {
        $this->patchJson('api/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function invalidDataProvider(): array
    {
        return [
            ['field' => 'bookmarkable_id', 'data' => ['bookmarkable_id' => 'String']],
            ['field' => 'bookmarkable_id', 'data' => ['bookmarkable_id' => null]],
            ['field' => 'bookmarkable_type', 'data' => ['bookmarkable_type' => 123]],
            ['field' => 'bookmarkable_type', 'data' => ['bookmarkable_type' => null]],
        ];
    }
}
