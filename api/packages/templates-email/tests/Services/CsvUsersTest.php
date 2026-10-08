<?php

namespace EscolaLms\TemplatesEmail\Tests\Services;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\CsvUsers\Events\EscolaLmsImportedNewUserTemplateEvent;
use EscolaLms\CsvUsers\Services\Contracts\CsvUserServiceContract;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
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
        if (!class_exists(\EscolaLms\CsvUsers\EscolaLmsCsvUsersServiceProvider::class)) {
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

        Event::assertDispatched(EscolaLmsImportedNewUserTemplateEvent::class,
            function (EscolaLmsImportedNewUserTemplateEvent $event) use ($userToImport) {
                return $event->getUser()->email === $userToImport['email'];
            });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new EscolaLmsImportedNewUserTemplateEvent($user,'http://localhost/set-password'));

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
