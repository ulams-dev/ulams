<?php

namespace Ulams\TemplatesEmail\Tests\Services;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\CsvUsers\Events\UlamsImportedNewUserTemplateEvent;
use Ulams\CsvUsers\Services\Contracts\CsvUserServiceContract;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class CsvUsersTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\Ulams\CsvUsers\UlamsCsvUsersServiceProvider::class)) {
            $this->markTestSkipped('Auth package not installed');
        }
    }

    public function testImportNewUserNotification(): void
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $userToImport = collect([
            'email' => 'import.user@poczta.com',
            'first_name' => 'Import',
            'last_name' => 'User',
        ]);

        $service = app(CsvUserServiceContract::class);
        $user = $service->saveUserFromImport($userToImport, 'http://localhost/set-password');

        Event::assertDispatched(UlamsImportedNewUserTemplateEvent::class,
            function (UlamsImportedNewUserTemplateEvent $event) use ($userToImport) {
                return $event->getUser()->email === $userToImport['email'];
            });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new UlamsImportedNewUserTemplateEvent($user,'http://localhost/set-password'));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals('User Import Notification', $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            $this->assertStringContainsString('token=' . $user->password_reset_token, $mailable->getHtml());
            $this->assertStringContainsString('email=' . $user->email, $mailable->getHtml());
            $this->assertStringContainsString('http://localhost/set-password', $mailable->getHtml());
            return true;
        });
    }
}
