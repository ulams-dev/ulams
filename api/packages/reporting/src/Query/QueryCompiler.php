<?php

namespace Ulams\Reporting\Query;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Definition\ReportDefinition;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * Builds the query of a validated report definition. The dataset's `baseQuery()` is wrapped as a derived table `t`, so
 * a report can only reach the columns the dataset declares, and every identifier below comes from the registry or from
 * a closed list (aggregate functions, date buckets); every value is a binding. There is no path from request data to
 * SQL text.
 *
 * Grouped reports also carry `__subjects`, the distinct learners per group, which `Suppression` needs; it is removed
 * before anything leaves the server.
 */
class QueryCompiler
{
    public const SUBJECTS = '__subjects';

    private const BUCKETS = ['day', 'week', 'month', 'quarter', 'year'];
    private const FUNCTIONS = ['count', 'count_distinct', 'sum', 'avg', 'min', 'max'];

    public function compile(ReportDefinition $definition, ReportDataset $dataset, ReportContext $context, ?CarbonImmutable $now = null): CompiledQuery
    {
        $columns = [];
        foreach ($dataset->columns() as $column) {
            $columns[$column->name] = $column;
        }

        $base = $dataset->baseQuery($context);
        $query = DB::query()->fromSub($base, 't');
        $this->filters($query, $definition, $columns, $now);

        return $definition->isGrouped()
            ? $this->grouped($query, $definition, $dataset, $columns)
            : $this->rows($query, $definition, $dataset, $columns);
    }

    /** @param array<string,Column> $columns */
    private function rows(Builder $query, ReportDefinition $definition, ReportDataset $dataset, array $columns): CompiledQuery
    {
        $query->select($definition->columns);
        foreach ($definition->sort as $sort) {
            $query->orderBy($sort['column'], $sort['dir']);
        }
        // a stable order, so that a page of a long report is the same page every time
        $first = array_key_first($columns);
        if (!in_array($first, array_column($definition->sort, 'column'), true)) {
            $query->orderBy($first);
        }

        $output = array_map(fn (string $name) => ['name' => $name, 'type' => $columns[$name]->type, 'label' => $columns[$name]->label], $definition->columns);
        $personal = (bool) array_filter($definition->columns, fn (string $name) => $columns[$name]->personal);

        return new CompiledQuery($definition, $query, $output, false, null, [], null, $personal);
    }

    /** @param array<string,Column> $columns */
    private function grouped(Builder $query, ReportDefinition $definition, ReportDataset $dataset, array $columns): CompiledQuery
    {
        $grammar = $query->getGrammar();
        $wrap = fn (string $name) => $grammar->wrap('t.' . $name);
        $subject = $dataset->subjectColumn();

        $select = [];
        $output = [];
        $groupOrdinals = [];
        $keys = [];
        foreach ($definition->groupBy as $i => $group) {
            $column = $columns[$group['column']];
            $expression = $this->groupExpression($wrap($group['column']), $column, $group['bucket']);
            $keys[] = $expression;
            $select[] = $expression . ' as ' . $grammar->wrap($group['name']);
            $groupOrdinals[] = (string) ($i + 1);
            $output[] = ['name' => $group['name'], 'type' => $group['bucket'] !== null ? Column::DATE : $column->type, 'label' => $group['bucket'] !== null ? "{$column->label} ({$group['bucket']})" : $column->label];
        }
        foreach ($definition->aggregates as $aggregate) {
            $select[] = $this->aggregate($aggregate, $columns, $wrap) . ' as ' . $grammar->wrap($aggregate['as']);
            $output[] = ['name' => $aggregate['as'], 'type' => $this->aggregateType($aggregate, $columns), 'label' => $aggregate['as']];
        }
        if ($subject !== null) {
            $select[] = 'COUNT(DISTINCT ' . $wrap($subject) . ') as ' . $grammar->wrap(self::SUBJECTS);
        }

        $query->selectRaw(implode(', ', $select));
        if ($groupOrdinals !== []) {
            $query->groupByRaw(implode(', ', $groupOrdinals));
        }

        if ($definition->sort === []) {
            foreach ($definition->groupBy as $group) {
                $query->orderBy($group['name']);
            }
        }
        foreach ($definition->sort as $sort) {
            $query->orderBy($sort['column'], $sort['dir']);
        }

        // the filtered rows with their group keys and learner: what the suppression check counts across groups
        $detail = null;
        if ($subject !== null && $keys !== []) {
            $detail = $this->detail($query, $keys, $wrap($subject), $grammar);
        }

        return new CompiledQuery($definition, $query, $output, true, $subject, array_column($definition->groupBy, 'name'), $detail, false);
    }

