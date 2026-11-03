<?php

namespace Ulams\Reporting\Services;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Definition\DefinitionValidator;
use Ulams\Reporting\Definition\InvalidReportDefinition;
use Ulams\Reporting\Query\CompiledQuery;
use Ulams\Reporting\Query\QueryCompiler;
use Ulams\Reporting\Query\Suppression;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * Runs a report definition for a viewer: validate, check the viewer's permission for the dataset, compile, query,
 * withhold small groups, shape the values. Everything that reads data for a report goes through here, so the
 * permission, the scope in `baseQuery()`, the suppression and the personal-data log apply to every caller.
 */
class ReportRunner
{
    /** What a withheld row shows in its first group column. */
    public const WITHHELD = 'withheld (fewer than %d learners)';

    public function __construct(
        private readonly DatasetRegistry $registry,
        private readonly DefinitionValidator $validator,
        private readonly QueryCompiler $compiler,
        private readonly Suppression $suppression,
        private readonly ReportingSettings $settings,
        private readonly ExportLog $log,
    ) {
    }

    /**
     * @param array<string,mixed> $input a report definition (schema v1)
     * @return array{columns:list<array{name:string,type:string,label:string}>,rows:list<array<string,mixed>>,meta:array<string,mixed>}
     *
     * @throws InvalidReportDefinition when the definition is malformed or asks for what its dataset does not offer
     * @throws AuthorizationException when the viewer may not run reports on the dataset
     */
    public function preview(array $input, ReportContext $context, ?CarbonImmutable $now = null): array
    {
        [$definition, $compiled] = $this->prepare($input, $context, $now);
        $cap = min($definition->limit ?? PHP_INT_MAX, $this->settings->previewRows());
        $result = $compiled->grouped ? $this->grouped($compiled, $cap) : $this->rows($compiled, $cap);

        if ($compiled->touchesPersonalData) {
            $this->log->personalRows($context->viewer?->getAuthIdentifier(), $definition->dataset, $input, count($result['rows']));
        }

        return ['columns' => $compiled->output] + $result;
    }

    /**
     * Every row of a report for an export, up to `$cap`: a row-level report is read with a cursor, a grouped one is
     * already small (its groups are capped). Permission, scope and suppression are the same as for a preview.
     *
     * @param array<string,mixed> $input
     * @return array{dataset:string,columns:list<array{name:string,type:string,label:string}>,rows:iterable<array<string,mixed>>}
     */
    public function stream(array $input, ReportContext $context, int $cap): array
    {
        [$definition, $compiled] = $this->prepare($input, $context);
        $cap = min($definition->limit ?? PHP_INT_MAX, $cap);

        if ($compiled->grouped) {
            $rows = $this->grouped($compiled, $cap)['rows'];
        } else {
            $query = (clone $compiled->query)->limit($cap);
            $rows = (function () use ($query, $compiled) {
                foreach ($query->cursor() as $row) {
                    yield $this->shape((array) $row, $compiled->output);
                }
            })();
        }

        return ['dataset' => $definition->dataset, 'columns' => $compiled->output, 'rows' => $rows];
    }

    /**
     * The output columns of a report (name, type, label) without running it: what a feed describes in its metadata.
     * Same checks as a run: the definition must be valid and the viewer must hold the dataset's permission.
     *
     * @param array<string,mixed> $input
     * @return array{columns:list<array{name:string,type:string,label:string}>,grouped:bool}
     */
    public function describe(array $input, ReportContext $context): array
    {
        [, $compiled] = $this->prepare($input, $context);

        return ['columns' => $compiled->output, 'grouped' => $compiled->grouped];
    }

    /**
     * One window of a report: `$take` rows after `$skip`, for a feed that pages. A row-level report is cut in SQL; a
     * grouped one is already small and is sliced after suppression, so a page boundary never shows a withheld group.
     *
     * @param array<string,mixed> $input
     * @return array{columns:list<array{name:string,type:string,label:string}>,grouped:bool,rows:list<array<string,mixed>>,total:?int,more:bool,dataset:string}
     */
    public function window(array $input, ReportContext $context, int $skip, int $take, bool $count): array
    {
        [$definition, $compiled] = $this->prepare($input, $context);
        $cap = min($definition->limit ?? PHP_INT_MAX, $this->settings->exportRows());
        $take = max(0, min($take, $cap - $skip));
        $base = ['columns' => $compiled->output, 'grouped' => $compiled->grouped, 'dataset' => $definition->dataset];

        if ($compiled->grouped) {
            $all = $this->grouped($compiled, $cap)['rows'];

            return $base + ['rows' => array_slice($all, $skip, $take), 'total' => $count ? count($all) : null, 'more' => count($all) > $skip + $take];
        }

        $fetched = $take > 0 ? (clone $compiled->query)->offset($skip)->limit($take + 1)->get()->all() : [];
        $more = count($fetched) > $take;
        $rows = array_map(fn ($row) => $this->shape((array) $row, $compiled->output), array_slice($fetched, 0, $take));
        $total = $count ? min($cap, (int) DB::query()->fromSub($this->unordered($compiled), 'c')->count()) : null;

        return $base + ['rows' => $rows, 'total' => $total, 'more' => $more];
    }

