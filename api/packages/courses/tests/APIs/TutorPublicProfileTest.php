<?php

namespace Ulams\Courses\Tests\APIs;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Ulams\Courses\Database\Seeders\CoursesPermissionSeeder;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Tests\TestCase;

/**
 * Public tutor and course author payloads carry no e-mail address; admin routes keep it.
 */
class TutorPublicProfileTest extends TestCase
{
    use DatabaseTransactions;

    private const EMAIL = 'private-author@example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoursesPermissionSeeder::class);

        $this->user = config('auth.providers.users.model')::factory()->create(['email' => self::EMAIL]);
        $this->user->guard_name = 'api';
        $this->user->assignRole('tutor');
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED, 'author_id' => $this->user->getKey()]);
        $course->authors()->sync([$this->user->getKey()]);
    }

    public function testPublicTutorsHaveNoEmail(): void
    {
        $list = $this->getJson('/api/tutors')->assertOk();
        $this->assertStringNotContainsString(self::EMAIL, $list->getContent());

        $show = $this->getJson('/api/tutors/' . $this->user->getKey())->assertOk();
        $show->assertJsonPath('data.id', $this->user->getKey())->assertJsonMissingPath('data.email');
    }

    public function testPublicCourseAuthorsHaveNoEmail(): void
    {
        $response = $this->getJson('/api/courses?per_page=100')->assertOk();

        $this->assertStringNotContainsString(self::EMAIL, $response->getContent());
    }
}
