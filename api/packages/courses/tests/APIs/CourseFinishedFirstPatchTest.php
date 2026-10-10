<?php

namespace Ulams\Courses\Tests\APIs;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event;
use Ulams\Courses\Enum\CourseStatusEnum;
use Ulams\Courses\Enum\ProgressStatus;
use Ulams\Courses\Events\CourseAccessFinished;
use Ulams\Courses\Events\CourseFinished;
use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\CourseProgress;
use Ulams\Courses\Models\CourseUserAttendance;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\Courses\Tests\Models\User;
use Ulams\Courses\Tests\ProgressConfigurable;
use Ulams\Courses\Tests\TestCase;

/**
 * Two requests building a fresh learner's progress at once (the front fires the course list and
 * the course progress together) used to insert two rows for the same topic. The extra row stayed
 * incomplete, so the PATCH that completed the last topic did not finish the course; the next one
 * happened to update the other row and did.
 */
class CourseFinishedFirstPatchTest extends TestCase
{
    use DatabaseTransactions, ProgressConfigurable;

    private Course $course;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->course = Course::factory()->create(['status' => CourseStatusEnum::PUBLISHED]);
        $lesson = Lesson::factory()->create(['course_id' => $this->course->getKey()]);
        Topic::factory(3)->create(['lesson_id' => $lesson->getKey(), 'active' => true]);

        $this->student = User::factory()->create();
        $this->student->courses()->attach($this->course->getKey());
    }

    public function test_first_patch_finishes_a_fresh_course_and_fires_the_event_once(): void
    {
        Event::fake([CourseFinished::class, CourseAccessFinished::class]);

        $this->completeAllTopics()->assertOk();

        $this->assertTrue($this->student->finishedCourse($this->course->getKey()));
        Event::assertDispatchedTimes(CourseFinished::class, 1);
        Event::assertDispatchedTimes(CourseAccessFinished::class, 1);

        $this->completeAllTopics()->assertOk();
        Event::assertDispatchedTimes(CourseFinished::class, 1);
    }

    public function test_the_migration_merges_existing_duplicates_keeping_the_completed_row(): void
    {
        $topic = $this->course->topics()->first();
        Schema::table('course_progress', fn ($table) => $table->dropUnique('course_progress_user_topic_unique'));

        $row = fn (int $status) => CourseProgress::create([
            'user_id' => $this->student->getKey(),
            'topic_id' => $topic->getKey(),
            'status' => $status,
        ]);
        $incomplete = $row(ProgressStatus::INCOMPLETE);
        $complete = $row(ProgressStatus::COMPLETE);
        $attendance = CourseUserAttendance::create([
            'course_progress_id' => $incomplete->getKey(),
            'attendance_date' => now(),
        ]);

        (require __DIR__ . '/../../database/migrations/2026_10_10_000000_add_unique_user_topic_to_course_progress_table.php')->up();

        $rows = CourseProgress::where('topic_id', $topic->getKey())->get();
        $this->assertCount(1, $rows);
        $this->assertSame($complete->getKey(), $rows->first()->getKey());
        $this->assertSame($complete->getKey(), $attendance->fresh()->course_progress_id);
    }

    public function test_the_database_allows_one_progress_row_per_learner_and_topic(): void
    {
        $topic = $this->course->topics()->first();
        CourseProgress::create(['user_id' => $this->student->getKey(), 'topic_id' => $topic->getKey()]);

        $this->expectException(UniqueConstraintViolationException::class);
        CourseProgress::create(['user_id' => $this->student->getKey(), 'topic_id' => $topic->getKey()]);
    }

    private function completeAllTopics()
    {
        return $this->actingAs($this->student, 'api')->json(
            'PATCH',
            '/api/courses/progress/' . $this->course->getKey(),
            ['progress' => $this->getProgressUpdate($this->course)]
        );
    }
}
