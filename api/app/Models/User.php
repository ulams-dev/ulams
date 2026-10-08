<?php

namespace App\Models;

use Ulams\Auth\Models\User as CoreUser;
use Ulams\Courses\Models\Traits\HasCourses;
use Ulams\Payments\Concerns\Billable;
use Ulams\Payments\Contracts\Billable as ContractsBillable;

// TODO: make user extendable from core + add all traits
class User extends CoreUser implements ContractsBillable
{
    use Billable;
    use HasCourses;
}
