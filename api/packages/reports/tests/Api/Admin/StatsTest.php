<?php

namespace EscolaLms\Reports\Tests\Api\Admin;

use EscolaLms\Cart\Models\Cart;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Exports\Stats\Course\FinishedTopicsExport;
use EscolaLms\Reports\Exports\Stats\Topic\QuizSummaryForTopicTypeGIFTExport;
use EscolaLms\Reports\Stats\Topic\QuizSummaryForTopicTypeGIFT;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Courses\Models\Group;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use EscolaLms\Reports\ValueObject\DateRange;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Facades\Excel;

class StatsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use CoursesTestingTrait;

    public function testAvailable()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->json('GET', '/api/admin/stats/available');
        $response->assertOk();
        $stats = config('reports.stats');
        $response->assertJsonFragment([
            'data' => $stats
        ]);
    }

    public function testCourse()
    {
        $admin = $this->makeAdmin();

        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInCourse($student, $course);
        $this->progressUserInCourse($student2, $course, 30, ProgressStatus::COMPLETE);

        /** @var TestResponse $response */
        $response = $this->actingAs($admin)->json('GET', '/api/admin/stats/course/' . $course->getKey());
        $response->assertOk();
        $stats = config('reports.stats')[Course::class] ?? [];

        $response->assertJsonStructure([
            'data' => $stats
        ]);

        $response->assertJsonFragment([
            'average_time' => 45
        ]);
    }

    public function testFinishedTopicsWithGroupUsers()
    {
        $admin = $this->makeAdmin();

        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $student3 = $this->makeStudent([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test@test.test'
        ]);
        $group = Group::factory()->create();
        $group->users()->save($student3);
        $course->groups()->save($group);


        $this->progressUserInCourse($student, $course);
        $this->progressUserInCourse($student2, $course, 30, ProgressStatus::COMPLETE);
        $this->progressUserInCourse($student3, $course, 45, ProgressStatus::COMPLETE);

        /** @var TestResponse $response */
        $this->actingAs($admin)->json('GET', '/api/admin/stats/course/' . $course->getKey(), [
            'stats' => [\EscolaLms\Reports\Stats\Course\FinishedTopics::class]
        ])
            ->assertOk()
            ->assertJsonFragment([
                'email' => $student3->email
            ]);
    }

    public function testCoursePartial()
    {
        $admin = $this->makeAdmin();

        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInCourse($student, $course);
        $this->progressUserInCourse($student2, $course, 30, ProgressStatus::COMPLETE);

        /** @var TestResponse $response */
        $response = $this->actingAs($admin)->json('GET', '/api/admin/stats/course/' . $course->getKey(), [
            'stats' => [\EscolaLms\Reports\Stats\Course\AverageTime::class]
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'data' => [
                \EscolaLms\Reports\Stats\Course\AverageTime::class => 135
            ]
        ]);
    }

    public function testCart(): void
    {
        $admin = $this->makeAdmin();

        $stats = config('reports.stats')[Cart::class] ?? [];

        $this->actingAs($admin)
            ->json('GET', '/api/admin/stats/cart')
            ->assertOk()
            ->assertJsonStructure(['data' => $stats]);
    }

    public function testDateRange(): void
    {
        $admin = $this->makeAdmin();

        $stats = config('reports.stats')[DateRange::class] ?? [];

        $this->actingAs($admin)
            ->json('GET', '/api/admin/stats/date-range')
            ->assertOk()
            ->assertJsonStructure(['data' => $stats]);
    }

    public function testExportFinishedTopicsStats(): void
    {
        Excel::fake();

        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInCourse($student, $course);
        $this->progressUserInCourse($student2, $course, 30, ProgressStatus::COMPLETE);

        $courseId = $course->getKey();

        /** @var TestResponse $response */
        $this
            ->actingAs($this->makeAdmin())
            ->json( 'GET', '/api/admin/stats/course/' . $courseId . '/export', [
                'stat' => \EscolaLms\Reports\Stats\Course\FinishedTopics::class,
            ])
            ->assertOk();

        Excel::assertDownloaded("finished_topics_$courseId.xlsx", function (FinishedTopicsExport $export) {
            $this->assertCount(4, $export->sheets());

            return true;
        });
    }

    public function testExportQuizSummaryStats(): void
    {
        Excel::fake();

        if (!class_exists(GiftQuiz::class)) {
            $this->markTestSkipped();
        }

        $giftQuiz = GiftQuiz::factory()->create();
        $course = \EscolaLms\Reports\Tests\Models\Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();
        $topic->topicable()->associate($giftQuiz);
        $topic->save();

        $this
            ->actingAs($this->makeAdmin())
            ->json( 'GET', '/api/admin/stats/topic/' . $topic->getKey() . '/export', [
                'stat' => \EscolaLms\Reports\Stats\Topic\QuizSummaryForTopicTypeGIFT::class,
            ])
            ->assertOk();

        Excel::assertDownloaded('quiz_summary_for_topic_type_g_i_f_t_' . $topic->getKey() . '.xlsx', function () {
            return true;
        });
    }

    public function testExportCourseStatsNotExists(): void
    {
        $course = $this->createCourseWithLessonAndTopic(3);

        /** @var TestResponse $response */
        $this
            ->actingAs($this->makeAdmin())
            ->json( 'GET', '/api/admin/stats/course/' . $course->getKey() . '/export', [
                'stat' => \EscolaLms\Reports\Stats\Course\MoneyEarned::class,
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => __('The export for the statistics does not exist.'),
            ]);
    }
}
