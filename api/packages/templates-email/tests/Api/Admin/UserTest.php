<?php

namespace EscolaLms\TemplatesEmail\Tests\Api\Admin;

use EscolaLms\Auth\Database\Seeders\AuthPermissionSeeder;
use EscolaLms\Auth\Events\AccountBlocked;
use EscolaLms\Auth\Events\AccountDeleted;
use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class UserTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\EscolaLms\Auth\EscolaLmsAuthServiceProvider::class)) {
            $this->markTestSkipped('Auth package not installed');
        }
    }

    public function testAccountDeletedNotification(): void
    {
        $this->seed(AuthPermissionSeeder::class);
        Mail::fake();
        Event::fake([AccountDeleted::class]);
        Notification::fake();

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $id = $student->getKey();
        $this->response = $this->actingAs($admin, 'api')->deleteJson("/api/admin/users/{$id}");
        $user = User::where('email', '=', $student->email)->first();
        if ($user) {
            $this->assertTrue($user->trashed());
        } else {
            $this->assertTrue(is_null($user));
        }
        $this->assertApiSuccess();


        Event::assertDispatched(AccountDeleted::class);

        $listener = app(TemplateEventListener::class);
        $listener->handle(new AccountDeleted($student));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student) {
            $this->assertEquals('Account Deleted Notification', $mailable->subject);
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }

    public function testAccountBlockedNotification(): void
    {
        $this->seed(AuthPermissionSeeder::class);
        Mail::fake();
        Notification::fake();
        Event::fake([AccountBlocked::class]);

        $user = $this->makeStudent([
            'is_active' => false,
        ]);

        $admin = $this->makeAdmin();

        $this->response = $this->actingAs($admin)->json('PUT', '/api/admin/users/' . $user->getKey(), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'is_active' => true,
        ]);

        Event::assertNotDispatched(AccountBlocked::class);
        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'is_active' => true,
        ]);

        $this->response = $this->actingAs($admin)->json('PUT', '/api/admin/users/' . $user->getKey(), [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'is_active' => false,
        ]);

        Event::assertDispatched(AccountBlocked::class);

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'is_active' => false,
        ]);

        $listener = app(TemplateEventListener::class);
        $listener->handle(new AccountBlocked($user));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals('Account Blocked Notification', $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }
}
