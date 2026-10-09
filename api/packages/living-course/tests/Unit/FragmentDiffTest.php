<?php

namespace Ulams\LivingCourse\Tests\Unit;

use Ulams\CourseBuilder\Ingestion\ConvertedDocument;
use Ulams\CourseBuilder\Ingestion\SourceDocumentBuilder;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Diff\FragmentDiff;
use Ulams\LivingCourse\Diff\Normaliser;
use Ulams\LivingCourse\Tests\TestCase;

/** ADR 0031: the deterministic alignment of two revisions, on fixtures for every case of the plan. */
class FragmentDiffTest extends TestCase
{
    /**
     * Builds fragment records exactly like a revision does (same source row id, positional ids).
     *
     * @param array<string,string>|string $documents markdown, or path => markdown for several files
     * @return array<int,array<string,mixed>>
     */
    private function fragments(array|string $documents): array
    {
        $docs = is_string($documents)
            ? [new ConvertedDocument($documents)]
            : array_map(fn ($path, $md) => new ConvertedDocument($md, [], [], $path), array_keys($documents), $documents);
        $source = new Source(['id' => '01hzzzzzzzzzzzzzzzzzzzzzzz', 'original_name' => 'x.md']);
        $rows = (new SourceDocumentBuilder())->rows($source, $docs)['rows'];

        return array_map(fn (array $r) => [
            'id' => $r['id'], 'text' => $r['text'], 'content_hash' => $r['content_hash'], 'normalised_hash' => Normaliser::hash($r['text']),
            'heading_path' => $r['heading_path'], 'file_path' => $r['file_path'],
        ], $rows);
    }

    private function diff(array|string $old, array|string $new)
    {
        return (new FragmentDiff())->compare($this->fragments($old), $this->fragments($new));
    }

    /** About 100 tokens of text no other paragraph shares a word 3-gram with. */
    private function para(int $n): string
    {
        return implode(' ', array_map(fn ($i) => "w{$n}x{$i}", range(1, 70))) . '.';
    }

    /** @return array<int,array<string,mixed>> */
    private function ofKind($set, string $kind): array
    {
        return array_values(array_filter($set->changes, fn ($c) => $c['kind'] === $kind));
    }

    public function testIdenticalAndWhitespaceOnlyDocumentsHaveNoSignificantChange(): void
    {
        $md = "# T\n\n## Alpha\n\nBrew **coffee** with \"care\" every morning at home.\n\n## Beta\n\nGrind the beans right before brewing them.\n";
        $same = $this->diff($md, $md);
        $this->assertSame([], $same->changes);
        $this->assertTrue($same->isNoImpact());

        $cosmetic = $this->diff($md, "# T\n\n## Alpha\n\nBrew coffee with \u{201C}care\u{201D}  every   morning at home.\n\n## Beta\n\nGrind the beans right before brewing them.\n");
        $this->assertCount(1, $cosmetic->changes);
        $this->assertSame('changed', $cosmetic->changes[0]['kind']);
        $this->assertSame('trivial', $cosmetic->changes[0]['magnitude']);
        $this->assertTrue($cosmetic->isNoImpact());
        $this->assertSame(1, $cosmetic->counts()['trivial']);
        $this->assertSame([], $cosmetic->significant());
    }

    public function testTypoFixIsAMinorChangeOnTheSameId(): void
    {
        $old = "# T\n\n## Alpha\n\nThe grinder should be cleaned every week to keep the flavour clear and the burrs sharp enough.\n";
        $new = "# T\n\n## Alpha\n\nThe grinder should be cleaned every weak to keep the flavour clear and the burrs sharp enough.\n";
        $set = $this->diff($old, $new);

        $this->assertCount(1, $set->changes);
        $c = $set->changes[0];
        $this->assertSame('changed', $c['kind']);
        $this->assertSame($c['old'], $c['new']);
        $this->assertSame('minor', $c['magnitude']);
        $this->assertSame([], $c['signals']);
        $this->assertGreaterThan(0.5, $c['similarity']);
        $this->assertStringContainsString('"-"', (string) json_encode($c['word_diff']));
        $this->assertFalse($set->isNoImpact());
    }

