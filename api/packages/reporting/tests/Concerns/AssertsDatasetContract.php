<?php

namespace Ulams\Reporting\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * What every dataset promises the query compiler: `baseQuery()` runs, and selects one SQL column per declared column,
 * named as the column and in the declared order. Works on an empty table (the statement is described, not fetched).
 */
trait AssertsDatasetContract
{
    protected function assertDatasetContract(ReportDataset $dataset, ?ReportContext $context = null): void
    {
        $this->assertTrue($dataset->available(), "{$dataset->key()} is not available in this test");
        $query = $dataset->baseQuery($context ?? new ReportContext(null));

        $statement = DB::connection()->getPdo()->prepare($query->toSql());
        $statement->execute($query->getConnection()->prepareBindings($query->getBindings()));
        $selected = [];
        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $selected[] = $statement->getColumnMeta($i)['name'];
        }

        $declared = array_map(fn (Column $c) => $c->name, $dataset->columns());
        $this->assertSame($declared, $selected, "{$dataset->key()}: the base query selects the declared columns in order");
        $this->assertSame(count($declared), count(array_unique($declared)), "{$dataset->key()}: column names are unique");
        $this->assertNotEmpty($dataset->dateColumns() ?: ['none'], 'date columns are declared');
        foreach ($dataset->dateColumns() as $name) {
            $this->assertTrue(collect($dataset->columns())->contains(fn (Column $c) => $c->name === $name && $c->isTemporal()), "{$dataset->key()}.{$name} is a date column");
        }
        if ($dataset->subjectColumn() !== null) {
            $this->assertContains($dataset->subjectColumn(), $declared, "{$dataset->key()}: the subject column is declared");
        }
        foreach ($dataset->columns() as $column) {
            $this->assertSame([], array_diff($column->filterOps, ['eq', 'neq', 'in', 'not_in', 'lt', 'lte', 'gt', 'gte', 'between', 'contains', 'is_null']), "{$dataset->key()}.{$column->name}: known operators only");
        }
    }
}
