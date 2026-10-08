<?php

namespace Ulams\Tasks\Tests;

use Ulams\Core\Enums\UserRole;
use Ulams\Core\Tests\CreatesUsers as CoreCreateUsers;
use Illuminate\Contracts\Auth\Authenticatable;

trait CreatesUsers
{
    use CoreCreateUsers;

    private function makeUser(array $data = [], bool $create = true): Authenticatable
    {
        return $this->create($data, $create);
    }

    private function makeStudent(array $data = [], bool $create = true): Authenticatable
    {
        $user = $this->create($data, $create);
        $user->assignRole(UserRole::STUDENT);

        return $user;
    }
}