    public function testNumberChangeIsSubstantiveWithTheNumberSignal(): void
    {
        $old = "# T\n\n## Ratio\n\nA ratio of 1:16 means one gram of coffee for every sixteen grams of water in the cup.\n";
        $new = "# T\n\n## Ratio\n\nA ratio of 1:15 means one gram of coffee for every sixteen grams of water in the cup.\n";
        $c = $this->diff($old, $new)->changes[0];

        $this->assertSame('substantive', $c['magnitude']);
        $this->assertContains('number', $c['signals']);
    }

    public function testIdentifierAndFlagRenamesAreSubstantive(): void
    {
        $old = "# T\n\n## CLI\n\nRun the tool with --merge to combine branches and set max_retries for slow networks in the config.\n";
        $flag = "# T\n\n## CLI\n\nRun the tool with --rebase to combine branches and set max_retries for slow networks in the config.\n";
        $snake = "# T\n\n## CLI\n\nRun the tool with --merge to combine branches and set retry_limit for slow networks in the config.\n";
        $call = "# T\n\n## CLI\n\nRun the tool with --merge to combine branches and set max_retries for slow networks in the config() file.\n";

        foreach ([$flag, $snake, $call] as $new) {
            $c = $this->diff($old, $new)->changes[0];
            $this->assertSame('substantive', $c['magnitude'], $new);
            $this->assertContains('identifier', $c['signals'], $new);
        }
    }

    public function testNegationAndModalityWordsAreSubstantive(): void
    {
        $old = "# T\n\n## Rules\n\nYou should always store the beans in an airtight container away from direct light and heat.\n";
        $new = "# T\n\n## Rules\n\nYou should never store the beans in an airtight container away from direct light and heat.\n";
        $c = $this->diff($old, $new)->changes[0];

        $this->assertSame('substantive', $c['magnitude']);
        $this->assertContains('modality', $c['signals']);
    }

    public function testCodeChangesAreSubstantive(): void
    {
        $old = "# T\n\n## Run\n\nInstall the tool first and then start it:\n\n```sh\nnpm install tool\nnpm start\n```\n";
        $new = "# T\n\n## Run\n\nInstall the tool first and then start it:\n\n```sh\nnpm install tool\nnpm run dev\n```\n";
        $c = $this->diff($old, $new)->changes[0];

        $this->assertSame('substantive', $c['magnitude']);
        $this->assertContains('code', $c['signals']);
    }

    public function testRewordingMoreThanFifteenPercentIsSubstantiveWithoutOtherSignals(): void
    {
        $old = "# T\n\n## Intro\n\nThis handbook explains how to brew better coffee at home with simple tools and some practice every single day, so that every cup you pour for your family and your friends tastes noticeably better than the last one did.\n";
        $new = "# T\n\n## Intro\n\nThis handbook explains how to brew better coffee at home with simple tools and some practice every single day, so that every cup you pour for guests and relatives ends up pleasantly sweeter than the last one did.\n";
        $c = $this->diff($old, $new)->changes[0];

        $this->assertSame('changed', $c['kind']);
        $this->assertSame('substantive', $c['magnitude']);
        $this->assertSame(['large'], $c['signals']);
    }

