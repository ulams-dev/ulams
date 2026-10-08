<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Consultations\Events\ApprovedTermWithTrainer;
use EscolaLms\Consultations\Events\RejectTermWithTrainer;
use EscolaLms\Consultations\Events\ReportTerm;
use EscolaLms\Consultations\Models\ConsultationProposedTerm;
use EscolaLms\Consultations\Models\ConsultationUserTerm;
use EscolaLms\Consultations\Tests\Models\User;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Events\ApprovedTerm;
use EscolaLms\Consultations\Events\RejectTerm;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationReportTermTest extends TestCase
{
    use DatabaseTransactions;
    private string $apiUrl;
    private Consultation $consultation;
    private ConsultationUserPivot $consultationUserPivot;
    private ConsultationUserTerm $consultationUserTerm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    private function initVariable(): void
    {
        $this->consultation = Consultation::factory()->create();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();
    }

    public function testConsultationReportTerm(): void
    {
        $this->initVariable();
        $now = now()->modify('+1 day');
        $this->response = $this->actingAs($this->user, 'api')
            ->json('POST',
                '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
                [
                    'term' => $now->format('Y-m-d H:i:s')
                ]
            );
        $this->consultationUserPivot->refresh();
        $userTerm = $this->consultationUserPivot->userTerms()->first();
        $this->assertTrue($userTerm->executed_at === $now->format('Y-m-d H:i:s'));
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::REPORTED);
        $this->response->assertOk();
    }

    public function testConsultationReportProposedTerm(): void
    {
        Event::fake([ReportTerm::class]);
        $this->initVariable();
        $this->consultation->update(['author_id' => null]);
        $this->consultation->teachers()->attach([
            User::factory()->create()->getKey(),
            User::factory()->create()->getKey(),
        ]);

        $proposedTerm = ConsultationProposedTerm::factory()
            ->state([
                'proposed_at' => now()->addDays(2),
            ])
            ->for($this->consultation)
            ->create();

        $this->response = $this->actingAs($this->user, 'api')
            ->json('POST',
                '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
                [
                    'term' => $proposedTerm->proposed_at,
                ]
            )
            ->assertOk();

        Event::assertDispatchedTimes(ReportTerm::class, 2);
    }

    public function testConsultationReportTermMultipleTerm(): void
    {
        $this->initVariable();
        $now = now()->modify('+1 day');
        /** @var ConsultationUserPivot $consultationUser */
        $consultationUser = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultationUserPivot->consultation_id,
            'user_id' => $this->user->getKey()
        ])->create();

        $consultationUser->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);
        $this->response = $this->actingAs($this->user, 'api')
            ->json('POST',
                '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
                [
                    'term' => $now->format('Y-m-d H:i:s')
                ]
            );
        $this->response->assertJson(fn (AssertableJson $json) => $json->where(
                'message', fn(string $json) => $json === __('You already reported this term.')
            )->etc()
        );
        $this->response->assertStatus(400);
    }

    public function testConsultationReportTermMultipleTermDifferentUsers(): void
    {
        $this->initVariable();
        $now = now()->modify('+1 day');

        $this->consultation->update([
            'max_session_students' => 4,
        ]);

        $user = User::factory()->create();
        $user->assignRole('student');
        /** @var ConsultationUserPivot $consultationUser */
        $consultationUser = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $user->getKey()
        ])->create();

        $consultationUser->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->json('POST',
                '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
                [
                    'term' => $now->format('Y-m-d H:i:s')
                ]
            )->assertOk();
        $this->consultationUserPivot->refresh();
        $userTerm = $this->consultationUserPivot->userTerms()->first();
        $this->assertTrue($userTerm->executed_at === $now->format('Y-m-d H:i:s'));
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::REPORTED);
    }

    public function testConsultationReportTermMultipleTermDifferentUsersLimit(): void
    {
        $this->initVariable();
        $now = now()->modify('+1 day');

        $this->consultation->update([
            'max_session_students' => 1,
        ]);

        $user = User::factory()->create();
        $user->assignRole('student');

        /** @var ConsultationUserPivot $consultationUser */
        $consultationUser = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $user->getKey()
        ])->create();

        $consultationUser->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);

        $this->response = $this->actingAs($this->user, 'api')
            ->json('POST',
                '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
                [
                    'term' => $now->format('Y-m-d H:i:s')
                ]
            )->assertStatus(400)
            ->assertJson(fn (AssertableJson $json) => $json->where(
                    'message', fn(string $json) => $json === __('Term is busy, change your term.')
                )->etc()
            );
    }

    public function testConsultationReportTermUnauthorized(): void
    {
        $this->initVariable();
        $now = now()->modify('+1 day');
        $this->response = $this->json('POST',
                '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
                [
                    'term' => $now->format('Y-m-d H:i:s')
                ]
            );
        $this->response->assertUnauthorized();
    }

    public function testConsultationTermApproved(): void
    {
        Event::fake([
            ApprovedTerm::class,
            ApprovedTermWithTrainer::class,
        ]);
        $this->initVariable();
        $now = now()->modify('+1 day');
        $this->response = $this->actingAs($this->user, 'api')->json('POST',
            '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
            [
                'term' => $now->format('Y-m-d H:i:s')
            ]
        );
        $this->consultationUserPivot->refresh();
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/consultations/approve-term/' . $this->consultationUserPivot->getKey(),
            ['term' => $now->format('Y-m-d H:i:s')]
        );
        $this->consultationUserPivot->refresh();
        $userId = $this->user->getKey();
        Event::assertDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $userId &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        Event::assertDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        $this->consultationUserPivot->refresh();
        $this->response->assertOk();
        /** @var ConsultationUserTerm $userTerm */
        $userTerm = $this->consultationUserPivot->userTerms()->first();
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::APPROVED);
    }

    public function testConsultationMultipleTermApproved(): void
    {
        Event::fake([
            ApprovedTerm::class,
            ApprovedTermWithTrainer::class,
        ]);
        $this->initVariable();
        $now = now()->modify('+1 day');

        $this->consultation->update([
            'max_session_students' => 3,
        ]);

        $student1 = User::factory()->create();
        $student1->assignRole('student');

        $student2 = User::factory()->create();
        $student2->assignRole('student');

        /** @var ConsultationUserPivot $consultationStudent1 */
        $consultationStudent1 = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $student1->getKey()
        ])->create();

        $userTermStudent1 = $consultationStudent1->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $consultationStudent2 = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $student2->getKey()
        ])->create();

        $userTermStudent2 = $consultationStudent2->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $this->consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/consultations/approve-term/' . $this->consultationUserPivot->getKey(),
            ['term' => $now->format('Y-m-d H:i:s')]
        )->assertOk();

        $this->consultationUserPivot->refresh();
        $this->consultationUserTerm->refresh();
        Event::assertDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        Event::assertDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        $this->assertTrue($this->consultationUserTerm->executed_status === ConsultationTermStatusEnum::APPROVED);

        $consultationStudent1->refresh();
        $userTermStudent1->refresh();
        Event::assertDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $student1->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent1->getKey()
        );
        Event::assertDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent1->getKey()
        );
        $this->assertTrue($userTermStudent1->executed_status === ConsultationTermStatusEnum::APPROVED);

        $consultationStudent2->refresh();
        $userTermStudent2->refresh();
        Event::assertDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $student2->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent2->getKey()
        );
        Event::assertDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent2->getKey()
        );
        $this->assertTrue($userTermStudent2->executed_status === ConsultationTermStatusEnum::APPROVED);
    }

    public function testConsultationMultipleTermOneApproved(): void
    {
        Event::fake([
            ApprovedTerm::class,
            ApprovedTermWithTrainer::class,
        ]);
        $this->initVariable();
        $now = now()->modify('+1 day');

        $this->consultation->update([
            'max_session_students' => 4,
        ]);

        $student1 = User::factory()->create();
        $student1->assignRole('student');

        $student2 = User::factory()->create();
        $student2->assignRole('student');

        $student3 = User::factory()->create();
        $student3->assignRole('student');

        /** @var ConsultationUserPivot $consultationStudent1 */
        $consultationStudent1 = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $student1->getKey()
        ])->create();

        $userTermStudent1 = $consultationStudent1->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $consultationStudent2 = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $student2->getKey()
        ])->create();

        $userTermStudent2 = $consultationStudent2->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $this->consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => $now->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/consultations/approve-term/' . $this->consultationUserPivot->getKey(),
            ['term' => $now->format('Y-m-d H:i:s'), 'user_id' => $student1->getKey()]
        )->assertOk();

        $this->consultationUserPivot->refresh();
        $this->consultationUserTerm->refresh();
        Event::assertNotDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        Event::assertNotDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        $this->assertTrue($this->consultationUserTerm->executed_status === ConsultationTermStatusEnum::REPORTED);

        $consultationStudent1->refresh();
        $userTermStudent1->refresh();
        Event::assertDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $student1->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent1->getKey()
        );
        Event::assertDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent1->getKey()
        );
        $this->assertTrue($userTermStudent1->executed_status === ConsultationTermStatusEnum::APPROVED);

        $consultationStudent2->refresh();
        $userTermStudent2->refresh();
        Event::assertNotDispatched(ApprovedTerm::class, fn (ApprovedTerm $event) =>
            $event->getUser()->getKey() === $student2->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent2->getKey()
        );
        Event::assertNotDispatched(ApprovedTermWithTrainer::class, fn (ApprovedTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $consultationStudent2->getKey()
        );
        $this->assertTrue($userTermStudent2->executed_status === ConsultationTermStatusEnum::REPORTED);
    }

    public function testConsultationTermApprovedUnauthorized(): void
    {
        $this->response = $this->json(
            'GET',
            '/api/consultations/approve-term/1'
        );
        $this->response->assertUnauthorized();
    }

    public function testConsultationTermReject(): void
    {
        Event::fake([
            RejectTerm::class,
            RejectTermWithTrainer::class
        ]);
        $this->initVariable();
        $now = now()->modify('+1 day');
        $this->response = $this->actingAs($this->user, 'api')->json('POST',
            '/api/consultations/report-term/' . $this->consultationUserPivot->getKey(),
            [
                'term' => $now->format('Y-m-d H:i:s')
            ]
        );
        $this->consultationUserPivot->refresh();
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            '/api/consultations/reject-term/' . $this->consultationUserPivot->getKey(),
            ['term' => $now->format('Y-m-d H:i:s')]
        );
        $userId = $this->user->getKey();
        Event::assertDispatched(RejectTerm::class, fn (RejectTerm $event) =>
            $event->getUser()->getKey() === $userId &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        Event::assertDispatched(RejectTermWithTrainer::class, fn (RejectTermWithTrainer $event) =>
            $event->getUser()->getKey() === $this->user->getKey() &&
            $event->getConsultationTerm()->getKey() === $this->consultationUserPivot->getKey()
        );
        $this->consultationUserPivot->refresh();
        $this->response->assertOk();
        $userTerm = $this->consultationUserPivot->userTerms()->first();
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::REJECT);
    }

    public function testConsultationTermRejectUnauthorized(): void
    {
        $this->response = $this->json(
            'GET',
            '/api/consultations/reject-term/1'
        );
        $this->response->assertUnauthorized();
    }
}
