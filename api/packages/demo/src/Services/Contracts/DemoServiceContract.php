<?php

namespace Ulams\Demo\Services\Contracts;

use Laravel\Passport\PersonalAccessTokenResult;
use Ulams\Auth\Models\User;
use Ulams\Demo\Enums\DemoRole;

interface DemoServiceContract
{
    public function isEnabled(): bool;

    /**
     * The seeded account a visitor is logged in as for the given role.
     *
     * @throws \Ulams\Demo\Exceptions\DemoUserNotFoundException
     */
    public function userFor(DemoRole $role): User;

    /**
     * A regular Passport personal access token for the demo account of that role, the same
     * token `POST /api/auth/login` issues. The demo student is given access to every
     * published course first.
     */
    public function login(DemoRole $role): PersonalAccessTokenResult;

    /**
     * Assigns the user to every published course (idempotent).
     *
     * @return int number of courses the user was newly assigned to
     */
    public function grantCourseAccess(User $user): int;

    /**
     * The demo accounts that exist on this tenant, without passwords.
     *
     * @return list<array{role: string, email: string}>
     */
    public function accounts(): array;
}
