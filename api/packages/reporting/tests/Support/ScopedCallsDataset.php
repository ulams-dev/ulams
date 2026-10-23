<?php

namespace Ulams\Reporting\Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\Reporting\Datasets\Dataset;
use Ulams\Reporting\Enums\ReportingPermissionsEnum;
use Ulams\Reporting\Support\Column;
use Ulams\Reporting\Support\ReportContext;

/** A test dataset with a scope: a viewer sees their own rows only, unless they manage reports (then every row). */
class ScopedCallsDataset extends Dataset
{
    public function key(): string
    {
        return 'my_calls';
    }

    public function label(): string
    {
        return 'My calls';
    }

    public function description(): string
    {
        return 'test';
    }

    public function columns(): array
    {
        return [Column::string('task', 'Task'), Column::int('user_id', 'Learner')];
    }

    public function permission(): string
    {
        return ReportingPermissionsEnum::READ;
    }

    public function baseQuery(ReportContext $context): Builder
    {
        $query = DB::table('ai_calls as c')->select(['c.task', 'c.user_id']);

        return $context->can(ReportingPermissionsEnum::MANAGE) ? $query : $query->where('c.user_id', $context->viewer?->getAuthIdentifier() ?? 0);
    }
}
