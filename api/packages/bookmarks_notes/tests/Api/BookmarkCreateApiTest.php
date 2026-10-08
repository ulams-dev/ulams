<?php

namespace EscolaLms\Bookmarks\Tests\Api;

use EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Tests\BookmarkTesting;
use EscolaLms\Bookmarks\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class BookmarkCreateApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    public function testCreateBookmark(): void
    {
        $user = $this->makeStudent();
        $payload = $this->bookmarkPayload();

        $this->actingAs($user, 'api')
            ->postJson('/api/bookmarks', $payload)
            ->assertCreated();

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $user->getKey(),
        ]);
    }

    public function testCreateBookmarkNullableValue(): void
    {
        $user = $this->makeStudent();
        $payload = $this->bookmarkPayload();
        $payload['value'] = null;

        $this->actingAs($user, 'api')
            ->postJson('/api/bookmarks', $payload)
            ->assertCreated();

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'value' => null,
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $user->getKey(),
        ]);
    }

    public function testCreateBookmarkExceptUserId(): void
    {
        $user = $this->makeStudent();
        $payload = $this->bookmarkPayload();
        $payload['user_id'] = 123;

        $this->actingAs($user, 'api')
            ->postJson('/api/bookmarks', $payload)
            ->assertCreated();

        $this->assertDatabaseHas($this->getTable(Bookmark::class), [
            'value' => $payload['value'],
            'bookmarkable_id' => $payload['bookmarkable_id'],
            'bookmarkable_type' => $payload['bookmarkable_type'],
            'user_id' => $user->getKey(),
        ]);
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testCreateBookmarkInvalidData(string $key, array $data): void
    {
        $user = $this->makeStudent();

        $this->actingAs($user, 'api')
            ->postJson('/api/bookmarks', $this->bookmarkPayload($data))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$key]);
    }

    public function testCreateBookmarkForbidden(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('/api/bookmarks', $this->bookmarkPayload())
            ->assertForbidden();
    }

    public function testCreateBookmarkUnauthorized(): void
    {
        $this->postJson('/api/bookmarks', $this->bookmarkPayload())
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
