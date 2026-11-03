<?php

namespace Ulams\Reporting\Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * A test dataset that behaves like a learner dataset (it has a subject column, a personal column and an optional
 * aggregate-only mode) over the `ai_calls` table, which any test can fill without more packages.
 */
class LearnerCallsDataset extends Dataset
{
    public function __construct(private readonly bool $aggregateOnly = false, private readonly string $key = 'learner_calls')
    {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return 'Learner calls';
    }

    public function description(): string
    {
        return 'test';
    }

    public function columns(): array
    {
        return [
            Column::string('call_id', 'Call', groupable: false),
            Column::datetime('created_at', 'Called at'),
            Column::string('task', 'Task'),
            Column::enum('status', 'Status', ['ok', 'error']),
            Column::int('user_id', 'Learner'),
            Column::string('note', 'Note', personal: true),
            Column::int('input_tokens', 'Input tokens'),
            Column::decimal('cost_usd', 'Cost'),
            Column::bool('cached', 'Cached'),
        ];
    }

    public function permission(): string
    {
        return ReportingPermissionsEnum::AI_USAGE;
    }

    public function subjectColumn(): ?string
    {
        return 'user_id';
    }

    public function aggregateOnly(): bool
    {
        return $this->aggregateOnly;
    }

    public function baseQuery(ReportContext $context): Builder
    {
        return DB::table('ai_calls as c')->select([
            'c.id as call_id', 'c.created_at', 'c.task', 'c.status', 'c.user_id', 'c.request_id as note', 'c.input_tokens',
            DB::raw('CAST(c.cost_micro_usd AS NUMERIC) / 1000000 as cost_usd'),
            DB::raw('(c.cache_read_tokens > 0) as cached'),
        ]);
    }
}
