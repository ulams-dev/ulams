<?php

namespace Ulams\Reporting\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Reporting\Tests\TestCase;

/** POST /api/admin/reporting/query: permissions, definition errors, previews, small-group suppression. */
class QueryApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLearnerDatasets();
    }

    private function run_(array $definition, ?object $as = null)
    {
        return $this->actingAs($as ?? $this->makeAdmin(), 'api')->postJson('/api/admin/reporting/query', $definition);
    }

    /** @return array{rows:list<array<string,mixed>>,meta:array<string,mixed>,columns:list<array<string,mixed>>} */
    private function grouped(array $extra = []): array
    {
        $response = $this->run_(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'calls'], ['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners']], 'sort' => [['column' => 'task']]] + $extra)->assertOk();

        return ['rows' => $response->json('data.rows'), 'meta' => $response->json('meta'), 'columns' => $response->json('data.columns')];
    }

    private function shown(array $result): array
    {
        return array_values(array_map(fn ($r) => $r['task'], array_filter($result['rows'], fn ($r) => !isset($r['_withheld']))));
    }

    public function testItNeedsReportingReadAndThePermissionOfTheDataset(): void
    {
        $definition = ['dataset' => 'learner_calls', 'columns' => ['task']];

        $this->postJson('/api/admin/reporting/query', $definition)->assertUnauthorized();
        $this->run_($definition, $this->makeStudent())->assertForbidden();
        $this->run_($definition, $this->makeInstructor())->assertForbidden();
        $tutor = $this->makeInstructor();
        $tutor->givePermissionTo('reporting_ai_usage');
        $this->run_($definition, $tutor)->assertOk();
        $this->run_($definition, $this->makeAdmin())->assertOk();
    }

    public function testAnInvalidDefinitionAnswers422WithThePathOfEachProblem(): void
    {
        $response = $this->run_(['dataset' => 'learner_calls', 'columns' => ['task', 'nope'], 'filters' => [['column' => 'input_tokens', 'op' => 'contains', 'value' => 'x']]])->assertStatus(422);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('columns.1', $errors);
        $this->assertArrayHasKey('filters.0.op', $errors);
        $this->run_(['dataset' => 'nope', 'columns' => ['x']])->assertStatus(422)->assertJsonValidationErrors(['dataset']);
        $this->run_([])->assertStatus(422);
    }

    public function testARowReportReturnsTypedValuesAndTheTotal(): void
    {
        $this->calls('outline', [7], ['input_tokens' => 42, 'cost_micro_usd' => 2500000, 'created_at' => '2026-10-20 08:30:00']);

        $response = $this->run_(['dataset' => 'learner_calls', 'columns' => ['task', 'user_id', 'input_tokens', 'cost_usd', 'cached', 'created_at']])->assertOk();

        $this->assertSame([
            ['name' => 'task', 'type' => 'string', 'label' => 'Task'], ['name' => 'user_id', 'type' => 'int', 'label' => 'Learner'], ['name' => 'input_tokens', 'type' => 'int', 'label' => 'Input tokens'],
            ['name' => 'cost_usd', 'type' => 'decimal', 'label' => 'Cost'], ['name' => 'cached', 'type' => 'bool', 'label' => 'Cached'], ['name' => 'created_at', 'type' => 'datetime', 'label' => 'Called at'],
        ], $response->json('data.columns'));
        $this->assertSame(['task' => 'outline', 'user_id' => 7, 'input_tokens' => 42, 'cost_usd' => 2.5, 'cached' => false, 'created_at' => '2026-10-20T08:30:00+00:00'], $response->json('data.rows.0'));
        $this->assertSame(['mode' => 'rows', 'total_rows' => 1, 'preview_rows' => 1, 'truncated' => false, 'withheld_groups' => 0, 'min_group_size' => null], $response->json('meta'));
    }

    public function testAPreviewIsCappedAtFiveHundredRowsAndReportsTheTotal(): void
    {
        $this->calls('bulk', range(1, 600));

        $all = $this->run_(['dataset' => 'learner_calls', 'columns' => ['call_id', 'user_id'], 'sort' => [['column' => 'user_id']]])->assertOk();
        $this->assertCount(500, $all->json('data.rows'));
        $this->assertSame(1, $all->json('data.rows.0.user_id'));
        $this->assertSame(600, $all->json('meta.total_rows'));
        $this->assertTrue($all->json('meta.truncated'));

        $limited = $this->run_(['dataset' => 'learner_calls', 'columns' => ['call_id'], 'limit' => 25])->assertOk();
        $this->assertCount(25, $limited->json('data.rows'));
        $this->assertSame(25, $limited->json('meta.total_rows'));
        $this->assertFalse($limited->json('meta.truncated'));
    }

    public function testBigGroupsAreShownWithTheirFigures(): void
    {
        $this->calls('alpha', range(1, 12));
        $this->calls('beta', range(20, 34));

        $result = $this->grouped();

        $this->assertSame(['alpha', 'beta'], $this->shown($result));
        $this->assertSame([12, 12], [$result['rows'][0]['calls'], $result['rows'][0]['learners']]);
        $this->assertSame(0, $result['meta']['withheld_groups']);
        $this->assertSame(10, $result['meta']['min_group_size']);
        $this->assertSame('grouped', $result['meta']['mode']);
    }

    public function testAGroupOfFourLearnersIsWithheldTogetherWithTheSmallestVisibleOne(): void
    {
        $this->calls('alpha', range(1, 12));
        $this->calls('beta', range(20, 23)); // four learners
        $this->calls('gamma', range(40, 54));

        $result = $this->grouped();

        // beta is below 10; alone it would be the grand total minus the others, so alpha (the smallest visible) goes with it
        $this->assertSame(['gamma'], $this->shown($result));
        $this->assertSame(2, $result['meta']['withheld_groups']);
        $last = end($result['rows']);
        $this->assertTrue($last['_withheld']);
        $this->assertSame('withheld (fewer than 10 learners)', $last['task']);
        $this->assertNull($last['calls']);
        $this->assertNull($last['learners']);
        $json = json_encode($result['rows']);
        foreach (['alpha', 'beta'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json, "{$hidden} must not appear anywhere in the response");
        }
        $this->assertSame(1, $result['meta']['total_rows'], 'the total counts the groups shown');
    }

    public function testSeveralSmallGroupsThatTogetherSpanTheThresholdNeedNoExtraGroup(): void
    {
        $this->calls('alpha', range(1, 20));
        $this->calls('beta', range(30, 35)); // six
        $this->calls('gamma', range(40, 45)); // six more, different learners: twelve together

        $result = $this->grouped();

        $this->assertSame(['alpha'], $this->shown($result));
        $this->assertSame(2, $result['meta']['withheld_groups']);
    }

    public function testSmallGroupsMadeOfTheSameLearnersStillCountAsOneSmallGroup(): void
    {
        $this->calls('alpha', range(1, 20));
        $this->calls('beta', range(30, 35));
        $this->calls('gamma', range(30, 35)); // the same six learners again: together they are six, not twelve

        $result = $this->grouped();

        $this->assertSame([], $this->shown($result), 'alpha goes with them: six learners together is still a small group');
        $this->assertSame(3, $result['meta']['withheld_groups']);
        $this->assertCount(1, $result['rows']);
    }

    public function testWhenEveryGroupIsSmallOnlyTheWithheldRowRemains(): void
    {
        $this->calls('alpha', range(1, 3));
        $this->calls('beta', range(10, 14));

        $result = $this->grouped();

        $this->assertCount(1, $result['rows']);
        $this->assertTrue($result['rows'][0]['_withheld']);
        $this->assertSame(2, $result['meta']['withheld_groups']);
        $this->assertSame(0, $result['meta']['total_rows']);
    }

    public function testAGrandTotalOverFewLearnersIsWithheld(): void
    {
        $this->calls('alpha', range(1, 4));
        $few = $this->run_(['dataset' => 'learner_calls', 'aggregates' => [['fn' => 'count', 'as' => 'calls']]])->assertOk();
        $this->assertTrue($few->json('data.rows.0._withheld'));
        $this->assertNull($few->json('data.rows.0.calls'));

        $this->calls('beta', range(10, 20));
        $many = $this->run_(['dataset' => 'learner_calls', 'aggregates' => [['fn' => 'count', 'as' => 'calls']]])->assertOk();
        $this->assertSame(15, $many->json('data.rows.0.calls'));
        $this->assertArrayNotHasKey('_withheld', $many->json('data.rows.0'));
    }

    public function testTheThresholdIsReportingFirstThenLearnerInsights(): void
    {
        $this->calls('alpha', range(1, 6));
        $this->calls('beta', range(10, 30));

        config(['learner_insights.min_group_size' => 5, 'reporting.min_group_size' => null]);
        $this->assertSame(['alpha', 'beta'], $this->shown($this->grouped()));

        config(['learner_insights.min_group_size' => 5, 'reporting.min_group_size' => 8]);
        $result = $this->grouped();
        $this->assertSame(8, $result['meta']['min_group_size']);
        $this->assertSame([], $this->shown($result), 'alpha (6) is withheld and beta goes with it');
    }

    public function testReportsOnDatasetsWithoutLearnersAreNeverWithheld(): void
    {
        DB::table('ai_calls')->insert(['id' => (string) \Illuminate\Support\Str::ulid(), 'task' => 'solo', 'driver' => 'fake', 'model_requested' => 'm', 'status' => 'ok', 'created_at' => now()]);

        $response = $this->run_(['dataset' => 'ai_usage', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'calls']]])->assertOk();

        $this->assertSame([['task' => 'solo', 'calls' => 1]], $response->json('data.rows'));
        $this->assertNull($response->json('meta.min_group_size'));
        $this->assertSame(0, $response->json('meta.withheld_groups'));
    }

    public function testAnAggregateOnlyDatasetRefusesARowReport(): void
    {
        $this->run_(['dataset' => 'learner_stats', 'columns' => ['task']])->assertStatus(422)->assertJsonValidationErrors(['columns']);

        $this->calls('alpha', range(1, 12));
        $this->run_(['dataset' => 'learner_stats', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])->assertOk();
    }

    public function testRowsWithPersonalColumnsAreRecordedAndAggregatesAreNot(): void
    {
        $this->calls('alpha', range(1, 12), ['request_id' => 'secret-note']);
        $admin = $this->makeAdmin();

        $this->run_(['dataset' => 'learner_calls', 'columns' => ['task']], $admin)->assertOk();
        $this->grouped();
        $this->assertSame(0, DB::table('compliance_exports')->count(), 'nothing personal left the server');

        $this->run_(['dataset' => 'learner_calls', 'columns' => ['task', 'note'], 'limit' => 5], $admin)->assertOk()->assertJsonPath('data.rows.0.note', 'secret-note');
        $log = DB::table('compliance_exports')->first();
        $this->assertSame('learner_calls', $log->dataset);
        $this->assertSame($admin->getKey(), $log->user_id);
        $this->assertSame(5, $log->row_count);
        $this->assertSame('learner_calls', json_decode($log->filters, true)['report']['dataset']);
    }

    public function testASmallGroupCountCannotBeReadThroughAFilterOnPersonalData(): void
    {
        $this->run_(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']], 'filters' => [['column' => 'note', 'op' => 'contains', 'value' => 'a']]])
            ->assertStatus(422)->assertJsonValidationErrors(['filters.0.column']);
    }

    public function testMoreGroupsThanTheCapAreRefused(): void
    {
        config(['reporting.max_groups' => 3]);
        $this->calls('a', [1]);
        $this->calls('b', [1]);
        $this->calls('c', [1]);
        $this->calls('d', [1]);

        $this->run_(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])->assertStatus(422)->assertJsonValidationErrors(['groupBy']);
        $this->run_(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']], 'filters' => [['column' => 'task', 'op' => 'in', 'value' => ['a', 'b']]]])->assertOk();
    }
}
