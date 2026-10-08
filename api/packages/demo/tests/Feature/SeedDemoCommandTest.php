<?php

namespace Ulams\Demo\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ulams\CourseAccess\Models\Course;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Demo\Tests\Mocks\FakeDemoCoursesSeeder;
use Ulams\Demo\Tests\TestCase;

class SeedDemoCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeDemoCoursesSeeder::$experience = null;
    }

    public function testGivesTheDemoStudentAccessToPublishedCourses(): void
    {
        Event::fake();
        config(['ulams_demo.content_seeder' => 'Ulams\\Demo\\Tests\\Missing\\Seeder']);
        [, $student] = $this->seedDemoUsers();
        $course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);

        $this->artisan('ulams:demo:seed')
            ->expectsOutputToContain('demo courses skipped')
            ->assertExitCode(0);

        $this->assertSame(1, $course->users()->whereKey($student->getKey())->count());
    }

    public function testSkipsUnknownExperiences(): void
    {
        config(['ulams_demo.content_seeder' => FakeDemoCoursesSeeder::class]);
        $this->seedDemoUsers();

        $this->artisan('ulams:demo:seed', ['--experience' => 'acme'])
            ->expectsOutputToContain("No demo experience for 'acme'")
            ->assertExitCode(0);
        $this->assertNull(FakeDemoCoursesSeeder::$experience);

        $this->artisan('ulams:demo:seed', ['--experience' => 'coffee'])->assertExitCode(0);
        $this->assertSame('coffee', FakeDemoCoursesSeeder::$experience);
        $this->assertFalse(getenv('DEMO_EXPERIENCE'));
    }
}
