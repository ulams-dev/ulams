<?php

namespace Ulams\Payments\Tests\Traits;

use Ulams\Core\Enums\UserRole;
use Ulams\Payments\Models\User;
use Illuminate\Support\Str;

trait CreatesBillable
{
    public function createBillableStudent()
    {
        $billable = new User([
            'first_name' => Str::random(5),
            'last_name' => Str::random(5),
        ]);
        $billable->assignRole(UserRole::STUDENT);
        $billable->save();
        return $billable;
    }
}
