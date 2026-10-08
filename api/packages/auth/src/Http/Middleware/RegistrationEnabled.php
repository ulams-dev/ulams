<?php

namespace Ulams\Auth\Http\Middleware;

use Closure;
use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\UlamsAuthServiceProvider;
use Illuminate\Support\Facades\Config;

class RegistrationEnabled
{
    public function handle($request, Closure $next)
    {
        if (Config::get(UlamsAuthServiceProvider::CONFIG_KEY . '.registration', SettingStatusEnum::DISABLED) === SettingStatusEnum::DISABLED) {
            abort(403);
        }

        return $next($request);
    }
}
