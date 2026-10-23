<?php

namespace Ulams\Reporting\Contracts;

use Illuminate\Database\Query\Builder;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * A set of rows a report can be built on. Packages register their own datasets with the `DatasetRegistry`;
 * the `reporting` package knows nothing about their tables.
 */
interface ReportDataset
{
    /** Stable identifier used in report definitions, schedules and feeds, for example `assignments`. */
    public function key(): string;

    public function label(): string;

    /** One sentence for the dataset picker: what a row is. */
    public function description(): string;

    /** @return list<Column> every column a report may select, filter, group or aggregate */
    public function columns(): array;

    /** @return list<string> names of the columns the date range of a report may use */
    public function dateColumns(): array;

    /** The permission a viewer needs to run a report on this dataset, for example `compliance_report`. */
    public function permission(): string;

    /**
     * The column that identifies a learner, used for small-group suppression (distinct subjects per group).
     * Null for datasets that hold no learner data.
     */
    public function subjectColumn(): ?string;

    /** True when only grouped output is allowed (a dataset made of aggregates). */
    public function aggregateOnly(): bool;

    /** False when the tables the dataset reads do not exist (an optional package is not installed). */
    public function available(): bool;

    /**
     * The rows the viewer may see, one SQL column per declared column, named exactly as the column. Scope is
     * applied here and nowhere else. Bindings only: no user input becomes SQL.
     */
    public function baseQuery(ReportContext $context): Builder;
}
