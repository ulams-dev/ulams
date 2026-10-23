<?php

namespace Ulams\Reporting\Datasets;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * Model calls the tenant paid for: one row per call of `ai_calls` with model, tokens, cost and latency. Prompt and
 * response text, errors, request ids, subject ids and the user who triggered a call are not exposed (ADR 0194).
 */
class AiUsageDataset extends Dataset
{
    public function key(): string
    {
        return 'ai_usage';
    }

    public function label(): string
    {
        return 'AI usage';
    }

    public function description(): string
    {
        return 'One row per model call: task, model, tokens, cost in USD and latency. No prompt text and no user.';
    }

    public function columns(): array
    {
        return [
            Column::string('call_id', 'Call', groupable: false),
            Column::datetime('created_at', 'Called at'),
            Column::date('day', 'Day'),
            Column::string('task', 'Task'),
            Column::string('prompt_id', 'Prompt'),
            Column::string('profile', 'Profile'),
            Column::string('driver', 'Driver'),
            Column::string('model', 'Model'),
            Column::enum('status', 'Status', ['ok', 'invalid', 'refused', 'max_tokens', 'error']),
            Column::string('subject_type', 'Subject type'),
            Column::int('input_tokens', 'Input tokens'),
            Column::int('output_tokens', 'Output tokens'),
            Column::int('cache_creation_tokens', 'Cache write tokens'),
            Column::int('cache_read_tokens', 'Cache read tokens'),
            Column::decimal('cost_usd', 'Cost (USD)'),
            Column::int('latency_ms', 'Latency (ms)'),
        ];
    }

    public function permission(): string
    {
        return ReportingPermissionsEnum::AI_USAGE;
    }

    public function available(): bool
    {
        return Schema::hasTable('ai_calls');
    }

    public function baseQuery(ReportContext $context): Builder
    {
        return DB::table('ai_calls as c')->select([
            'c.id as call_id',
            'c.created_at',
            DB::raw('CAST(c.created_at AS DATE) as day'),
            'c.task',
            'c.prompt_id',
            'c.profile',
            'c.driver',
            DB::raw('COALESCE(c.model_served, c.model_requested) as model'),
            'c.status',
            'c.subject_type',
            'c.input_tokens',
            'c.output_tokens',
            'c.cache_creation_tokens',
            'c.cache_read_tokens',
            DB::raw('CAST(c.cost_micro_usd AS NUMERIC) / 1000000 as cost_usd'),
            'c.latency_ms',
        ]);
    }
}
