<?php

namespace Ulams\Demo\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\PersonalAccessTokenResult;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\AuthServiceContract;
use Ulams\CourseAccess\Models\Course;
use Ulams\CourseAccess\Services\Contracts\CourseAccessServiceContract;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Demo\Enums\DemoRole;
use Ulams\Demo\Exceptions\DemoUserNotFoundException;
use Ulams\Demo\UlamsDemoServiceProvider;

class DemoService implements Contracts\DemoServiceContract
{
    public function __construct(private Container $app, private AuthServiceContract $auth)
    {
    }

    public function isEnabled(): bool
    {
        return (bool) config(UlamsDemoServiceProvider::CONFIG_KEY . '.enabled', false);
    }

    public function userFor(DemoRole $role): User
    {
        $user = $this->findUser($role);
        if (!$user) {
            throw DemoUserNotFoundException::forRole($role);
        }

        return $user;
    }

    public function login(DemoRole $role): PersonalAccessTokenResult
    {
        $user = $this->userFor($role);
        if ($role === DemoRole::STUDENT) {
            $this->grantCourseAccess($user);
        }

        // "remember me" lifetime: the token dies with the hourly reset anyway, and a short
        // one would only make the front refresh it.
        return $this->auth->createTokenForUser($user, true);
    }

    public function grantCourseAccess(User $user): int
    {
        if (!$this->app->bound(CourseAccessServiceContract::class) || !class_exists(Course::class)) {
            return 0;
        }

        $access = $this->app->make(CourseAccessServiceContract::class);
        $courses = Course::query()
            ->whereIn('status', [CourseStatusEnum::PUBLISHED, CourseStatusEnum::PUBLISHED_UNACTIVATED])
            ->whereDoesntHave('users', fn (Builder $query) => $query->whereKey($user->getKey()))
            ->orderBy('id')
            ->get();

        foreach ($courses as $course) {
            $access->addAccessForUsers($course, [$user->getKey()]);
        }

        return $courses->count();
    }

    public function accounts(): array
    {
        $accounts = [];
        foreach (DemoRole::cases() as $role) {
            $user = $this->findUser($role);
            if ($user) {
                $accounts[] = ['role' => $role->value, 'email' => $user->email];
            }
        }

        return $accounts;
    }

    private function findUser(DemoRole $role): ?User
    {
        try {
            return $this->findUserWithRole($role);
        } catch (RoleDoesNotExist) {
            // not seeded yet (PermissionsSeeder creates the roles)
            return null;
        }
    }

    private function findUserWithRole(DemoRole $role): ?User
    {
        $email = $this->configuredEmail($role);
        if ($email) {
            $user = $this->usersWithRole($role)->where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        return $this->usersWithRole($role)->orderBy('id')->first();
    }

    private function configuredEmail(DemoRole $role): ?string
    {
        $config = UlamsDemoServiceProvider::CONFIG_KEY;
        $admin = (string) config($config . '.admin_email');

        if ($role === DemoRole::ADMIN) {
            return $admin ?: null;
        }

        $local = $role === DemoRole::TUTOR ? 'tutor' : 'student1';
        $configured = (string) config($config . ($role === DemoRole::TUTOR ? '.tutor_email' : '.student_email'));
        if ($configured) {
            return $configured;
        }

        return str_contains($admin, '@') ? $local . '@' . substr($admin, strpos($admin, '@') + 1) : null;
    }

    private function usersWithRole(DemoRole $role): Builder
    {
        /** @var class-string<User> $model */
        $model = config('auth.providers.users.model', User::class);

        return $model::query()->role($role->value, 'api');
    }
}
