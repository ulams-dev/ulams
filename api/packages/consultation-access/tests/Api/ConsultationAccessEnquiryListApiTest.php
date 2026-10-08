<?php

namespace EscolaLms\ConsultationAccess\Tests\Api;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Support\Carbon;

class ConsultationAccessEnquiryListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationAccessPermissionSeeder::class);
    }

    public function testConsultationAccessEnquiryListUnauthorized(): void
    {
        $this->getJson('api/consultation-access-enquiries')
            ->assertUnauthorized();
    }

    public function testConsultationAccessEnquiryList(): void
    {
        $student = $this->makeStudent();

        ConsultationAccessEnquiry::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(3)
            ->has(ConsultationAccessEnquiryProposedTerm::factory())
            ->create();

        ConsultationAccessEnquiry::factory()->count(2)->create();

        $this->actingAs($student, 'api')->getJson('api/consultation-access-enquiries')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [[
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
                ]],
            ]);
    }

    public function testConsultationAccessEnquiryListFiltering(): void
    {
        $student = $this->makeStudent();

        ConsultationAccessEnquiry::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(4)
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->state(['proposed_at' => Carbon::now()]))
            ->create();

        /** @var ConsultationAccessEnquiry $approved */
        $approved = ConsultationAccessEnquiry::factory()
            ->state(['user_id' => $student->getKey()])
            ->approved()
            ->has(ConsultationAccessEnquiryProposedTerm::factory()->state(['proposed_at' => Carbon::now()->addDays(3)]))
            ->create();

        $this->actingAs($student, 'api')->getJson('api/consultation-access-enquiries?proposed_at_from=' . Carbon::now()->addDays(2))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($student, 'api')->getJson('api/consultation-access-enquiries?proposed_at_to=' . Carbon::now()->addDay())
            ->assertOk()
            ->assertJsonCount(4, 'data');

        $this->actingAs($student, 'api')->getJson('api/consultation-access-enquiries?is_coming=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($student, 'api')->getJson('api/consultation-access-enquiries?consultation_term_ids[]=' . $approved->consultation_user_id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function testConsultationAccessEnquiryListPagination(): void
    {
        $student = $this->makeStudent();

        ConsultationAccessEnquiry::factory()
            ->state(['user_id' => $student->getKey()])
            ->count(25)
            ->create();

        $this->actingAs($student, 'api')
            ->getJson('api/consultation-access-enquiries?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 25
                ]
            ]);

        $this->actingAs($student, 'api')
            ->getJson('api/consultation-access-enquiries?per_page=10&page=3')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJson([
                'meta' => [
                    'total' => 25
                ]
            ]);
    }
}
