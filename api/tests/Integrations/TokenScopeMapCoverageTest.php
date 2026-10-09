<?php

namespace Tests\Integrations;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Ulams\Auth\Support\TokenScopes;

/**
 * Every API route must be in `packages/auth/resources/token-scopes.php` (ADR 0074): scoped tokens
 * fail closed on anything unmapped, so a new route that is not mapped is unusable with a token.
 */
class TokenScopeMapCoverageTest extends TestCase
{
    /** URI prefixes that never carry API tokens (framework tooling, assets, the Horizon dashboard). */
    private const IGNORED = ['_ignition', 'horizon', 'docs', 'storage/', 'trax/', 'email', 'stripe-test', 'sanctum', 'up'];

    public function testEveryApiRouteIsMapped(): void
    {
        $unmapped = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/') && !$this->authenticated($route)) {
                continue;
            }
            foreach (self::IGNORED as $prefix) {
                if (str_starts_with($uri, $prefix)) {
                    continue 2;
                }
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                if (TokenScopes::requirement($uri, $method) === null) {
                    $unmapped[] = "{$method} {$uri}";
                }
            }
        }
        $this->assertSame([], array_values(array_unique($unmapped)), "Add these routes to packages/auth/resources/token-scopes.php:\n" . implode("\n", $unmapped));
    }

    public function testEveryAdminRouteMapsToAnAdminArea(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/admin/')) {
                continue;
            }
            $req = TokenScopes::requirement($route->uri(), 'GET');
            $this->assertNotNull($req, $route->uri());
            $this->assertNotSame('learner', $req['area'], "{$route->uri()} must not be a learner route");
            $this->assertNotSame('public', $req['area'], "{$route->uri()} must not be public");
        }
    }

    public function testTheMapUsesKnownAreasOnly(): void
    {
        $allowed = array_merge(TokenScopes::AREAS, ['public', 'none']);
        foreach (TokenScopes::map() as $entry) {
            $this->assertContains($entry[1], $allowed, $entry[0]);
        }
    }

    public function testRoutesThatChangeDataThroughGetAreWrites(): void
    {
        $this->assertTrue(TokenScopes::requirement('api/admin/courses/{course}/clone', 'GET')['write']);
        $this->assertFalse(TokenScopes::requirement('api/admin/courses/{course}', 'GET')['write']);
        $this->assertTrue(TokenScopes::requirement('api/admin/courses/{course}', 'PATCH')['write']);
        $this->assertSame('enrolments', TokenScopes::requirement('api/admin/courses/{id}/access/add', 'POST')['area']);
        $this->assertSame('none', TokenScopes::requirement('api/auth/refresh', 'GET')['area']);
    }

    public function testTheCommittedJsonExportIsCurrent(): void
    {
        $this->artisan('ulams:tokens:export-scopes', ['--check' => true])->assertSuccessful();
    }

    private function authenticated(\Illuminate\Routing\Route $route): bool
    {
        foreach ($route->gatherMiddleware() as $m) {
            if (is_string($m) && str_contains($m, 'auth:api') || is_string($m) && str_contains($m, 'Authenticate:api')) {
                return true;
            }
        }

        return false;
    }
}
