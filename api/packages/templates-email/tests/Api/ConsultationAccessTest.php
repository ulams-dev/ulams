<?php

namespace EscolaLms\TemplatesEmail\Tests\Api;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryAdminCreatedEvent;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryApprovedEvent;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryDisapprovedEvent;
use EscolaLms\ConsultationAccess\Models\Consultation;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Templates\Listeners\TemplateEventListener;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ConsultationAccessTest extends TestCase
{
    use CreatesUsers, DatabaseTransactions, WithFaker;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\ConsultationAccess\EscolaLmsConsultationAccessServiceProvider::class)) {
            $this->markTestSkipped('Consultation-Access package not installed');
        }

        $this->seed(ConsultationAccessPermissionSeeder::class);
    }

    public function testAdminNotificationOnConsultationEnquiryCreated(): void
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $consultation = Consultation::factory()->create();
        $proposedTerm = Carbon::now()->addDay();

        $this->actingAs($student, 'api')
            ->postJson('api/consultation-access-enquiries', [
                'consultation_id' => $consultation->getKey(),
                'proposed_terms' => [
                    $proposedTerm,
                ]
            ])
            ->assertCreated();

        $enquiry = ConsultationAccessEnquiry::latest()->first();

        Event::assertDispatched(function (ConsultationAccessEnquiryAdminCreatedEvent $event) use ($enquiry) {
            $this->assertEquals($event->getConsultationAccessEnquiry()->getKey(), $enquiry->getKey());
            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ConsultationAccessEnquiryAdminCreatedEvent($admin, $enquiry));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($admin, $enquiry, $proposedTerm) {
            $this->assertEquals(__('New consultation access enquiry'), $mailable->subject);
            $this->assertTrue($mailable->hasTo($admin->email));
            $this->assertStringContainsString($proposedTerm->format('Y-m-d H:i'), $mailable->getHtml());
            return true;
        });
    }

    public function testNotificationOnConsultationEnquiryDisapproved(): void
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        $admin = $this->makeAdmin();
        $enquiry = ConsultationAccessEnquiry::factory()->create();
        $message = 'Example message';

        $this->actingAs($admin, 'api')
            ->postJson('api/admin/consultation-access-enquiries/disapprove/' . $enquiry->getKey(), [
                'message' => $message,
            ])->assertOk();

        Event::assertDispatched(function (ConsultationAccessEnquiryDisapprovedEvent $event) use ($enquiry, $message) {
            $this->assertEquals($event->getConsultationName(), $enquiry->consultation->name);
            $this->assertEquals($message, $event->getMessage());
            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ConsultationAccessEnquiryDisapprovedEvent($enquiry->user, $enquiry, $message));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($enquiry) {
            $this->assertEquals(__('Consultation access enquiry disapproved'), $mailable->subject);
            $this->assertTrue($mailable->hasTo($enquiry->user->email));
            return true;
        });
    }

    public function testNotificationOnConsultationEnquiryApproved(): void
    {
        Notification::fake();
        Event::fake();
        Mail::fake();

        /** @var ConsultationAccessEnquiryProposedTerm $proposedTerm */
        $proposedTerm = ConsultationAccessEnquiryProposedTerm::factory()->create();
        $meetingLink = $this->faker->url;
        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/consultation-access-enquiries/approve/' . $proposedTerm->getKey(), [
                'meeting_link' => $meetingLink,
            ])
            ->assertOk();

        $enquiry = $proposedTerm->consultationAccessEnquiry;

        Event::assertDispatched(function (ConsultationAccessEnquiryApprovedEvent $event) use ($enquiry) {
            $this->assertEquals($event->getConsultationAccessEnquiry()->getKey(), $enquiry->getKey());
            return true;
        });

        $listener = app(TemplateEventListener::class);
        $listener->handle(new ConsultationAccessEnquiryApprovedEvent($enquiry->user, $enquiry));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($enquiry) {
            $this->assertEquals(__('Approved term ":consultation"', ['consultation' => $enquiry->consultation->name]), $mailable->subject);
            $this->assertTrue($mailable->hasTo($enquiry->user->email));
            return true;
        });
    }
}
