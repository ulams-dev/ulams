<?php

namespace Ulams\ConsultationAccess\Tests\Api;

use Ulams\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use Ulams\ConsultationAccess\Tests\TestCase;
use Ulams\Core\Tests\CreatesUsers;

class ConsultationAccessEnquiryDeleteApiTest extends TestCase
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

    public function testConsultationAccessEnquiryDeleteUnauthorized(): void
    {
        $this->deleteJson('api/consultation-access-enquiries/' . $this->enquiry->getKey())
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryDelete(): void
    {
        $this->actingAs($this->student, 'api')
            ->deleteJson('api/consultation-access-enquiries/' . $this->enquiry->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('consultation_access_enquiries', [
            'id' => $this->enquiry->getKey(),
        ]);

        $this->assertDatabaseMissing('consultation_access_enquiry_proposed_terms', [
            'consultation_access_enquiry_id' => $this->enquiry->getKey(),
        ]);
    }

    public function testDeleteApprovedConsultationAccessEnquiry(): void
    {
        $this->enquiry = ConsultationAccessEnquiry::factory()
            ->state([
                'user_id' => $this->student->getKey(),
            ])
            ->approved()
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->count(4))
            ->create();

        $this->actingAs($this->student, 'api')
            ->deleteJson('api/consultation-access-enquiries/' . $this->enquiry->getKey())
            ->assertOk();

        $this->assertDatabaseMissing('consultation_access_enquiries', [
            'id' => $this->enquiry->getKey(),
        ]);

        $this->assertDatabaseMissing('consultation_access_enquiry_proposed_terms', [
            'consultation_access_enquiry_id' => $this->enquiry->getKey(),
        ]);

        $this->assertDatabaseMissing('consultation_user', [
            'id' => $this->enquiry->consultation_user_id,
        ]);
    }
}
