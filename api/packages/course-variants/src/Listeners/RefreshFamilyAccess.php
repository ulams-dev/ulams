<?php

namespace Ulams\CourseVariants\Listeners;

use Ulams\Core\Models\User;
use Ulams\CourseVariants\Services\Contracts\CourseVariantServiceContract;
use Ulams\CourseVariants\Services\FamilyAccess;
use Ulams\CourseVariants\Services\RuleAssigner;
use Ulams\Entitlements\Events\AccessProjected;

/**
 * When access to a course of a family changes, the learner's derived `family` entitlements follow (they end with what they
 * derive from), and a learner who has just got access is routed by the family's rules. Must never break the grant or
 * revocation that caused it.
 */
final class RefreshFamilyAccess
{
    public function __construct(private readonly CourseVariantServiceContract $families, private readonly FamilyAccess $access, private readonly RuleAssigner $rules)
    {
    }

    public function handle(AccessProjected $event): void
    {
        try {
            $group = $this->families->groupForCourse($event->courseId);
            if ($group === null || $this->access->busy($event->userId, $group)) {
                return;
            }
            $group->load('members');
            $this->access->refresh($event->userId, $group);
            $user = User::query()->find($event->userId);
            if ($user !== null) {
                $this->rules->evaluateUser($user, $group);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
