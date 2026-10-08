<?php

namespace EscolaLms\ConsultationAccess\Tests\Api;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;

class ConsultationAccessEnquiryReadApiTest extends TestCase
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

    public function testConsultationAccessEnquiryReadUnauthorized(): void
    {
        $this->getJson('api/consultation-access-enquiries/' . $this->enquiry->getKey())
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryReadForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/consultation-access-enquiries/' . $this->enquiry->getKey())
            ->assertForbidden();
    }

    public function testConsultationAccessEnquiryRead(): void
    {
        $this->actingAs($this->student, 'api')
            ->getJson('api/consultation-access-enquiries/' . $this->enquiry->getKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'created_at',
                    'status',
                    'consultation' => [
                        'id',
                        'name',
                    ],
                    'consultation_term_id',
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'proposed_terms' => [
                        [
                            'id',
                            'proposed_at',
                        ],
                    ],
                    'description',
                ],
            ]);
    }
}
