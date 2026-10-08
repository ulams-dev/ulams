<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Enum\ConsultationTermStatusEnum;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Consultations\Tests\TestCase;
use EscolaLms\Jitsi\Services\Contracts\JitsiServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationGenerateJitsiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
    }

    private function initVariable(): void
    {
        $consultation = Consultation::factory()->create();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();
    }

    public function testGenerateJitsiUnAuthorized(): void
    {
        $response = $this->json('GET', 'api/consultations/generate-jitsi/1');
        $response->assertUnauthorized();
    }

    public function testGenerateJitsiWithApprovedTerm(): void
    {
        $consultation = Consultation::factory()->create();
        $time = now();
        $this->consultationUserPivot = ConsultationUserPivot::factory([
            'consultation_id' => $consultation->getKey(),
            'user_id' => $this->user->getKey(),
        ])->create();

        $consultationUserTerm = $this->consultationUserPivot->userTerms()->create([
            'executed_at' => $time->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);

        $returnData = [
            'data' =>
                [
                    'domain' => 'meet-stage.escolalms.com',
                    'roomName' => lcfirst(Str::studly($this->consultationUserPivot->consultation->name)),
                    'configOverwrite' => [],
                    'interfaceConfigOverwrite' => [],
                    'userInfo' => [
                        'displayName' => "{$this->user->first_name} {$this->user->last_name}",
                        'email' => $this->user->email,
                    ],
                    'jwt' => 'test',
                ],
            "domain" => "meet-stage.escolalms.com",
            "url" => "test",
        ];
        $jitsiService = $this->mock(JitsiServiceContract::class);
        $jitsiService->shouldReceive('getChannelData')->once()->andReturn($returnData);
        $response = $this->actingAs($this->user, 'api')->json('GET', 'api/consultations/generate-jitsi/' . $this->consultationUserPivot->getKey(), ['term' => $consultationUserTerm->executed_at]);
        $response->assertOk();
        $response->assertJson(
            fn (AssertableJson $json) => $json->has('data',
                fn (AssertableJson $json) => $json->has('data',
                    fn (AssertableJson $json) => $json->has('jwt')
                        ->has('userInfo',
                            fn (AssertableJson $json) => $json
                                ->where('displayName', "{$this->user->first_name} {$this->user->last_name}")
                                ->where('email', $this->user->email)
                        )
                        ->where('roomName', lcfirst(Str::studly($this->consultationUserPivot->consultation->name)))
                        ->etc()
                )
                    ->etc()
            )->where('success', true)->etc()
        );
    }

    public function testGenerateJitsiWithRejectedTerm(): void
    {
        $this->initVariable();

        $time = now();
        $this->consultationUserPivot->userTerms()->create([
            'executed_at' => $time->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::REJECT,
        ]);

        $response = $this->actingAs($this->user, 'api')->json('GET', 'api/consultations/generate-jitsi/' . $this->consultationUserPivot->getKey(), ['term' => $time->format('Y-m-d H:i:s')]);
        $response->assertNotFound();
        $response->assertJson(fn (AssertableJson $json) => $json->where('message', __('Consultation term is not available'))->etc());
    }

    public function testGenerateJitsiBeforeExecutedAt(): void
    {
        $this->initVariable();

        $time = now()->addHours(4);
        $this->consultationUserPivot->userTerms()->create([
            'executed_at' => $time->format('Y-m-d H:i:s'),
            'executed_status' => ConsultationTermStatusEnum::APPROVED,
        ]);

        $response = $this->actingAs($this->user, 'api')->json('GET', 'api/consultations/generate-jitsi/' . $this->consultationUserPivot->getKey(), ['term' => $time->format('Y-m-d H:i:s')]);
        $response->assertNotFound();
        $response->assertJson(fn (AssertableJson $json) => $json->where('message', __('Consultation term is not available'))->etc());
    }
}
