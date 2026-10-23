<?php

namespace Ulams\Core\Http\Middleware;

use Closure;
use Ulams\Core\Models\User;
use Illuminate\Http\Request;

class SetTimezoneForUserMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // probes and scrapes carry no user; resolving the guard would also make Passport report the scrape token
        // as an invalid JWT on every request and blank the Authorization header
        if ($request->is('up', 'metrics')) {
            return $next($request);
        }

        /** @var ?User $user */
        $user = auth()->user() ? User::whereId(auth()->user()->getKey())->first() : null;
        $currentTimezone = $request->header('CURRENT-TIMEZONE');
        if ($currentTimezone && $user && $user->current_timezone !== $currentTimezone) {
            $user->current_timezone = $currentTimezone;
            $user->save();
        }

        return $next($request);
    }
}
