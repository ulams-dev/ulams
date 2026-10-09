<?php

namespace Ulams\Auth\Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\Group;
use Ulams\Auth\Support\GroupTree;
use Ulams\Auth\Tests\TestCase;

class GroupTreeTest extends TestCase
{
    use DatabaseTransactions;

    private function group(?Group $parent = null): Group
    {
        return Group::factory()->create(['parent_id' => $parent?->getKey()]);
    }

    public function testThreeLevelTreeReturnsAllDescendants(): void
    {
        $root = $this->group();
        $child = $this->group($root);
        $otherChild = $this->group($root);
        $grandChild = $this->group($child);
        $greatGrandChild = $this->group($grandChild);
        $unrelated = $this->group();
        $this->group($unrelated);

        $descendants = GroupTree::descendants($root->getKey());

        $this->assertEqualsCanonicalizing(
            [$child->getKey(), $otherChild->getKey(), $grandChild->getKey(), $greatGrandChild->getKey()],
            $descendants
        );
        $this->assertSame([], GroupTree::descendants($greatGrandChild->getKey()));
    }

    public function testCycleTerminates(): void
    {
        $a = $this->group();
        $b = $this->group($a);
        DB::table('groups')->where('id', $a->getKey())->update(['parent_id' => $b->getKey()]);

        $this->assertSame([$b->getKey()], GroupTree::descendants($a->getKey()));
        $this->assertSame([$a->getKey()], GroupTree::descendants($b->getKey()));
    }

    public function testDepthIsLimited(): void
    {
        $group = $this->group();
        $root = $group;
        $ids = [];
        for ($i = 0; $i < GroupTree::MAX_DEPTH + 3; $i++) {
            $group = $this->group($group);
            $ids[] = $group->getKey();
        }

        $this->assertSame(
            array_slice($ids, 0, GroupTree::MAX_DEPTH),
            GroupTree::descendants($root->getKey())
        );
    }

    public function testManyGroupsAreWalkedTogether(): void
    {
        $first = $this->group();
        $second = $this->group();
        $firstChild = $this->group($first);
        $secondChild = $this->group($second);

        $this->assertEqualsCanonicalizing(
            [$firstChild->getKey(), $secondChild->getKey()],
            GroupTree::descendantsOfMany([$first->getKey(), $second->getKey()])
        );
    }
}
