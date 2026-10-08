<?php

namespace Database\Factories\Ulams\Payments\Models;

use Database\Factories\Ulams\Core\Models\UserFactory;
use Ulams\Payments\Models\Billable;

class BillableFactory extends UserFactory
{
    protected $model = Billable::class;
}
