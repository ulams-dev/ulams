<?php

namespace Ulams\Core\Observability;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Adds the fields every JSON log line carries: request id (also inside queued jobs, through Laravel's
 * Context), tenant, host, route and user id (never an e-mail address).
 */
class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = array_filter([
            'request_id' => Context::get('request_id'),
            'tenant' => config('ulams_tenancy.tenant_slug') ?: 'platform',
            'host' => null,
            'route' => null,
            'user_id' => $this->userId(),
        ], fn ($value) => $value !== null);

        // in a console process the bound request is a blank one; a matched route shows that an HTTP request is in flight
        $request = app()->bound('request') ? app('request') : null;
        $route = $request?->route();
        if ($request !== null && (!app()->runningInConsole() || $route !== null)) {
            $extra['host'] = $request->getHost();
            if ($route !== null) {
                $extra['route'] = $route->uri();
            }
        }

        return $record->with(extra: array_filter($extra + $record->extra, fn ($value) => $value !== null));
    }

    private function userId(): int|string|null
    {
        try {
            $guard = Auth::guard('api');

            return method_exists($guard, 'hasUser') && $guard->hasUser() ? $guard->id() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
