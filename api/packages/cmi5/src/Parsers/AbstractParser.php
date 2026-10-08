<?php

namespace EscolaLms\Cmi5\Parsers;

use Illuminate\Support\Collection;

abstract class AbstractParser
{
    public static function parseCollection(array $data): Collection
    {
        $collection = new Collection();

        if (self::isAssoc($data)) {
            return $collection->push(static::parse($data));
        }

        foreach ($data as $item) {
            $collection->push(static::parse($item));
        }

        return $collection;
    }

    public static function isAssoc(array $arr): bool
    {
        if (array() === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
