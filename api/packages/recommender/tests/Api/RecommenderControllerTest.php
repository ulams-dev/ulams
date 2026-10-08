<?php

namespace EscolaLms\Recommender\Tests\Api;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Database\Seeders\CoursesPermissionSeeder;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Models\AggregatedFrame;
use EscolaLms\Recommender\Models\MeetRecording;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Recommender\Tests\CreatesCourse;
use EscolaLms\Recommender\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class RecommenderControllerTest extends TestCase
{
    use CreatesCourse, CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoursesPermissionSeeder::class);

        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.course_model', '{"model": "course"}');
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.exercise_model', '{"model": "exercise"}');
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.enabled', true);
    }

    public function testCourseRecommendation(): void
    {
        Http::fakeSequence()
            ->push(['data' => [
                'value' => $this->faker->randomFloat()]
            ]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/course/' . $this->createCourseWithStructure()->getKey())
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'probability'
                ]
            ]);
    }

    public function testCourseRecommendationApiDisabled(): void
    {
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url');

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/course/' . $this->createCourseWithStructure()->getKey())
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Recommender API URL is not set!'
            ]);
    }

    public function testCourseRecommendationCourseNotFound(): void
    {
        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/course/1')
            ->assertNotFound();
    }

    public function testCourseRecommendationForbidden(): void
    {
        $this
            ->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/recommender/course/' . $this->createCourse()->getKey())
            ->assertForbidden();
    }

    public function testCourseRecommendationUnauthorized(): void
    {
        $this
            ->getJson('api/admin/recommender/course/' . $this->createCourse()->getKey())
            ->assertUnauthorized();
    }

    public function testTopicRecommendation(): void
    {
        Http::fakeSequence()
            ->push([
                'data' => [
                    'key' => $this->faker->word,
                    'value' => $this->faker->randomFloat()
                ]
            ]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/lesson/' . $this->createCourseWithStructure()->lessons->first()->getKey() . '/topic')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'topic_type',
                    'probability',
                ]
            ]);
    }

    public function testTopicRecommendationApiDisabled(): void
    {
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.api_url');

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/lesson/' . $this->createCourseWithStructure()->lessons->first()->getKey() . '/topic')
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Recommender API URL is not set!'
            ]);
    }

    public function testTopicRecommendationLessonNotFoundForbidden(): void
    {
        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/lesson/1/topic')
            ->assertNotFound();
    }

    public function testTopicRecommendationForbidden(): void
    {
        $this
            ->actingAs($this->makeStudent(), 'api')
            ->getJson('api/admin/recommender/lesson/' . $this->createCourseWithStructure()->lessons->first()->getKey() . '/topic')
            ->assertForbidden();
    }

    public function testTopicRecommendationUnauthorized(): void
    {
        $this
            ->getJson('api/admin/recommender/lesson/' . $this->createCourseWithStructure()->lessons->first()->getKey() . '/topic')
            ->assertUnauthorized();
    }

    public function testRecommenderDisabled(): void
    {
        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.enabled', false);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/course/' . $this->createCourseWithStructure()->getKey())
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Recommender is disabled.'
            ]);

        $this
            ->actingAs($this->makeAdmin(), 'api')
            ->getJson('api/admin/recommender/lesson/' . $this->createCourseWithStructure()->lessons->first()->getKey() . '/topic')
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'Recommender is disabled.'
            ]);
    }

    public function testAggregateFramesWithInterval(): void
    {
        $modelType = 'consultation';
        $modelId = 1;
        $term = Carbon::createFromTimestamp('1770628500');
        $interval = 15;

        $admin = $this->makeAdmin();

        $meet = MeetRecording::factory()->create([
            'model_type' => $modelType,
            'model_id' => $modelId,
            'term' => $term,
            'start_at' => $term,
            'end_at' => $term->copy()->addHour(),
            'url' => null,
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

        foreach (range(0, 3) as $i) {
            AggregatedFrame::factory()->create([
                'model_type' => $modelType,
                'model_id' => $modelId,
                'term' => $term,
                'window_start' => $term->copy()->addSeconds($i * $interval),
                'window_end' => $term->copy()->addSeconds(($i+1) * $interval),
                'sum_attention' => 1,
                'count' => 1,
                'sum_emotions_happy' => 0.5,
                'sum_emotions_neutral' => 0.5,
                'term_analytic_id' => $termAnalytic->getKey(),
            ]);
        }

        $response = $this->actingAs($admin, 'api')->getJson("api/admin/recommender/analytics/aggregated-frames/{$termAnalytic->getKey()}?interval=15");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(4, $data);

        $response60 = $this->actingAs($admin, 'api')->getJson("api/admin/recommender/analytics/aggregated-frames/{$termAnalytic->getKey()}?interval=60");
        $data60 = $response60->json('data');

        $this->assertCount(1, $data60);
    }
}
