<?php

namespace EscolaLms\TemplatesEmail\Tests\Feature;

use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Facades\Template;
use EscolaLms\Templates\Repository\Contracts\TemplateRepositoryContract;
use EscolaLms\TemplatesEmail\Core\EmailChannel;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder;
use EscolaLms\TemplatesEmail\Tests\Mocks\TestEvent;
use EscolaLms\TemplatesEmail\Tests\Mocks\TestVariables;
use EscolaLms\TemplatesEmail\Tests\TestCase;
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
