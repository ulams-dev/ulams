<?php

namespace EscolaLms\TemplatesEmail\Providers;

class AuthTemplatesEventServiceProvider extends \Illuminate\Foundation\Support\Providers\EventServiceProvider
{
    public function register()
    {
        $this->booted(function () {
        });
        parent::register();
    }
}
