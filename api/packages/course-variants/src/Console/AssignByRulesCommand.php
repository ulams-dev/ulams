<?php

namespace Ulams\CourseVariants\Console;

use Illuminate\Console\Command;
use Ulams\CourseVariants\Models\Rule;
use Ulams\CourseVariants\Models\VariantGroup;
use Ulams\CourseVariants\Services\RuleAssigner;

/** Applies the assignment rules of every family to learners who have access and no variant yet (also catches attribute changes). */
class AssignByRulesCommand extends Command
{
    protected $signature = 'course-variants:assign-by-rules';

    protected $description = 'Put learners without a variant into the variant an active assignment rule gives them';

    public function handle(RuleAssigner $assigner): int
    {
        $assigned = 0;
        VariantGroup::query()->with('members')->whereIn('id', Rule::query()->where('active', true)->select('group_id'))->each(function (VariantGroup $group) use ($assigner, &$assigned) {
            $assigned += $assigner->evaluateAll($group);
        });
        $this->info("Assigned {$assigned} learner(s).");

        return self::SUCCESS;
    }
}
