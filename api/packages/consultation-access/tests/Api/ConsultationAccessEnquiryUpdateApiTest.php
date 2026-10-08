<?php

namespace EscolaLms\ConsultationAccess\Tests\Api;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryAdminUpdatedEvent;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class ConsultationAccessEnquiryUpdateApiTest extends TestCase
{
    use CreatesUsers;

    private $enquiry;
    private $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationAccessPermissionSeeder::class);
        $this->student = $this->makeStudent();
        $this->enquiry = ConsultationAccessEnquiry::factory()
            ->state(['user_id' => $this->student->getKey()])
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->count(4))
            ->create();
    }

    public function testConsultationAccessEnquiryUpdateUnauthorized(): void
    {
        $proposedTerm = Carbon::now()->addDays();

        $this->patchJson('api/consultation-access-enquiries/' . $this->enquiry->getKey(), [
            'proposed_terms' => $proposedTerm,
        ])
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryUpdate(): void
    {
        Event::fake([ConsultationAccessEnquiryAdminUpdatedEvent::class]);
        $proposedTerm = Carbon::now()->addDays();

        $this->actingAs($this->student, 'api')
            ->patchJson('api/consultation-access-enquiries/' . $this->enquiry->getKey(), [
                'proposed_terms' => [
                    $proposedTerm,
                ],
            ])
            ->assertOk();

        $this->enquiry->refresh();
        $terms = $this->enquiry->consultationAccessEnquiryProposedTerms;
        $this->assertCount(1, $terms);
        $this->assertEquals($proposedTerm->format('Y-m-d H:i'), $terms->first()->proposed_at->format('Y-m-d H:i'));

        Event::assertDispatched(function (ConsultationAccessEnquiryAdminUpdatedEvent $event) {
            $this->assertEquals($this->enquiry->consultation->author_id, $event->getUser()->getKey());
            $this->assertEquals($this->enquiry->consultation->getKey(), $event->getConsultationAccessEnquiry()->consultation_id);
            return true;
        });
    }

    public function testUpdateApprovedConsultationAccessEnquiry(): void
    {
        Event::fake([ConsultationAccessEnquiryAdminUpdatedEvent::class]);

        $this->enquiry = ConsultationAccessEnquiry::factory()
            ->state(['user_id' => $this->student->getKey()])
            ->approved()
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->count(4))
            ->create();

        $proposedTerm = Carbon::now()->addDays();

        $this->actingAs($this->student, 'api')
            ->patchJson('api/consultation-access-enquiries/' . $this->enquiry->getKey(), [
                'proposed_terms' => [
                    $proposedTerm,
                ],
            ])
            ->assertOk();

        $this->enquiry->refresh();
        $this->assertEquals(EnquiryStatusEnum::PENDING, $this->enquiry->status);
        $this->assertNull($this->enquiry->consultationUser);
    }
}
