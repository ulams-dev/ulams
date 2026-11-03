<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

/** Deactivating (or deleting) a user ends every session and token of theirs (ADR 0121). */
class DeactivationRevokesTokensTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function testDeactivationRevokesEveryTokenOfTheUserAndOnlyHis(): void
    {
        /** @var User $user */
        $user = $this->makeStudent();
        $other = $this->makeStudent();
        $login = $user->createToken('login')->accessToken;
        $scoped = $user->createToken('agent', ['courses:read'])->accessToken;
        $otherToken = $other->createToken('login')->accessToken;
        $this->getJson('api/profile/me', $this->bearer($login))->assertOk();
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->makeAdmin())->putJson('api/admin/users/' . $user->getKey(), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'is_active' => false,
        ])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->assertSame(0, $user->tokens()->where('revoked', false)->count());
        $this->assertSame(1, $other->tokens()->where('revoked', false)->count());
        $this->getJson('api/profile/me', $this->bearer($login))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('api/profile/me', $this->bearer($scoped))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->getJson('api/profile/me', $this->bearer($otherToken))->assertOk();
    }

    public function testReactivatingDoesNotBringOldTokensBack(): void
    {
        $user = $this->makeStudent();
        $token = $user->createToken('login')->accessToken;

        $user->update(['is_active' => false]);
        $user->update(['is_active' => true]);

        $this->getJson('api/profile/me', $this->bearer($token))->assertUnauthorized();
    }

    public function testDeletingAUserRevokesTheirTokens(): void
    {
        $user = $this->makeStudent();
        $token = $user->createToken('login')->accessToken;

        $user->delete();

        $this->assertSame(0, $user->tokens()->where('revoked', false)->count());
        $this->getJson('api/profile/me', $this->bearer($token))->assertUnauthorized();
    }

    public function testDeactivationClearsAnApprovedButUncollectedDeviceLogin(): void
    {
        $user = $this->makeStudent();
        \DB::table('device_authorizations')->insert([
            'device_code_hash' => hash('sha256', 'd'),
            'user_code_hash' => hash('sha256', 'u'),
            'client_name' => 'ulams cli',
            'requested_scopes' => '[]',
            'status' => 'approved',
            'user_id' => $user->getKey(),
            'access_token_encrypted' => 'secret',
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->update(['is_active' => false]);

        $this->assertDatabaseHas('device_authorizations', ['user_id' => $user->getKey(), 'access_token_encrypted' => null, 'status' => 'expired']);
    }
}
