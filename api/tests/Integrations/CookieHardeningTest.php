<?php

namespace Tests\Integrations;

use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * Same-site content origin (docs/content-origin.md): third-party package code on
 * `<slug>.content.<app domain>` can set cookies for the parent domain and receives no SameSite
 * protection from the app's cookies. Every cookie the API sets is therefore host-only and
 * `__Host-` prefixed in production, and nothing authenticates through a cookie alone.
 */
class CookieHardeningTest extends TestCase
{
    /** @return array<string, mixed> config/session.php evaluated as if the app ran with these env values */
    private function sessionConfig(array $env): array
    {
        $saved = [$_ENV, $_SERVER];
        foreach ($env as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        try {
            return require base_path('config/session.php');
        } finally {
            [$_ENV, $_SERVER] = $saved;
        }
    }

    public function testProductionSessionCookiesAreHostPrefixed(): void
    {
        $config = $this->sessionConfig(['APP_ENV' => 'production', 'APP_NAME' => 'Ulams']);

        $this->assertSame('__Host-ulams_session', $config['cookie']);
        $this->assertSame('__Host-XSRF-TOKEN', $config['xsrf_cookie']);
        $this->assertTrue($config['secure']);
        $this->assertSame('/', $config['path']);
        $this->assertNull($config['domain']);
        $this->assertTrue($config['http_only']);
        $this->assertSame('lax', $config['same_site']);
    }

    public function testSessionDomainCannotBeConfigured(): void
    {
        $config = $this->sessionConfig(['APP_ENV' => 'production', 'SESSION_DOMAIN' => '.ulams.app']);

        $this->assertNull($config['domain']);
    }

    public function testDevelopmentOverPlainHttpFallsBackToUnprefixedNames(): void
    {
        $config = $this->sessionConfig(['APP_ENV' => 'local', 'APP_NAME' => 'Ulams']);

        $this->assertSame('ulams_session', $config['cookie']);
        $this->assertSame('XSRF-TOKEN', $config['xsrf_cookie']);
        $this->assertFalse($config['secure']);
        $this->assertNull($config['domain']);
    }

    public function testTheFallbackNameIsConfigurable(): void
    {
        $config = $this->sessionConfig(['APP_ENV' => 'production', 'SESSION_COOKIE_PREFIX' => 'dev_', 'SESSION_SECURE_COOKIE' => 'false']);

        $this->assertStringStartsWith('dev_', $config['cookie']);
        $this->assertSame('dev_XSRF-TOKEN', $config['xsrf_cookie']);
        $this->assertFalse($config['secure']);
    }

    public function testRealResponseCookiesAreHostOnlyAndPrefixedInProduction(): void
    {
        config(['session' => $this->sessionConfig(['APP_ENV' => 'production', 'APP_NAME' => 'Ulams', 'SESSION_DRIVER' => 'file'])]);

        config(['ulams_tenancy.enforce_known_hosts' => false]);
        if (!is_file(storage_path('oauth-private.key'))) {
            // Passport signs while the request is dispatched; give it throwaway keys
            $key = openssl_pkey_new(['private_key_bits' => 2048]);
            openssl_pkey_export($key, $private);
            config(['passport.private_key' => $private, 'passport.public_key' => openssl_pkey_get_details($key)['key']]);
        }
        $response = $this->get('/email');
        $cookies = collect($response->headers->getCookies());

        $this->assertNotEmpty($cookies, 'the web group sets the session and CSRF cookies');
        $this->assertContains('__Host-XSRF-TOKEN', $cookies->map->getName()->all());
        $cookies->each(function (Cookie $cookie) {
            $this->assertStringStartsWith('__Host-', $cookie->getName());
            $this->assertNull($cookie->getDomain(), $cookie->getName() . ' must not carry Domain');
            $this->assertSame('/', $cookie->getPath());
            $this->assertTrue($cookie->isSecure());
            $this->assertSame('lax', $cookie->getSameSite());
        });
    }

    public function testNoRouteAuthenticatesThroughACookieAlone(): void
    {
        // the default guard is the Passport bearer-token guard; the session guard exists for the
        // web group only (Horizon/Telescope dashboards, Passport's own screens)
        $this->assertSame('api', config('auth.defaults.guard'));
        $this->assertSame('passport', config('auth.guards.api.driver'));
        $this->assertNull(config('auth.guards.sanctum'));
        $this->assertNull(config('sanctum'), 'Sanctum (stateful domains) is not installed');

        foreach (Route::getRoutes() as $route) {
            $middleware = array_map(fn ($m) => is_string($m) ? $m : '', $route->middleware());
            $uri = $route->uri();

            foreach ($middleware as $m) {
                // Passport's own oauth/* screens use the session guard; nothing in the API logs a
                // user into a session (see the next test), and EnforceTrustedOrigin covers them
                if (!str_starts_with($uri, 'oauth/')) {
                    $this->assertStringNotContainsString('auth:web', $m, "{$uri} authenticates by session");
                }
                $this->assertStringNotContainsString('sanctum', $m, "{$uri} uses Sanctum");
                $this->assertStringNotContainsString('CreateFreshApiToken', $m, "{$uri} issues a cookie API token");
            }
            if (str_starts_with($uri, 'api/') || str_starts_with($uri, 'trax/')) {
                $this->assertNotContains('web', $middleware, "{$uri} is an API route in the cookie-based web group");
                $this->assertNotContains(\Illuminate\Session\Middleware\StartSession::class, $middleware, "{$uri} starts a session");
            }
        }
    }

    public function testNothingLogsAUserIntoASession(): void
    {
        foreach ($this->phpFiles() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/Auth::(login|attempt|loginUsingId|guard\(\s*[\'"]web[\'"]\s*\))|Auth::guard\(\s*[\'"]web/',
                file_get_contents($file),
                "{$file} would create a cookie-authenticated session"
            );
        }
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        foreach ([base_path('app'), ...glob(base_path('packages/*/src'))] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        $this->assertNotEmpty($files);

        return $files;
    }

    public function testApplicationCodeNeverSetsADomainOrRawCookie(): void
    {
        $files = $this->phpFiles();

        foreach ($files as $file) {
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/\b(setcookie|setrawcookie|withCookie|Cookie::queue|Cookie::make|cookie\(\s*[\'"])/',
                $source,
                "{$file} sets a cookie: it must be `__Host-` prefixed and host-only, review it and extend this audit"
            );
        }
    }
}
