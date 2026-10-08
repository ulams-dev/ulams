<?php

namespace Ulams\TemplatesPdf\Providers;

use Ulams\TemplatesPdf\Models\FabricPDF;
use Ulams\TemplatesPdf\Policies\TemplatePdfPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        FabricPDF::class => TemplatePdfPolicy::class,
    ];

    public function boot()
    {
        $this->registerPolicies();
    }
}
