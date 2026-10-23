<?php

namespace Ulams\Reporting\Tests\Feature;

use Ulams\Reporting\Definition\DefinitionValidator;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Tests\TestCase;

/** What a report definition may ask for: the schema, then the capabilities of the dataset's columns. */
class DefinitionValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerLearnerDatasets();
    }

    /** @return array<string,list<string>> path => messages of the first failed validation of $input */
    private function errors(array $input): array
    {
        try {
            $this->app->make(DefinitionValidator::class)->validate($input);
        } catch (InvalidReportDefinition $e) {
            return $e->messages();
        }
        $this->fail('the definition was accepted');
    }

    private function accepted(array $input): void
    {
        $definition = $this->app->make(DefinitionValidator::class)->validate($input);
        $this->assertSame($input['dataset'], $definition->dataset);
    }

    public function testAnUnknownDatasetColumnOrSortIsNamed(): void
    {
        $this->assertArrayHasKey('dataset', $this->errors(['dataset' => 'nope', 'columns' => ['task']]));
        $this->assertArrayHasKey('columns.1', $this->errors(['dataset' => 'learner_calls', 'columns' => ['task', 'password']]));
        $this->assertArrayHasKey('filters.0.column', $this->errors(['dataset' => 'learner_calls', 'columns' => ['task'], 'filters' => [['column' => 'secret', 'op' => 'eq', 'value' => 'x']]]));
        $this->assertArrayHasKey('groupBy.0', $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['nope'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]]));
        $this->assertArrayHasKey('sort.0.column', $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']], 'sort' => [['column' => 'cost_usd']]]));
        $this->assertArrayHasKey('sort.0.column', $this->errors(['dataset' => 'learner_calls', 'columns' => ['task'], 'sort' => [['column' => 'nope']]]));
    }

    public function testSqlInANameIsRefusedAndSqlInAValueIsJustAValue(): void
    {
        foreach (['task; drop table users', 'task"', "task' or '1'='1", 'TASK', 'task--', 'a.b'] as $name) {
            $errors = $this->errors(['dataset' => 'learner_calls', 'columns' => [$name]]);
            $this->assertNotEmpty($errors, "{$name} must not be accepted as a column name");
        }
        $this->errors(['dataset' => 'learner_calls; select 1', 'columns' => ['task']]);
        $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count) from users; --', 'as' => 'n']]]);
        $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n; drop table users']]]);

        $this->accepted(['dataset' => 'learner_calls', 'columns' => ['task'], 'filters' => [['column' => 'task', 'op' => 'eq', 'value' => "'; drop table ai_calls; --"]]]);
    }

    public function testOperatorsAndValuesFollowTheColumnType(): void
    {
        $base = ['dataset' => 'learner_calls', 'columns' => ['task']];

        $this->assertArrayHasKey('filters.0.op', $this->errors($base + ['filters' => [['column' => 'input_tokens', 'op' => 'contains', 'value' => '1']]]));
        $this->assertArrayHasKey('filters.0.op', $this->errors($base + ['filters' => [['column' => 'cached', 'op' => 'gt', 'value' => true]]]));
        $this->assertArrayHasKey('filters.0.value', $this->errors($base + ['filters' => [['column' => 'input_tokens', 'op' => 'eq', 'value' => 'ten']]]));
        $this->assertArrayHasKey('filters.0.value.1', $this->errors($base + ['filters' => [['column' => 'status', 'op' => 'in', 'value' => ['ok', 'bogus']]]]));
        $this->assertArrayHasKey('filters.0.value', $this->errors($base + ['filters' => [['column' => 'created_at', 'op' => 'between', 'value' => ['2026-01-01']]]]));
        $this->assertArrayHasKey('filters.0.value', $this->errors($base + ['filters' => [['column' => 'task', 'op' => 'in', 'value' => 'a']]]));
        $this->assertArrayHasKey('filters.0', $this->errors($base + ['filters' => [['column' => 'task', 'op' => 'eq']]]));
        $this->assertArrayHasKey('filters.0.op', $this->errors($base + ['filters' => [['column' => 'task', 'op' => 'like', 'value' => 'a']]]));
        $this->assertArrayHasKey('filters.0.value', $this->errors($base + ['filters' => [['column' => 'created_at', 'op' => 'gte', 'value' => 'not a date']]]));

        $this->accepted($base + ['filters' => [
            ['column' => 'input_tokens', 'op' => 'between', 'value' => [1, '9']],
            ['column' => 'status', 'op' => 'in', 'value' => ['ok', 'error']],
            ['column' => 'created_at', 'op' => 'gte', 'value' => '2026-01-01'],
            ['column' => 'note', 'op' => 'is_null', 'value' => false],
            ['column' => 'cached', 'op' => 'eq', 'value' => 'true'],
            ['column' => 'task', 'op' => 'contains', 'value' => 'out'],
        ]]);
    }

    public function testGroupingAggregatesAndBucketsFollowTheColumns(): void
    {
        $group = fn (array $extra) => ['dataset' => 'learner_calls'] + $extra;

        $this->assertArrayHasKey('groupBy.0.bucket', $this->errors($group(['groupBy' => [['column' => 'task', 'bucket' => 'month']], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])));
        $this->assertArrayHasKey('groupBy.0', $this->errors($group(['groupBy' => ['cost_usd'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])));
        $this->assertArrayHasKey('groupBy.1', $this->errors($group(['groupBy' => ['task', 'task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])));
        $this->assertArrayHasKey('aggregates.0.column', $this->errors($group(['groupBy' => ['task'], 'aggregates' => [['fn' => 'sum', 'column' => 'task', 'as' => 'n']]])));
        $this->assertArrayHasKey('aggregates.0.column', $this->errors($group(['groupBy' => ['task'], 'aggregates' => [['fn' => 'avg', 'as' => 'n']]])));
        $this->assertArrayHasKey('aggregates.0.column', $this->errors($group(['groupBy' => ['task'], 'aggregates' => [['fn' => 'min', 'column' => 'task', 'as' => 'n']]])));
        $this->assertArrayHasKey('aggregates.1.as', $this->errors($group(['groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n'], ['fn' => 'sum', 'column' => 'cost_usd', 'as' => 'n']]])));
        $this->assertArrayHasKey('aggregates.0.as', $this->errors($group(['groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'task']]])));
        $this->assertArrayHasKey('columns.0', $this->errors($group(['columns' => ['status'], 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]])));

        $this->accepted($group(['groupBy' => ['task', ['column' => 'created_at', 'bucket' => 'month']], 'aggregates' => [
            ['fn' => 'count', 'as' => 'calls'], ['fn' => 'count_distinct', 'column' => 'user_id', 'as' => 'learners'],
            ['fn' => 'sum', 'column' => 'cost_usd', 'as' => 'cost'], ['fn' => 'avg', 'column' => 'input_tokens', 'as' => 'avg_tokens'],
            ['fn' => 'min', 'column' => 'created_at', 'as' => 'first'], ['fn' => 'max', 'column' => 'cost_usd', 'as' => 'most'],
        ], 'sort' => [['column' => 'created_at_month', 'dir' => 'desc'], ['column' => 'calls']], 'columns' => ['task']]));
    }

    public function testPersonalColumnsStayOutOfGroupsAggregatesAndGroupedFilters(): void
    {
        $this->assertArrayHasKey('groupBy.0', $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['note'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]]));
        $this->assertArrayHasKey('aggregates.0.column', $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count_distinct', 'column' => 'note', 'as' => 'n']]]));
        $this->assertArrayHasKey('filters.0.column', $this->errors(['dataset' => 'learner_calls', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']], 'filters' => [['column' => 'note', 'op' => 'contains', 'value' => 'a']]]));

        $this->accepted(['dataset' => 'learner_calls', 'columns' => ['task', 'note'], 'filters' => [['column' => 'note', 'op' => 'contains', 'value' => 'a']]]);
    }

    public function testModeRulesAndAggregateOnlyDatasets(): void
    {
        $this->assertArrayHasKey('columns', $this->errors(['dataset' => 'learner_calls']));
        $this->assertArrayHasKey('columns', $this->errors(['dataset' => 'learner_calls', 'columns' => []]));
        $this->assertArrayHasKey('columns', $this->errors(['dataset' => 'learner_stats', 'columns' => ['task']]), 'an aggregate-only dataset refuses a row-level report');

        $this->accepted(['dataset' => 'learner_stats', 'groupBy' => ['task'], 'aggregates' => [['fn' => 'count', 'as' => 'n']]]);
        $this->accepted(['dataset' => 'learner_stats', 'aggregates' => [['fn' => 'count', 'as' => 'n']]]);
    }

    public function testDateRangesAndTheShapeOfTheDocument(): void
    {
        $base = ['dataset' => 'learner_calls', 'columns' => ['task']];

        $this->assertArrayHasKey('dateRange.column', $this->errors($base + ['dateRange' => ['column' => 'task', 'preset' => 'this_year']]));
        $this->assertArrayHasKey('dateRange', $this->errors($base + ['dateRange' => ['column' => 'created_at']]));
        $this->assertArrayHasKey('dateRange', $this->errors($base + ['dateRange' => ['column' => 'created_at', 'preset' => 'this_year', 'from' => '2026-01-01']]));
        $this->assertArrayHasKey('dateRange.preset', $this->errors($base + ['dateRange' => ['column' => 'created_at', 'preset' => 'forever']]));
        $this->assertArrayHasKey('dateRange.from', $this->errors($base + ['dateRange' => ['column' => 'created_at', 'from' => '2026-02-31']]));
        $this->assertArrayHasKey('dateRange.to', $this->errors($base + ['dateRange' => ['column' => 'created_at', 'from' => '2026-05-01', 'to' => '2026-04-01']]));
        $this->assertArrayHasKey('limit', $this->errors($base + ['limit' => 0]));
        $this->assertArrayHasKey('limit', $this->errors($base + ['limit' => 1000001]));
        $this->assertNotEmpty($this->errors($base + ['surprise' => true]));
        $this->assertNotEmpty($this->errors(['columns' => ['task']]));

        $this->accepted($base + ['dateRange' => ['column' => 'created_at', 'preset' => 'last_90_days'], 'limit' => 1000000, 'version' => 1]);
        $this->accepted($base + ['dateRange' => ['column' => 'created_at', 'from' => '2026-01-01', 'to' => '2026-01-31']]);
    }
}
