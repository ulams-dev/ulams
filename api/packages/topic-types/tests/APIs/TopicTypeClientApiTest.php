<?php

namespace Tests\APIs;

use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypes\Models\TopicContent\Audio;
use Ulams\TopicTypes\Models\TopicContent\H5P;
use Ulams\TopicTypes\Models\TopicContent\Image;
use Ulams\TopicTypes\Models\TopicContent\OEmbed;
use Ulams\TopicTypes\Models\TopicContent\PDF;
use Ulams\TopicTypes\Models\TopicContent\RichText;
use Ulams\TopicTypes\Models\TopicContent\ScormSco;
use Ulams\TopicTypes\Models\TopicContent\Video;
use Ulams\TopicTypes\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TopicTypeClientApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('admin');
        $this->course = Course::factory(['author_id' => $this->user->id])->create();
        $this->lesson = Lesson::factory(['course_id' => $this->course->id])->create();
        $this->topic = Topic::factory(['lesson_id' => $this->lesson->id])->create();
    }

    public function topicTypeDataProvider(): array
    {
        $types = [
            [Audio::class],
            [H5P::class],
            [Image::class],
            [OEmbed::class],
            [PDF::class],
            [RichText::class],
            [ScormSco::class],
            [Video::class],
        ];
        if (class_exists(\Ulams\Cmi5\UlamsCmi5ServiceProvider::class)) {
            $types[] = [\Ulams\TopicTypes\Models\TopicContent\Cmi5Au::class];
        }
        return $types;
    }

    /**
     * @dataProvider topicTypeDataProvider
     */
    public function testGetTopic($class): void
    {
        $model = $class::factory()->create();
        $this->topic->topicable()->associate($model)->save();

        $this->response = $this->actingAs($this->user, 'api')
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/admin/topics/' . $this->topic->getKey());

        $this->response->assertOk();
        $this->response->assertJsonFragment([
            'topicable_type' => $class
        ]);
    }
}
