<?php

namespace Tests\APIs;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TopicTypesAnonymousApiTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create([
            'course_id' => $course->id,
        ]);
        $this->topic = Topic::factory()->create([
            'lesson_id' => $lesson->id,
        ]);
    }

    /**
     * @test
     */
    public function testReadTopic()
    {
        $this->response = $this->json(
            'GET',
            '/api/admin/topics/'.$this->topic->id
        );

        $this->response->assertStatus(401);
    }

    /**
     * @test
     */
    public function testDeleteTopic()
    {
        $this->response = $this->json(
            'DELETE',
            '/api/admin/topics/'.$this->topic->id
        );

        $this->response->assertStatus(401);
    }

    /**
     * @test
     */
    public function testUpdateTopic()
    {
        $this->response = $this->json(
            'POST',
            '/api/admin/topics/'.$this->topic->id
        );

        $this->response->assertStatus(401);
    }

    /**
     * @test
     */
    public function testReadTopicTypes()
    {
        $this->response = $this->json(
            'GET',
            '/api/admin/topics/types'
        );

        $this->response->assertStatus(401);
    }
}
