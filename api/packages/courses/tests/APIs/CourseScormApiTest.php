<?php

namespace Ulams\Courses\Tests\APIs;

use Ulams\Core\Tests\CreatesUsers;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Tests\TestCase;
use Ulams\Scorm\Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Peopleaps\Scorm\Model\ScormModel;
use PHPUnit\Framework\Attributes\Test;

class CourseScormApiTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    #[Test]

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_read_scorm()
    {
        $user = $this->makeStudent();

        $scorm = ScormModel::with('scos')->firstOrFail();
        $sco = $scorm->scos->first();

        $course = Course::factory()->create([
            'scorm_sco_id' => $sco->id,
            'status' => CourseStatusEnum::PUBLISHED
        ]);
        $course->users()->attach($user);

        $this->response = $this
            ->actingAs($user, 'api')
            ->get('/api/courses/' . $course->id . '/scorm');

        $this->response->assertStatus(200);

        $this->assertStringContainsString('<iframe', $this->response->getContent());
    }
}
