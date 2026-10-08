<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Events\ChangeTerm;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Tests\TestCase;
use EscolaLms\Core\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;

class ConsultationChangeTermTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
        $this->consultation = Consultation::factory()->create();
        $this->consultation->author()->associate($this->user);
        $data = [
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
            'executed_status' => ConsultationTermStatusEnum::NOT_REPORTED
        ];
        $this->consultation->attachToConsultationPivot($data);
    }

    public function testChangeTermWithAdmin()
    {
        Event::fake();
        /** @var ConsultationUserPivot $term */
        $term = $this->consultation->terms()->first();

        $userTerm = $term->userTerms()->create([
            'executed_at' => now()->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $newTerm = now()->modify('+2 hours')->format('Y-m-d H:i:s');
        $this->response = $this->actingAs($this->user, 'api')->post(
            '/api/admin/consultations/change-term/' . $term->getKey(),
            ['executed_at' => $newTerm, 'term' => $userTerm->executed_at, 'accept' => true]
        );
        $this->response->assertOk();
        $userTerm->refresh();
        $this->assertTrue($userTerm->executed_at === $newTerm);
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::APPROVED);
        Event::assertDispatched(ChangeTerm::class);
    }

    public function testChangeTermForOneUser()
    {
        Event::fake();
        /** @var ConsultationUserPivot $term */
        $term = $this->consultation->terms()->first();

        $userTerm = $term->userTerms()->create([
            'executed_at' => now()->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $user = User::factory()->create();
        $user->guard_name = 'api';
        $user->assignRole('student');

        $this->consultation->attachToConsultationPivot([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $user->getKey(),
            'executed_status' => ConsultationTermStatusEnum::NOT_REPORTED,
        ]);

        /** @var ConsultationUserPivot $term */
        $changedTerm = $this->consultation->terms()->where('user_id', '=', $user->getKey())->first();

        $userChangedTerm = $changedTerm->userTerms()->create([
            'executed_at' => now()->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $newTerm = now()->modify('+2 hours')->format('Y-m-d H:i:s');
        $this->response = $this->actingAs($this->user, 'api')->post(
            '/api/admin/consultations/change-term/' . $term->getKey(),
            ['executed_at' => $newTerm, 'term' => $userChangedTerm->executed_at, 'user_id' => $user->getKey(), 'accept' => true]
        );
        $this->response->assertOk();
        $userTerm->refresh();
        $userChangedTerm->refresh();
        $this->assertTrue($userChangedTerm->executed_at === $newTerm);
        $this->assertTrue($userChangedTerm->executed_status === ConsultationTermStatusEnum::APPROVED);
        $this->assertTrue($userTerm->executed_at !== $newTerm);
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::REPORTED);
        Event::assertDispatched(ChangeTerm::class);
    }

    public function testChangeTermWithUser()
    {
        Event::fake();
        /** @var ConsultationUserPivot $term */
        $term = $this->consultation->terms()->first();

        $userTerm = $term->userTerms()->create([
            'executed_at' => now()->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REPORTED,
        ]);

        $newTerm = now()->modify('+2 hours')->format('Y-m-d H:i:s');
        $this->response = $this->actingAs($this->user, 'api')->post(
            '/api/consultations/change-term/' . $term->getKey(),
            ['executed_at' => $newTerm, 'term' => $userTerm->executed_at]
        );
        $this->response->assertOk();
        $userTerm->refresh();
        $this->assertTrue($userTerm->executed_at === $newTerm);
        $this->assertTrue($userTerm->executed_status === ConsultationTermStatusEnum::REPORTED);
        Event::assertDispatched(ChangeTerm::class);
    }

    public function testChangeTermUnauthorized()
    {
        $this->response = $this->json('POST','/api/admin/consultations/change-term/1');
        $this->response->assertUnauthorized();
    }
}
