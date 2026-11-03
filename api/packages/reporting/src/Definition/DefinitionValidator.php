<?php

namespace Ulams\Reporting\Definition;

use Carbon\Carbon;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Query\DateRanges;
use Ulams\Reporting\Services\DatasetRegistry;
use Ulams\Reporting\Support\Column;

/**
 * Turns request data into a `ReportDefinition`: first the JSON Schema (shape), then the dataset's own capabilities
 * (which columns exist, which operators, groupings and aggregates each one allows, which values fit its type).
 * Nothing the user writes is ever used as SQL; names are looked up, values are coerced and later bound.
 */
class DefinitionValidator
{
    /** The schema version a saved report is stored with. */
    public const VERSION = 1;

    public const SCHEMA = __DIR__ . '/../../resources/schemas/report-definition/v1.json';

    public function __construct(private readonly DatasetRegistry $registry)
    {
    }

    /** @param array<string,mixed> $input */
    public function validate(array $input): ReportDefinition
    {
        $errors = $this->schemaErrors($input);
        if ($errors !== []) {
            throw new InvalidReportDefinition($errors);
        }
        $dataset = $this->registry->get($input['dataset']);
        if ($dataset === null) {
            throw new InvalidReportDefinition([['path' => '/dataset', 'message' => 'Unknown or unavailable dataset.']]);
        }

        $errors = [];
        $definition = $this->capabilities($dataset, $input, $errors);
        if ($errors !== []) {
            throw new InvalidReportDefinition($errors);
        }

        return $definition;
    }

