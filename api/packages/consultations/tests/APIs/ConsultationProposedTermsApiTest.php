<?php

namespace EscolaLms\Consultations\Tests\APIs;

use Carbon\Carbon;
use EscolaLms\Consultations\Tests\Models\User;
use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationProposedTerm;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationProposedTermsApiTest extends TestCase
{
    use DatabaseTransactions;
    private Consultation $consultation;
    private ConsultationUserPivot $consultationUserPivot;
    private string $apiUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    private function initVariable(): void
    {
        $this->consultation = Consultation::factory()->create();
        $this->consultation->proposedTerms()->saveMany(ConsultationProposedTerm::factory(3)->create());
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $this->consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();
    }

    public function testConsultationProposedTermsUnauthorized()
    {
        $this->response = $this->json(
            'GET',
            'api/consultations/proposed-terms/1'
        );
        $this->response->assertUnauthorized();
    }

    public function testConsultationProposedTerms()
    {
        $this->initVariable();
        $consultationProposedTerms = $this->consultation->proposedTerms->map(
            fn (ConsultationProposedTerm $consultationTerm) => (string)Carbon::make($consultationTerm->proposed_at)
        )->toArray();
        $this->response = $this->actingAs($this->user, 'api')->json(
            'GET',
            'api/consultations/proposed-terms/' . $this->consultationUserPivot->getKey()
        );
        $this->response->assertOk();
        $this->response->assertJson(fn (AssertableJson $json) => $json
            ->where('success', true)
            ->where('data', function ($json) use ($consultationProposedTerms) {
                foreach ($json->toArray() as $date) {
                    if (!in_array((string)Carbon::make($date), $consultationProposedTerms)) {
                        return false;
                    }
                }
                return true;
            })
            ->etc()
        );
    }
}
