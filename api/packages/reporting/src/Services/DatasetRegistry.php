<?php

namespace Ulams\Reporting\Services;

use LogicException;
use Ulams\Reporting\Contracts\ReportDataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * The datasets a report can be built on, registered by the package that owns the data (compliance, authoring
 * metrics, AI usage). Column names the query compiler accepts come from here and nowhere else.
 */
class DatasetRegistry
{
    /** @var array<string, ReportDataset> */
    private array $datasets = [];

    public function register(ReportDataset $dataset): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $dataset->key())) {
            throw new LogicException("Dataset key \"{$dataset->key()}\" must be lower snake case.");
        }
        if (isset($this->datasets[$dataset->key()])) {
            throw new LogicException("Dataset \"{$dataset->key()}\" is registered twice.");
        }
        $this->datasets[$dataset->key()] = $dataset;
    }

    /** Every registered dataset whose tables exist, in registration order. @return array<string, ReportDataset> */
    public function all(): array
    {
        return array_filter($this->datasets, fn (ReportDataset $d) => $d->available());
    }

    public function get(string $key): ?ReportDataset
    {
        $dataset = $this->datasets[$key] ?? null;

        return $dataset !== null && $dataset->available() ? $dataset : null;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /** @return list<string> */
    public function columnNames(string $key): array
    {
        $dataset = $this->get($key) ?? throw new LogicException("Unknown dataset \"{$key}\".");

        return array_map(fn (Column $c) => $c->name, $dataset->columns());
    }

    /** The datasets the viewer holds the permission for. @return array<string, ReportDataset> */
    public function runnableBy(ReportContext $context): array
    {
        return array_filter($this->all(), fn (ReportDataset $d) => $context->can($d->permission()));
    }
}