    /** @return list<array{path:string,message:string}> */
    private function schemaErrors(array $input): array
    {
        $validator = new Validator();
        $validator->setMaxErrors(20);
        $validator->setStopAtFirstError(false);
        $validator->parser()->setOption('defaultDraft', '2020-12');
        $schema = json_decode((string) file_get_contents(self::SCHEMA));
        // an empty PHP array is `[]` once encoded, but an object where the schema wants one
        $data = json_decode((string) json_encode($input, JSON_UNESCAPED_UNICODE));
        $result = $validator->validate($data, $schema);
        if ($result->isValid()) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter())->format($result->error(), true) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = ['path' => $path === '' ? '/' : $path, 'message' => (string) $message];
            }
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $input
     * @param list<array{path:string,message:string}> $errors
     */
    private function capabilities(ReportDataset $dataset, array $input, array &$errors): ReportDefinition
    {
        $columns = [];
        foreach ($dataset->columns() as $column) {
            $columns[$column->name] = $column;
        }
        $fail = function (string $path, string $message) use (&$errors): void {
            $errors[] = ['path' => $path, 'message' => $message];
        };
        $known = function (string $name, string $path) use ($columns, $dataset, $fail): ?Column {
            if (!isset($columns[$name])) {
                $fail($path, "Unknown column \"{$name}\" for dataset \"{$dataset->key()}\".");

                return null;
            }

            return $columns[$name];
        };

        $groupedRequest = !empty($input['groupBy']) || !empty($input['aggregates']);
        $listed = $input['columns'] ?? [];

        // --- mode
        if (!$groupedRequest && $listed === []) {
            $fail('/columns', 'List the columns of a row-level report, or group and aggregate.');
        } elseif (!$groupedRequest && $dataset->aggregateOnly()) {
            $fail('/columns', "Dataset \"{$dataset->key()}\" only offers aggregated reports: group it or aggregate.");
        }

        // --- columns
        foreach ($listed as $i => $name) {
            $column = $known($name, "/columns/{$i}");
            if ($column !== null && $groupedRequest && !$this->inGroupBy($input, $name)) {
                $fail("/columns/{$i}", 'A grouped report outputs its groups and aggregates; list only grouped columns here, or omit columns.');
            }
        }

        // --- group by
        $groupBy = [];
        $seen = [];
        foreach ($input['groupBy'] ?? [] as $i => $item) {
            $name = is_array($item) ? $item['column'] : $item;
            $bucket = is_array($item) ? $item['bucket'] : null;
            $column = $known($name, "/groupBy/{$i}" . (is_array($item) ? '/column' : ''));
            if ($column === null) {
                continue;
            }
            if ($column->personal) {
                $fail("/groupBy/{$i}", "Column \"{$name}\" holds personal data and cannot be grouped on.");
            } elseif (!$column->groupable) {
                $fail("/groupBy/{$i}", "Column \"{$name}\" cannot be grouped on.");
            }
            if ($bucket !== null && !$column->isTemporal()) {
                $fail("/groupBy/{$i}/bucket", "Only date columns take a bucket; \"{$name}\" is {$column->type}.");
            }
            $output = $bucket !== null ? "{$name}_{$bucket}" : $name;
            if (isset($seen[$output])) {
                $fail("/groupBy/{$i}", "\"{$output}\" is grouped on twice.");
            }
            $seen[$output] = true;
            $groupBy[] = ['column' => $name, 'bucket' => $bucket, 'name' => $output];
        }

        // --- aggregates
        $aggregates = [];
        foreach ($input['aggregates'] ?? [] as $i => $aggregate) {
            $fn = $aggregate['fn'];
            $name = $aggregate['column'] ?? null;
            $alias = $aggregate['as'];
            if (isset($seen[$alias])) {
                $fail("/aggregates/{$i}/as", "The name \"{$alias}\" is used twice in the output.");
            }
            $seen[$alias] = true;
            if ($name === null) {
                if ($fn !== 'count') {
                    $fail("/aggregates/{$i}/column", "{$fn} needs a column.");
                }
            } elseif (($column = $known($name, "/aggregates/{$i}/column")) !== null) {
                if ($column->personal) {
                    $fail("/aggregates/{$i}/column", "Column \"{$name}\" holds personal data and cannot be aggregated.");
                } elseif (in_array($fn, ['sum', 'avg'], true) && !$column->aggregatable) {
                    $fail("/aggregates/{$i}/column", "{$fn} needs a numeric column; \"{$name}\" is {$column->type}.");
                } elseif (in_array($fn, ['min', 'max'], true) && !$column->aggregatable && !$column->isTemporal()) {
                    $fail("/aggregates/{$i}/column", "{$fn} needs a numeric or date column; \"{$name}\" is {$column->type}.");
                }
            }
            $aggregates[] = ['fn' => $fn, 'column' => $name, 'as' => $alias];
        }

        // --- filters (personal columns only filter a row-level report: a filtered group count would leak them)
        $filters = [];
        foreach ($input['filters'] ?? [] as $i => $filter) {
            $column = $known($filter['column'], "/filters/{$i}/column");
            if ($column === null) {
                continue;
            }
            if ($groupedRequest && $column->personal) {
                $fail("/filters/{$i}/column", "Column \"{$column->name}\" holds personal data and cannot filter a grouped report.");
            }
            if (!in_array($filter['op'], $column->filterOps, true)) {
                $fail("/filters/{$i}/op", "Operator \"{$filter['op']}\" does not apply to {$column->type} column \"{$column->name}\".");
                continue;
            }
            $value = $this->filterValue($column, $filter['op'], $filter['value'] ?? null, "/filters/{$i}/value", $fail);
            $filters[] = ['column' => $column->name, 'op' => $filter['op'], 'value' => $value];
        }

        // --- date range
        $range = null;
        $preset = null;
        if (isset($input['dateRange'])) {
            $dr = $input['dateRange'];
            $column = $known($dr['column'], '/dateRange/column');
            if ($column !== null && !in_array($column->name, $dataset->dateColumns(), true)) {
                $fail('/dateRange/column', "Column \"{$column->name}\" is not a date column of dataset \"{$dataset->key()}\".");
            }
            $hasBounds = isset($dr['from']) || isset($dr['to']);
            if (isset($dr['preset']) === $hasBounds) {
                $fail('/dateRange', 'Give either a preset or a from and to date.');
            }
            foreach (['from', 'to'] as $edge) {
                if (isset($dr[$edge]) && !$this->validDate($dr[$edge])) {
                    $fail("/dateRange/{$edge}", 'Not a valid date.');
                }
            }
            if (isset($dr['from'], $dr['to']) && $dr['from'] > $dr['to']) {
                $fail('/dateRange/to', 'The end date is before the start date.');
            }
            $preset = $dr['preset'] ?? null;
            if ($preset !== null && !DateRanges::known($preset)) {
                $fail('/dateRange/preset', 'Unknown preset.');
            }
            $range = ['column' => $dr['column'], 'from' => $dr['from'] ?? null, 'to' => $dr['to'] ?? null];
        }

        // --- sort
        $outputs = $groupedRequest ? [...array_column($groupBy, 'name'), ...array_column($aggregates, 'as')] : array_keys($columns);
        $sort = [];
        foreach ($input['sort'] ?? [] as $i => $item) {
            if (!in_array($item['column'], $outputs, true)) {
                $fail("/sort/{$i}/column", $groupedRequest
                    ? "Sort by a group or an aggregate of this report; \"{$item['column']}\" is neither."
                    : "Unknown column \"{$item['column']}\" for dataset \"{$dataset->key()}\".");
                continue;
            }
            $sort[] = ['column' => $item['column'], 'dir' => $item['dir'] ?? 'asc'];
        }

        return new ReportDefinition(
            dataset: $dataset->key(),
            columns: $groupedRequest ? [] : array_values($listed),
            filters: $filters,
            dateRange: $range,
            datePreset: $preset,
            groupBy: $groupBy,
            aggregates: $aggregates,
            sort: $sort,
            limit: $input['limit'] ?? null,
        );
    }

    private function validDate(string $value): bool
    {
        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function inGroupBy(array $input, string $name): bool
    {
        foreach ($input['groupBy'] ?? [] as $item) {
            if ((is_array($item) ? $item['column'] : $item) === $name) {
                return true;
            }
        }

        return false;
    }

    /** @param \Closure(string,string):void $fail */
    private function filterValue(Column $column, string $op, mixed $value, string $path, \Closure $fail): mixed
    {
        if ($op === 'is_null') {
            return $value === null ? true : (bool) $value;
        }
        if ($op === 'in' || $op === 'not_in') {
            $coerced = [];
            foreach ($value as $i => $item) {
                $coerced[] = $this->coerce($column, $op, $item, "{$path}/{$i}", $fail, false);
            }

            return $coerced;
        }
        if ($op === 'between') {
            return [$this->coerce($column, $op, $value[0], $path . '/0', $fail, false), $this->coerce($column, $op, $value[1], $path . '/1', $fail, true)];
        }

        return $this->coerce($column, $op, $value, $path, $fail, in_array($op, ['lte', 'gt'], true));
    }

    /**
     * One value in the type of its column. A date without a time bounds a datetime column from above at the end of that
     * day (`lte`, `gt`, the end of `between`) and from below at its start.
     *
     * @param \Closure(string,string):void $fail
     */
    private function coerce(Column $column, string $op, mixed $value, string $path, \Closure $fail, bool $endOfDay): mixed
    {
        switch ($column->type) {
            case Column::INT:
                if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,18}$/', $value))) {
                    return (int) $value;
                }
                break;
            case Column::DECIMAL:
                if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
                    return (float) $value;
                }
                break;
            case Column::BOOL:
                if (is_bool($value)) {
                    return $value;
                }
                if (in_array($value, ['true', 'false', 1, 0, '1', '0'], true)) {
                    return in_array($value, ['true', 1, '1'], true);
                }
                break;
            case Column::ENUM:
                if (is_string($value) && in_array($value, $column->enumValues, true)) {
                    return $value;
                }
                $fail($path, "Not one of: " . implode(', ', $column->enumValues) . '.');

                return null;
            case Column::DATE:
                if (is_string($value) && $this->validDate($value)) {
                    return $value;
                }
                break;
            case Column::DATETIME:
                if (is_string($value) && $value !== '') {
                    try {
                        $parsed = Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'));
                    } catch (\Throwable) {
                        break;
                    }
                    $dateOnly = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

                    return ($dateOnly && $endOfDay ? $parsed->endOfDay() : $parsed)->format('Y-m-d H:i:s');
                }
                break;
            default:
                if (is_string($value) || is_int($value) || is_float($value)) {
                    return (string) $value;
                }
        }
        $fail($path, "Not a valid {$column->type} value for \"{$column->name}\".");

        return null;
    }
}
