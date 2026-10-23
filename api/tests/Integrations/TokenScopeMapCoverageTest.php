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

    /** ADR 0194, Q10: the report, its export and the schedules are analytics (reports:read); the rest of compliance stays with certificates. */
    public function testComplianceReportsAndTheReportBuilderAreInTheReportsArea(): void
    {
        foreach ([
            ['api/admin/compliance/report', 'GET'], ['api/admin/compliance/report/export', 'GET'],
            ['api/admin/compliance/schedules', 'GET'], ['api/admin/compliance/schedules', 'POST'], ['api/admin/compliance/schedules/{id}', 'DELETE'],
            ['api/admin/reporting/datasets', 'GET'],
        ] as [$uri, $method]) {
            $this->assertSame('reports', TokenScopes::requirement($uri, $method)['area'], "{$method} {$uri}");
        }
        foreach (['api/admin/compliance/assignments', 'api/admin/compliance/dashboard', 'api/admin/compliance/courses/{course}/settings', 'api/admin/compliance/rules'] as $uri) {
            $this->assertSame('certificates', TokenScopes::requirement($uri, 'GET')['area'], $uri);
        }
        $this->assertFalse(TokenScopes::requirement('api/admin/reporting/datasets', 'GET')['write']);
        $this->assertFalse(TokenScopes::requirement('api/admin/reporting/saved/{id}/run', 'GET')['write'], 'running a saved report reads');
        $this->assertTrue(TokenScopes::requirement('api/admin/reporting/saved', 'POST')['write']);
        $this->assertTrue(TokenScopes::requirement('api/admin/reporting/saved/{id}', 'DELETE')['write']);
        $this->assertFalse(TokenScopes::requirement('api/admin/reporting/query', 'POST')['write'], 'a preview changes nothing: reports:read is enough');
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
