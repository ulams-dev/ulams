<?php

namespace EscolaLms\ConsultationAccess\Tests\Api\Admin;

use EscolaLms\ConsultationAccess\Database\Seeders\ConsultationAccessPermissionSeeder;
use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\ConsultationAccess\Models\Consultation;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\ConsultationAccess\Tests\TestCase;
use EscolaLms\Core\Tests\CreatesUsers;
use Illuminate\Database\Eloquent\Factories\Sequence;

class ConsultationAccessEnquiryAdminListApiTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationAccessPermissionSeeder::class);

        $this->student1 = $this->makeStudent();
        $this->student2 = $this->makeStudent();

        $this->consultation1 = Consultation::factory()->create();
        $this->consultation2 = Consultation::factory()->create();
        $this->consultation3 = Consultation::factory()->create();

        ConsultationAccessEnquiry::factory()
            ->count(5)
            ->state(new Sequence(
                ['user_id' => $this->student1->getKey(), 'consultation_id' => $this->consultation1->getKey()],
                ['user_id' => $this->student1->getKey(), 'consultation_id' => $this->consultation2->getKey()],

                ['user_id' => $this->student2->getKey(), 'consultation_id' => $this->consultation1->getKey()],
                ['user_id' => $this->student2->getKey(), 'consultation_id' => $this->consultation2->getKey(), 'status' => EnquiryStatusEnum::APPROVED],
                ['user_id' => $this->student2->getKey(), 'consultation_id' => $this->consultation3->getKey()],
            ))
            ->create();
    }

    public function testConsultationAccessEnquiryAdminListUnauthorized(): void
    {
        $this->getJson('api/admin/consultation-access-enquiries')
            ->assertUnauthorized();
    }

    /**
     * @dataProvider adminFilterDataProvider
     */
    public function testConsultationAccessEnquiryAdminList(callable $filter, int $count): void
    {
        $queryParams = $filter($this->student2->getKey(), $this->consultation2->getKey());

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/consultation-access-enquiries?' . $queryParams)
            ->assertOk()
            ->assertJsonCount($count, 'data')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'created_at',
                    'status',
                    'consultation' => [
                        'id',
                        'name',
                    ],
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'proposed_terms',
                    'description',
                    'title',
                    'related_type',
                    'related_id',
                ]],
            ]);
    }

    public function adminFilterDataProvider(): array
    {
        return [
            [
                'filter' => (function (int $idStudent2, int $idConsultation2) {
                    return 'consultation_id=' . $idConsultation2;
                }),
                'count' => 2,
            ],
            [
                'filter' => (function (int $idStudent2) {
                    return 'user_id=' . $idStudent2;
                }),
                'count' => 3,
            ],
            [
                'filter' => (function (int $idStudent2, int $idConsultation2) {
                    return 'user_id=' . $idStudent2 . '&consultation_id=' . $idConsultation2;
                }),
                'count' => 1,
            ],
            [
                'filter' => (function () {
                    return 'status=' . EnquiryStatusEnum::APPROVED;
                }),
                'count' => 1,
            ],
        ];
    }
}
