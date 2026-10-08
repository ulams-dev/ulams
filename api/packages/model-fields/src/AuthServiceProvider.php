<?php

namespace Ulams\ModelFields;

use Ulams\ModelFields\Models\Metadata;
use Ulams\ModelFields\Policies\MetadataPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Metadata::class => MetadataPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
