<?php

namespace Ulams\Reporting\Definition;

/**
 * A validated, normalised report definition (schema `resources/schemas/report-definition/v1.json`). Built only by
 * `DefinitionValidator`, so every column named here exists in the dataset and every value has the column's type.
 */
final class ReportDefinition
{
    /**
     * @param list<string> $columns row-level output columns (empty for a grouped report)
     * @param list<array{column:string,op:string,value:mixed}> $filters values already coerced to the column type
     * @param array{column:string,from:?string,to:?string}|null $dateRange resolved bounds are computed at run time
     * @param list<array{column:string,bucket:?string,name:string}> $groupBy `name` is the output column
     * @param list<array{fn:string,column:?string,as:string}> $aggregates
     * @param list<array{column:string,dir:string}> $sort
     */
    public function __construct(
        public readonly string $dataset,
        public readonly array $columns,
        public readonly array $filters,
        public readonly ?array $dateRange,
        public readonly ?string $datePreset,
        public readonly array $groupBy,
        public readonly array $aggregates,
        public readonly array $sort,
        public readonly ?int $limit,
    ) {
    }

    public function isGrouped(): bool
    {
        return $this->groupBy !== [] || $this->aggregates !== [];
    }

    /** @return list<string> the names of the output columns of a grouped report */
    public function groupedOutputNames(): array
    {
        return [...array_map(fn (array $g) => $g['name'], $this->groupBy), ...array_map(fn (array $a) => $a['as'], $this->aggregates)];
    }
}
