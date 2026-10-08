<?php

namespace EscolaLms\Video\Tests\Api;

use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypes\Models\TopicContent\Video as TopicContentVideo;
use EscolaLms\Video\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;

class TopicApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers, WithFaker;

    private array $json = [];
    private User $user;
    private Course $course;
    private Topic $topic;
    private TopicContentVideo $topicable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->json = [
            'status' => 'finished',
            'ffmpeg' => [
                'path' => $this->faker->filePath()
            ]
        ];

        $this->user = $this->makeAdmin();
        $this->course = Course::factory(['author_id' => $this->user->id])->create();
        $lesson = Lesson::factory(['course_id' => $this->course->id, 'active' => true])->create();
        $this->topic = Topic::factory([
            'lesson_id' => $lesson->id,
            'json' => json_encode($this->json)
        ])->create();

        $this->topicable = TopicContentVideo::factory()->create();
        $this->topic->topicable()->associate($this->topicable)->save();
    }

    public function testGetTopic(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/admin/topics/' . $this->topic->getKey());

        $response->assertJsonFragment([
            'topicable' => [
                'id' => $this->topicable->getKey(),
                'value' => $this->json['ffmpeg']['path'],
                'url' => Storage::url($this->json['ffmpeg']['path']),
                'length' => null,
                'poster' => 'poster.jpg',
                'poster_url' => Storage::url('poster.jpg'),
                'width' => 640,
                'height' => 480
            ]
        ]);
    }

    public function testGetAdminTopic(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/admin/courses/' . $this->course->getKey() . '/program');

        $response->assertJsonFragment([
            'topicable' => [
                'id' => $this->topicable->getKey(),
                'value' => '1.mp4',
                'url' => Storage::url('1.mp4'),
                'poster' => 'poster.jpg',
                'poster_url' => Storage::url('poster.jpg'),
                'width' => 640,
                'height' => 480,
                'length' => null,
                'created_at' => $this->topicable->created_at,
                'updated_at' => $this->topicable->updated_at,
                'hls' => $this->json['ffmpeg']['path'],
                'hls_url' => Storage::url($this->json['ffmpeg']['path']),
            ]
        ]);
    }
}