    /** @return array{0:\Ulams\Reporting\Definition\ReportDefinition,1:CompiledQuery} */
    private function prepare(array $input, ReportContext $context, ?CarbonImmutable $now = null): array
    {
        $definition = $this->validator->validate($input);
        $dataset = $this->registry->get($definition->dataset);
        if (!$context->can($dataset->permission())) {
            throw new AuthorizationException('You cannot run reports on this dataset.');
        }

        return [$definition, $this->compiler->compile($definition, $dataset, $context, $now)];
    }

    /** @return array{rows:list<array<string,mixed>>,meta:array<string,mixed>} */
    private function rows(CompiledQuery $compiled, int $cap): array
    {
        $total = (int) DB::query()->fromSub($this->unordered($compiled), 'c')->count();
        if ($compiled->definition->limit !== null) {
            $total = min($total, $compiled->definition->limit);
        }
        $rows = (clone $compiled->query)->limit($cap)->get()->map(fn ($row) => $this->shape((array) $row, $compiled->output))->all();

        return ['rows' => $rows, 'meta' => ['mode' => 'rows', 'total_rows' => $total, 'preview_rows' => count($rows), 'truncated' => $total > count($rows), 'withheld_groups' => 0, 'min_group_size' => null]];
    }

    /** @return array{rows:list<array<string,mixed>>,meta:array<string,mixed>} */
    private function grouped(CompiledQuery $compiled, int $cap): array
    {
        $max = $this->settings->maxGroups();
        $groups = (clone $compiled->query)->limit($max + 1)->get()->map(fn ($row) => (array) $row)->all();
        if (count($groups) > $max) {
            throw new InvalidReportDefinition([['path' => '/groupBy', 'message' => "The report has more than {$max} groups: narrow it with a filter or a coarser grouping."]]);
        }

        $withheld = 0;
        $min = null;
        if ($compiled->subjectColumn !== null) {
            $min = $this->settings->minGroupSize();
            [$groups, $withheld] = $this->suppression->apply($compiled, $groups, $min);
        }

        $visible = array_map(fn (array $row) => $this->shape($row, $compiled->output), $groups);
        $limit = $compiled->definition->limit;
        $total = $limit !== null ? min(count($visible), $limit) : count($visible);
        $rows = array_slice($visible, 0, min($cap, $total));
        $truncated = $total > count($rows);

        if ($withheld > 0) {
            $rows[] = $this->withheldRow($compiled, $min);
        }

        return ['rows' => $rows, 'meta' => ['mode' => 'grouped', 'total_rows' => $total, 'preview_rows' => count($rows), 'truncated' => $truncated, 'withheld_groups' => $withheld, 'min_group_size' => $min]];
    }

    /** The rows of the report without the ordering or the columns, to count them. */
    private function unordered(CompiledQuery $compiled): \Illuminate\Database\Query\Builder
    {
        $query = clone $compiled->query;
        $query->orders = null;
        $query->bindings['order'] = [];

        return $query;
    }

    /** One placeholder for every group that was held back: no figures, only that it exists. */
    private function withheldRow(CompiledQuery $compiled, int $min): array
    {
        $row = ['_withheld' => true];
        foreach ($compiled->output as $i => $column) {
            $row[$column['name']] = $i === 0 && $compiled->groupNames !== [] ? sprintf(self::WITHHELD, $min) : null;
        }

        return $row;
    }

    /**
     * One output row with every value in the type of its column.
     *
     * @param array<string,mixed> $row
     * @param list<array{name:string,type:string,label:string}> $output
     * @return array<string,mixed>
     */
    private function shape(array $row, array $output): array
    {
        $shaped = [];
        foreach ($output as $column) {
            $shaped[$column['name']] = $this->value($row[$column['name']] ?? null, $column['type']);
        }

        return $shaped;
    }

    private function value(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            Column::INT => (int) $value,
            Column::DECIMAL => (float) $value,
            Column::BOOL => (bool) $value,
            Column::DATE => substr((string) $value, 0, 10),
            Column::DATETIME => Carbon::parse((string) $value, config('app.timezone'))->utc()->toIso8601String(),
            default => (string) $value,
        };
    }
}
