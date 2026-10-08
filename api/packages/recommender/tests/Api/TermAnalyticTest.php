<?php

namespace EscolaLms\Recommender\Tests\Api;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Recommender\Jobs\RebuildTermAnalyticJob;
use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Models\MeetRecording;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Recommender\Services\Contracts\TermAnalyticServiceContract;
use EscolaLms\Recommender\Tests\CreatesCourse;
use EscolaLms\Recommender\Tests\TestCase;
use EscolaLms\Webinar\Database\Seeders\WebinarsPermissionSeeder;
use EscolaLms\Webinar\Models\Webinar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;

class TermAnalyticTest extends TestCase
{
    use CreatesCourse, CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ConsultationsPermissionSeeder::class);
        $this->seed(WebinarsPermissionSeeder::class);
    }

    public function testTermAnalyticList(): void
    {
        $modelType = 'consultation';
        $term = Carbon::now();

        $startAt = Carbon::now();
        $endAt = Carbon::now()->addHour();

        $consultation = Consultation::factory()->create([
            'name' => 'Consultation test',
        ]);
        $modelId = $consultation->getKey();

        $meetRecordingConsultation = MeetRecording::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);

        $termConsultation = TermAnalytic::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'aggregated_frames_count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'meet_recording_id' => $meetRecordingConsultation->getKey(),
        ]);

        AggregatedFrame::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'window_start' => $startAt->copy()->addSeconds(15),
            'window_end' => $startAt->copy()->addSeconds(30),
        ]);

        $modelTypeWebinar = 'webinar';

        $webinar = Webinar::factory()->create([
            'name' => 'Webinar test',
        ]);
        $modelWebinarId = $webinar->getKey();

        $meetRecordingWebinar = MeetRecording::factory()->create([
            'model_type' => $modelTypeWebinar,
            'model_id' => $modelWebinarId,
            'term' => $term,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);

        $termWebinar = TermAnalytic::factory()->create([
            'model_type' => $modelTypeWebinar,
            'model_id' => $modelWebinarId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'aggregated_frames_count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'meet_recording_id' => $meetRecordingWebinar->getKey(),
        ]);

        AggregatedFrame::factory()->create([
            'model_type' => $modelTypeWebinar,
            'model_id' => $modelWebinarId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'window_start' => $startAt->copy()->addSeconds(15),
            'window_end' => $startAt->copy()->addSeconds(30),
        ]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson("api/admin/recommender/terms/{$modelType}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['model_type' => $modelTypeWebinar, 'id' => $termWebinar->getKey()]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson("api/admin/recommender/terms/{$modelTypeWebinar}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['model_type' => $modelType, 'id' => $termConsultation->getKey()]);
    }

    public function testRebuildTermAnalytic(): void
    {
        $modelType = 'consultation';
        $modelId = 37;
        $term = Carbon::now();
        $startAt = Carbon::now();
        $endAt = Carbon::now()->addMinutes(30);

        $aggregatedFrame = AggregatedFrame::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'window_start' => $startAt->copy()->addSeconds(15),
            'window_end' => $startAt->copy()->addSeconds(30),
        ]);

        MeetRecording::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'start_at' => $startAt,
            'end_at' => $endAt,
        ]);

        $job = new RebuildTermAnalyticJob();
        $service = app(TermAnalyticServiceContract::class);
        $job->handle($service);

        $this->assertDatabaseHas('term_analytics', [
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => '1.0000000000000000000000000',
            'count' => 1,
            'sum_emotions_happy' => '0.6000000000000000000000000',
            'sum_emotions_sad' => '0.4000000000000000000000000',
            'aggregated_frames_count' => 1,
        ]);
    }

    public function testRebuildTermAnalyticExisting(): void
    {
        $modelType = 'consultation';
        $modelId = 2137;
        $term = Carbon::now();
        $startAt = Carbon::now();
        $endAt = Carbon::now()->addMinutes(30);

        $meet = MeetRecording::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'start_at' => $startAt,
            'end_at' => $endAt,
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
            'meet_recording_id' => $meet->getKey(),
        ]);

        $aggregatedFrame = AggregatedFrame::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.6,
            'sum_emotions_sad' => 0.4,
            'term_analytic_id' => $termAnalytic->getKey(),
            'window_start' => $startAt->copy()->addSeconds(15),
            'window_end' => $startAt->copy()->addSeconds(30),
        ]);

        $aggregatedFrame = AggregatedFrame::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.5,
            'sum_emotions_sad' => 0.5,
            'window_start' => $startAt->copy()->addSeconds(30),
            'window_end' => $startAt->copy()->addSeconds(45),
        ]);

        $job = new RebuildTermAnalyticJob();
        $service = app(TermAnalyticServiceContract::class);
        $job->handle($service);

        sleep(1);

        $aggregatedFrame = AggregatedFrame::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'sum_attention' => 1,
            'count' => 3,
            'sum_emotions_happy' => 0.5,
            'sum_emotions_sad' => 0.5,
            'window_start' => $startAt->copy()->addSeconds(45),
            'window_end' => $startAt->copy()->addSeconds(60),
        ]);

        $job = new RebuildTermAnalyticJob();
        $service = app(TermAnalyticServiceContract::class);
        $job->handle($service);

        $this->assertDatabaseHas('term_analytics', [
            'id' => $termAnalytic->getKey(),
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'count' => 5,
            'sum_attention' => '3.0000000000000000000000000',
            'sum_emotions_happy' => '1.6000000000000000000000000',
            'sum_emotions_sad' => '1.4000000000000000000000000',
            'aggregated_frames_count' => 3,
        ]);
    }

    public function testModelAnalyticsReturnsAllTerms(): void
    {
        $modelType = 'consultation';
        $modelId = 1;

        $term1 = Carbon::now()->subDay();
        $term2 = Carbon::now();

        AggregatedFrame::factory()->count(5)->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term1,
            'sum_attention' => 1,
            'count' => 1,
            'sum_emotions_happy' => 0.5,
            'sum_emotions_neutral' => 0.5,
        ]);

        AggregatedFrame::factory()->count(7)->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term2,
            'sum_attention' => 2,
            'count' => 2,
            'sum_emotions_happy' => 0.7,
            'sum_emotions_neutral' => 0.3,
        ]);

        $response = $this->actingAs($this->makeAdmin(), 'api')->getJson("api/admin/recommender/analytics/{$modelType}/{$modelId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'term',
                        'attention',
                        'max_emotion',
                        'max_emotion_percentage',
                    ],
                ]
            ]);

        $json = $response->json('data');

        $this->assertCount(2, $json);
    }

    public function testModelTermAnalyticsReturnsSingleTerm(): void
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

        $response = $this->actingAs($this->makeAdmin(), 'api')->getJson("api/admin/recommender/analytics/{$modelType}/{$modelId}/" . $termAnalytic->getKey());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'term',
                    'model_id',
                    'model_type',
                    'model_name',
                    'rating',
                    'url',
                ],
            ]);
    }
}
