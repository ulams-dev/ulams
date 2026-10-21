<?php

namespace Ulams\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use Ulams\Sdk\Api\AdminCoursesApi;
use Ulams\Sdk\Pagination;

class PaginationTest extends TestCase
{
    private function page(array $data, int $current, int $last): array
    {
        return ['success' => true, 'message' => '', 'data' => $data, 'meta' => ['current_page' => $current, 'last_page' => $last, 'total' => 3]];
    }

    public function testItWalksEveryPageAndStopsAtTheLastOne(): void
    {
        $pages = [1 => $this->page([['id' => 1], ['id' => 2]], 1, 2), 2 => $this->page([['id' => 3]], 2, 2)];
        $asked = [];

        $items = iterator_to_array(Pagination::items(function (int $page) use ($pages, &$asked) {
            $asked[] = $page;

            return $pages[$page];
        }), false);

        $this->assertSame([1, 2, 3], array_column($items, 'id'));
        $this->assertSame([1, 2], $asked);
    }

    public function testAnEmptyPageEndsTheWalkAndMaxPagesCapsIt(): void
    {
        $this->assertSame([], iterator_to_array(Pagination::items(fn () => ['data' => []]), false));

        $asked = 0;
        $items = iterator_to_array(Pagination::items(function (int $page) use (&$asked) {
            $asked++;

            return $this->page([['id' => $page]], $page, 99);
        }, 1, 3), false);
        $this->assertSame([1, 2, 3], array_column($items, 'id'));
        $this->assertSame(3, $asked);
    }

    public function testANestedPaginatorIsUnwrapped(): void
    {
        $items = iterator_to_array(Pagination::items(fn () => ['data' => ['data' => [['id' => 7]], 'current_page' => 1, 'last_page' => 1]]), false);

        $this->assertSame([7], array_column($items, 'id'));
    }

    public function testItWorksWithAGeneratedMethod(): void
    {
        $mock = new MockedClient([
            MockedClient::json($this->page([['id' => 1, 'title' => 'a']], 1, 2)),
            MockedClient::json($this->page([['id' => 2, 'title' => 'b']], 2, 2)),
        ]);
        $api = $mock->client->api(AdminCoursesApi::class);

        $items = iterator_to_array(Pagination::items(fn (int $page) => $api->adminCoursesGet(page: $page)), false);

        $this->assertSame([1, 2], array_column($items, 'id'));
        $this->assertStringContainsString('page=2', $mock->request(1)->getUri()->getQuery());
    }
}
