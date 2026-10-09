<?php

namespace Ulams\Auth\Support;

use Illuminate\Support\Facades\DB;

/**
 * Walks the group hierarchy (`groups.parent_id`) downwards.
 *
 * Breadth-first with a visited set, so a cycle (A -> B -> A) ends, and with a depth limit, so a
 * very deep or corrupt tree cannot trigger an unbounded number of queries. One query per level.
 */
class GroupTree
{
    public const MAX_DEPTH = 10;

    /**
     * Ids of all descendants of one group, without the group itself.
     *
     * @return array<int, int>
     */
    public static function descendants(int $groupId): array
    {
        return self::descendantsOfMany([$groupId]);
    }

    /**
     * Ids of all descendants of the given groups, without the groups themselves.
     *
     * @param iterable<int|string> $groupIds
     * @return array<int, int>
     */
    public static function descendantsOfMany(iterable $groupIds): array
    {
        $frontier = [];
        foreach ($groupIds as $groupId) {
            $frontier[(int) $groupId] = (int) $groupId;
        }

        $visited = $frontier;
        $descendants = [];

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $children = DB::table('groups')
                ->whereIn('parent_id', array_values($frontier))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $frontier = [];
            foreach ($children as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }
                $visited[$childId] = $childId;
                $frontier[$childId] = $childId;
                $descendants[] = $childId;
            }
        }

        return $descendants;
    }
}
