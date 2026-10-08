<?php

namespace Ulams\ConsultationAccess\Tests\Api;

use Ulams\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use Ulams\ConsultationAccess\Enum\ConsultationAccessPermissionEnum;
use Ulams\ConsultationAccess\Events\ConsultationAccessEnquiryAdminCreatedEvent;
use Ulams\ConsultationAccess\Models\Consultation;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\ConsultationAccess\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class ConsultationAccessEnquiryCreateApiTest extends TestCase
{
    use CreatesUsers;

    private $consultation;
    private $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationAccessPermissionSeeder::class);
        $this->consultation = Consultation::factory()->create();
        $this->student = $this->makeStudent();
        $this->makeAdmin();
    }

    public function testConsultationAccessEnquiryCreateUnauthorized(): void
    {
        $this->postJson('api/consultation-access-enquiries')
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryCreate(): void
    {
        Event::fake([ConsultationAccessEnquiryAdminCreatedEvent::class]);
        $proposedTerm = Carbon::now()->addDays();
        $description = $this->faker->text();

        $this->actingAs($this->student, 'api')
            ->postJson('api/consultation-access-enquiries', [
                'consultation_id' => $this->consultation->getKey(),
                'description' => $description,
                'proposed_terms' => [
                    $proposedTerm,
                ],
            ])->assertCreated();

        $this->assertDatabaseHas('consultation_access_enquiries', [
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->student->getKey(),
            'description' => $description,
        ]);

        /** @var ConsultationAccessEnquiry $enquiry */
        $enquiry = ConsultationAccessEnquiry::query()->latest()->first();
        $this->assertCount(1, $enquiry->consultationAccessEnquiryProposedTerms);

        Event::assertDispatched(function (ConsultationAccessEnquiryAdminCreatedEvent $event) {
            $this->assertTrue($event->getUser()->hasPermissionTo(ConsultationAccessPermissionEnum::APPROVE_CONSULTATION_ACCESS_ENQUIRY));
            $this->assertEquals($this->consultation->getKey(), $event->getConsultationAccessEnquiry()->consultation_id);
            return true;
        });
    }
}
