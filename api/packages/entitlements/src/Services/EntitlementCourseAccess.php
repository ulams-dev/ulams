<?php

namespace Ulams\Entitlements\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\CourseAccess\Support\AccessSourceContext;
use Ulams\Courses\Models\CourseUserPivot;
use Ulams\Entitlements\Models\Entitlement;

/**
 * Decorates `CourseAccessService` so that every `course_user` row written by anything other than the entitlements
 * themselves also has an entitlement (ADR 0130): an admin attaching a course records an `admin` entitlement, an
 * LTI launch an `lti` one, a compliance assignment a `compliance` one (see `AccessSourceContext`). That keeps
 * `course_user` a pure projection: refunding a purchase can never remove access someone else granted, and the
 * nightly parity check has nothing to explain away.
 *
 * `EntitlementService` is built on the undecorated service (see the provider), so projecting does not loop.
 * Behaviour stays what callers know: the same events, the same `end_date` rules. One deliberate difference: an
 * explicit removal (`removeAccessForUsers`, `setAccessForUsers`) revokes every entitlement of the user for the
 * course with reason `admin_removed`, because an admin who removes someone expects the access to be gone.
 * Group access is not recorded (it is not a `course_user` row) and passes through untouched.
 */
class EntitlementCourseAccess implements CourseAccessServiceContract
{
    public const REVOKE_REASON = 'admin_removed';

    public function __construct(
        private readonly CourseAccessServiceContract $inner,
        private readonly EntitlementService $entitlements,
    ) {
    }

    public function addAccessForUsers(Course $course, array $users = []): void
    {
        $entries = $this->normalise($users);
        if ($entries === []) {
            return;
        }
        $courseId = (int) $course->getKey();
        foreach (array_keys($entries) as $userId) {
            $this->entitlements->adoptExistingAccess($userId, $courseId); // before the write below can change the row
        }
        $this->inner->addAccessForUsers($course, $users);

        $context = AccessSourceContext::current();
        $type = $context['type'] ?? Entitlement::SOURCE_ADMIN;
        $ref = array_merge(['actor' => Auth::check() ? 'user' : 'system'], $context['ref'] ?? []);
        $grantedBy = Auth::check() ? (string) Auth::id() : null;
        foreach ($entries as $userId => $attrs) {
            $hasEnd = array_key_exists('end_date', $attrs);
            $end = $hasEnd
                ? ($attrs['end_date'] === null ? null : Carbon::parse($attrs['end_date']))
                : CourseUserPivot::query()->where('user_id', $userId)->where('course_id', $courseId)->first()?->end_date;
            $this->entitlements->grantManual($userId, $courseId, $type, $end, $ref, $grantedBy, $hasEnd);
            $this->entitlements->project($userId, $courseId);
        }
    }

    public function removeAccessForUsers(Course $course, array $users = []): void
    {
        $courseId = (int) $course->getKey();
        $remaining = [];
        foreach (array_keys($this->normalise($users)) as $userId) {
            $this->entitlements->revokeAllForUser($userId, $courseId, self::REVOKE_REASON);
            if (CourseUserPivot::query()->where('user_id', $userId)->where('course_id', $courseId)->exists()) {
                $remaining[] = $userId; // a row nothing explained (not backfilled yet): remove it as before
            }
        }
        $this->inner->removeAccessForUsers($course, $remaining);
    }

    public function setAccessForUsers(Course $course, array $users = []): void
    {
        $desired = $this->normalise($users);
        $current = CourseUserPivot::query()->where('course_id', $course->getKey())->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $this->removeAccessForUsers($course, array_values(array_diff($current, array_keys($desired))));
        $this->addAccessForUsers($course, $users);
    }

    public function addAccessForGroups(Course $course, array $groups = []): void
    {
        $this->inner->addAccessForGroups($course, $groups);
    }

    public function removeAccessForGroups(Course $course, array $groups = []): void
    {
        $this->inner->removeAccessForGroups($course, $groups);
    }

    public function setAccessForGroups(Course $course, array $groups = []): void
    {
        $this->inner->setAccessForGroups($course, $groups);
    }

    public function getUserCourseIds(int $userId, ?bool $active = null): array
    {
        return $this->inner->getUserCourseIds($userId, $active);
    }

    /**
     * Callers pass `[1, 2]`, `[1 => ['end_date' => ...]]` or user models.
     *
     * @param array<int|string, mixed> $users
     * @return array<int, array<string, mixed>> user id => pivot attributes
     */
    private function normalise(array $users): array
    {
        $entries = [];
        foreach ($users as $key => $value) {
            if (is_array($value)) {
                $entries[(int) $key] = $value;
            } else {
                $entries[$value instanceof Model ? (int) $value->getKey() : (int) $value] = [];
            }
        }

        return $entries;
    }
}
