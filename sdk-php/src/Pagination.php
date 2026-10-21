<?php

namespace Ulams\Sdk;

/**
 * Walks the pages of a list endpoint. The API answers a page as `{data: [...], meta: {current_page, last_page, ...}}`.
 *
 *     foreach (Pagination::items(fn (int $page) => $api->adminCoursesGet(page: $page)) as $course) { ... }
 *
 * The callback returns what a generated method returns (a model, an array or an object); items are yielded as
 * associative arrays. It stops after the last page, on an empty page, or after `$maxPages`.
 */
final class Pagination
{
    /**
     * @param callable(int):mixed $fetch gets the page number, returns one page
     * @return \Generator<int,array<string,mixed>>
     */
    public static function items(callable $fetch, int $firstPage = 1, int $maxPages = PHP_INT_MAX): \Generator
    {
        $page = $firstPage;
        for ($n = 0; $n < $maxPages; $n++, $page++) {
            $body = self::toArray($fetch($page));
            $items = $body['data'] ?? [];
            // some endpoints nest the paginator: data.data
            if (is_array($items) && !array_is_list($items) && isset($items['data']) && is_array($items['data'])) {
                $body['meta'] ??= array_diff_key($items, ['data' => 1]);
                $items = $items['data'];
            }
            if (!is_array($items) || $items === []) {
                return;
            }
            foreach ($items as $item) {
                yield is_array($item) ? $item : ['value' => $item];
            }
            $last = $body['meta']['last_page'] ?? null;
            if ($last === null || (int) ($body['meta']['current_page'] ?? $page) >= (int) $last) {
                return;
            }
        }
    }

    /** @return array<string,mixed> */
    public static function toArray(mixed $response): array
    {
        $plain = json_decode((string) json_encode(ObjectSerializer::sanitizeForSerialization($response)), true);

        return is_array($plain) ? $plain : [];
    }
}