    public function testAParagraphInsertedInTheMiddleOfASectionDoesNotChangeEverythingAfterIt(): void
    {
        $paras = array_map(fn ($n) => $this->para($n), range(1, 14));
        $old = "# T\n\n## Long section\n\n" . implode("\n\n", $paras) . "\n\n## Next\n\nShort closing section that stays as it is for readers.\n";
        $inserted = $paras;
        array_splice($inserted, 2, 0, [$this->para(99)]);
        $new = "# T\n\n## Long section\n\n" . implode("\n\n", $inserted) . "\n\n## Next\n\nShort closing section that stays as it is for readers.\n";

        $oldFragments = $this->fragments($old);
        $this->assertGreaterThanOrEqual(3, count($oldFragments), 'the section is split into several chunks');
        $set = $this->diff($old, $new);

        $added = array_filter($set->changes, fn ($c) => $c['kind'] === 'added');
        $removed = array_filter($set->changes, fn ($c) => $c['kind'] === 'removed');
        // the chunk packing shifts, but chunks are matched by content: nothing is removed, at most one chunk is new,
        // and the untouched "Next" section is not reported at all
        $this->assertLessThanOrEqual(1, count($added));
        $this->assertSame([], array_values($removed));
        $this->assertLessThanOrEqual(count($oldFragments), count($set->changes));
        $next = collect($oldFragments)->first(fn ($f) => $f['heading_path'] === ['Next']);
        $this->assertNotContains($next['id'], array_merge(array_column($set->changes, 'old'), array_column($set->changes, 'new')));
    }

    public function testASectionMovedToAnotherChapterIsAMoveWithItsNewId(): void
    {
        $a = $this->para(1);
        $b = $this->para(2);
        $c = $this->para(3);
        $old = "# T\n\n## Part one\n\n### Alpha\n\n{$a}\n\n### Beta\n\n{$b}\n\n## Part two\n\n### Gamma\n\n{$c}\n";
        $new = "# T\n\n## Part one\n\n### Alpha\n\n{$a}\n\n## Part two\n\n### Gamma\n\n{$c}\n\n### Beta\n\n{$b}\n";
        $set = $this->diff($old, $new);

        $moves = $this->ofKind($set, 'moved');
        $this->assertCount(1, $moves);
        $this->assertNotSame($moves[0]['old'], $moves[0]['new']);
        $this->assertSame('trivial', $moves[0]['magnitude']);
        $this->assertSame([], $this->ofKind($set, 'removed'));
        $this->assertSame([], $this->ofKind($set, 'added'));
        $this->assertFalse($set->isNoImpact(), 'a moved fragment gets a new id, so citations need a remap');
    }

    public function testARenamedHeadingRemapsTheIds(): void
    {
        $a = $this->para(1);
        $old = "# T\n\n## Brewing basics\n\n{$a}\n";
        $new = "# T\n\n## Brewing fundamentals\n\n{$a}\n";
        $set = $this->diff($old, $new);

        $this->assertCount(1, $set->changes);
        $this->assertSame('moved', $set->changes[0]['kind']);
        $this->assertNotSame($set->changes[0]['old'], $set->changes[0]['new']);
        $this->assertSame(1.0, $set->changes[0]['similarity']);
    }

    public function testARenamedHeadingWithEditedTextIsAFuzzyMove(): void
    {
        $words = implode(' ', array_map(fn ($i) => "term{$i}", range(1, 40)));
        $old = "# T\n\n## Old title\n\n{$words} end.\n";
        $new = "# T\n\n## New title\n\n{$words} finish.\n";
        $set = $this->diff($old, $new);

        $this->assertCount(1, $set->changes);
        $this->assertSame('moved', $set->changes[0]['kind']);
        $this->assertNotSame('trivial', $set->changes[0]['magnitude']);
        $this->assertGreaterThan(0.6, $set->changes[0]['similarity']);
    }

    public function testARemovedSectionAndAnAddedSectionAreReported(): void
    {
        $a = $this->para(1);
        $b = $this->para(2);
        $c = $this->para(3);
        $old = "# T\n\n## Keep\n\n{$a}\n\n## Drop\n\n{$b}\n";
        $new = "# T\n\n## Keep\n\n{$a}\n\n## Fresh\n\n{$c}\n";
        $set = $this->diff($old, $new);

        $this->assertCount(1, $this->ofKind($set, 'removed'));
        $this->assertCount(1, $this->ofKind($set, 'added'));
        $this->assertSame(['changed' => 0, 'moved' => 0, 'removed' => 1, 'added' => 1, 'trivial' => 0, 'minor' => 0, 'substantive' => 0, 'total' => 2], $set->counts());
        // removed fragments sit in document order, after the one before them
        $this->assertSame(['removed', 'added'], array_column($set->changes, 'kind'));
    }

