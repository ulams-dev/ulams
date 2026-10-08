<?php

namespace EscolaLms\TopicTypes\Facades;

use Illuminate\Support\Facades\Facade;

class Path extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'export-path';
    }
}
