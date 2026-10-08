<?php

namespace Ulams\TemplatesSms\Tests\Api;

use Ulams\Core\Models\User;
use Ulams\Core\Tests\CreatesUsers;
use Ulams\Templates\Events\ManuallyTriggeredEvent;
use Ulams\Templates\Listeners\TemplateEventListener;
use Ulams\Templates\Models\Template;
use Ulams\Templates\Models\TemplateSection;
use Ulams\TemplatesSms\Core\SmsChannel;
use Ulams\TemplatesSms\Facades\Sms;
use Ulams\TemplatesSms\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

class TemplateApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tutor = User::factory()->create([
            'phone' => '666888111',
            'notification_channels' => json_encode([
                "Ulams\\TemplatesEmail\\Core\\EmailChannel",
                "Ulams\\TemplatesSms\\Core\\SmsChannel"
            ])
        ]);
        $this->tutor->guard_name = 'api';
        $this->tutor->assignRole('tutor');
    }

    public function testManuallyTriggeredEvent(): void
    {
        Sms::fake();
        Event::fake([ManuallyTriggeredEvent::class]);

        $template = Template::factory()->create([
            'name' => 'Sms',
            'channel' => SmsChannel::class,
            'event' => ManuallyTriggeredEvent::class,
            'default' => true,
        ]);

        TemplateSection::factory()->create([
            'key' => 'content',
            'content' => 'Simple content sent to @VarUserName',
            'template_id' => $template->getKey()
        ]);

        $admin = $this->makeAdmin();
        $this->response = $this->actingAs($admin, 'api')->postJson(
            '/api/admin/events/trigger-manually/' . $template->getKey(),
            ['users' => [$this->tutor->getKey()]]
        )->assertOk();

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ManuallyTriggeredEvent($this->tutor));

        Sms::assertSent(function ($sms) {
            return $sms->to === $this->tutor->phone
                && str_contains($sms->content, ($this->tutor->first_name . ' ' . $this->tutor->last_name));
        });
    }
}
