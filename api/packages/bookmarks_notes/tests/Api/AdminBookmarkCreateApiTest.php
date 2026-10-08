<?php

namespace EscolaLms\Bookmarks\Tests\Api;

use EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Tests\BookmarkTesting;
use EscolaLms\Bookmarks\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class AdminBookmarkCreateApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    public function testCreateBookmark(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $payload = $this->bookmarkPayload(['user_id' => $student->getKey()]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/bookmarks', $payload)
            ->assertCreated();

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $student->getKey(),
        ]);
    }

    public function testCreateBookmarkNullableValue(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $payload = $this->bookmarkPayload(['value' => null, 'user_id' => $student->getKey()]);

        $this->actingAs($admin, 'api')
            ->postJson('/api/admin/bookmarks', $payload)
            ->assertCreated();

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'value' => null,
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $student->getKey(),
        ]);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testCreateBookmarkInvalidData(string $key, array $data): void
    {
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/bookmarks', $this->bookmarkPayload($data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$key]);
    }

    public function testCreateBookmarkForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('/api/admin/bookmarks', $this->bookmarkPayload())
            ->assertForbidden();

        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('/api/admin/bookmarks', $this->bookmarkPayload())
            ->assertForbidden();
    }

    public function testCreateBookmarkUnauthorized(): void
    {
        $this->postJson('/api/admin/bookmarks', $this->bookmarkPayload())
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
