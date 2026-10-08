<?php

namespace EscolaLms\Recommender\Tests\Api;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Recommender\Dto\SatisfactionDto;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Models\MeetRecording;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Recommender\Services\Contracts\TermAnalyticServiceContract;
use EscolaLms\Recommender\Tests\TestCase;
use EscolaLms\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SatisfactionModelsTest extends TestCase
{
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ConsultationsPermissionSeeder::class);
        $this->seed(WebinarsPermissionSeeder::class);
    }

    private function makeTermAnalytic(): TermAnalytic
    {
        $consultation = Consultation::factory()->create();
        $modelType = 'consultation';
        $modelId = $consultation->getKey();
        $term = Carbon::now();

        $meetRecording = MeetRecording::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now(),
        ]);

        $termAnalytic = TermAnalytic::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'aggregated_frames_count' => 1,
            'meet_recording_id' => $meetRecording->getKey(),
        ]);

        AggregatedFrame::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'term_analytic_id' => $termAnalytic->getKey(),
        ]);

        return $termAnalytic;
    }

    public function testPredictSatisfactionSendsSelectedModelsFromSettings(): void
    {
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.frames_microservice_url', 'http://frames.test');
        Config::set(
            EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.satisfaction_models',
            [
                ['model' => 'prod_elasticnet', 'enabled' => true],
                ['model' => 'retrained_ridge3', 'enabled' => true],
                ['model' => 'retrained_rf13', 'enabled' => false],
            ]
        );

        Http::fake(['http://frames.test/api/frames/satisfaction' => Http::response([], 200)]);

        $termAnalytic = $this->makeTermAnalytic();

        app(TermAnalyticServiceContract::class)->predictSatisfaction($termAnalytic);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://frames.test/api/frames/satisfaction'
                && ($request['satisfaction_models'] ?? null) === ['prod_elasticnet', 'retrained_ridge3'];
        });
    }

    public function testSaveSatisfactionStoresPerModelRatingsAndResourceReturnsThem(): void
    {
        $termAnalytic = $this->makeTermAnalytic();

        $ratings = [
            ['model' => 'prod_elasticnet', 'model_label' => 'Produkcyjny ElasticNet', 'model_version' => 'v1.0-mse095', 'mean_predicted_rating' => 7.42],
        ];

        $dto = new SatisfactionDto($termAnalytic->getKey(), '7.42', $ratings);
        app(TermAnalyticServiceContract::class)->saveSatisfaction($dto);

        $this->assertEquals('prod_elasticnet', $termAnalytic->fresh()->satisfaction_models[0]['model']);

        $this->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/analytics/consultation/' . $termAnalytic->model_id . '/' . $termAnalytic->getKey())
            ->assertOk()
            ->assertJsonStructure(['data' => ['rating', 'ratings']])
            ->assertJsonCount(1, 'data.ratings')
            ->assertJsonPath('data.ratings.0.model', 'prod_elasticnet')
            ->assertJsonPath('data.ratings.0.mean_predicted_rating', '7.42000');
    }
}
