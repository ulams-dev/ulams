<?php


namespace Ulams\Courses\Database\Factories;

use Database\Factories\Ulams\Auth\Models\GroupFactory as AuthGroupFactory;
use Ulams\Courses\Models\Group;

class GroupFactory extends AuthGroupFactory
{
    protected $model = Group::class;
}
