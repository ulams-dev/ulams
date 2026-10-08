<?php

namespace Ulams\HeadlessH5P;

use Ulams\HeadlessH5P\Models\H5PContent;
use Ulams\HeadlessH5P\Models\H5PLibrary;
use Ulams\HeadlessH5P\Policies\H5PContentPolicy;
use Ulams\HeadlessH5P\Policies\H5PLibraryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        H5PContent::class => H5PContentPolicy::class,
        H5PLibrary::class => H5PLibraryPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
