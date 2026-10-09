<?php

namespace Ulams\CourseAccess\Providers;

use Ulams\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        CourseAccessEnquiry::class
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