    /**
     * @param list<string> $keys group key expressions
     * @return Builder rows `g0 .. gn, __s`: every filtered row with its group and learner
     */
    private function detail(Builder $grouped, array $keys, string $subjectSql, $grammar): Builder
    {
        $select = [];
        foreach ($keys as $i => $expression) {
            $select[] = $expression . ' as ' . $grammar->wrap("g{$i}");
        }
        $select[] = $subjectSql . ' as ' . $grammar->wrap('__s');

        $detail = clone $grouped;
        $detail->columns = null;
        $detail->groups = null;
        $detail->orders = null;
        $detail->bindings['select'] = [];
        $detail->bindings['order'] = [];

        return $detail->selectRaw(implode(', ', $select));
    }

    /** @param array<string,Column> $columns */
    private function filters(Builder $query, ReportDefinition $definition, array $columns, ?CarbonImmutable $now): void
    {
        foreach ($definition->filters as $filter) {
            $name = $filter['column'];
            $value = $filter['value'];
            match ($filter['op']) {
                'eq' => $query->where($name, '=', $value),
                'neq' => $query->where($name, '<>', $value),
                'in' => $query->whereIn($name, $value),
                'not_in' => $query->whereNotIn($name, $value),
                'lt' => $query->where($name, '<', $value),
                'lte' => $query->where($name, '<=', $value),
                'gt' => $query->where($name, '>', $value),
                'gte' => $query->where($name, '>=', $value),
                'between' => $query->whereBetween($name, $value),
                'contains' => $query->where($name, 'ilike', '%' . addcslashes((string) $value, '\\%_') . '%'),
                'is_null' => $value ? $query->whereNull($name) : $query->whereNotNull($name),
            };
        }

        if ($definition->dateRange !== null) {
            $range = $definition->dateRange;
            $column = $columns[$range['column']];
            if ($definition->datePreset !== null) {
                [$from, $to] = DateRanges::resolve($definition->datePreset, $now);
            } else {
                $from = $range['from'] !== null ? CarbonImmutable::parse($range['from'], config('app.timezone'))->startOfDay() : null;
                $to = $range['to'] !== null ? CarbonImmutable::parse($range['to'], config('app.timezone'))->endOfDay() : null;
            }
            $format = $column->type === Column::DATE ? 'Y-m-d' : 'Y-m-d H:i:s';
            $from !== null && $query->where($range['column'], '>=', $from->format($format));
            $to !== null && $query->where($range['column'], '<=', $to->format($format));
        }
    }

    private function groupExpression(string $wrapped, Column $column, ?string $bucket): string
    {
        if ($bucket === null) {
            return $wrapped;
        }
        // the bucket comes from the closed list the schema enforces; it is still checked here, where it becomes SQL text
        if (!in_array($bucket, self::BUCKETS, true)) {
            throw new \LogicException("Unknown bucket {$bucket}.");
        }

        return "CAST(date_trunc('{$bucket}', CAST({$wrapped} AS TIMESTAMP)) AS DATE)";
    }

    /**
     * @param array{fn:string,column:?string,as:string} $aggregate
     * @param array<string,Column> $columns
     */
    private function aggregate(array $aggregate, array $columns, \Closure $wrap): string
    {
        if (!in_array($aggregate['fn'], self::FUNCTIONS, true)) {
            throw new \LogicException("Unknown aggregate {$aggregate['fn']}.");
        }
        $target = $aggregate['column'] === null ? '*' : $wrap($aggregate['column']);

        return match ($aggregate['fn']) {
            'count' => "COUNT({$target})",
            'count_distinct' => "COUNT(DISTINCT {$target})",
            'sum' => "SUM({$target})",
            'avg' => "AVG({$target})",
            'min' => "MIN({$target})",
            'max' => "MAX({$target})",
        };
    }

    /**
     * @param array{fn:string,column:?string,as:string} $aggregate
     * @param array<string,Column> $columns
     */
    private function aggregateType(array $aggregate, array $columns): string
    {
        $source = $aggregate['column'] !== null ? $columns[$aggregate['column']] : null;

        return match ($aggregate['fn']) {
            'count', 'count_distinct' => Column::INT,
            'avg' => Column::DECIMAL,
            'sum' => $source->type === Column::INT ? Column::INT : Column::DECIMAL,
            'min', 'max' => $source->type,
        };
    }
}
