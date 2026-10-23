<?php

namespace Ulams\AuthoringMetrics\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\AuthoringMetrics\Enums\AuthoringMetricsPermissionsEnum;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/**
 * The authoring metric as a report dataset: one row per course built with the Course Builder, straight from the
 * `authoring_course_metrics` snapshot (time in seconds, cost in USD, counts, the split of AI elements). The figures
 * are the tenant's own measurements; a course with no recorded review time has `author_minutes` empty, never zero.
 */
class AuthoringDataset extends Dataset
{
    public function key(): string
    {
        return 'authoring';
    }

    public function label(): string
    {
        return 'Authoring metrics';
    }

    public function description(): string
    {
        return 'One row per course built with the Course Builder: generation time, cost, counts and how many AI elements were accepted, revised or edited.';
    }

    public function columns(): array
    {
        return [
            Column::int('course_id', 'Course id', groupable: true),
            Column::string('course_title', 'Course'),
            Column::datetime('course_created_at', 'Course created'),
            Column::decimal('generation_seconds', 'Generation time (s)'),
            Column::decimal('ai_edit_seconds', 'AI edit time (s)'),
            Column::decimal('interview_seconds', 'Interview time (s)'),
            Column::decimal('model_seconds', 'Model time (s)'),
            Column::decimal('author_minutes', 'Author review time (min)'),
            Column::decimal('cost_build_usd', 'Build cost (USD)'),
            Column::decimal('cost_maintenance_usd', 'Maintenance cost (USD)'),
            Column::int('llm_calls', 'Model calls'),
            Column::int('llm_calls_failed', 'Failed model calls'),
            Column::int('lessons', 'Lessons'),
            Column::int('questions', 'Questions'),
            Column::int('blocks', 'Blocks'),
            Column::int('cited_blocks', 'Cited blocks'),
            Column::int('ai_elements', 'AI elements'),
            Column::int('ai_accepted_unchanged', 'AI elements accepted unchanged'),
            Column::int('ai_revised_on_request', 'AI elements revised on request'),
            Column::int('ai_edited_by_author', 'AI elements edited by the author'),
            Column::int('ai_unknown', 'AI elements of unknown provenance'),
            Column::int('author_elements', 'Author-written elements'),
            Column::int('learner_minutes_estimate', 'Learner minutes (estimate)'),
            Column::int('method_version', 'Method version', groupable: true),
            Column::datetime('computed_at', 'Computed'),
        ];
    }

    public function dateColumns(): array
    {
        return ['course_created_at', 'computed_at'];
    }

    public function permission(): string
    {
        return AuthoringMetricsPermissionsEnum::READ;
    }

    public function baseQuery(ReportContext $context): Builder
    {
        return DB::table('authoring_course_metrics as m')
            ->leftJoin('courses as c', 'c.id', '=', 'm.course_id')
            ->select([
                'm.course_id',
                'c.title as course_title',
                'c.created_at as course_created_at',
                'm.generation_seconds',
                'm.ai_edit_seconds',
                'm.interview_seconds',
                'm.model_seconds',
                'm.author_minutes',
                DB::raw('CAST(m.cost_build_micro_usd AS NUMERIC) / 1000000 as cost_build_usd'),
                DB::raw('CAST(m.cost_maintenance_micro_usd AS NUMERIC) / 1000000 as cost_maintenance_usd'),
                'm.llm_calls',
                'm.llm_calls_failed',
                'm.lessons',
                'm.questions',
                'm.blocks',
                'm.cited_blocks',
                'm.ai_elements',
                'm.ai_accepted_unchanged',
                'm.ai_revised_on_request',
                'm.ai_edited_by_author',
                'm.ai_unknown',
                'm.author_elements',
                'm.learner_minutes_estimate',
                'm.method_version',
                'm.computed_at',
            ]);
    }
}
