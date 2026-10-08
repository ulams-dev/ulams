<?php

namespace Database\Factories\EscolaLms\Payments\Models;

use Database\Factories\EscolaLms\Core\Models\UserFactory;
use EscolaLms\Payments\Models\Billable;

class BillableFactory extends UserFactory
{
    protected $model = Billable::class;
}
