<?php

namespace Ulams\Lrs\Tests\API;

use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Lrs\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Lrs\Database\Seeders\LrsSeeder;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;

class cmi5ApiTest extends TestCase
{
    use DatabaseTransactions;

    public $user;
    public $course;
    public $tutor;
    public $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LrsSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create();
        $this->user->guard_name = 'api';
        $this->user->assignRole('student');

        $this->tutor = config('auth.providers.users.model')::factory()->create();
        $this->tutor->guard_name = 'api';
        $this->tutor->assignRole('tutor');

        $this->course = Course::factory()->create([
            'author_id' => $this->tutor->id,
            'status' => CourseStatusEnum::PUBLISHED,
        ]);

        $lesson = Lesson::factory()->create([
            'course_id' => $this->course->id,
        ]);

        $topic = Topic::factory()->create([
            'lesson_id' => $lesson->id,
            'json' => ['foo' => 'bar', 'bar' => 'foo'],
        ]);

        $this->course->users()->syncWithoutDetaching([$this->user->id]);

        $this->token = $this->user->createToken("Ulams User Token")->accessToken;
    }

    public function test_get_course_lanuch_params()
    {
        $this->response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}"
        ])->json(
            'GET',
            '/api/cmi5/courses/' . $this->course->id
        );

        $this->response->assertOk();

        $this->response->assertJsonStructure([
            'data' => [
                "endpoint",
                "fetch",
                "actor",
                "registration",
                "activityId",
                "url"
            ]
        ]);
    }

    public function test_the_launch_params_hold_no_access_token(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->json('GET', '/api/cmi5/courses/' . $this->course->id);

        $response->assertOk();
        $this->assertStringNotContainsString($this->token, $response->json('data.fetch'));
        $this->assertStringNotContainsString($this->token, $response->json('data.url'));
    }
}
