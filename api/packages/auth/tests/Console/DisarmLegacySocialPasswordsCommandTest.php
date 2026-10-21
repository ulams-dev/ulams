<?php

namespace Ulams\Auth\Tests\Console;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Ulams\Auth\Models\SocialAccount;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\AuthService;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

class DisarmLegacySocialPasswordsCommandTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    private function user(string $password, bool $social, array $data = []): User
    {
        $user = $this->makeStudent(['password' => Hash::make($password)] + $data);
        $user->refresh();
        $this->assertTrue(Hash::check($password, $user->password), 'the fixture stores a hash of the password');
        if ($social) {
            SocialAccount::factory()->create(['user_id' => $user->getKey()]);
        }

        return $user;
    }

    public function testDisarmsTheLegacyPasswordOfSocialAccountsAndRevokesTheirTokens(): void
    {
        Log::spy();
        $legacy = $this->user('1', true);
        $token = $legacy->createToken(AuthService::TOKEN_NAME)->token;
        $oldHash = $legacy->password;

        $this->artisan('ulams:auth:disarm-legacy-social-passwords')
            ->expectsOutputToContain('Disarmed 1 of 1 social login accounts')
            ->assertSuccessful();

        $legacy->refresh();
        $this->assertNotSame($oldHash, $legacy->password);
        $this->assertFalse(Hash::check('1', $legacy->password));
        $this->assertNotEmpty($legacy->password);
        $this->assertTrue((bool) $token->fresh()->revoked);
        Log::shouldHaveReceived('info')->with('auth.legacy_social_passwords', ['checked' => 1, 'disarmed' => 1])->once();
    }

    public function testUsersWithARealPasswordAreUntouched(): void
    {
        $social = $this->user('zaq1@WSX-real', true);
        $local = $this->user('1', false); // a local account that chose "1" itself is not ours to change
        $socialToken = $social->createToken(AuthService::TOKEN_NAME)->token;
        $localToken = $local->createToken(AuthService::TOKEN_NAME)->token;
        $socialHash = $social->password;
        $localHash = $local->password;

        $this->artisan('ulams:auth:disarm-legacy-social-passwords')
            ->expectsOutputToContain('Disarmed 0 of 1 social login accounts')
            ->assertSuccessful();

        $this->assertSame($socialHash, $social->fresh()->password);
        $this->assertSame($localHash, $local->fresh()->password);
        $this->assertFalse((bool) $socialToken->fresh()->revoked);
        $this->assertFalse((bool) $localToken->fresh()->revoked);
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $legacy = $this->user('1', true);
        $this->artisan('ulams:auth:disarm-legacy-social-passwords')->assertSuccessful();
        $afterFirst = $legacy->fresh()->password;
        $token = $legacy->createToken(AuthService::TOKEN_NAME)->token;

        $this->artisan('ulams:auth:disarm-legacy-social-passwords')
            ->expectsOutputToContain('Disarmed 0 of 1 social login accounts')
            ->assertSuccessful();

        $this->assertSame($afterFirst, $legacy->fresh()->password);
        $this->assertFalse((bool) $token->fresh()->revoked, 'a token issued after the first run survives the second');
    }

    public function testAnAccountWithoutPasswordIsSkipped(): void
    {
        $user = $this->user('x', true);
        $user->forceFill(['password' => null])->save();

        $this->artisan('ulams:auth:disarm-legacy-social-passwords')
            ->expectsOutputToContain('Disarmed 0 of 0')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->password);
    }

    public function testDryRunCountsAndChangesNothing(): void
    {
        Log::spy();
        $legacy = $this->user('1', true);
        $oldHash = $legacy->password;

        $this->artisan('ulams:auth:disarm-legacy-social-passwords', ['--dry-run' => true])
            ->expectsOutputToContain('Would disarm 1 of 1')
            ->assertSuccessful();

        $this->assertSame($oldHash, $legacy->fresh()->password);
        Log::shouldNotHaveReceived('info');
    }

    public function testTheOutputNamesNoUser(): void
    {
        $legacy = $this->user('1', true, ['email' => 'secret.person@example.test']);

        $this->artisan('ulams:auth:disarm-legacy-social-passwords')
            ->doesntExpectOutputToContain('secret.person')
            ->doesntExpectOutputToContain((string) $legacy->getKey() . '@')
            ->assertSuccessful();
    }
}
