<?php

namespace EscolaLms\ConsultationAccess\Tests\Api\Admin;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Events\ConsultationAccessEnquiryDisapprovedEvent;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Support\Facades\Event;

class ConsultationAccessEnquiryAdminDisapproveApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationAccessPermissionSeeder::class);
    }

    public function testConsultationAccessEnquiryAdminDisapproveUnauthorized(): void
    {
        $this->postJson('api/admin/consultation-access-enquiries/disapprove/1')
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryAdminDisapprove(): void
    {
        Event::fake([ConsultationAccessEnquiryDisapprovedEvent::class]);

        $enquiry = ConsultationAccessEnquiry::factory()
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->count(4))
            ->create();

        $this->actingAs($this->makeAdmin(), 'api')
            ->postJson('api/admin/consultation-access-enquiries/disapprove/' . $enquiry->getKey(), [
                'message' => 'Example message',
            ])
            ->assertOk();

        Event::assertDispatched(function (ConsultationAccessEnquiryDisapprovedEvent $event) use ($enquiry) {
            $this->assertEquals($enquiry->user_id, $event->getUser()->getKey());
            $this->assertEquals($enquiry->consultation->name, $event->getConsultationName());
            $this->assertEquals('Example message', $event->getMessage());
            return true;
        });
    }
}
