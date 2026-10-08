<?php

namespace Ulams\CsvUsers;

use Ulams\CsvUsers\Models\Group;
use Ulams\CsvUsers\Models\User;
use Ulams\CsvUsers\Policies\CsvUserGroupsPolicy;
use Ulams\CsvUsers\Policies\CsvUsersPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => CsvUsersPolicy::class,
        Group::class => CsvUserGroupsPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached() && method_exists(Passport::class, 'routes')) {
            Passport::routes();
        }
    }
}
