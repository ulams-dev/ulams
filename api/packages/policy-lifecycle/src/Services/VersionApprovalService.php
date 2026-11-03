<?php

namespace Ulams\PolicyLifecycle\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Ulams\Auth\Models\Group;
use Ulams\Compliance\Models\Rule;
use Ulams\Compliance\Services\CourseSettings;
use Ulams\Compliance\Services\PathService;
use Ulams\Compliance\Services\RuleEngine;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\PolicyLifecycle\Exceptions\PolicyException;
use Ulams\PolicyLifecycle\Models\Policy;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Models\PolicyVersion;

/**
 * Approval of the first version (ADR 0140, flow step 5): the owner has reviewed and published every department's
 * course in the Studio; approving the version makes those courses mandatory for the department's group, enrols the
 * group through an enrolment rule on a one-course path (so reminders, escalation and reports apply unchanged) and
 * makes the version the one learners are trained on. The decision is the owner's; nothing here is automatic.
 */
final class VersionApprovalService
{
    public function __construct(
        private readonly PathService $paths,
        private readonly CourseSettings $settings,
        private readonly RuleEngine $rules,
        private readonly PolicyAudit $audit,
    ) {
    }

    /**
     * What is still missing before the initial version can be approved, per department.
     *
     * @return array<int,string> empty when it can be approved
     */
    public function blockers(Policy $policy): array
    {
        $problems = [];
        foreach ($policy->variants as $variant) {
            $session = $variant->session_id !== null ? Session::query()->find($variant->session_id) : null;
            $course = $session?->course_id !== null ? Course::query()->find($session->course_id) : null;
            if ($session === null) {
                $problems[] = "{$variant->department_label}: the course has not been generated yet.";
            } elseif ($course === null) {
                $problems[] = "{$variant->department_label}: review and apply the course in the Studio first.";
            } elseif ($course->status !== CourseStatusEnum::PUBLISHED) {
                $problems[] = "{$variant->department_label}: publish the course in the Studio first.";
            }
        }

        return $problems;
    }

    /** @throws PolicyException */
    public function approveInitial(Policy $policy, PolicyVersion $version, Authenticatable $actor): PolicyVersion
    {
        if ($version->policy_id !== $policy->id) {
            throw new PolicyException('That version belongs to another policy.', 404);
        }
        if ($version->change_class !== PolicyVersion::INITIAL || $version->status !== PolicyVersion::DRAFT) {
            throw new PolicyException('Only the first version, while it is a draft, is approved here.', 409);
        }
        $problems = $this->blockers($policy);
        if ($problems !== []) {
            throw new PolicyException("The policy cannot be approved yet.\n" . implode("\n", $problems));
        }

        $rules = [];
        DB::transaction(function () use ($policy, $version, $actor, &$rules) {
            foreach ($policy->variants as $variant) {
                $rules[] = $this->activate($policy, $variant);
            }
            $version->forceFill([
                'status' => PolicyVersion::APPROVED,
                'approved_by' => $actor->getAuthIdentifier(),
                'approved_at' => now(),
                'effective_at' => now(),
            ])->save();
            $policy->forceFill(['current_version_id' => $version->id])->save();
            $this->audit->record('policy.version.approved', $policy, $version, [
                'actor_for_owner' => (int) $actor->getAuthIdentifier() !== (int) $policy->owner_user_id,
                'departments' => $policy->variants->map(fn (PolicyVariant $v) => $v->department_label)->all(),
            ]);
        });
        foreach ($rules as [$variant, $rule]) {
            $this->enrolMembers($variant, $rule);
        }

        return $version->refresh();
    }

    /** @return array{0:PolicyVariant,1:Rule} */
    private function activate(Policy $policy, PolicyVariant $variant): array
    {
        $session = Session::query()->findOrFail($variant->session_id);
        $courseId = (int) $session->course_id;
        $group = Group::query()->findOrFail($variant->group_id);

        $this->settings->save($courseId, ['mandatory' => true, 'default_due_days' => $policy->major_due_days]);
        $path = $this->paths->save(['title' => mb_substr($policy->title . ' / ' . $variant->department_label, 0, 255), 'steps' => [['course_id' => $courseId]]]);
        $rule = Rule::query()->create([
            'name' => mb_substr('Policy: ' . $policy->title . ' / ' . $variant->department_label, 0, 255),
            'active' => true,
            'conditions' => [['attribute' => 'group', 'operator' => 'equals', 'value' => [(string) $group->name]]],
            'path_id' => $path->getKey(),
            'due_days' => $policy->major_due_days,
        ]);
        $variant->forceFill(['course_id' => $courseId, 'path_id' => $path->getKey(), 'rule_id' => $rule->getKey()])->save();

        return [$variant, $rule];
    }

    /** Group members who already exist are enrolled now; later members by the rule when their user is saved or the daily run. */
    private function enrolMembers(PolicyVariant $variant, Rule $rule): void
    {
        $group = Group::query()->find($variant->group_id);
        if ($group === null) {
            return;
        }
        // the application's own user model: the rule engine reads the user's groups through it
        $model = config('auth.providers.users.model');
        $model::query()->whereIn('id', $group->users()->pluck('users.id'))->orderBy('id')->each(fn ($user) => $this->rules->evaluateUser($user, $rule));
    }
}
