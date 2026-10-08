<?php

namespace Ulams\TopicTypeGift\Support;

use Illuminate\Support\Collection;

final class SeededShuffle
{
    /**
     * Deterministic shuffle with the algorithm Laravel up to 10 used for `Collection::shuffle($seed)`
     * (`mt_srand` + `shuffle`), so question and option orders already shown in open attempts stay the
     * same. Laravel 11 dropped the seed argument: `shuffle($seed)` now ignores it and shuffles at random.
     *
     * @template TCollection of Collection
     * @param TCollection $items
     * @return TCollection
     */
    public static function shuffle(Collection $items, int $seed): Collection
    {
        $array = $items->all();

        mt_srand($seed);
        shuffle($array);
        mt_srand();

        return new ($items::class)($array);
    }
}
