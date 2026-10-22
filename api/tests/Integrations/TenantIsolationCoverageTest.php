<?php

namespace Tests\Integrations;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\Core\Tests\CreatesUsers;

/**
 * Tenant isolation for every endpoint (ADR 0190, docs/plans/tech-debt-and-quality-bar.md TD-3).
 *
 * Each tenant has its own database and its own Passport key pair (ADR 0007), so a token issued by one
 * tenant must be rejected by every other tenant. This test walks all registered routes, after every
 * package's service provider has booted, and checks that:
 *
 *  1. a route is either behind `auth:api` or listed in `tests/fixtures/public-routes.php` with a reason
 *     (a new route that is neither fails, and so does a list entry that matches no route);
 *  2. every route behind `auth:api` answers 401 to a request with no token and to a request with a
 *     token signed by another tenant's key pair (route parameters filled in, empty body, so
 *     authentication has to reject before binding, validation or any controller code);
 *  3. every listed route that checks the caller itself rejects such requests too (see the file for the
 *     kinds).
 *
 * What it does not prove: data separation inside one tenant (authors, learners) and routes that are
 * public by design, whose tenant is chosen by host. Those stay with the per-package tests and with the
 * two-tenant HTTP run (api/packages/tenancy/tests/Integration, nightly).
 */
class TenantIsolationCoverageTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;
    use ForeignTenantToken;

    /** Verbs the walker skips: HEAD mirrors GET, OPTIONS is answered by the CORS middleware, QUERY is a Route::any() artefact. */
    private const SKIPPED_METHODS = ['HEAD', 'OPTIONS', 'QUERY'];

    private const KINDS = ['open', 'inline-auth', 'own-credentials', 'known-gap'];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ulams_tenancy.enforce_known_hosts' => false,
            // the platform tenant API answers 404 before authentication unless it is on (ADR 0078)
            'ulams_tenancy.platform_api' => true,
        ]);
        Cache::flush();
    }

    public function testEveryRouteIsAuthenticatedOrListedAsPublic(): void
    {
        $unlisted = [];
        $matched = [];
        foreach ($this->routes() as [$route, $method]) {
            if ($this->authenticated($route)) {
                continue;
            }
            $entry = $this->publicEntry($method, $route->uri());
            if ($entry === null) {
                $unlisted[] = "{$method} {$route->uri()}";
            } else {
                $matched[array_search($entry, $this->publicRoutes(), true)] = true;
            }
        }

        $this->assertSame([], $unlisted, "These routes are not behind auth:api and not in api/tests/fixtures/public-routes.php.\n"
            . "Put them behind auth:api, or add an entry with the reason they are public (a security review, ADR 0190):\n" . implode("\n", $unlisted));

        $stale = [];
        foreach ($this->publicRoutes() as $index => $entry) {
            if (!isset($matched[$index]) && empty($entry['optional'])) {
                $stale[] = "{$entry['method']} {$entry['uri']}";
            }
        }
        $this->assertSame([], $stale, "These entries of api/tests/fixtures/public-routes.php match no route that is not behind auth:api; remove them (or mark them optional when the route exists in some environments only):\n" . implode("\n", $stale));
    }

    public function testPublicRouteEntriesAreComplete(): void
    {
        foreach ($this->publicRoutes() as $entry) {
            $label = "{$entry['method']} {$entry['uri']}";
            $this->assertContains($entry['kind'] ?? null, self::KINDS, "{$label}: unknown kind");
            $this->assertGreaterThan(10, strlen((string) ($entry['reason'] ?? '')), "{$label}: write the reason this route may be public");
            if (($entry['kind'] ?? null) === 'known-gap') {
                $this->assertMatchesRegularExpression('/ulams-dev\/ulams#\d+/', $entry['reason'], "{$label}: a known gap names its issue");
            }
        }
    }

    public function testRoutesBehindAuthRejectGuestsAndTokensOfAnotherTenant(): void
    {
        $token = $this->tenantAToken();

        // control: the token is good for its own tenant, so the 401s below come from the key pair
        $this->sendAs($token, 'GET', '/api/profile/me')->assertOk();
        $this->sendAs($token, 'GET', '/api/admin/courses')->assertOk();

        $this->switchToTenantWithOwnKeys();
        $this->sendAs($token, 'GET', '/api/profile/me')->assertUnauthorized();

        $failures = [];
        $probed = 0;
        foreach ($this->routes() as [$route, $method]) {
            if (!$this->authenticated($route)) {
                continue;
            }
            $probed++;
            $url = $this->url($route);
            foreach (['no token' => null, 'token of another tenant' => $token] as $who => $bearer) {
                $status = $this->sendAs($bearer, $method, $url)->getStatusCode();
                if ($status !== 401) {
                    $failures[] = "{$method} {$route->uri()} answered {$status} to {$who}";
                }
            }
        }

        $this->assertGreaterThan(500, $probed, 'The walker found suspiciously few routes behind auth:api');
        $this->assertSame([], $failures, "Routes behind auth:api must answer 401 before anything else:\n" . implode("\n", $failures));
    }

    public function testListedRoutesThatCheckTheCallerRejectGuestsAndTokensOfAnotherTenant(): void
    {
        $token = $this->tenantAToken();
        $this->switchToTenantWithOwnKeys();

        $failures = [];
        $probed = 0;
        foreach ($this->routes() as [$route, $method]) {
            if ($this->authenticated($route)) {
                continue;
            }
            $entry = $this->publicEntry($method, $route->uri());
            if ($entry === null || !in_array($entry['kind'], ['inline-auth', 'own-credentials'], true) || ($entry['probe'] ?? true) === false) {
                continue;
            }
            $probed++;
            $url = $this->url($route);
            foreach (['no token' => null, 'token of another tenant' => $token] as $who => $bearer) {
                $status = $this->sendAs($bearer, $method, $url)->getStatusCode();
                $accepted = $entry['kind'] === 'inline-auth' ? in_array($status, [401, 403], true) : ($status >= 400 && $status < 500);
                if (!$accepted) {
                    $failures[] = "{$method} {$route->uri()} ({$entry['kind']}) answered {$status} to {$who}";
                }
            }
        }

        $this->assertGreaterThan(30, $probed);
        $this->assertSame([], $failures, "These listed routes accepted a request without valid credentials:\n" . implode("\n", $failures));
    }

    /** A tenant A administrator's personal access token, issued with the key pair of this process. */
    private function tenantAToken(): string
    {
        $admin = $this->makeAdmin();
        $model = config('auth.providers.users.model');

        return $model::query()->findOrFail($admin->getKey())->createToken('isolation')->accessToken;
    }

    /** @return list<array{0: LaravelRoute, 1: string}> one pair per route and verb */
    private function routes(): array
    {
        $pairs = [];
        foreach (Route::getRoutes() as $route) {
            foreach (array_diff($route->methods(), self::SKIPPED_METHODS) as $method) {
                $pairs[] = [$route, $method];
            }
        }

        return $pairs;
    }

    private function authenticated(LaravelRoute $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && (str_contains($middleware, 'auth:api') || str_contains($middleware, 'Authenticate:api'))) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed>|null */
    private function publicEntry(string $method, string $uri): ?array
    {
        foreach ($this->publicRoutes() as $entry) {
            if ($entry['method'] !== '*' && $entry['method'] !== $method) {
                continue;
            }
            if ($entry['uri'] === $uri || fnmatch($entry['uri'], $uri)) {
                return $entry;
            }
        }

        return null;
    }

    /** @return list<array<string, mixed>> */
    private function publicRoutes(): array
    {
        static $routes;

        return $routes ??= require __DIR__ . '/../fixtures/public-routes.php';
    }

    /** The route's URI with every parameter filled in with a value its `where` constraint accepts. */
    private function url(LaravelRoute $route): string
    {
        $uri = preg_replace('#/?\{[^}]+\?\}#', '', $route->uri());

        return '/' . ltrim(preg_replace_callback('#\{([^}]+)\}#', function (array $m) use ($route): string {
            $pattern = $route->wheres[$m[1]] ?? null;
            foreach (['1', '1111', 'a1b2c3d4', '11111111-1111-1111-1111-111111111111', '01arz3ndektsv4rrffq69g5fav', 'a'] as $candidate) {
                if ($pattern === null || preg_match('#^(?:' . $pattern . ')$#', $candidate)) {
                    return $candidate;
                }
            }

            return '1';
        }, $uri), '/');
    }
}
