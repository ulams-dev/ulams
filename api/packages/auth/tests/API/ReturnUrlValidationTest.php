<?php

namespace Ulams\Auth\Tests\API;

use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Ulams\Auth\Events\AccountDeletionRequested;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Events\ForgotPassword;
use Ulams\Auth\Models\User;
use Ulams\Auth\Tests\TestCase;
use Ulams\Core\Enums\UserRole;
use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;

/**
 * Every link the API puts into an e-mail (password reset, verification, account deletion) is built
 * from a return URL the caller sends: only the tenant's own apps may be named there.
 */
class ReturnUrlValidationTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, DatabaseTransactions;

    private const FRONT = 'https://learn.tenant-a.example';
    private const ADMIN = 'https://admin.tenant-a.example';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.frontend_url', self::FRONT . '/email');
        Config::set('ulams.core.security.admin_url', self::ADMIN);
        Config::set('ulams.core.security.trusted_origins', ['https://partner.example']);
    }

    /** @return array<string, array{string}> */
    public static function allowed(): array
    {
        return [
            'front end' => [self::FRONT . '/reset-password'],
            'front end with a port' => ['http://localhost:1000/reset-password'],
            'admin' => [self::ADMIN . '/user/reset-password'],
            'trusted origin' => ['https://partner.example/set-password'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function rejected(): array
    {
        return [
            'another site' => ['https://evil.example/reset-password'],
            'look-alike subdomain' => [self::FRONT . '.evil.example/reset'],
            'userinfo trick' => ['https://learn.tenant-a.example@evil.example/reset'],
            'backslash trick' => ['https://evil.example\\@learn.tenant-a.example/reset'],
            'scheme mismatch' => ['http://learn.tenant-a.example/reset'],
            'other port' => ['https://learn.tenant-a.example:8443/reset'],
            'not http' => ['javascript:alert(1)'],
            'protocol relative' => ['//evil.example/reset'],
            'content origin' => ['https://tenant-a.content.ulams.app/reset'],
            'too long' => ['https://learn.tenant-a.example/' . str_repeat('a', 2100)],
        ];
    }

    #[DataProvider('allowed')]
    public function testForgotPasswordAcceptsTheTenantsOwnApps(string $url): void
    {
        Event::fake([ForgotPassword::class]);
        $user = $this->makeStudent();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email, 'return_url' => $url])->assertOk();

        Event::assertDispatched(ForgotPassword::class, fn (ForgotPassword $e) => $e->getReturnUrl() === $url);
    }

    #[DataProvider('rejected')]
    public function testForgotPasswordRefusesOtherUrls(string $url): void
    {
        Event::fake([ForgotPassword::class]);
        Notification::fake();
        $user = $this->makeStudent();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email, 'return_url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_url']);

        Event::assertNotDispatched(ForgotPassword::class);
        Notification::assertNothingSent();
    }

    public function testForgotPasswordRefusesAMissingUrl(): void
    {
        $user = $this->makeStudent();

        $this->postJson('/api/auth/password/forgot', ['email' => $user->email])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_url']);
    }

    #[DataProvider('rejected')]
    public function testRegisterRefusesOtherUrls(string $url): void
    {
        Event::fake([AccountRegistered::class]);

        $this->postJson('/api/auth/register', [
            'email' => 'new@tenant-a.example',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'testtest',
            'password_confirmation' => 'testtest',
            'return_url' => $url,
        ])->assertUnprocessable()->assertJsonValidationErrors(['return_url']);

        $this->assertDatabaseMissing('users', ['email' => 'new@tenant-a.example']);
        Event::assertNotDispatched(AccountRegistered::class);
    }

    public function testRegisterAcceptsTheFrontEnd(): void
    {
        Event::fake([AccountRegistered::class, Registered::class]);

        $this->postJson('/api/auth/register', [
            'email' => 'new@tenant-a.example',
            'first_name' => 'New',
            'last_name' => 'User',
            'password' => 'testtest',
            'password_confirmation' => 'testtest',
            'return_url' => self::FRONT . '/email/verify',
        ])->assertSuccessful();

        Event::assertDispatched(AccountRegistered::class, fn (AccountRegistered $e) => $e->getReturnUrl() === self::FRONT . '/email/verify');
    }

    #[DataProvider('rejected')]
    public function testResendVerificationRefusesOtherUrls(string $url): void
    {
        Event::fake([AccountRegistered::class]);
        $user = $this->makeStudent(['email_verified_at' => null]);

        $this->actingAs($user, 'api')->postJson('/api/auth/email/resend', ['email' => $user->email, 'return_url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_url']);

        Event::assertNotDispatched(AccountRegistered::class);
    }

    #[DataProvider('allowed')]
    public function testResendVerificationAcceptsTheTenantsOwnApps(string $url): void
    {
        Event::fake([AccountRegistered::class]);
        $user = $this->makeStudent(['email_verified_at' => null]);

        $this->actingAs($user, 'api')->postJson('/api/auth/email/resend', ['email' => $user->email, 'return_url' => $url])->assertOk();

        Event::assertDispatched(AccountRegistered::class, fn (AccountRegistered $e) => $e->getReturnUrl() === $url);
    }

    #[DataProvider('rejected')]
    public function testAccountDeletionRefusesOtherUrls(string $url): void
    {
        Event::fake([AccountDeletionRequested::class]);

        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('/api/profile/delete/init', ['return_url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_url']);

        Event::assertNotDispatched(AccountDeletionRequested::class);
    }

    public function testAccountDeletionAcceptsTheFrontEnd(): void
    {
        Event::fake([AccountDeletionRequested::class]);

        $this->actingAs($this->makeStudent(), 'api')
            ->postJson('/api/profile/delete/init', ['return_url' => self::FRONT . '/delete-account'])
            ->assertOk();

        Event::assertDispatched(AccountDeletionRequested::class);
    }

    #[DataProvider('rejected')]
    public function testAdminUserCreationRefusesOtherUrls(string $url): void
    {
        Event::fake([AccountRegistered::class]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/users', $this->newUser() + ['return_url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['return_url']);

        $this->assertDatabaseMissing('users', ['email' => 'created@tenant-a.example']);
        Event::assertNotDispatched(AccountRegistered::class);
    }

    public function testAdminUserCreationAcceptsTheAdminApp(): void
    {
        Event::fake([AccountRegistered::class]);

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('/api/admin/users', $this->newUser() + ['return_url' => self::ADMIN . '/user/reset-password'])
            ->assertCreated();

        Event::assertDispatched(AccountRegistered::class, fn (AccountRegistered $e) => $e->getReturnUrl() === self::ADMIN . '/user/reset-password');
    }

    public function testOneTenantsUrlsAreNotAcceptedByAnother(): void
    {
        $send = fn (string $url) => $this->postJson('/api/auth/password/forgot', [
            'email' => $this->makeStudent()->email,
            'return_url' => $url,
        ])->getStatusCode();

        // tenant A
        $this->assertSame(200, $send('https://learn.tenant-a.example/reset'));
        $this->assertSame(422, $send('https://learn.tenant-b.example/reset'));

        // the same request on tenant B, whose configuration is its own
        Config::set('app.frontend_url', 'https://learn.tenant-b.example/email');
        Config::set('ulams.core.security.admin_url', 'https://admin.tenant-b.example');
        Config::set('ulams.core.security.trusted_origins', []);

        $this->assertSame(200, $send('https://learn.tenant-b.example/reset'));
        $this->assertSame(422, $send('https://learn.tenant-a.example/reset'));
        $this->assertSame(422, $send('https://admin.tenant-a.example/reset'));
        $this->assertSame(422, $send('https://partner.example/reset'));
    }

    /** @return array<string, mixed> */
    private function newUser(): array
    {
        $data = User::factory()->raw([
            'email' => 'created@tenant-a.example',
            'roles' => [UserRole::STUDENT],
            'password' => 'secret',
            'phone' => '+48600600600',
        ]);
        unset($data['email_verified_at'], $data['remember_token']);

        return $data;
    }
}