    public function testARenamedFileIsMovedWhenTheContentIsTheSame(): void
    {
        $text = "## Rebasing\n\n" . $this->para(7) . "\n";
        $other = "## Merging\n\n" . $this->para(8) . "\n";
        $old = ['docs/rebase.md' => $text, 'docs/merge.md' => $other];
        $new = ['docs/rewrite.md' => $text, 'docs/merge.md' => $other];
        $set = $this->diff($old, $new);

        $this->assertCount(1, $set->changes);
        $this->assertSame('moved', $set->changes[0]['kind']);
        $this->assertSame('trivial', $set->changes[0]['magnitude']);
    }

    public function testSameHeadingInTwoFilesIsNotConfused(): void
    {
        $old = ['a.md' => "## Setup\n\n" . $this->para(1) . "\n", 'b.md' => "## Setup\n\n" . $this->para(2) . "\n"];
        $new = ['a.md' => "## Setup\n\n" . $this->para(1) . "\n", 'b.md' => "## Setup\n\n" . $this->para(2) . " Extra words appended.\n"];
        $set = $this->diff($old, $new);

        $this->assertCount(1, $set->changes);
        $this->assertSame('changed', $set->changes[0]['kind']);
        $this->assertSame($set->changes[0]['old'], $set->changes[0]['new']);
    }

    public function testWordDiffIsCompactAndRebuildsBothTexts(): void
    {
        $old = "# T\n\n## Ratio\n\nA ratio of 1:16 means one gram of coffee for every sixteen grams of water in the cup.\n";
        $new = "# T\n\n## Ratio\n\nA ratio of 1:15 means one gram of coffee for every sixteen grams of water in the cup.\n";
        $ops = $this->diff($old, $new)->changes[0]['word_diff'];

        $oldText = implode('', array_map(fn ($o) => $o[0] === '+' ? '' : $o[1], $ops));
        $newText = implode('', array_map(fn ($o) => $o[0] === '-' ? '' : $o[1], $ops));
        $this->assertStringContainsString('A ratio of 1:16 means one gram', $oldText);
        $this->assertStringContainsString('A ratio of 1:15 means one gram', $newText);
        $this->assertLessThan(1024, strlen(json_encode($ops)));
        $this->assertContains(['-', ' 1:16'], $ops);
        $this->assertContains(['+', ' 1:15'], $ops);
    }

    public function testTwoThousandByTwoThousandFragmentsFinishInUnderTwoSeconds(): void
    {
        $make = function (int $shift) {
            $rows = [];
            for ($i = 0; $i < 2000; $i++) {
                $text = "Fragment number {$i} talks about topic " . ($i % 40) . ' and mentions brewing, grinding and tasting at step ' . ($i + ($i % 7 === 0 ? $shift : 0)) . '.';
                $rows[] = ['id' => 'frg_' . str_pad((string) $i, 12, 'a', STR_PAD_LEFT), 'text' => $text, 'content_hash' => hash('sha256', $text),
                    'normalised_hash' => Normaliser::hash($text), 'heading_path' => ["S{$i}"], 'file_path' => null];
            }

            return $rows;
        };
        $started = microtime(true);
        $set = (new FragmentDiff())->compare($make(0), $make(3));
        $elapsed = microtime(true) - $started;

        $this->assertLessThan(2.0, $elapsed);
        $this->assertGreaterThan(200, count($set->changes));
    }

    public function testTooManyFragmentsFailWithAReadableMessage(): void
    {
        config(['living_course.diff.max_fragments' => 3]);
        $rows = array_map(fn ($i) => ['id' => "frg_aaaaaaaaaaa{$i}", 'text' => "t{$i}", 'content_hash' => "h{$i}", 'normalised_hash' => "n{$i}", 'heading_path' => []], range(1, 4));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('more than 3 fragments');
        (new FragmentDiff())->compare($rows, $rows);
    }
}
