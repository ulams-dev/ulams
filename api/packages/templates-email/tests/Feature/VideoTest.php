<?php

namespace EscolaLms\TemplatesEmail\Tests\Feature;

use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TemplatesEmail\Core\EmailMailable;
use EscolaLms\TemplatesEmail\Tests\TestCase;
use EscolaLms\Video\Events\ProcessVideoFailed;
use EscolaLms\Video\Events\ProcessVideoFinished;
use EscolaLms\Video\Events\ProcessVideoStarted;
use EscolaLms\Video\Events\ProcessVideoState;
use EscolaLms\Video\Models\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

class VideoTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    public function setUp(): void
    {
        parent::setUp();

        if (!class_exists(\EscolaLms\Video\EscolaLmsVideoServiceProvider::class)) {
            $this->markTestSkipped('Video package not installed');
        }
    }
    public function testNotificationSentWhenVideoProcessingStarted(): void
    {
        Mail::fake();

        $tutor = $this->makeInstructor();
        $topic = $this->createTopicVideo();

        Event::dispatch(new ProcessVideoStarted($tutor, $topic));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($topic, $tutor) {
            $this->assertTrue($mailable->hasTo($tutor->email));
            $this->assertEquals(__('Video processing progress starting for topic - :topic', ['topic' => $topic->title]), $mailable->subject);
            $this->assertStringContainsString($tutor->name, $mailable->getHtml());
            $this->assertStringContainsString($topic->title, $mailable->getHtml());

            return true;
        });
    }
    public function testNotificationSentWhenVideoProcessingSucceed(): void
    {
        Mail::fake();

        $tutor = $this->makeInstructor();
        $topic = $this->createTopicVideo();

        Event::dispatch(new ProcessVideoFinished($tutor, $topic));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($topic, $tutor) {
            $this->assertTrue($mailable->hasTo($tutor->email));
            $this->assertEquals(__('Video processing finished for topic - :topic', ['topic' => $topic->title]), $mailable->subject);
            $this->assertStringContainsString($tutor->name, $mailable->getHtml());
            $this->assertStringContainsString($topic->title, $mailable->getHtml());

            return true;
        });
    }

    public function testNotificationSentWhenVideoProcessingFailed(): void
    {
        Mail::fake();

        $tutor = $this->makeInstructor();
        $topic = $this->createTopicVideo();

        Event::dispatch(new ProcessVideoFailed($tutor, $topic));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($topic, $tutor) {
            $this->assertTrue($mailable->hasTo($tutor->email));
            $this->assertEquals(__('Video processing progress failed for topic - :topic', ['topic' => $topic->title]), $mailable->subject);
            $this->assertStringContainsString($tutor->name, $mailable->getHtml());
            $this->assertStringContainsString($topic->title, $mailable->getHtml());

            return true;
        });
    }

    public function testNotificationSentWhenVideoProcessingState(): void
    {
        Mail::fake();

        $tutor = $this->makeInstructor();
        $topic = $this->createTopicVideo();

        Event::dispatch(new ProcessVideoState($tutor, $topic, 60));

        Mail::assertSent(EmailMailable::class, function (EmailMailable $mailable) use ($topic, $tutor) {
            $this->assertTrue($mailable->hasTo($tutor->email));
            $this->assertEquals(__('Video processing progress update for topic - :topic - :percentage%', ['topic' => $topic->title, 'percentage' => 60]), $mailable->subject);
            $this->assertStringContainsString('60%', $mailable->getHtml());
            $this->assertStringContainsString($tutor->name, $mailable->getHtml());
            $this->assertStringContainsString($topic->title, $mailable->getHtml());

            return true;
        });
    }

    private function createTopicVideo(): Topic
    {
        /** @var Topic */
        return Topic::factory()
            ->for(Lesson::factory()->for(Course::factory()))
            ->state(fn() => [
                'topicable_type' => \EscolaLms\TopicTypes\Models\TopicContent\Video::class,
                'topicable_id' => Video::factory()->create()->getKey()
            ])
            ->create();
    }
}
