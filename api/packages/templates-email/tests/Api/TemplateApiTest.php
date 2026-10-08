<?php

namespace Ulams\TemplatesEmail\Tests\Api;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Database\Seeders\PermissionTableSeeder;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesEmail\Core\EmailChannel;
use Ulams\TemplatesEmail\Core\EmailMailable;
use Ulams\TemplatesEmail\Core\UserVariables;
use Ulams\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class TemplateApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionTableSeeder::class);
    }

    public function testManuallyTriggeredEvent(): void
    {
        Event::fake(ManuallyTriggeredEvent::class);
        Mail::fake();

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $template = Template::factory()->create([
            'channel' => EmailChannel::class,
            'event' => ManuallyTriggeredEvent::class,
        ]);

        TemplateSection::factory(['key' => 'title', 'template_id' => $template->getKey()])->create();
        TemplateSection::factory(['key' => 'content', 'template_id' => $template->getKey(), 'content' => UserVariables::defaultSectionsContent()['content']])->create();

        $this->response = $this->actingAs($admin, 'api')->postJson(
            '/api/admin/events/trigger-manually/' . $template->getKey(),
            ['users' => [$student->getKey()]]
        )->assertOk();

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ManuallyTriggeredEvent($student));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($student) {
            $this->assertTrue($mailable->hasTo($student->email));
            return true;
        });
    }
}
