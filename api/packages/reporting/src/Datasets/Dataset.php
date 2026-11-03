<?php

namespace Ulams\Reporting\Datasets;

use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Support\Column;

/** Defaults for a dataset: temporal columns are the date range candidates, no subject, no restrictions. */
abstract class Dataset implements ReportDataset
{
    public function dateColumns(): array
    {
        return array_values(array_map(fn (Column $c) => $c->name, array_filter($this->columns(), fn (Column $c) => $c->isTemporal())));
    }

    public function subjectColumn(): ?string
    {
        return null;
    }

    public function aggregateOnly(): bool
    {
        return false;
    }

    public function available(): bool
    {
        return true;
    }

    /** @return list<string> */
    public function columnNames(): array
    {
        return array_map(fn (Column $c) => $c->name, $this->columns());
    }

    public function column(string $name): ?Column
    {
        foreach ($this->columns() as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }
}
