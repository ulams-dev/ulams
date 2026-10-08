<?php

namespace EscolaLms\ConsultationAccess\Tests\Api;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Enum\MeetingLinkTypeEnum;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\PencilSpaces\Facades\PencilSpace;

class ConsultationAccessEnquiryJoinApiTest extends TestCase
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

    public function testConsultationAccessEnquiryJoinUnauthorized(): void
    {
        $this->getJson('api/consultation-access-enquiries/' . $this->enquiry->getKey() . '/join')
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryJoinForbidden(): void
    {
        $this->actingAs($this->makeStudent(), 'api')
            ->getJson('api/consultation-access-enquiries/' . $this->enquiry->getKey() . '/join')
            ->assertForbidden();
    }

    public function testConsultationAccessEnquiryJoinToPencilSpace(): void
    {
        PencilSpace::fake();

        $this->enquiry->update([
            'meeting_link' => $this->faker->url,
            'meeting_link_type' => MeetingLinkTypeEnum::PENCIL_SPACES,
        ]);

        $this->actingAs($this->student, 'api')
            ->getJson('api/consultation-access-enquiries/' . $this->enquiry->getKey() . '/join')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'meeting_link',
                    'meeting_link_type',
                ],
            ]);
    }
}
