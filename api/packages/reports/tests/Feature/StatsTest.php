<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\User as CartUser;
use EscolaLms\Cart\Services\Contracts\OrderServiceContract;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\CourseUserAttendance;
use EscolaLms\Courses\Models\CourseUserPivot;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\Cart\NewCustomers;
use EscolaLms\Reports\Stats\Cart\ReturningCustomers;
use EscolaLms\Reports\Stats\Cart\SpendPerCustomer;
use EscolaLms\Reports\Stats\Course\AttendanceList;
use EscolaLms\Reports\Stats\Course\AverageTime;
use EscolaLms\Reports\Stats\Course\AverageTimePerTopic;
use EscolaLms\Reports\Stats\Course\Finished;
use EscolaLms\Reports\Stats\Course\FinishedCourse;
use EscolaLms\Reports\Stats\Course\FinishedTopics;
use EscolaLms\Reports\Stats\Course\MoneyEarned;
use EscolaLms\Reports\Stats\Course\PeopleBought;
use EscolaLms\Reports\Stats\Course\PeopleFinished;
use EscolaLms\Reports\Stats\Course\PeopleStarted;
use EscolaLms\Reports\Stats\Course\Started;
use EscolaLms\Reports\Stats\Topic\QuizSummaryForTopicTypeGIFT;
use EscolaLms\Reports\Stats\User\ActiveUsers;
use EscolaLms\Reports\Stats\User\NewUsers;
use EscolaLms\Reports\Tests\Models\Course;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use EscolaLms\Reports\Tests\Traits\NotificationTestingTrait;
use EscolaLms\TopicTypeGift\Dtos\SaveAttemptAnswerDto;
use EscolaLms\TopicTypeGift\Enum\AnswerKeyEnum;
use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Services\AttemptAnswerService;
use EscolaLms\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class StatsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use CoursesTestingTrait, NotificationTestingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        User::query()->delete();
    }

    public function testCourseAverageTime(): void
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->saveMany([$course, $course2, $course3]);

        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2, $course3]);

        $this->progressUserInCourse($student, $course, 60);
        $this->progressUserInCourse($student, $course2, 60);
        $this->progressUserInCourse($student, $course3, 60);
        $this->progressUserInCourse($student2, $course, 30);
        $this->progressUserInCourse($student2, $course3, 0);

        $result1 = AverageTime::make($course)->calculate();
        $result2 = AverageTime::make($course2)->calculate();
        $result3 = AverageTime::make($course3)->calculate();

        $this->assertEquals(45, $result1);
        $this->assertEquals(60, $result2); // there is user that has no progress entry about this topic, but in reality this is impossible as giving access to course automatically generates progresses for all topics
        $this->assertEquals(30, $result3);
    }

    public function testCourseAverageTimePerTopic(): void
    {
        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInTopic($student, $course->topics->get(0), 30);
        $this->progressUserInTopic($student, $course->topics->get(1), 50);
        $this->progressUserInTopic($student, $course->topics->get(2), 100);
        $this->progressUserInTopic($student2, $course->topics->get(0), 60);
        $this->progressUserInTopic($student2, $course->topics->get(1), 0);
        $this->progressUserInTopic($student2, $course->topics->get(2), 50);

        $results = AverageTimePerTopic::make($course)->calculate();

        $this->assertEquals(45, $results->get($course->topics->get(0)->id)['average_time']);
        $this->assertEquals(25, $results->get($course->topics->get(1)->id)['average_time']);
        $this->assertEquals(75, $results->get($course->topics->get(2)->id)['average_time']);
    }

    public function testCourseMoneyEarned(): void
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $this->makePaidOrder($student, $course);
        $this->makePaidOrder($student2, $course);

        $result = MoneyEarned::make($course)->calculate();

        $this->assertEquals(2000, $result);
    }

    public function testBundledCoursesMoneyEarned(): void
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $this->makePaidOrder($student, $course);
        $this->makePaidOrder($student2, $course, $course2);

        $result = MoneyEarned::make($course)->calculate();
        $this->assertEquals(1500, $result);

        $result = MoneyEarned::make($course2)->calculate();
        $this->assertEquals(500, $result);
    }

    public function testCourseMoneyEarnedWithOrderItemThatIsNotRepresentingProductModel(): void
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();

        $order = $this->makePaidOrder($student, $course);

        /** This order item will be ignored in stats calculation as it does not represent product as defined by Cart package */
        $order->items()->save(new OrderItem([
            'price' => 999,
            'quantity' => 1,
            'buyable_id' => $course->getKey(),
            'buyable_type' => get_class($course),
        ]));

        $result = MoneyEarned::make($course)->calculate();

        $this->assertEquals(1000, $result);
    }

    public function testPeopleBought(): void
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $order = $this->makePaidOrder($student, $course);
        $order2 = $this->makePaidOrder($student2, $course);

        app(OrderServiceContract::class)->processOrderItems($order);
        app(OrderServiceContract::class)->processOrderItems($order2);

        $result = PeopleBought::make($course)->calculate();
        $this->assertEquals(2, $result);

        $result = PeopleBought::make($course)->calculateReallyBought();
        $this->assertEquals(2, $result);
    }

    public function testPeopleFinished(): void
    {
        $course = $this->createCourseWithLessonAndTopic();
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $result = PeopleFinished::make($course)->calculate();
        $this->assertEquals(0, $result);

        $this->progressUserInCourse($student, $course, 60, ProgressStatus::COMPLETE);
        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::INCOMPLETE);

        $result = PeopleFinished::make($course)->calculate();
        $this->assertEquals(1, $result);

        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::COMPLETE);

        $result = PeopleFinished::make($course)->calculate();
        $this->assertEquals(2, $result);
    }

    public function testPeopleStarted(): void
    {
        $course = $this->createCourseWithLessonAndTopic();
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $result = PeopleStarted::make($course)->calculate();
        $this->assertEquals(2, $result);

        $this->progressUserInCourse($student, $course, 60, ProgressStatus::COMPLETE);
        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::INCOMPLETE);

        $result = PeopleStarted::make($course)->calculate();
        $this->assertEquals(1, $result);

        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::COMPLETE);

        $result = PeopleStarted::make($course)->calculate();
        $this->assertEquals(0, $result);
    }

    public function testNewCustomers(): void
    {
        CartUser::factory()
            ->count(10)
            ->has(Order::factory())
            ->create();

        CartUser::factory()
            ->count(15)
            ->has(Order::factory())
            ->create(['created_at' => Carbon::now()->subDay()]);

        $result = NewCustomers::make()->calculate();

        $this->assertEquals(10, $result);
    }

    public function testSpendPerCustomer(): void
    {
        $users = CartUser::factory()
            ->count(20)
            ->has(Order::factory()->state(['total' => rand(10, 1000)]))
            ->create();

        $orders = Order::whereIn('user_id', $users->pluck('id'));

        $result = SpendPerCustomer::make()->calculate();

        $this->assertEquals(round($orders->sum('total') / $users->count()), $result);
    }

    public function testReturningCustomers(): void
    {
        $users = CartUser::factory()
            ->count(20)
            ->has(Order::factory()->state(['created_at' => Carbon::now()->subYear()->subDay()]))
            ->create();

        $users
            ->slice(0, 10)
            ->each(fn ($user) => Order::factory()->create(['user_id' => $user->getKey(), 'created_at' => Carbon::now()->subMonth()]));
        $users
            ->slice(10, 5)
            ->each(fn ($user) => Order::factory()->create(['user_id' => $user->getKey(), 'created_at' => Carbon::today()]));

        $result = ReturningCustomers::make()->calculate();

        $this->assertEquals(5, $result);
    }

    public function testCourseStarted(): void
    {
        $start = Carbon::now()->subDays(3)->format('Y-m-d');
        $end = Carbon::now()->addDays(10)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $course1 = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course1->getKey()]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course2->getKey()]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course3->getKey()]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course1->getKey()]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course2->getKey()]);

        $result = Started::make()->calculate();
        $this->assertEquals(5, $result[$today]);

        $student3 = $this->makeStudent();
        $student4 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course3->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->subDays(3)]);

        $result = Started::make(Carbon::now()->subDays(4))->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(5, $result[$today]);
        $this->assertEquals(5, $result[$start]);

        $result = Started::make(Carbon::now()->subDays(4), Carbon::now()->subDays(2))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(5, $result[$start]);

        $student5 = $this->makeStudent();
        $student6 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course3->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->addDays(10)]);

        $result = Started::make(Carbon::now()->addDays(9), Carbon::now()->addDays(10))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(5, $result[$end]);

        $result = Started::make(Carbon::now()->subMonth(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(5, $result[$today]);
        $this->assertEquals(5, $result[$start]);
        $this->assertEquals(5, $result[$end]);
    }

    public function testCoursesFinished(): void
    {
        $start = Carbon::now()->subDays(3)->format('Y-m-d');
        $end = Carbon::now()->addDays(10)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $course1 = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course3->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::today(), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::today(), 'finished' => false]);

        $result = Finished::make()->calculate();
        $this->assertEquals(3, $result[$today]);

        $student3 = $this->makeStudent();
        $student4 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course3->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => true]);

        $result = Finished::make(Carbon::now()->subDays(4))->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(3, $result[$today]);
        $this->assertEquals(2, $result[$start]);

        $result = Finished::make(Carbon::now()->subDays(4), Carbon::now()->subDays(2))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(2, $result[$start]);

        $student5 = $this->makeStudent();
        $student6 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course3->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);

        $result = Finished::make(Carbon::now()->addDays(9), Carbon::now()->addDays(10))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(1, $result[$end]);

        $result = Finished::make(Carbon::now()->subMonth(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(3, $result[$today]);
        $this->assertEquals(2, $result[$start]);
        $this->assertEquals(1, $result[$end]);
    }

    public function testActiveUsers(): void
    {
        $start = Carbon::now()->subMonth()->format('Y-m-d');
        $end = Carbon::now()->addMonth()->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->createNotification($user1);
        $this->createNotification($user2);
        $this->createNotification($user1, Carbon::now()->addMonth());
        $this->createNotification($user1, Carbon::now()->subMonth());

        $result = ActiveUsers::make()->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(2, $result[$today]);

        $result = ActiveUsers::make(Carbon::now()->subMonth(), Carbon::now()->subDay())->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(1, $result[$start]);
    }

    public function testNewUsers(): void
    {
        $start = Carbon::now()->subMonth()->format('Y-m-d');
        $end = Carbon::now()->addDays(5)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        User::factory()->count(10)->create(['created_at' => Carbon::today()]);
        User::factory()->count(5)->create(['created_at' => Carbon::now()->addDays(5)]);
        User::factory()->count(20)->create(['created_at' => Carbon::now()->subMonth()]);

        $result = NewUsers::make()->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(10, $result[$today]);

        $result = NewUsers::make(Carbon::now()->subMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(10, $result[$today]);
        $this->assertEquals(20, $result[$start]);

        $result = NewUsers::make(Carbon::now()->addDay(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(5, $result[$end]);

        $result = NewUsers::make(Carbon::now()->subMonth(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(10, $result[$today]);
        $this->assertEquals(20, $result[$start]);
        $this->assertEquals(5, $result[$end]);
    }

    public function testFinishedTopics(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic1 = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();
        $topic2 = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();

        $course->users()->attach($user1);
        $course->users()->attach($user2);
        $course->users()->attach($user3);

        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user1->getKey(), 'seconds' => 100, 'finished_at' => Carbon::now()]);
        CourseProgress::create(['topic_id' => $topic2->getKey(), 'user_id' => $user1->getKey(), 'seconds' => 50, 'finished_at' => Carbon::now()]);
        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user2->getKey(), 'seconds' => 150, 'finished_at' => Carbon::now()]);

        $result = FinishedTopics::make($course)->calculate();

        // user1
        $this->assertCount(3, $result);
        $this->assertEquals($user1->id, $result[0]['id']);
        $this->assertEquals($user1->email, $result[0]['email']);
        $this->assertEquals($user1->name, $result[0]['name']);
        $this->assertCount(2, $result[0]['topics']);
        $this->assertCount(2, $result[0]['topics']->filter(fn ($topic) => $topic['finished_at']));
        $this->assertArrayHasKey('finished_at', $result[0]['topics'][0]);
        $this->assertArrayHasKey('seconds', $result[0]['topics'][0]);
        $this->assertArrayHasKey('started_at', $result[0]['topics'][0]);

        // user2
        $this->assertEquals($user2->email, $result[1]['email']);
        $this->assertEquals($user2->id, $result[1]['id']);
        $this->assertEquals($user2->email, $result[1]['email']);
        $this->assertEquals($user2->name, $result[1]['name']);
        $this->assertCount(2, $result[1]['topics']);
        $this->assertCount(1, $result[1]['topics']->filter(fn ($topic) => $topic['finished_at']));
        $this->assertArrayHasKey('finished_at', $result[1]['topics'][0]);
        $this->assertArrayHasKey('seconds', $result[1]['topics'][0]);
        $this->assertArrayHasKey('started_at', $result[1]['topics'][0]);

        // user3
        $this->assertEquals($user3->email, $result[2]['email']);
        $this->assertEquals($user3->id, $result[2]['id']);
        $this->assertEquals($user3->email, $result[2]['email']);
        $this->assertEquals($user3->name, $result[2]['name']);
        $this->assertCount(2, $result[2]['topics']);
        $this->assertCount(0, $result[2]['topics']->filter(fn ($topic) => $topic['finished_at']));
        $this->assertArrayHasKey('finished_at', $result[2]['topics'][0]);
        $this->assertArrayHasKey('seconds', $result[2]['topics'][0]);
        $this->assertArrayHasKey('started_at', $result[2]['topics'][0]);
    }

    public function testFinishedCourse(): void
    {
        $today = Carbon::today()->format('Y-m-d H:i:s');

        $course = $this->createCourseWithLessonAndTopic();
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course->getKey(), 'updated_at' => Carbon::today(), 'finished' => false]);

        $result = FinishedCourse::make($course)->calculate();
        $this->assertEquals($student1->id, $result[0]['id']);
        $this->assertEquals($student1->email, $result[0]['email']);
        $this->assertEquals($student1->name, $result[0]['name']);
        $this->assertEquals(true, $result[0]['finished']);
        $this->assertEquals($today, $result[0]['finished_at']);
        $this->assertEquals($student2->id, $result[1]['id']);
        $this->assertEquals($student2->email, $result[1]['email']);
        $this->assertEquals($student2->name, $result[1]['name']);
        $this->assertEquals(false, $result[1]['finished']);
        $this->assertEquals(null, $result[1]['finished_at']);
    }

    public function testAttendanceList(): void
    {
        $now = now();
        $course = $this->createCourseWithLessonAndTopic();
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $result = AttendanceList::make($course)->calculate();
        $this->assertEquals([], $result);

        $topic = $course->topics->first();

        $userProgres = CourseProgress::factory()->create([
            'user_id' => $student->getKey(),
            'topic_id' => $topic->getKey(),
            'seconds' => 360,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
            'status' => ProgressStatus::COMPLETE,
        ]);
        $userProgres->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now()->subDay(),
            'seconds' => 0,
        ])->make());
        $userProgres->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now()->subDay()->addSeconds(10),
            'seconds' => 230,
        ])->make());
        $userProgres->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now()->subSeconds(10),
            'seconds' => 230,
        ])->make());
        $userProgres->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now(),
            'seconds' => 360,
        ])->make());

        $userProgres2 = CourseProgress::factory()->create([
            'user_id' => $student2->getKey(),
            'topic_id' => $topic->getKey(),
            'seconds' => 240,
            'created_at' => now(),
            'updated_at' => now(),
            'status' => ProgressStatus::COMPLETE,
        ]);
        $userProgres2->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now(),
            'seconds' => 0,
        ])->make());
        $userProgres2->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now()->addSeconds(10),
            'seconds' => 100,
        ])->make());
        $userProgres2->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now()->addSeconds(10),
            'seconds' => 100,
            'attempt' => 1,
        ])->make());
        $userProgres2->courseUserAttendances()->save(CourseUserAttendance::factory([
            'attendance_date' => now()->addSeconds(20),
            'seconds' => 240,
            'attempt' => 1,
        ])->make());

        $result = AttendanceList::make($course)->calculate();

        // student1
        $this->assertCount(2, $result);
        $this->assertEquals($student->email, $result[0]['email']);
        $this->assertEquals($student->name, $result[0]['name']);
        $this->assertCount(1, $result[0]['attempts']);
        $this->assertEquals(0, $result[0]['attempts'][0]['attempt']);
        $this->assertEquals(now()->subDay()->format('Y-m-d'), $result[0]['attempts'][0]['dates']->first()['date']);
        $this->assertEquals($now->format('Y-m-d'), $result[0]['attempts'][0]['dates']->last()['date']);
        $this->assertEquals(230, $result[0]['attempts'][0]['dates']->first()['seconds_total']);
        $this->assertEquals(130, $result[0]['attempts'][0]['dates']->last()['seconds_total']);
        // student2
        $this->assertEquals($student2->email, $result[1]['email']);
        $this->assertEquals($student2->name, $result[1]['name']);
        $this->assertCount(2, $result[1]['attempts']);
        $this->assertEquals(0, $result[1]['attempts'][0]['attempt']);
        $this->assertEquals($now->format('Y-m-d'), $result[1]['attempts'][0]['dates']->first()['date']);
        $this->assertEquals(100, $result[1]['attempts'][0]['dates']->first()['seconds_total']);
        $this->assertEquals(1, $result[1]['attempts'][1]['attempt']);
        $this->assertEquals($now->format('Y-m-d'), $result[1]['attempts'][1]['dates']->first()['date']);
        $this->assertEquals(140, $result[1]['attempts'][1]['dates']->first()['seconds_total']);
    }

    public function testGiftQuizTopics(): void
    {
        Carbon::setTestNow();

        if (!class_exists(GiftQuiz::class)) {
            $this->markTestSkipped();
        }

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();
        $course->users()->attach($user1);
        $course->users()->attach($user2);

        assert($topic instanceof Topic);

        $giftQuiz = GiftQuiz::factory()->create();

        $topic->topicable()->associate($giftQuiz);
        $topic->save();

        $questions = [
            [
                'question' => '2+2?{~3 ~5 =4}',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'answer' => [AnswerKeyEnum::TEXT => '4'],
                'wrongAnswer' => [AnswerKeyEnum::TEXT => '3'],
                'score' => 1,
            ],
            [
                'question' => '2+2?{~3 =4 ~5}',
                'type' => QuestionTypeEnum::MULTIPLE_CHOICE,
                'answer' => [AnswerKeyEnum::TEXT => '4'],
                'wrongAnswer' => [AnswerKeyEnum::TEXT => '5'],
                'score' => 1,
            ],
            [
                'question' => '// question: 0 name: FalseStatement using {FALSE} style
                               ::FalseStatement about sun::The sun rises in the West.{FALSE}',
                'type' => QuestionTypeEnum::TRUE_FALSE,
                'answer' => [AnswerKeyEnum::BOOL => false],
                'wrongAnswer' => [AnswerKeyEnum::BOOL => true],
                'score' => 1,
            ],
        ];

        $createdQuestions = [];
        foreach ($questions as $question) {
            $createdQuestion = GiftQuestion::factory()
                ->state([
                    'topic_gift_quiz_id' => $giftQuiz->getKey(),
                    'value' => $question['question'],
                    'type' => $question['type'],
                    'score' => $question['score'],
                ])->create();

            $createdQuestions[$createdQuestion->getKey()] = $question;
        }
        $giftQuiz->load('questions');

        $attempt11 = QuizAttempt::factory()
            ->state([
                'user_id' => $user1->getKey(),
                'topic_gift_quiz_id' => $giftQuiz->getKey(),
                'started_at' => Carbon::now()->subMinutes(30),
            ])
            ->create();

        $service = app(AttemptAnswerServiceContract::class);
        assert($service instanceof AttemptAnswerService);

        foreach ($giftQuiz->questions as $question) {
            $service->saveAnswer(new SaveAttemptAnswerDto($attempt11->getKey(), $question->getKey(), $createdQuestions[$question->getKey()]['wrongAnswer']));
            MarkAttemptAsEnded::dispatch($attempt11->getKey());
        }

        $attempt12 = QuizAttempt::factory()
            ->state([
                'user_id' => $user1->getKey(),
                'topic_gift_quiz_id' => $giftQuiz->getKey(),
                'started_at' => Carbon::now()->subMinutes(15),
            ])
            ->create();

        foreach ($giftQuiz->questions as $question) {
            $service->saveAnswer(new SaveAttemptAnswerDto($attempt12->getKey(), $question->getKey(), $createdQuestions[$question->getKey()]['answer']));
            MarkAttemptAsEnded::dispatch($attempt12->getKey());
        }

        $attempt21 = QuizAttempt::factory()
            ->state([
                'user_id' => $user2->getKey(),
                'topic_gift_quiz_id' => $giftQuiz->getKey(),
                'started_at' => Carbon::now()->subMinutes(25),
            ])
            ->create();

        foreach ($giftQuiz->questions as $question) {
            $service->saveAnswer(new SaveAttemptAnswerDto($attempt21->getKey(), $question->getKey(), $createdQuestions[$question->getKey()]['answer']));
            MarkAttemptAsEnded::dispatch($attempt21->getKey());
        }

        $result = QuizSummaryForTopicTypeGIFT::make($topic)->calculate();

        $this->assertTrue((1801 - $result[1]['attempt_time']) <= 1);
        $this->assertEquals($result[1]['user_id'], $user1->getKey());
        $this->assertEquals($result[1]['name'], $user1->name);
        $this->assertEquals($result[1]['attempt'], 1);

        $this->assertTrue((901 - $result[2]['attempt_time']) <= 1);
        $this->assertEquals($result[2]['user_id'], $user1->getKey());
        $this->assertEquals($result[2]['name'], $user1->name);
        $this->assertEquals($result[2]['attempt'], 2);

        $this->assertTrue((1501 - $result[3]['attempt_time']) <= 1);
        $this->assertEquals($result[3]['user_id'], $user2->getKey());
        $this->assertEquals($result[3]['name'], $user2->name);
        $this->assertEquals($result[3]['attempt'], 1);

        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)
            ->json('GET', '/api/admin/stats/topic/' . $topic->getKey() . '?' . Arr::query([
                'stats' => [
                    QuizSummaryForTopicTypeGIFT::class,
                ]
            ]));
        $response->assertOk();
        $response->assertJsonFragment([
            'user_id' => $user1->getKey(),
            'attempt' => 2,
        ]);
    }
}
