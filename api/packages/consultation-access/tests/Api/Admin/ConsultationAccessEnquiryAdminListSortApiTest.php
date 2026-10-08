<?php

namespace EscolaLms\ConsultationAccess\Tests\Api\Admin;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\ConsultationAccess\Models\Consultation;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Carbon;

class ConsultationAccessEnquiryAdminListSortApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationAccessPermissionSeeder::class);
    }

    public function testConsultationAccessEnquiryListSorting(): void
    {
        $admin = $this->makeAdmin();

        $studentOne = $this->makeStudent([
            'first_name' => 'A Student',
        ]);
        $studentTwo = $this->makeStudent([
            'first_name' => 'B Student',
        ]);
        $tutorOne = $this->makeInstructor();
        $tutorTwo = $this->makeInstructor();

        $consultationOne = Consultation::factory()->create(['name' => 'A Consultation']);
        $consultationTwo = Consultation::factory()->create(['name' => 'B Consultation']);

        /** @var ConsultationUserPivot $consultationUserOne */
        $consultationUserOne = ConsultationUserPivot::factory()->create([
            'user_id' => $studentOne->getKey(),
            'consultation_id' => $consultationOne->getKey(),
        ]);

        /** @var ConsultationUserTerm $userTermOne */
        $userTermOne = $consultationUserOne->userTerms()->create([
            'executed_status' => 'approved',
            'executed_at' => Carbon::today()->addDays(1),
        ]);

        /** @var ConsultationUserPivot $consultationUserTwo */
        $consultationUserTwo = ConsultationUserPivot::factory()->create([
            'user_id' => $studentTwo->getKey(),
            'consultation_id' => $consultationTwo->getKey(),
        ]);

        /** @var ConsultationUserTerm $userTermTwo */
        $userTermTwo = $consultationUserTwo->userTerms()->create([
            'executed_status' => 'approved',
            'executed_at' => Carbon::today()->addDays(2),
        ]);

        $enquiryOne = ConsultationAccessEnquiry::factory()
            ->state([
                'user_id' => $tutorOne->getKey(),
                'consultation_user_id' => $consultationUserOne->getKey(),
                'description' => 'A description',
                'consultation_id' => $consultationOne->getKey(),
                'meeting_link' => 'A link',
                'status' => EnquiryStatusEnum::APPROVED,
                'consultation_user_term_id' => $userTermOne->getKey(),
            ])
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->state(['proposed_at' => Carbon::today()->addDays(1)]))
            ->create();

        $enquiryTwo = ConsultationAccessEnquiry::factory()
            ->state([
                'user_id' => $tutorTwo->getKey(),
                'consultation_user_id' => $consultationUserTwo->getKey(),
                'description' => 'B description',
                'consultation_id' => $consultationTwo->getKey(),
                'meeting_link' => 'B link',
                'status' => EnquiryStatusEnum::PENDING,
                'consultation_user_term_id' => $userTermTwo->getKey(),
            ])
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->state(['proposed_at' => Carbon::today()->addDays(2)]))
            ->create();

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'term_date',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'term_date',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'id',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'id',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'status',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'status',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'description',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'description',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'meeting_link',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'meeting_link',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryOne->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'user_id',
            'order' => 'ASC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryOne->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryTwo->getKey());

        $response = $this->actingAs($admin, 'api')->json('GET', 'api/admin/consultation-access-enquiries', [
            'order_by' => 'user_id',
            'order' => 'DESC',
        ]);

        $this->assertTrue($response->json('data.0.id') === $enquiryTwo->getKey());
        $this->assertTrue($response->json('data.1.id') === $enquiryOne->getKey());
    }
}
