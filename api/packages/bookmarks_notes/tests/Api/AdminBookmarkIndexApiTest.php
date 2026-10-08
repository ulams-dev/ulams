<?php

namespace EscolaLms\Bookmarks\Tests\Api;

use EscolaLms\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use EscolaLms\Bookmarks\Models\Bookmark;
use EscolaLms\Bookmarks\Tests\BookmarkTesting;
use EscolaLms\Bookmarks\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Support\Arr;

class AdminBookmarkIndexApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testIndexBookmarkFilters(array $filters, callable $generator, int $filterCount): void
    {
        $user = $this->makeAdmin();
        $generator()->each(fn($factory) => $factory->create());

        $this->actingAs($user, 'api')
            ->getJson($this->prepareUri('api/admin/bookmarks', $filters))
            ->assertOk()
            ->assertJsonCount($filterCount, 'data')
            ->assertJsonStructure(['data' => [[
                'id',
                'value',
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                ],
                'bookmarkable',
                'bookmarkable_id',
                'bookmarkable_type',
            ]]]);
    }

    public function testIndexBookmarkFilterByUserId(): void
    {
        $user = $this->makeAdmin();
        $student = $this->makeStudent();

        Bookmark::factory()->count(5)->create();
        Bookmark::factory()->count(3)->create(['user_id' => $student]);

        $this->actingAs($user, 'api')
            ->getJson($this->prepareUri('api/admin/bookmarks', ['user_id' => $student->getKey()]))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [[
                'id',
                'value',
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                ],
                'bookmarkable',
                'bookmarkable_id',
                'bookmarkable_type',
            ]]]);
    }

    public function testIndexBookmarkPagination(): void
    {
        $user = $this->makeAdmin();
        Bookmark::factory()->count(35)->create();

        $this->actingAs($user, 'api')
            ->getJson('api/admin/bookmarks?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35
                ]
            ]);

        $this->actingAs($user, 'api')
            ->getJson('api/admin/bookmarks?per_page=10&page=4')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 35
                ]
            ]);
    }

    /**
     * @dataProvider orderDataProvider
     */
    public function testIndexBookmarkOrder(array $order, callable $generator, callable $assertion): void
    {
        $user = $this->makeAdmin();
        $generator()->each(fn($factory) => $factory->create());

        $response = $this->actingAs($user, 'api')
            ->getJson($this->prepareUri('api/admin/bookmarks', $order))
            ->assertOk();

        $assertion($response);
    }

    public function filterDataProvider(): array
    {
        return [
            [
                'filter' => [
                    'has_value' => 0
                ],
                'data' => (function () {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['value' => null]));
                    $items->push(Bookmark::factory()->state(['value' => null]));
                    $items->push(Bookmark::factory()->state(['value' => null]));

                    return $items;
                }),
                'filterCount' => 3
            ],
            [
                'filter' => [
                    'has_value' => 1
                ],
                'data' => (function () {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['value' => null]));

                    return $items;
                }),
                'filterCount' => 2
            ],
            [
                'filter' => [
                ],
                'data' => (function () {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory());

                    return $items;
                }),
                'filterCount' => 3
            ],
            [
                'filter' => [
                    'bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic',
                ],
                'data' => (function () {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Course']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Course']));

                    return $items;
                }),
                'filterCount' => 3
            ],
            [
                'filter' => [
                    'bookmarkable_id' => 123,
                    'bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic',
                ],
                'data' => (function () {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic', 'bookmarkable_id' => 123]));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Course']));

                    return $items;
                }),
                'filterCount' => 1
            ],
            [
                'filter' => [
                    'bookmarkable_ids' => [123, 456],
                    'bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic',
                ],
                'data' => (function () {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic', 'bookmarkable_id' => 123]));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic', 'bookmarkable_id' => 456]));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['bookmarkable_type' => 'EscolaLms\\Courses\\Models\\Course']));

                    return $items;
                }),
                'filterCount' => 2
            ],
        ];
    }

    public function orderDataProvider(): array
    {
        return [
            [
                'order' => [
                    'order_by' => 'id',
                    'order' => 'asc',
                ],
                'data' => (function() {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['id' => 1]));
                    $items->push(Bookmark::factory()->state(['id' => 2]));
                    $items->push(Bookmark::factory()->state(['id' => 3]));

                    return $items;
                }),
                'assert' => (function($data) {
                    $this->assertEquals(1, Arr::first($data->getData()->data)->id);
                    $this->assertEquals(3, Arr::last($data->getData()->data)->id);
                })
            ],
            [
                'order' => [
                    'order_by' => 'id',
                    'order' => 'desc',
                ],
                'data' => (function() {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['id' => 1]));
                    $items->push(Bookmark::factory()->state(['id' => 2]));
                    $items->push(Bookmark::factory()->state(['id' => 3]));

                    return $items;
                }),
                'assert' => (function($data) {
                    $this->assertEquals(1, Arr::last($data->getData()->data)->id);
                    $this->assertEquals(3, Arr::first($data->getData()->data)->id);
                })
            ],
            [
                'order' => [
                    'order_by' => 'value',
                    'order' => 'asc',
                ],
                'data' => (function() {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['value' => 'aaa']));
                    $items->push(Bookmark::factory()->state(['value' => 'bbb']));
                    $items->push(Bookmark::factory()->state(['value' => 'ccc']));

                    return $items;
                }),
                'assert' => (function($data) {
                    $this->assertEquals('aaa', Arr::first($data->getData()->data)->value);
                    $this->assertEquals('ccc', Arr::last($data->getData()->data)->value);
                })
            ],
            [
                'order' => [
                    'order_by' => 'value',
                    'order' => 'desc',
                ],
                'data' => (function() {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['value' => 'aaa']));
                    $items->push(Bookmark::factory()->state(['value' => 'bbb']));
                    $items->push(Bookmark::factory()->state(['value' => 'ccc']));

                    return $items;
                }),
                'assert' => (function($data) {
                    $this->assertEquals('aaa', Arr::last($data->getData()->data)->value);
                    $this->assertEquals('ccc', Arr::first($data->getData()->data)->value);
                })
            ],
        ];
    }
}
