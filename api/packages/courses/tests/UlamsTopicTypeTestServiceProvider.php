<?php

namespace Ulams\Courses\Tests;

use Illuminate\Support\ServiceProvider;

class UlamsTopicTypeTestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
