<?php

namespace Ulams\TemplatesEmail\Tests\Feature;

use Ulams\Core\Tests\ApiTestTrait;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Facades\Template;
use Ulams\Templates\Repository\Contracts\TemplateRepositoryContract;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use Ulams\TemplatesEmail\Tests\Mocks\TestEvent;
use Ulams\TemplatesEmail\Tests\Mocks\TestVariables;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class EmailChannelTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        Template::register(TestEvent::class, EmailChannel::class, TestVariables::class);
        $this->seed(TemplatesEmailSeeder::class);
    }

    public function testPreview()
    {
        Mail::fake();
        Event::fake();
        Notification::fake();

        $admin = $this->makeAdmin();

        $template = app(TemplateRepositoryContract::class)->findTemplateDefault(TestEvent::class, EmailChannel::class);

        Template::sendPreview($admin, $template);

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($admin) {
            $this->assertEquals(__('New friend request'), $mailable->subject);
            $this->assertTrue($mailable->hasTo($admin->email));
            return true;
        });
    }
}
