<?php

namespace Ulams\CourseBuilder\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ulams\CourseBuilder\CourseAsCode\ElementMerge;

/**
 * The element-level three-way merge, run on the cases shared with the CLI (tests/fixtures/merge/cases.json; the
 * CLI runs the same file in front/cli/tests/course/merge.test.ts), so the server and the CLI cannot drift. A few
 * cases are spelled out in words as well, so a failing fixture reads as a rule and not as a diff.
 */
class ElementMergeTest extends TestCase
{
    /** @return array<string,array{0:array}> */
    public static function cases(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/merge/cases.json'), true, 512, JSON_THROW_ON_ERROR);
        $out = [];
        foreach ($cases as $case) {
            $out[$case['name']] = [$case];
        }

        return $out;
    }

    public function testTheFixtureFileHasTheCases(): void
    {
        $this->assertGreaterThanOrEqual(30, count(self::cases()));
    }

    #[DataProvider('cases')]
    public function testMergeMatchesTheSharedCase(array $case): void
    {
        $r = ElementMerge::merge($case['base'], $case['ours'], $case['theirs']);

        $this->assertEquals($case['expected']['doc'], $r['doc'], $case['description']);
        $this->assertSame(
            array_map(fn ($c) => ['id' => $c['id'], 'kind' => $c['kind'], 'held' => $c['held'], 'fields' => array_column($c['fields'], 'field')], $r['conflicts']),
            array_map(fn ($c) => ['id' => $c['id'], 'kind' => $c['kind'], 'held' => $c['held'], 'fields' => $c['fields']], $case['expected']['conflicts']),
        );
        $this->assertSame($case['expected']['notes'], array_map(fn ($n) => ['code' => $n['code'], 'id' => $n['id']], $r['notes']));
        $this->assertSame($case['expected']['fromOurs'], $r['fromOurs']);
        $this->assertSame($case['expected']['fromTheirs'], $r['fromTheirs']);
    }

    private function case(string $name): array
    {
        return self::cases()[$name][0];
    }

    public function testDisjointEditsMergeAndSameElementEditsConflict(): void
    {
        $c = $this->case('disjoint edits merge');
        $r = ElementMerge::merge($c['base'], $c['ours'], $c['theirs']);
        $this->assertSame([], $r['conflicts']);
        $this->assertSame(['Ours edits the first block.', 'Second block.', 'Theirs edits the third block.'], array_column($r['doc']['modules'][0]['lessons'][0]['blocks'], 'markdown'));

        $c = $this->case('same element edited differently');
        $r = ElementMerge::merge($c['base'], $c['ours'], $c['theirs']);
        $this->assertSame(['b2'], array_column($r['conflicts'], 'id'));
        $this->assertSame('Ours wording.', $r['doc']['modules'][0]['lessons'][0]['blocks'][1]['markdown'], 'ours is kept until a person decides');
    }

    public function testDeleteAgainstEditIsAConflictInBothDirections(): void
    {
        $c = $this->case('ours deletes what theirs edited');
        $this->assertSame('removed-ours', ElementMerge::merge($c['base'], $c['ours'], $c['theirs'])['conflicts'][0]['kind']);
        $c = $this->case('ours edits what theirs deleted');
        $this->assertSame('removed-theirs', ElementMerge::merge($c['base'], $c['ours'], $c['theirs'])['conflicts'][0]['kind']);
    }

    public function testNothingIsInventedForEqualSides(): void
    {
        $c = $this->case('nothing changed');
        foreach ([[$c['base'], $c['base']], [$c['ours'], $c['theirs']]] as [$ours, $theirs]) {
            $r = ElementMerge::merge($c['base'], $ours, $theirs);
            $this->assertEquals($c['base'], $r['doc']);
            $this->assertSame([], $r['conflicts']);
        }
    }

    public function testMovesAndOrder(): void
    {
        $c = $this->case('ours moves a lesson to another module');
        $r = ElementMerge::merge($c['base'], $c['ours'], $c['theirs']);
        $this->assertSame(['l2', 'l3'], array_column($r['doc']['modules'][1]['lessons'], 'id'));

        $c = $this->case('both reorder differently');
        $r = ElementMerge::merge($c['base'], $c['ours'], $c['theirs']);
        $this->assertSame(['b3', 'b2', 'b1'], array_column($r['doc']['modules'][0]['lessons'][0]['blocks'], 'id'));
        $this->assertSame('ORDER_BOTH', $r['notes'][0]['code']);
    }
}
