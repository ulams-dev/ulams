<?php

namespace Ulams\PolicyLifecycle\Tests\Support;

use Ulams\Auth\Models\Group;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Models\User as CoreUser;
use Ulams\CourseBuilder\Models\Session;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Courses\Tests\Models\User;
use Ulams\PolicyLifecycle\Models\Policy;

/** An approved policy with people enrolled by the departments' groups, shared by the dashboard and change tests. */
trait TrainsPeople
{
    protected function named(string $first, string $last): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $user->assignRole(UserRole::STUDENT);

        return $user;
    }

    /**
     * Two engineers (Ada, Linus) and one salesperson (Sam), all enrolled; nobody has finished.
     *
     * @return array{0:Policy,1:array<int,User>,2:User}
     */
    protected function trainedPolicy($owner, array $overrides = []): array
    {
        $id = $this->createPolicy($owner, $overrides);
        $policy = Policy::query()->findOrFail($id);
        $engineers = [$this->named('Ada', 'Engineer'), $this->named('Linus', 'Engineer')];
        $seller = $this->named('Sam', 'Seller');
        foreach ($engineers as $user) {
            Group::query()->findOrFail($policy->variants[0]->group_id)->users()->attach($user->getKey());
        }
        Group::query()->findOrFail($policy->variants[1]->group_id)->users()->attach($seller->getKey());
        $this->generateAll($owner, $id);
        foreach ($policy->refresh()->variants as $variant) {
            $this->reviewAndPublish($owner, Session::query()->findOrFail($variant->session_id));
        }
        $this->actingAs($owner, 'api')->postJson("/api/admin/policies/{$id}/versions/1/approve")->assertOk();

        return [$policy->refresh(), $engineers, $seller];
    }

    /** The learner finishes the course: compliance closes the assignment and the certificate template issues a certificate. */
    protected function finish(User $student, int $courseId): void
    {
        CourseUserPivot::query()->where('user_id', $student->getKey())->where('course_id', $courseId)->update(['finished' => true]);
        event(new CourseFinished(CoreUser::find($student->getKey()), Course::query()->findOrFail($courseId)));
    }
}
