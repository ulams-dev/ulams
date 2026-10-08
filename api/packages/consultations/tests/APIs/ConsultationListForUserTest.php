<?php

namespace EscolaLms\Consultations\Tests\APIs;

use EscolaLms\Categories\Models\Category;
use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Tests\Models\User;
use EscolaLms\Consultations\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Testing\Fluent\AssertableJson;

class ConsultationListForUserTest extends TestCase
{
    use DatabaseTransactions;
    private string $apiUrl;
    private Collection $consultations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ConsultationsPermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
        $this->apiUrl = 'api/consultations/me';
    }

    private function initVariable(): void
    {
        $categories = Category::factory(5)->create();
        $this->user->categories()->sync($categories);
        $this->consultations = Consultation::factory(3)->create();
        $this->user->consultations()->sync($this->consultations->pluck('id')->toArray());
    }

    public function testConsultationListForUser(): void
    {
        $this->initVariable();
        $this->response = $this->actingAs($this->user, 'api')->json('GET', $this->apiUrl);
        $consArray = $this->consultations->pluck('id')->toArray();
        $this->response->assertJson(fn (AssertableJson $json) => $json->has(
            'data',
                fn ($json) => $json->each(fn (AssertableJson $json) =>
                    $json->where('id', fn ($json) =>
                        in_array($json, $consArray)
                    )->has('author')
                    ->etc()
                )
                ->etc()
            )
            ->etc()
        );
        $this->response->assertOk();
    }

    public function testConsultationListForUserOrderByBoughtDate(): void
    {
        $consultationOne = Consultation::factory()->create();
        $consultationTwo = Consultation::factory()->create();

        $this->user->consultations()->save($consultationOne);
        $this->travel(5)->days();
        $this->user->consultations()->save($consultationTwo);

        $this->response = $this->actingAs($this->user, 'api')->json('GET', $this->apiUrl);

        $this->assertTrue($this->response['data'][0]['id'] === $consultationTwo->getKey());
        $this->assertTrue($this->response['data'][1]['id'] === $consultationOne->getKey());
    }

    public function testConsultationListForUserFilterByIds(): void
    {
        $consultation1 = Consultation::factory()->create();
        $consultation2 = Consultation::factory()->create();

        $this->user->consultations()->save($consultation1);
        $this->user->consultations()->save($consultation2);

        $this->response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', $this->apiUrl)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->response = $this
            ->actingAs($this->user, 'api')
            ->json('GET', $this->apiUrl . '?ids[]=' . $consultation1->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function testConsultationListForUserFilterConsultationTerm(): void
    {
        $this->initVariable();
        $consultationTermRandom = $this->consultations->random(1)->first()->terms->random(1)->first();
        $filterData = [
            'consultation_term_id=' . $consultationTermRandom->getKey(),
        ];
        $this->response = $this->actingAs($this->user, 'api')->json('GET', $this->apiUrl. '?' . implode('&', $filterData));
        $this->response->assertJson(fn (AssertableJson $json) => $json->has(
            'data',
            fn ($json) => $json->each(fn (AssertableJson $json) =>
            $json->where('consultation_term_id', fn ($json) => in_array($json, [$consultationTermRandom->getKey()]))
                ->where('id', fn ($json) =>
            in_array($json, [$consultationTermRandom->consultation->getKey()])
            )->has('author')
                ->etc()
            )
                ->etc()
        )
            ->etc()
        );
        $this->response->assertOk();
    }

    public function testConsultationReportTermUnauthorized(): void
    {
        $this->initVariable();
        $this->response = $this->json('GET', $this->apiUrl);
        $this->response->assertUnauthorized();
    }
}
