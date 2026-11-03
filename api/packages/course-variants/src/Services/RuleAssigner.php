<?php

namespace Ulams\CourseVariants\Services;

use Illuminate\Support\Facades\DB;
use Ulams\Auth\Support\GroupTree;
use Ulams\Core\Models\User;
use Ulams\Courses\Models\CourseGroupPivot;
use Ulams\CourseVariants\Contracts\ConditionMatcher;
use Ulams\CourseVariants\Exceptions\VariantException;
use Ulams\CourseVariants\Models\Assignment;
use Ulams\CourseVariants\Models\Rule;
use Ulams\CourseVariants\Models\VariantGroup;

/**
 * Assignment by attribute (the `variant` action of the enrolment rules, ADR 0192): "department equals Sales puts the
 * learner in the Sales variant". Rules route learners who already have access to the family; they never enrol anyone,
 * never override a learner's own choice or an admin's assignment (a learner with a current variant is skipped), and the
 * first matching rule in position order wins.
 */
final class RuleAssigner
{
    public function __construct(private readonly ConditionMatcher $matcher, private readonly AssignmentService $assignments, private readonly FamilyAccess $access)
    {
    }

    /** @return Assignment|null the assignment made, null when nothing matched or the learner already has a variant */
    public function evaluateUser(User $user, VariantGroup $group, ?Rule $only = null): ?Assignment
    {
        $userId = (int) $user->getKey();
        if ($this->assignments->current($userId, $group) !== null || !$this->access->covers($userId, $group)) {
            return null;
        }
        $rules = Rule::query()->where('group_id', $group->id)->when($only, fn ($q) => $q->whereKey($only->getKey()), fn ($q) => $q->where('active', true))
            ->orderBy('position')->orderBy('id')->get();
        foreach ($rules as $rule) {
            if (!$this->matcher->matches($user, (array) $rule->conditions)) {
                continue;
            }
            try {
                $result = $this->assignments->choose($userId, $group, (int) $rule->course_id, Assignment::RULE, ['key' => 'rule', 'params' => ['name' => $rule->name]], null, (int) $rule->id);
            } catch (VariantException) {
                continue; // the rule points at a variant that is gone or retired: try the next one
            }

            return $result['assignment'];
        }

        return null;
    }

    /** Learners with access to the family and no variant yet, that the rule (or any active rule) puts somewhere. @return int how many were assigned */
    public function evaluateAll(VariantGroup $group, ?Rule $only = null): int
    {
        $assigned = 0;
        $model = config('auth.providers.users.model');
        $model::query()->whereIn('id', $this->candidateIds($group))->orderBy('id')->chunkById(200, function ($users) use ($group, $only, &$assigned) {
            foreach ($users as $user) {
                $assigned += $this->evaluateUser($user, $group, $only) !== null ? 1 : 0;
            }
        });

        return $assigned;
    }

    /**
     * Who the rule would put in a variant now, without doing it.
     *
     * @return array{count:int,sample:list<array{id:int,name:string}>}
     */
    public function preview(VariantGroup $group, Rule $rule, int $sample = 20): array
    {
        $count = 0;
        $users = [];
        $model = config('auth.providers.users.model');
        $model::query()->whereIn('id', $this->candidateIds($group))->orderBy('id')->chunkById(200, function ($chunk) use ($group, $rule, $sample, &$count, &$users) {
            foreach ($chunk as $user) {
                if ($this->assignments->current((int) $user->getKey(), $group) === null && $this->matcher->matches($user, (array) $rule->conditions)) {
                    $count++;
                    if (count($users) < $sample) {
                        $users[] = ['id' => (int) $user->getKey(), 'name' => (string) $user->name];
                    }
                }
            }
        });

        return ['count' => $count, 'sample' => $users];
    }

    /** @return \Illuminate\Support\Collection<int,int> users who may hold access to a course of the family and have no variant yet */
    private function candidateIds(VariantGroup $group)
    {
        $courseIds = $group->courseIds();
        $direct = DB::table('course_user')->whereIn('course_id', $courseIds)->pluck('user_id');
        $groupIds = CourseGroupPivot::query()->whereIn('course_id', $courseIds)->pluck('group_id')->unique()->all();
        $viaGroup = $groupIds === [] ? collect() : DB::table('group_user')->whereIn('group_id', array_merge($groupIds, GroupTree::descendantsOfMany($groupIds)))->pluck('user_id');
        $decided = Assignment::query()->current()->where('group_id', $group->id)->pluck('user_id');

        return $direct->concat($viaGroup)->unique()->diff($decided)->values();
    }
}
