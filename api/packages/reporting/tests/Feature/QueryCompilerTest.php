<?php

namespace Ulams\Reporting\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Definition\DefinitionValidator;
use Ulams\Reporting\Query\DateRanges;
use Ulams\Reporting\Query\QueryCompiler;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\ReportContext;
use Ulams\Reporting\Tests\TestCase;

/** How a definition becomes SQL: bound values, identifiers from the registry only, buckets, ranges, sorting. */
class QueryCompilerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLearnerDatasets();
    }

    private function compile(array $input, ?CarbonImmutable $now = null)
    {
        $definition = $this->app->make(DefinitionValidator::class)->validate($input);
        $dataset = $this->app->make(DatasetRegistry::class)->get($definition->dataset);

        return $this->app->make(QueryCompiler::class)->compile($definition, $dataset, new ReportContext(null), $now);
    }

    public function testEveryValueIsABindingAndNoValueReachesTheSqlText(): void
    {
        $evil = "x'; DROP TABLE ai_calls; --";
        $compiled = $this->compile(['dataset' => 'learner_calls', 'columns' => ['task', 'status'], 'filters' => [
            ['column' => 'task', 'op' => 'eq', 'value' => $evil],
            ['column' => 'status', 'op' => 'in', 'value' => ['ok', 'error']],
            ['column' => 'input_tokens', 'op' => 'between', 'value' => [5, 50]],
            ['column' => 'task', 'op' => 'contains', 'value' => '50%_off'],
            ['column' => 'note', 'op' => 'is_null', 'value' => true],
        ]]);

        $sql = $compiled->sql();
        $this->assertStringNotContainsString('DROP', $sql);
        $this->assertStringNotContainsString('50%', $sql);
        $this->assertStringNotContainsString("'ok'", $sql);
        $this->assertSame(substr_count($sql, '?'), count($compiled->bindings()), 'one binding per placeholder');
        $this->assertContains($evil, $compiled->bindings());
        $this->assertContains('%50\\%\\_off%', $compiled->bindings(), 'wildcards typed by the user are escaped');

        // and it runs: the hostile string matches nothing and the table is still there
        $this->calls('out', [1, 2]);
        $this->assertSame(0, DB::query()->fromSub($compiled->query, 'x')->count());
        $this->assertSame(2, DB::table('ai_calls')->count());
    }

    public function testIdentifiersComeFromTheDatasetAndAreQuoted(): void
    {
        $compiled = $this->compile(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'sum', 'column' => 'cost_usd', 'as' => 'cost']], 'sort' => [['column' => 'cost', 'dir' => 'desc']]]);

        $sql = $compiled->sql();
        $this->assertStringContainsString('SUM("t"."cost_usd") as "cost"', $sql);
        $this->assertStringContainsString('"t"."task" as "task"', $sql);
        $this->assertStringContainsString('order by "cost" desc', $sql);
        $this->assertStringContainsString('group by 1', $sql);
        $this->assertSame([['name' => 'task', 'type' => 'string', 'label' => 'Task'], ['name' => 'cost', 'type' => 'decimal', 'label' => 'cost']], $compiled->output);
    }

    public function testMonthBucketsGroupDatesAndNameTheOutputColumn(): void
    {
        $this->calls('a', [1, 2], ['created_at' => '2026-01-05 10:00:00']);
        $this->calls('a', [3], ['created_at' => '2026-01-31 23:59:59']);
        $this->calls('a', [4], ['created_at' => '2026-02-01 00:00:00']);
        $compiled = $this->compile(['dataset' => 'learner_calls', 'groupBy' => [['column' => 'created_at', 'bucket' => 'month']], 'aggregates' => [['fn' => 'count', 'as' => 'calls']]]);

        $this->assertStringContainsString("date_trunc('month'", $compiled->sql());
        $this->assertSame([['name' => 'created_at_month', 'type' => 'date', 'label' => 'Called at (month)'], ['name' => 'calls', 'type' => 'int', 'label' => 'calls']], $compiled->output);
        $rows = $compiled->query->get();
        $this->assertSame(['2026-01-01', '2026-02-01'], $rows->pluck('created_at_month')->all(), 'ordered by the group key by default');
        $this->assertSame([3, 1], $rows->pluck('calls')->map(fn ($n) => (int) $n)->all());
    }

    public function testEveryBucketCompiles(): void
    {
        $this->calls('a', [1], ['created_at' => '2026-05-14 12:00:00']);
        $expected = ['day' => '2026-05-14', 'week' => '2026-05-11', 'month' => '2026-05-01', 'quarter' => '2026-04-01', 'year' => '2026-01-01'];
        foreach ($expected as $bucket => $start) {
            $rows = $this->compile(['dataset' => 'learner_calls', 'groupBy' => [['column' => 'created_at', 'bucket' => $bucket]], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])->query->get();
            $this->assertSame($start, $rows[0]->{"created_at_{$bucket}"}, $bucket);
        }
    }

    public function testAggregatesGroupsAndSorting(): void
    {
        $this->calls('big', [1, 2, 3], ['input_tokens' => 100, 'cost_micro_usd' => 2000000]);
        $this->calls('small', [1], ['input_tokens' => 10, 'cost_micro_usd' => 500000]);
        $compiled = $this->compile(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [
            ['fn' => 'count', 'as' => 'calls'], ['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners'], ['fn' => 'sum', 'column' => 'cost_usd', 'as' => 'cost'],
            ['fn' => 'avg', 'column' => 'input_tokens', 'as' => 'avg_tokens'], ['fn' => 'min', 'column' => 'input_tokens', 'as' => 'least'], ['fn' => 'max', 'column' => 'input_tokens', 'as' => 'most'],
        ], 'sort' => [['column' => 'calls', 'dir' => 'desc']]]);

        $rows = $compiled->query->get();

        $this->assertSame(['big', 'small'], $rows->pluck('task')->all());
        $this->assertSame([3, 3, 1], [(int) $rows[0]->calls, (int) $rows[0]->learners, (int) $rows[1]->calls]);
        $this->assertEqualsWithDelta(6.0, (float) $rows[0]->cost, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $rows[0]->avg_tokens, 0.0001);
        $this->assertSame([10, 10], [(int) $rows[1]->least, (int) $rows[1]->most]);
        $this->assertSame(3, (int) $rows[0]->{'__subjects'}, 'the distinct learners per group ride along for suppression');
    }

    public function testAGrandTotalIsOneRow(): void
    {
        $this->calls('a', [1, 2, 3]);
        $rows = $this->compile(['dataset' => 'learner_calls', 'aggregates' => [['fn' => 'count', 'as' => 'calls']]])->query->get();

        $this->assertCount(1, $rows);
        $this->assertSame(3, (int) $rows[0]->calls);
    }

    public function testRowReportsAreOrderedAndFiltered(): void
    {
        $this->calls('b', [2], ['input_tokens' => 20]);
        $this->calls('a', [1], ['input_tokens' => 10]);
        $this->calls('c', [3], ['input_tokens' => 30, 'status' => 'error']);

        $by = fn (array $extra) => $this->compile(['dataset' => 'learner_calls', 'columns' => ['task', 'input_tokens']] + $extra)->query->get()->pluck('task')->all();

        $this->assertSame(['c', 'b', 'a'], $by(['sort' => [['column' => 'input_tokens', 'dir' => 'desc']]]));
        $this->assertSame(['c'], $by(['filters' => [['column' => 'status', 'op' => 'eq', 'value' => 'error']]]));
        $this->assertEqualsCanonicalizing(['a', 'b'], $by(['filters' => [['column' => 'task', 'op' => 'not_in', 'value' => ['c']]]]));
        $this->assertEqualsCanonicalizing(['a', 'b'], $by(['filters' => [['column' => 'input_tokens', 'op' => 'lt', 'value' => 30]]]));
        $this->assertEqualsCanonicalizing(['b', 'c'], $by(['filters' => [['column' => 'input_tokens', 'op' => 'gte', 'value' => 20]]]));
        $this->assertSame(['b'], $by(['filters' => [['column' => 'task', 'op' => 'contains', 'value' => 'B']]]), 'contains ignores case');
        $this->assertSame(['a'], $by(['filters' => [['column' => 'input_tokens', 'op' => 'between', 'value' => [5, 15]]]]));
        $this->assertSame([], $by(['filters' => [['column' => 'task', 'op' => 'is_null', 'value' => true]]]));
    }

    public function testDateRangesFromPresetsAndDates(): void
    {
        $now = CarbonImmutable::parse('2026-10-22 15:00:00', config('app.timezone'));
        $this->calls('old', [1], ['created_at' => '2026-05-01 10:00:00']);
        $this->calls('last_week', [2], ['created_at' => '2026-10-17 00:00:00']);
        $this->calls('today', [3], ['created_at' => '2026-10-22 09:00:00']);
        $this->calls('last_month', [4], ['created_at' => '2026-09-30 23:59:59']);

        $in = fn (array $range) => $this->compile(['dataset' => 'learner_calls', 'columns' => ['task'], 'dateRange' => ['column' => 'created_at'] + $range], $now)->query->get()->pluck('task')->sort()->values()->all();

        $this->assertSame(['last_week', 'today'], $in(['preset' => 'last_7_days']));
        $this->assertSame(['last_week', 'today'], $in(['preset' => 'this_month']));
        $this->assertSame(['last_month'], $in(['preset' => 'last_month']));
        $this->assertSame(['last_month', 'last_week', 'today'], $in(['preset' => 'last_90_days']));
        $this->assertSame(['last_month', 'last_week', 'old', 'today'], $in(['preset' => 'this_year']));
        $this->assertSame(['last_week'], $in(['from' => '2026-10-17', 'to' => '2026-10-17']), 'the end date is inclusive');
        $this->assertSame(['old'], $in(['to' => '2026-06-01']));
    }

    public function testPresetsAreEvaluatedOnEveryRunNotStored(): void
    {
        $jan = CarbonImmutable::parse('2026-01-15 12:00:00', config('app.timezone'));

        [$from, $to] = DateRanges::resolve('this_quarter', $jan);
        $this->assertSame(['2026-01-01 00:00:00', '2026-03-31 23:59:59'], [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
        [$from, $to] = DateRanges::resolve('last_month', $jan);
        $this->assertSame(['2025-12-01', '2025-12-31'], [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        [$from] = DateRanges::resolve('last_12_months', $jan);
        $this->assertSame('2025-01-16', $from->format('Y-m-d'));
        foreach (DateRanges::PRESETS as $preset) {
            [$from, $to] = DateRanges::resolve($preset, $jan);
            $this->assertTrue($from->lessThanOrEqualTo($to), $preset);
        }
    }
}
