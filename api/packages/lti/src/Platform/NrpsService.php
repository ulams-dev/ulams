<?php

namespace Ulams\Lti\Platform;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Ulams\Courses\Models\Course;
use Ulams\Lti\Models\LtiTool;
use Ulams\Lti\Support\Lti;

/**
 * Platform side of LTI Names and Role Provisioning Services 2.0 (membership container): the
 * learners with access to a course and its authors. Names and e-mails are only included when the
 * tool registration shares them (`share_name`, `share_email`); `user_id` is the same `sub` the
 * tool saw in launches.
 */
class NrpsService
{
    public const CONTAINER_TYPE = 'application/vnd.ims.lti-nrps.v2.membershipcontainer+json';
    public const DEFAULT_LIMIT = 100;
    public const MAX_LIMIT = 100;

    public function __construct(private readonly RoleMapper $roles)
    {
    }

    /**
     * @return array{body: array, next: ?array{page: int, limit: int}}
     */
    public function memberships(LtiTool $tool, Course $course, int $page, int $limit, ?string $role): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $page = max(1, $page);

        $users = $this->query($course, $role)->orderBy('id')->offset(($page - 1) * $limit)->limit($limit + 1)->get();
        $hasMore = $users->count() > $limit;

        $members = $users->take($limit)->map(fn ($user) => $this->member($tool, $user))->values()->all();

        return [
            'body' => [
                'id' => $this->url($course, $page, $limit, $role, false),
                'context' => [
                    'id' => 'course-' . $course->getKey(),
                    'title' => (string) $course->title,
                ],
                'members' => $members,
            ],
            'next' => $hasMore ? ['page' => $page + 1, 'limit' => $limit] : null,
        ];
    }

    public function url(Course $course, int $page, int $limit, ?string $role, bool $always = true): string
    {
        $query = array_filter([
            'role' => $role,
            'limit' => $always || $limit !== self::DEFAULT_LIMIT ? $limit : null,
            'page' => $page > 1 ? $page : null,
        ], fn ($value) => $value !== null);

        return Lti::url('api/lti/platform/nrps/' . $course->getKey()) . ($query ? '?' . http_build_query($query) : '');
    }

    private function query(Course $course, ?string $role): Builder
    {
        $model = config('auth.providers.users.model');
        $query = $model::query()->where(function (Builder $q) use ($course) {
            $q->whereIn('id', DB::table('course_user')->where('course_id', $course->getKey())->select('user_id'))
                ->orWhereIn('id', DB::table('course_author')->where('course_id', $course->getKey())->select('author_id'));
        });

        if ($role === null || $role === '') {
            return $query;
        }

        // role: the full LTI URI or its short name ("Learner", "Instructor"), as RoleMapper issues them
        $name = strtolower(preg_replace('/^.*[#\/]/', '', $role));
        $staff = fn (Builder $q) => $q->whereIn('name', ['admin', 'tutor']);

        return match ($name) {
            'learner' => $query->whereDoesntHave('roles', $staff),
            'instructor' => $query->whereHas('roles', $staff),
            'administrator' => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'admin')),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private function member(LtiTool $tool, $user): array
    {
        $member = [
            'status' => 'Active',
            'user_id' => (string) $user->getAuthIdentifier(),
            'roles' => $this->roles->ltiRoles($user),
        ];
        if ($tool->share_name) {
            $member['given_name'] = (string) ($user->first_name ?? '');
            $member['family_name'] = (string) ($user->last_name ?? '');
            $member['name'] = trim($member['given_name'] . ' ' . $member['family_name']);
        }
        if ($tool->share_email && !empty($user->email)) {
            $member['email'] = (string) $user->email;
        }

        return $member;
    }
}
