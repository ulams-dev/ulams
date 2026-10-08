<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Models\Template;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Tests\TestCase;
use Ulams\Youtube\Dto\YTBroadcastDto;
use Ulams\Youtube\UlamsYoutubeServiceProvider;
use Ulams\Youtube\Events\YtProblem;
use Ulams\Youtube\Services\Contracts\YoutubeServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class YoutubeTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithFaker;

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(UlamsYoutubeServiceProvider::class)) {
            $this->markTestSkipped('Youtube package not installed');
        }

        config([
            'services.youtube.client_id' => 'test_client_id',
            'services.youtube.client_secret' => 'test_secret',
            'services.youtube.api_key' => 'test_api_key',
            'services.youtube.refresh_token' => 'test_refresh_token',
            'services.youtube.redirect_url' => 'redirect_url',
        ]);

        $this->seed(PermissionTableSeeder::class);
    }

    public function testVerifyEmailAfterWrongYt()
    {
        Event::fake();
        Mail::fake();

        $email = $this->faker->email;
        Config::set('services.youtube.email', $email);
        $ytServiceContract = app(YoutubeServiceContract::class);
        try {
            $ytServiceContract->generateYTStream(new YTBroadcastDto());
        } catch (\Exception $ex) {
            //
        }
        $user = new User([
            'email' => $email
        ]);
        Event::assertDispatched(YtProblem::class, function (YtProblem $event) use ($user) {
            return $event->getUser()->email === $user->email;
        });
        $listener = app(TemplateEventListener::class);
        $listener->handle(new YtProblem($user));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($user) {
            $this->assertEquals(__('Problem with Yt integration'), $mailable->subject);
            $this->assertTrue($mailable->hasTo($user->email));
            return true;
        });
    }
}
