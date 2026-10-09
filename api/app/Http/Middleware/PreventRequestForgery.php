<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery as Middleware;

/**
 * CSRF protection (Laravel 13 renamed `VerifyCsrfToken` to `PreventRequestForgery`; it also accepts
 * requests whose `Sec-Fetch-Site` is `same-origin` without a token).
 */
class PreventRequestForgery extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
    ];

    /**
     * The CSRF cookie under the same `__Host-` rules as the session cookie (config/session.php).
     */
    protected function newCookie($request, $config)
    {
        return new \Symfony\Component\HttpFoundation\Cookie(
            $config['xsrf_cookie'] ?? 'XSRF-TOKEN',
            $request->session()->token(),
            $this->availableAt(60 * $config['lifetime']),
            '/',
            null,
            (bool) $config['secure'],
            false,
            false,
            $config['same_site'] ?? 'lax',
            false
        );
    }

    public function handle($request, \Closure $next)
    {
        // Don't validate CSRF when testing.
        if (env('APP_ENV') === 'testing') {
            return $this->addCookieToResponse($request, $next($request));
        }

        return parent::handle($request, $next);
    }
}
