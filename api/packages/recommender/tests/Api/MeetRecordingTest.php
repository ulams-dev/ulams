<?php

namespace EscolaLms\Recommender\Tests\Api;

use EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Recommender\Enum\MeetRecordingEnum;
use EscolaLms\Recommender\EscolaLmsRecommenderServiceProvider;
use EscolaLms\Recommender\Jobs\ProcessingMeetingFramesJob;
use EscolaLms\Recommender\Models\MeetRecording;
use EscolaLms\Recommender\Models\TermAnalytic;
use EscolaLms\Recommender\Tests\CreatesCourse;
use EscolaLms\Recommender\Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MeetRecordingTest extends TestCase
{
    use CreatesCourse, CreatesUsers, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ConsultationsPermissionSeeder::class);
    }

    public function testCreateMeetRecording(): void
    {
        $term = Carbon::now()->subMinutes(30);
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $this->actingAs($this->makeAdmin(), 'api')->postJson('api/recommender/meet-recordings', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $now,
            'action' => MeetRecordingEnum::START_RECORDING,
        ])->assertCreated();

        $this->assertDatabaseHas('meet_recordings', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $now,
            'end_at' => null,
            'url' => null,
        ]);
    }

    public function testCreateNewMeetRecording(): void
    {
        $term = Carbon::now()->subMinutes(30)->format('Y-m-d H:i:s');
        $start = Carbon::now()->subMinutes(15)->format('Y-m-d H:i:s');
        $end = Carbon::now()->subMinutes(10)->format('Y-m-d H:i:s');
        $meetRecording = MeetRecording::factory()->create([
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $start,
            'end_at' => $end,
            'url' => null,
        ]);
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $this->actingAs($this->makeAdmin(), 'api')->postJson('api/recommender/meet-recordings', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $now,
            'action' => MeetRecordingEnum::START_RECORDING,
        ])->assertCreated();

        $this->assertDatabaseCount('meet_recordings', 2);

        $this->assertDatabaseHas('meet_recordings', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $now,
            'end_at' => null,
            'url' => null,
        ]);
    }

    public function testUpdateMeetRecording(): void
    {
        Bus::fake([
            ProcessingMeetingFramesJob::class
        ]);

        Config::set(EscolaLmsRecommenderServiceProvider::CONFIG_KEY . '.frames_microservice_url', 'http://localhost-frames');

        Http::fake(['http://localhost-frames/api/frames/satisfaction' => Http::response(null, 204)]);

        $term = Carbon::now()->subMinutes(30)->format('Y-m-d H:i:s');
        $start = Carbon::now()->subMinutes(15);
        $meetRecording = MeetRecording::factory()->create([
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $start,
            'end_at' => null,
            'url' => null,
        ]);

        $termAnalytic = TermAnalytic::factory()->create([
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'meet_recording_id' => $meetRecording->getKey(),
        ]);

        $this->assertDatabaseHas('meet_recordings', [
            'id' => $meetRecording->getKey(),
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $start,
            'end_at' => null,
            'url' => null,
        ]);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $this->actingAs($this->makeAdmin(), 'api')->postJson('api/recommender/meet-recordings', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'end_at' => $now,
            'action' => MeetRecordingEnum::END_RECORDING,
            'url' => 'http://test-recording.com',
            'url_expiration_time_millis' => 123456,
        ])->assertOk();

        $this->assertDatabaseHas('meet_recordings', [
            'id' => $meetRecording->getKey(),
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $term,
            'start_at' => $start,
            'end_at' => $now,
            'url' => 'http://test-recording.com',
            'url_expires_at' => Carbon::now()->addMilliseconds(123456)
        ]);

        Bus::assertDispatched(ProcessingMeetingFramesJob::class);
    }

    public function testMeetRecordingScreen(): void
    {
        $time = Carbon::now();
        $screenTime = Carbon::now()->addMinutes(10);
        $screenTime2 = Carbon::now()->addMinutes(15);

        $meetRecording = MeetRecording::factory()->create([
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $time,
            'start_at' => $time,
            'end_at' => null,
            'url' => null,
        ]);
        Storage::fake();
        $this->actingAs($this->makeAdmin(), 'api')->postJson('api/recommender/meet-recordings/screens', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $time,
            'files' => [
                [
                    'file' => UploadedFile::fake()->image('image.jpg'),
                    'timestamp' => $screenTime->copy()->setTimezone('Europe/Warsaw')->toIso8601String(),
                ],
                [
                    'file' => UploadedFile::fake()->image('image.jpg'),
                    'timestamp' => $screenTime2->copy()->setTimezone('Europe/Warsaw')->toIso8601String(),
                ]
            ]
        ])
            ->assertOk();

        $this->assertDatabaseCount('meet_recording_screens', 2);

        $this->assertDatabaseHas('meet_recording_screens', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $time,
            'file_timestamp' => $screenTime->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('meet_recording_screens', [
            'model_type' => 'consultation',
            'model_id' => 1,
            'term' => $time,
            'file_timestamp' => $screenTime2->format('Y-m-d H:i:s'),
        ]);
    }
}
