<?php

namespace Ulams\Video\Tests\Feature;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Video\Enums\VideoProcessState;
use Ulams\Video\Events\ProcessVideoFailed;
use Ulams\Video\Jobs\DetectStuckVideo;
use Ulams\Video\Models\Video;
use Ulams\Video\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class DetectStuckVideoTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function testDetectFailedVideo(): void
    {
        Event::fake();

        $topic1 = $this->createVideoWithState(['state' => VideoProcessState::CODING, 'date_time' => Carbon::now()->subHours(7)]);
        $topic2 = $this->createVideoWithState(['state' => VideoProcessState::CODING, 'date_time' => Carbon::now()->subHours(6)]);
        $topic3 = $this->createVideoWithState(['state' => VideoProcessState::CODING, 'date_time' => Carbon::now()->subDays(2)]);
        $topic4 = $this->createVideoWithState(['state' => VideoProcessState::CODING, 'date_time' => Carbon::now()]);
        $topic5 = $this->createVideoWithState(['state' => VideoProcessState::FINISHED]);
        $topic6 = $this->createVideoWithState(['state' => VideoProcessState::ERROR]);
        $topic7 = $this->createVideoWithState(['state' => VideoProcessState::QUEUE]);

        DetectStuckVideo::dispatch();

        $topic1->refresh();
        $topic2->refresh();
        $topic3->refresh();
        $topic4->refresh();
        $topic5->refresh();
        $topic6->refresh();
        $topic7->refresh();

        $this->assertEquals($topic1->json['ffmpeg']['state'], VideoProcessState::ERROR);
        $this->assertEquals($topic1->json['ffmpeg']['message'], 'An unknown error occurred during video processing');
        $this->assertEquals($topic2->json['ffmpeg']['state'], VideoProcessState::ERROR);
        $this->assertEquals($topic2->json['ffmpeg']['message'], 'An unknown error occurred during video processing');
        $this->assertEquals($topic3->json['ffmpeg']['state'], VideoProcessState::ERROR);
        $this->assertEquals($topic3->json['ffmpeg']['message'], 'An unknown error occurred during video processing');
        $this->assertEquals($topic4->json['ffmpeg']['state'], VideoProcessState::CODING);
        $this->assertEquals($topic5->json['ffmpeg']['state'], VideoProcessState::FINISHED);
        $this->assertEquals($topic6->json['ffmpeg']['state'], VideoProcessState::ERROR);
        $this->assertEquals($topic7->json['ffmpeg']['state'], VideoProcessState::QUEUE);

        Event::assertDispatchedTimes(ProcessVideoFailed::class, 3);
        Event::assertDispatched(
            ProcessVideoFailed::class,
            fn (ProcessVideoFailed $event) => in_array($event->getTopic()->getKey(), [$topic1->getKey(), $topic2->getKey(), $topic3->getKey()])
        );
        Event::assertNotDispatched(
            ProcessVideoFailed::class,
            fn (ProcessVideoFailed $event) => in_array($event->getTopic()->getKey(), [$topic4->getKey(), $topic5->getKey(), $topic6->getKey(), $topic7->getKey()])
        );
    }

    public function testDetectFailedVideoMultipleAuthors(): void
    {
        Event::fake();

        $course = Course::factory()->create();
        $course->authors()->attach($this->makeInstructor());
        $course->authors()->attach($this->makeInstructor());
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()
            ->state(fn() => [
                'lesson_id' => $lesson->getKey(),
                'json' => [
                    'ffmpeg' => ['state' => VideoProcessState::CODING, 'date_time' => Carbon::now()->subHours(8)]
                ],
                'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                'topicable_id' => Video::factory()->create()->getKey()
            ])
            ->create();

        DetectStuckVideo::dispatch();

        $topic->refresh();

        $this->assertEquals($topic->json['ffmpeg']['state'], VideoProcessState::ERROR);
        $this->assertEquals($topic->json['ffmpeg']['message'], 'An unknown error occurred during video processing');

        Event::assertDispatchedTimes(ProcessVideoFailed::class, 2);
    }

    private function createVideoWithState(array $state): Topic
    {
        $course = Course::factory()->create();
        $course->authors()->attach($this->makeInstructor());
        $lesson = Lesson::factory()->create(['course_id' => $course->getKey()]);
        $topic = Topic::factory()
            ->state(fn() => [
                'lesson_id' => $lesson->getKey(),
                'json' => [
                    'ffmpeg' => [...$state]
                ],
                'topicable_type' => \Ulams\TopicTypes\Models\TopicContent\Video::class,
                'topicable_id' => Video::factory()->create()->getKey()
            ])
            ->create();

        return $topic;
    }

}
