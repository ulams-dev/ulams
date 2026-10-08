<?php

namespace Ulams\Courses\Facades;

use Ulams\Courses\Repositories\Contracts\TopicRepositoryContract;
use Illuminate\Support\Facades\Facade;

class Topic extends Facade
{
    protected static function getFacadeAccessor()
    {
        return TopicRepositoryContract::class;
    }
}
