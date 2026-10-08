<?php

namespace Ulams\Courses\Tests\APIs;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;

class TopicAnonymousApiTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create([
            'course_id' => $course->getKey()
        ]);
        $this->topic = Topic::factory()->create([
            'lesson_id' => $lesson->getKey()
        ]);
    }

    #[Test]
    public function testReadTopic()
    {
        $this->response = $this->json(
            'GET',
            '/api/admin/topics/' . $this->topic->getKey()
        );

        $this->response->assertStatus(401);
    }
}
