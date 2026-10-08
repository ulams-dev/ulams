<?php

namespace EscolaLms\Bookmarks\Tests\Api;

use EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Tests\BookmarkTesting;
use EscolaLms\Bookmarks\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class AdminBookmarkUpdateApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    public function testUpdateBookmark(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $bookmark = Bookmark::factory()->create();
        $payload = $this->bookmarkPayload(['user_id' =>  $student->getKey()]);

        $this->actingAs($admin, 'api')
            ->patchJson('api/admin/bookmarks/' . $bookmark->getKey(), $payload);

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'id' => $bookmark->getKey(),
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $student->getKey(),
        ]);
    }

    public function testUpdateBookmarkNullableValue(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $bookmark = Bookmark::factory()->create();
        $payload = $this->bookmarkPayload(['value' => null, 'user_id' => $student->getKey()]);

        $this->actingAs($admin, 'api')
            ->patchJson('api/admin/bookmarks/' . $bookmark->getKey(), $payload);

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'id' => $bookmark->getKey(),
            'value' => null,
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $student->getKey(),
        ]);
    }

    public function testUpdateBookmarkUpdateUser(): void
    {
        $admin = $this->makeAdmin();
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();
        $bookmark = Bookmark::factory()->create(['user_id' => $student1->getKey()]);
        $payload = $this->bookmarkPayload(['user_id' => $student2->getKey()]);

        $this->actingAs($admin, 'api')
            ->patchJson('api/admin/bookmarks/' . $bookmark->getKey(), $payload);

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'id' => $bookmark->getKey(),
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $student2->getKey(),
        ]);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testUpdateBookmarkInvalidData(string $key, array $data): void
    {
        $admin = $this->makeAdmin();
        $bookmark = Bookmark::factory()->create();

        $this->actingAs($admin, 'api')
            ->patchJson('/api/admin/bookmarks/' . $bookmark->getKey(), $this->bookmarkPayload($data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$key]);
    }

    public function testUpdateBookmarkNotFound(): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->patchJson('api/admin/bookmarks/123')
            ->assertNotFound();
    }

    public function testUpdateBookmarkForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->patchJson('api/admin/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();

        $this->actingAs($this->makeStudent(), 'api')
            ->patchJson('api/admin/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertForbidden();
    }

    public function testUpdateBookmarkUnauthorized(): void
    {
        $this->patchJson('api/admin/bookmarks/' . Bookmark::factory()->create()->getKey())
            ->assertUnauthorized();
    }

    public function invalidDataProvider(): array
    {
        return [
            ['field' => 'bookmarkable_id', 'data' => ['bookmarkable_id' => 'String']],
            ['field' => 'bookmarkable_id', 'data' => ['bookmarkable_id' => null]],
            ['field' => 'bookmarkable_type', 'data' => ['bookmarkable_type' => 123]],
            ['field' => 'bookmarkable_type', 'data' => ['bookmarkable_type' => null]],
            ['field' => 'user_id', 'data' => ['user_id' => null]],
            ['field' => 'user_id', 'data' => ['user_id' => 'String']],
            ['field' => 'user_id', 'data' => ['user_id' => 123]],
        ];
    }
}
