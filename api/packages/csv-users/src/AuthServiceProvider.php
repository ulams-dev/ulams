<?php

namespace EscolaLms\CsvUsers;

use EscolaLms\CsvUsers\Models\Group;
use EscolaLms\CsvUsers\Models\User;
use EscolaLms\CsvUsers\Policies\CsvUserGroupsPolicy;
use EscolaLms\CsvUsers\Policies\CsvUsersPolicy;
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
