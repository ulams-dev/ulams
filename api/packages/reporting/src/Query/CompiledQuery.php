<?php

namespace Ulams\Reporting\Query;

use Illuminate\Database\Query\Builder;
use Ulams\Reporting\Definition\ReportDefinition;

/**
 * A report definition compiled against its dataset: the parameterised query, the output columns (name, type, label)
 * and, for a grouped report on a dataset with learners, what small-group suppression needs.
 */
final class CompiledQuery
{
    /**
     * @param list<array{name:string,type:string,label:string}> $output
     * @param list<string> $groupNames output names of the group keys (empty for a grand total)
     * @param Builder|null $detail the filtered rows with group keys and the subject, for the suppression check
     */
    public function __construct(
        public readonly ReportDefinition $definition,
        public readonly Builder $query,
        public readonly array $output,
        public readonly bool $grouped,
        public readonly ?string $subjectColumn,
        public readonly array $groupNames,
        public readonly ?Builder $detail,
        public readonly bool $touchesPersonalData,
    ) {
    }

    /** The statement with `?` placeholders, for inspection and tests. */
    public function sql(): string
    {
        return $this->query->toSql();
    }

    /** @return list<mixed> */
    public function bindings(): array
    {
        return $this->query->getBindings();
    }
}
