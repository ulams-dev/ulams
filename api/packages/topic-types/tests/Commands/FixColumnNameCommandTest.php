<?php

namespace Tests\Commands;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Tests\TestCase;
use EscolaLms\TopicTypes\Models\TopicContent\Video;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;
use EscolaLms\TopicTypes\Services\Contracts\TopicTypeServiceContract;

class FixColumnNameCommand extends TestCase
{
    use /*ApiTestTrait,*/ DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');

        $course = Course::factory()->create([
            'author_id' => $this->user->id,
        ]);
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
        ]);
        $this->topic_video = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);

        $this->topicable_video = Video::factory()->create([
            'value' => 'dummy.mp4',
            'poster' => 'dummy.png',
        ]);

        $this->topic_video->topicable()->associate($this->topicable_video)->save();

        $this->course_id = $course->id;
    }

    public function testService()
    {
        // TODO FIXME
        // $this->expectException(\Exception::class);
        // $this->expectException(\Error::class);

        $t = Topic::find($this->topic_video->id)
            ->update(['topicable_type' => Video::class]);

        try {
            $this->topic_video->refresh();
        } finally {
            // TODO FIXME
            // $this->assertNull($this->topic_video->topicable);
            // Artisan::call('escolalms:fix-type-column-name');
            $service = App::make(TopicTypeServiceContract::class);
            $service->fixTopicTypeColumnName();

            $this->topic_video->refresh();

            $this->assertEquals($this->topic_video->topicable->id, $this->topicable_video->id);
        }
    }

    public function testCommand()
    {
        // TODO FIXME
        // $this->expectException(\Exception::class);
        // $this->expectException(\Error::class);

        $t = Topic::find($this->topic_video->id)
            ->update(['topicable_type' => Video::class]);

        try {
            $this->topic_video->refresh();
        } finally {
            // TODO FIXME
            // $this->assertNull($this->topic_video->topicable);
            Artisan::call('escolalms:fix-type-column-name');
            /*
            $service = App::make(TopicTypeServiceContract::class);
            $service->fixTopicTypeColumnName();
            */

            $this->topic_video->refresh();

            $this->assertEquals($this->topic_video->topicable->id, $this->topicable_video->id);
        }
    }
}
