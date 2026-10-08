<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Ulams\Auth\Database\Seeders\AuthPermissionSeeder;
use Ulams\Auth\Enums\SettingStatusEnum;
use Ulams\Auth\UlamsAuthServiceProvider;
use Ulams\Auth\Events\AccountDeletionRequested;
use Ulams\Auth\Events\AccountMustBeEnableByAdmin;
use Ulams\Auth\Events\AccountRegistered;
use Ulams\Auth\Events\ForgotPassword;
use Ulams\Core\Models\User;
use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Auth\Notifications\ResetPassword as LaravelResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class AuthTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Ulams\Auth\UlamsAuthServiceProvider::class)) {
            $this->markTestSkipped('Auth package not installed');
        }
    }

    public function testVerifyEmail()
    {
        Mail::fake();
        Event::fake([AccountRegistered::class]);
        Notification::fake();

        $this->response = $this->json('POST', '/api/auth/register', [
            'email' => 'test@test.test',
            'first_name' => 'tester',
            'last_name' => 'tester',
            'password' => 'testtest',
            'password_confirmation' => 'testtest',
            'return_url' => 'https://ulams.app/email/verify',
        ]);

        $this->assertApiSuccess();
        $this->assertDatabaseHas('users', [
            'email' => 'test@test.test',
            'first_name' => 'tester',
            'last_name' => 'tester',
        ]);

        $user = User::where('email', 'test@test.test')->first();

        Event::assertDispatched(AccountRegistered::class);
        Notification::assertNotSentTo($user, VerifyEmail::class);

        $listener = app(TemplateEventListener::class);
        $listener->handle(new AccountRegistered($user, 'https://ulams.app/email/verify'));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals('Verify Email Address', $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }

    public function testResetPassword()
    {
        Mail::fake();
        Event::fake();
        Notification::fake();

        $user = $this->makeStudent();

        $this->response = $this->json('POST', '/api/auth/password/forgot', [
            'email' => $user->email,
            'return_url' => 'http://localhost/password-forgot',
        ]);

        $this->assertApiSuccess();

        Event::assertDispatched(ForgotPassword::class);
        Notification::assertNotSentTo($user, ForgotPassword::class);
        Notification::assertNotSentTo($user, LaravelResetPassword::class);

        $event = new ForgotPassword($user, 'http://localhost/password-forgot');
        $listener = app(TemplateEventListener::class);
        $listener->handle($event);

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals('Reset Password Notification', $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            $this->assertStringContainsString('http://localhost/password-forgot', $mailable->getHtml());
            $this->assertStringContainsString('token=' . $user->password_reset_token, $mailable->getHtml());
            $this->assertStringContainsString('email=' . $user->email, $mailable->getHtml());
            return true;
        });
    }

    public function testAccountMustBeEnableByAdmin(): void
    {
        $this->seed(AuthPermissionSeeder::class);
        Mail::fake();
        Event::fake([AccountMustBeEnableByAdmin::class]);
        Notification::fake();
        Config::set(UlamsAuthServiceProvider::CONFIG_KEY  . '.account_must_be_enabled_by_admin', SettingStatusEnum::ENABLED);

        $admin = config('auth.providers.users.model')::factory()->create();
        $admin->guard_name = 'api';
        $admin->assignRole('admin');

        $this->response = $this->json('POST', '/api/auth/register', [
            'email' => 'test@test.test',
            'first_name' => 'tester',
            'last_name' => 'tester',
            'password' => 'testtest',
            'password_confirmation' => 'testtest',
            'return_url' => 'https://ulams.app/email/verify',
        ]);

        $this->assertApiSuccess();
        $this->assertDatabaseHas('users', [
            'email' => 'test@test.test',
            'first_name' => 'tester',
            'last_name' => 'tester',
        ]);

        $newUser = User::where('email', 'test@test.test')->first();

        Event::assertDispatched(AccountMustBeEnableByAdmin::class);
        Notification::assertNotSentTo($newUser, VerifyEmail::class);

        $listener = app(TemplateEventListener::class);
        $listener->handle(new AccountMustBeEnableByAdmin($admin, $newUser));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($admin, $newUser) {
            $this->assertEquals('Verify User account', $mailable->subject);
            $this->assertTrue($mailable->hasTo($admin->email));
            $this->assertFalse($mailable->hasTo($newUser->email));

            return true;
        });
    }

    public function testInitProfileDeletion(): void
    {
        $this->seed(AuthPermissionSeeder::class);

        Mail::fake();
        Event::fake();
        Notification::fake();

        $user = $this->makeStudent();

        $this
            ->actingAs($user, 'api')
            ->postJson('/api/profile/delete/init', ['return_url' => 'https://ulams.app/delete-account'])
            ->assertOk();

        Event::assertDispatched(AccountDeletionRequested::class);
        Notification::assertNotSentTo($user, VerifyEmail::class);

        $listener = app(TemplateEventListener::class);
        $listener->handle(new AccountDeletionRequested($user, 'https://ulams.app/delete-account'));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals('Confirmation of account deletion', $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }
}
