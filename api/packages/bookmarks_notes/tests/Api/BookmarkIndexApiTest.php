<?php

namespace Ulams\Bookmarks\Tests\Api;

use Ulams\Bookmarks\Database\Seeders\BookmarkPermissionSeeder;
use Ulams\Bookmarks\Models\Bookmark;
use Ulams\Bookmarks\Tests\BookmarkTesting;
use Ulams\Bookmarks\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;

class BookmarkIndexApiTest extends TestCase
{
    use BookmarkTesting, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BookmarkPermissionSeeder::class);
    }

    #[DataProvider('filterDataProvider')]
    public function testIndexBookmarkFilters(array $filters, callable $generator, int $filterCount): void
    {
        $user = $this->makeStudent();
        $generator($user->getKey())->each(fn($factory) => $factory->create());

        $this->actingAs($user, 'api')
            ->getJson($this->prepareUri('api/bookmarks', $filters))
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

    public function testIndexBookmarkFiltersSkipUserIdFilter(): void
    {
        $user1 = $this->makeStudent();
        $user2 = $this->makeStudent();

        Bookmark::factory()->count(5)->create(['user_id' => $user1->getKey()]);
        Bookmark::factory()->count(3)->create(['user_id' => $user2->getKey()]);

        $this->actingAs($user1, 'api')
            ->getJson($this->prepareUri('api/bookmarks', ['user_id' => $user2->getKey()]))
            ->assertOk()
            ->assertJsonCount(5, 'data')
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

    public function testIndexBookmarkTopic(): void
    {
        $user = $this->makeStudent();
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create([
            'course_id' => $course->getKey(),
        ]);
        $topic = Topic::factory()->create([
            'lesson_id' => $lesson->getKey()
        ]);
        Bookmark::factory()->create([
            'user_id' => $user->getKey(),
            'bookmarkable_id' => $topic->getKey(),
            'bookmarkable_type' => 'Ulams\Courses\Models\Topic'
        ]);

        $this->actingAs($user, 'api')
            ->getJson('api/bookmarks')
            ->assertOk()
            ->assertJsonStructure(['data' => [[
                'id',
                'value',
                'user' => [
                    'id',
                    'first_name',
                    'last_name',
                ],
                'bookmarkable' => [
                    'id',
                    'title',
                    'type',
                    'lesson_id',
                    'course_id',
                ],
                'bookmarkable_id',
                'bookmarkable_type',
            ]]]);
    }

    public function testIndexBookmarkPagination(): void
    {
        $user = $this->makeStudent();
        Bookmark::factory()->count(10)->create();
        Bookmark::factory()->count(25)->create(['user_id' => $user->getKey()]);

        $this->actingAs($user, 'api')
            ->getJson('api/bookmarks?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 25
                ]
            ]);

        $this->actingAs($user, 'api')
            ->getJson('api/bookmarks?per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 25
                ]
            ]);
    }

    #[DataProvider('orderDataProvider')]
    public function testIndexBookmarkOrder(array $order, callable $generator, callable $assertion): void
    {
        $user = $this->makeStudent();
        $generator($user->getKey())->each(fn($factory) => $factory->create());

        $response = $this->actingAs($user, 'api')
            ->getJson($this->prepareUri('api/bookmarks', $order))
            ->assertOk();

        $assertion($response);
    }

    public static function filterDataProvider(): array
    {
        return [
            [
                'filters' => [
                    'has_value' => 0
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['value' => null, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['value' => null, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['value' => null, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));

                    return $items;
                }),
                'filterCount' => 3
            ],
            [
                'filters' => [
                    'has_value' => 1
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['value' => null, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));

                    return $items;
                }),
                'filterCount' => 2
            ],
            [
                'filters' => [
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));

                    return $items;
                }),
                'filterCount' => 2
            ],
            [
                'filters' => [
                    'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Course']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Course']));

                    return $items;
                }),
                'filterCount' => 3
            ],
            [
                'filters' => [
                    'bookmarkable_id' => 123,
                    'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic', 'bookmarkable_id' => 123]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Course']));

                    return $items;
                }),
                'filterCount' => 1
            ],
            [
                'filters' => [
                    'bookmarkable_ids' => [123, 456],
                    'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory());
                    $items->push(Bookmark::factory()->state(['user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic', 'bookmarkable_id' => 123]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic', 'bookmarkable_id' => 456]));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Topic']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'bookmarkable_type' => 'Ulams\\Courses\\Models\\Course']));

                    return $items;
                }),
                'filterCount' => 2
            ],
        ];
    }

    public static function orderDataProvider(): array
    {
        return [
            [
                'order' => [
                    'order_by' => 'id',
                    'order' => 'asc',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['id' => 1, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['id' => 2, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['id' => 3, 'user_id' => $userId]));

                    return $items;
                }),
                'assertion' => (function ($data) {
                    self::assertEquals(1, Arr::first($data->getData()->data)->id);
                    self::assertEquals(3, Arr::last($data->getData()->data)->id);
                })
            ],
            [
                'order' => [
                    'order_by' => 'id',
                    'order' => 'desc',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['id' => 1, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['id' => 2, 'user_id' => $userId]));
                    $items->push(Bookmark::factory()->state(['id' => 3, 'user_id' => $userId]));

                    return $items;
                }),
                'assertion' => (function ($data) {
                    self::assertEquals(1, Arr::last($data->getData()->data)->id);
                    self::assertEquals(3, Arr::first($data->getData()->data)->id);
                })
            ],
            [
                'order' => [
                    'order_by' => 'value',
                    'order' => 'asc',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'value' => 'aaa']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'value' => 'bbb']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'value' => 'ccc']));

                    return $items;
                }),
                'assertion' => (function ($data) {
                    self::assertEquals('aaa', Arr::first($data->getData()->data)->value);
                    self::assertEquals('ccc', Arr::last($data->getData()->data)->value);
                })
            ],
            [
                'order' => [
                    'order_by' => 'value',
                    'order' => 'desc',
                ],
                'generator' => (function (int $userId) {
                    $items = collect();
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'value' => 'aaa']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'value' => 'bbb']));
                    $items->push(Bookmark::factory()->state(['user_id' => $userId, 'value' => 'ccc']));

                    return $items;
                }),
                'assertion' => (function ($data) {
                    self::assertEquals('aaa', Arr::last($data->getData()->data)->value);
                    self::assertEquals('ccc', Arr::first($data->getData()->data)->value);
                })
            ],
        ];
    }
}
