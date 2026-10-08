<?php

namespace EscolaLms\Video\Tests\Feature;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Events\TopicTypeChanged;
use EscolaLms\Video\Events\ProcessVideoFailed;
use EscolaLms\Video\Events\ProcessVideoStarted;
use EscolaLms\Video\Jobs\ProcessVideo;
use EscolaLms\Video\Models\Video;
use EscolaLms\Video\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class VideoTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = config('auth.providers.users.model')::factory()->create();

        Event::fake([TopicTypeChanged::class, ProcessVideoStarted::class, ProcessVideoFailed::class]);
    }

    public function diskDataProvider(): array
    {
        return [
            ['s3'],
            ['local']
        ];
    }

    /**
     * @dataProvider diskDataProvider
     * @group expensive
     */
    public function testSuccessProcessVideo(string $disk)
    {
        Storage::fake($disk);

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey()]);
        $video = new Video();

        $path = __DIR__ . '/../samples/sample.mp4';

        $this->assertFileExists($path);

        $file = fopen($path, 'r');

        $this->assertIsResource($file);

        $targetPath = 'video_test/' . Carbon::now()->format('Y_m_d_His') . '/sample.mp4';

        if (!Storage::disk($disk)->exists($targetPath)) {
            $success = Storage::disk($disk)->put($targetPath, $file);
            $this->assertTrue($success);
        }

        $fullTargetPath = Storage::disk($disk)->path($targetPath);

        $this->assertFileExists($fullTargetPath);

        $video->value = $targetPath;
        $video->save();
        $video->topic()->save($topic);

        $job = new ProcessVideo($video, $this->user, $disk);
        $job->handle();

        $video->refresh();
        $json = $video->topic->json;

        Storage::disk($disk)->assertExists($json['ffmpeg']['path']);

        $fullPlaylistPath = Storage::disk($disk)->path($json['ffmpeg']['path']);
        $this->assertFileExists($fullPlaylistPath);
        $this->assertEquals('finished', $json['ffmpeg']['state']);

        Event::assertDispatched(
            ProcessVideoStarted::class,
            fn (ProcessVideoStarted $event) =>
                $event->getUser()->getKey() === $this->user->getKey() && $event->getTopic()->getKey() === $topic->getKey()
        );
        Event::assertNotDispatched(ProcessVideoFailed::class);
    }

    /**
     * @dataProvider diskDataProvider
     */
    public function testFailProcessVideo(string $disk)
    {
        Storage::fake($disk);

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey()]);
        $video = new Video();

        $path = __DIR__ . '/../samples/video.mp4';
        $file = fopen($path, 'r');
        $targetPath = 'video_test/' . Carbon::now()->format('Y_m_d_His') . '/video.mp4';

        if (!Storage::disk($disk)->exists($targetPath)) {
            $success = Storage::disk($disk)->put($targetPath, $file);
            $this->assertTrue($success);
        }

        $fullTargetPath = Storage::disk($disk)->path($targetPath);

        $this->assertFileExists($fullTargetPath);

        $video->value = $targetPath;
        $video->save();
        $video->topic()->save($topic);

        $this->expectException(\Exception::class);

        $job = new ProcessVideo($video, $this->user, $disk);
        $job->handle();

        $video->refresh();
        $json = $video->topic->json;

        $this->assertEquals('error', $json['ffmpeg']['state']);

        Event::assertDispatched(ProcessVideoStarted::class,
            fn (ProcessVideoStarted $event) =>
                $event->getUser()->getKey() === $this->user->getKey() && $event->getTopic()->getKey() === $topic->getKey()
        );
        Event::assertDispatched(ProcessVideoFailed::class,
            fn (ProcessVideoStarted $event) =>
                $event->getUser()->getKey() === $this->user->getKey() && $event->getTopic()->getKey() === $topic->getKey());
    }

    /**
     * @dataProvider diskDataProvider
     */
    public function testStateUpdatedFailProcessVideo(string $disk)
    {
        Storage::fake($disk);

        $video = $this->makeVideo($disk);

        $job = new ProcessVideo($video, $this->user, $disk);
        $job->failed(new \Exception("Exception message"));

        $json = $video->topic->json;
        $this->assertEquals('error', $json['ffmpeg']['state']);
        $this->assertEquals('Exception message', $json['ffmpeg']['message']);
    }

    private function makeVideo(string $disk): Video
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()->create(['lesson_id' => $lesson->getKey()]);

        $video = new Video();
        $path = __DIR__ . '/../samples/video.mp4';
        $file = fopen($path, 'r');
        $targetPath = 'video_test/' . Carbon::now()->format('Y_m_d_His') . '/video.mp4';

        if (!Storage::disk($disk)->exists($targetPath)) {
            $success = Storage::disk($disk)->put($targetPath, $file);
            $this->assertTrue($success);
        }

        $fullTargetPath = Storage::disk($disk)->path($targetPath);

        $this->assertFileExists($fullTargetPath);

        $video->value = $targetPath;

        $video->save();
        $video->topic()->save($topic);

        return $video;
    }
}
