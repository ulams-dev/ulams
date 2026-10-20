<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Ulams\Auth\Models\ApiTokenUsage;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

/** Per-token rate limits that can be changed after issue, and daily usage counters (Phase 7.3). */
class TokenUsageApiTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /** @return array{0:string,1:string} secret and token id */
    private function mint(\Ulams\Auth\Models\User $user, array $scopes, ?int $limit = null): array
    {
        $issued = app(PersonalAccessTokenServiceContract::class)->issue($user, 'usage token', $scopes, 30, 'agent', 'tester', 'admin', $limit);

        return [$issued->secret, $issued->token->getKey()];
    }

    private function bearer(string $secret): array
    {
        return ['Authorization' => 'Bearer ' . $secret, 'Accept' => 'application/json'];
    }

    public function testEveryRequestWithAScopedTokenIsCountedPerDay(): void
    {
        $user = $this->makeStudent();
        [$secret, $id] = $this->mint($user, ['learner:read', 'tokens:read']);

        $this->getJson('/api/profile/me', $this->bearer($secret))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/profile/me', $this->bearer($secret))->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/admin/users', $this->bearer($secret))->assertStatus(403);
        $this->app['auth']->forgetGuards();

        $usage = $this->getJson("/api/auth/tokens/{$id}/usage", $this->bearer($secret))->assertOk()->json('data');

        $this->assertSame($id, $usage['token_id']);
        $this->assertSame(30, $usage['days']);
        $this->assertNull($usage['window']);
        // the usage call itself is counted after its response, so it shows up in the next read
        $this->assertSame(['requests' => 3, 'errors' => 1, 'rate_limited' => 0], $usage['totals']);
        $this->assertCount(1, $usage['daily']);
        $this->assertSame(now()->toDateString(), $usage['daily'][0]['date']);
        $this->assertNotNull($usage['last_used_at']);
    }

    public function testRequestsWithoutAScopedTokenAreNotCounted(): void
    {
        $user = $this->makeStudent();
        $this->actingAs($user, 'api')->getJson('/api/profile/me')->assertOk();

        $this->assertSame(0, ApiTokenUsage::query()->count());
    }

    public function testRateLimitedRequestsAreCountedSeparatelyAndTheWindowIsReported(): void
    {
        $user = $this->makeStudent();
        [$secret, $id] = $this->mint($user, ['learner:read', 'tokens:read'], 2);

        foreach ([200, 200, 429] as $expected) {
            $this->getJson('/api/profile/me', $this->bearer($secret))->assertStatus($expected);
            $this->app['auth']->forgetGuards();
        }
        $usage = $this->actingAs($user, 'api')->getJson("/api/auth/tokens/{$id}/usage")->assertOk()->json('data');

        $this->assertSame(['requests' => 3, 'errors' => 0, 'rate_limited' => 1], $usage['totals']);
        $this->assertSame(2, $usage['rate_limit_per_minute']);
        $this->assertSame(2, $usage['window']['limit']);
        $this->assertSame(2, $usage['window']['used']);
        $this->assertSame(0, $usage['window']['remaining']);
        $this->assertGreaterThan(0, $usage['window']['resets_in_seconds']);
    }

    public function testOnlyTheOwnerOrAnAdminReadsUsage(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $admin = $this->makeAdmin();
        [, $id] = $this->mint($owner, ['learner:read']);

        $this->actingAs($owner, 'api')->getJson("/api/auth/tokens/{$id}/usage")->assertOk();
        $this->actingAs($other, 'api')->getJson("/api/auth/tokens/{$id}/usage")->assertForbidden();
        $this->actingAs($other, 'api')->getJson("/api/admin/tokens/{$id}/usage")->assertForbidden();
        $this->actingAs($admin, 'api')->getJson("/api/admin/tokens/{$id}/usage?days=7")->assertOk()->assertJsonPath('data.days', 7);
        $this->actingAs($owner, 'api')->getJson('/api/auth/tokens/does-not-exist/usage')->assertNotFound();
        $this->actingAs($owner, 'api')->getJson("/api/auth/tokens/{$id}/usage?days=500")->assertStatus(422);
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/auth/tokens/{$id}/usage")->assertUnauthorized();
    }

    public function testTheRateLimitCanBeChangedAndRemoved(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $admin = $this->makeAdmin();
        [, $id] = $this->mint($owner, ['learner:read'], 10);

        $this->actingAs($owner, 'api')->patchJson("/api/auth/tokens/{$id}", ['rate_limit_per_minute' => 30])->assertOk()->assertJsonPath('data.rate_limit_per_minute', 30);
        $this->actingAs($admin, 'api')->patchJson("/api/admin/tokens/{$id}", ['rate_limit_per_minute' => 60])->assertOk()->assertJsonPath('data.rate_limit_per_minute', 60);
        $this->actingAs($admin, 'api')->patchJson("/api/admin/tokens/{$id}", ['rate_limit_per_minute' => null])->assertOk()->assertJsonPath('data.rate_limit_per_minute', null);
        $this->actingAs($owner, 'api')->patchJson("/api/auth/tokens/{$id}", ['rate_limit_per_minute' => 0])->assertStatus(422);
        $this->actingAs($owner, 'api')->patchJson("/api/auth/tokens/{$id}", ['rate_limit_per_minute' => 6001])->assertStatus(422);
        $this->actingAs($owner, 'api')->patchJson("/api/auth/tokens/{$id}", [])->assertStatus(422);
        $this->actingAs($other, 'api')->patchJson("/api/auth/tokens/{$id}", ['rate_limit_per_minute' => 5])->assertForbidden();
        $this->actingAs($owner, 'api')->patchJson('/api/auth/tokens/current', ['rate_limit_per_minute' => 5])->assertNotFound();
    }

    public function testATokenCannotRaiseItsOwnLimitWithoutTheTokensWriteScope(): void
    {
        $user = $this->makeStudent();
        [$secret, $id] = $this->mint($user, ['tokens:read'], 5);

        $this->patchJson("/api/auth/tokens/{$id}", ['rate_limit_per_minute' => 6000], $this->bearer($secret))
            ->assertStatus(403)->assertJsonPath('required', ['tokens:write']);
    }
}
