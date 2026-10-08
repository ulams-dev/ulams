<?php

namespace Ulams\ModelFields\Facades;

use Illuminate\Support\Facades\Facade;
use Ulams\ModelFields\Services\Contracts\ModelFieldsServiceContract;

class ModelFields extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ModelFieldsServiceContract::class;
    }
}
