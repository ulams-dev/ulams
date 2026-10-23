<?php

namespace Ulams\Reporting\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ulams\Core\Tests\Concerns\ForeignTenantToken;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Reporting\Tests\TestCase;

/** GET /api/admin/reporting/datasets: who may list, what comes back, tenant isolation (ADR 0190). */
class DatasetApiTest extends TestCase
{
    use CreatesUsers;
    use ForeignTenantToken;

    public function testAnAdminListsTheDatasetsWithEveryColumnCapability(): void
    {
        $data = $this->actingAs($this->makeAdmin(), 'api')->getJson('/api/admin/reporting/datasets')->assertOk()->json('data');

        $this->assertSame(['ai_usage'], array_column($data, 'key'), 'only the datasets whose package is loaded are offered');
        $dataset = $data[0];
        $this->assertSame('AI usage', $dataset['label']);
        $this->assertSame('reporting_ai_usage', $dataset['permission']);
        $this->assertNull($dataset['subject_column']);
        $this->assertFalse($dataset['aggregate_only']);
        $this->assertSame(['created_at', 'day'], $dataset['date_columns']);
        $cost = collect($dataset['columns'])->firstWhere('name', 'cost_usd');
        $this->assertSame('decimal', $cost['type']);
        $this->assertTrue($cost['aggregatable']);
        $this->assertFalse($cost['personal']);
        $this->assertContains('between', $cost['filterOps']);
        $this->assertSame(['ok', 'invalid', 'refused', 'max_tokens', 'error'], collect($dataset['columns'])->firstWhere('name', 'status')['enumValues']);
    }

    public function testATutorMayListButSeesOnlyTheDatasetsTheyHoldThePermissionFor(): void
    {
        $this->actingAs($this->makeInstructor(), 'api')->getJson('/api/admin/reporting/datasets')->assertOk()->assertJsonPath('data', []);
    }

    public function testAStudentAndAGuestAreRefused(): void
    {
        $this->getJson('/api/admin/reporting/datasets')->assertUnauthorized();
        $this->actingAs($this->makeStudent(), 'api')->getJson('/api/admin/reporting/datasets')->assertForbidden();
    }

    public function testEveryRouteOfThePackageIsCoveredByTheIsolationCheck(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_starts_with($r->uri(), 'api/admin/reporting'))
            ->flatMap(fn ($r) => collect($r->methods())->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS']))->map(fn ($m) => "{$m} {$r->uri()}"))->sort()->values()->all();
        $listed = collect(array_values(self::routeProvider()))->map(fn ($r) => "{$r[0]} {$r[1]}")->sort()->values()->all();

        $this->assertSame($listed, $routes, 'a new route of the package must be added to routeProvider with its isolation checks');
    }

    public static function routeProvider(): array
    {
        return [
            'datasets' => ['GET', 'api/admin/reporting/datasets', []],
            'query' => ['POST', 'api/admin/reporting/query', ['dataset' => 'ai_usage', 'columns' => ['task']]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routeProvider')]
    public function testATokenOfAnotherTenantIsRejectedOnEveryRoute(string $method, string $uri, array $body): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('test')->accessToken;

        $this->sendAs($token, $method, "/{$uri}", $body)->assertOk();
        $this->sendAs(null, $method, "/{$uri}", $body)->assertUnauthorized();

        $this->switchToTenantWithOwnKeys();

        $this->sendAs($token, $method, "/{$uri}", $body)->assertUnauthorized();
        $this->sendAs($admin->createToken('test')->accessToken, $method, "/{$uri}", $body)->assertOk();
    }
}
