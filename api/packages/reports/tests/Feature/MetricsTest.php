<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Questionnaire\Models\Question;
use EscolaLms\Questionnaire\Models\QuestionAnswer;
use EscolaLms\Questionnaire\Models\Questionnaire;
use EscolaLms\Questionnaire\Models\QuestionnaireModel;
use EscolaLms\Reports\Metrics\CoursesBestRatedMetric;
use EscolaLms\Reports\Metrics\CoursesMoneySpentMetric;
use EscolaLms\Reports\Metrics\CoursesPopularityMetric;
use EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric;
use EscolaLms\Reports\Metrics\CoursesTopSellingMetric;
use EscolaLms\Reports\Metrics\TutorsPopularityMetric;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use EscolaLms\Reports\Tests\Traits\QuestionnaireTestingTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class MetricsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions, WithFaker;
    use CoursesTestingTrait, QuestionnaireTestingTrait;

    public function testCoursesSecondsSpentMetric(): void
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->saveMany([$course, $course2]);

        $this->progressUserInCourse($student, $course, 999999);

        $results = CoursesSecondsSpentMetric::make()->calculate();

        $this->assertEquals(999999, $results[0]['value']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals($course->id, $results[0]['id']);

        $this->progressUserInCourse($student, $course, 888888);
        $this->progressUserInCourse($student, $course2, 777777);

        $results = CoursesSecondsSpentMetric::make()->calculate();

        $this->assertEquals(888888, $results[0]['value']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals($course->id, $results[0]['id']);
        $this->assertEquals(777777, $results[1]['value']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals($course2->id, $results[1]['id']);

        $report = CoursesSecondsSpentMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(888888, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesSecondsSpentMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesSecondsSpentMetric::make()->requiredPackage());
    }

    public function testCoursesPopularityMetric()
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        $students = User::factory()->count(200)->create();
        $course->users()->attach($students);
        $students = User::factory()->count(100)->create();
        $course2->users()->attach($students);

        $results = CoursesPopularityMetric::make()->calculate();

        $this->assertEquals($course->getKey(), $results[0]['id']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals(200, $results[0]['value']);

        $this->assertEquals($course2->getKey(), $results[1]['id']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals(100, $results[1]['value']);

        $report = CoursesPopularityMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(200, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesPopularityMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesPopularityMetric::make()->requiredPackage());
    }

    public function testTutorsPopularityMetric()
    {
        $tutor = $this->makeInstructor();
        $course = $this->createCourseWithLessonAndTopic();
        $course->authors()->sync($tutor);
        $course2 = $this->createCourseWithLessonAndTopic();
        $course2->authors()->sync($tutor);

        $tutor2 = $this->makeInstructor();
        $course3 = $this->createCourseWithLessonAndTopic();
        $course3->authors()->sync($tutor2);

        $students = User::factory()->count(100)->create();
        $course->users()->sync($students);
        $students = User::factory()->count(100)->create();
        $course2->users()->sync($students);

        $students = User::factory()->count(100)->create();
        $course3->users()->sync($students);

        $results = TutorsPopularityMetric::make()->calculate();

        $this->assertEquals($tutor->getKey(), $results[0]['id']);
        $this->assertEquals($tutor->email, $results[0]['label']);
        $this->assertEquals(200, $results[0]['value']);
        $this->assertEquals($tutor2->getKey(), $results[1]['id']);
        $this->assertEquals($tutor2->email, $results[1]['label']);
        $this->assertEquals(100, $results[1]['value']);

        $report = TutorsPopularityMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', User::class)->where('measurable_id', '=', $tutor->getKey())->first();

        $this->assertEquals(200, $measurement->value);
        $this->assertEquals($tutor->email, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($tutor->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), TutorsPopularityMetric::make()->requiredPackageInstalled());
        $this->assertIsString(TutorsPopularityMetric::make()->requiredPackage());
    }

    public function testCourseMoneySpentMetric()
    {
        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        $order = $this->makePaidOrder($student, $course);
        $order2 = $this->makePaidOrder($student, $course2);
        $order3 = $this->makePaidOrder($student2, $course);

        $results = CoursesMoneySpentMetric::make()->calculate();

        $this->assertEquals($course->getKey(), $results[0]['id']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals(2000, $results[0]['value']);
        $this->assertEquals($course2->getKey(), $results[1]['id']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals(1000, $results[1]['value']);

        $report = CoursesMoneySpentMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(2000, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesMoneySpentMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesMoneySpentMetric::make()->requiredPackage());
    }

    public function testCourseTopSellingMetric(): void
    {
        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $course1 = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();

        $this->makePaidOrder($student, $course1);
        $this->makePaidOrder($student, $course1);
        $this->makePaidOrder($student, $course1);
        $this->makePaidOrder($student, $course2);
        $this->makePaidOrder($student2, $course3);
        $this->makePaidOrder($student2, $course3);

        $results = CoursesTopSellingMetric::make()->calculate();

        $this->assertEquals($course1->getKey(), $results[0]['id']);
        $this->assertEquals($course1->title, $results[0]['label']);
        $this->assertEquals(3, $results[0]['value']);
        $this->assertEquals($course2->getKey(), $results[2]['id']);
        $this->assertEquals($course2->title, $results[2]['label']);
        $this->assertEquals(1, $results[2]['value']);
        $this->assertEquals($course3->getKey(), $results[1]['id']);
        $this->assertEquals($course3->title, $results[1]['label']);
        $this->assertEquals(2, $results[1]['value']);

        $report = CoursesTopSellingMetric::make()->calculateAndStore();

        $measurement = $report->measurements()
            ->where('measurable_type', '=', Course::class)
            ->orderBy('id')
            ->get();

        $this->assertEquals(3, $measurement->first()->value);
        $this->assertEquals($course1->title, $measurement->first()->label);
        $this->assertEquals($report->getKey(), $measurement->first()->report->getKey());
        $this->assertEquals($course1->getKey(), $measurement->first()->measurable->getKey());
        $this->assertEquals(1, $measurement->last()->value);
        $this->assertEquals($course2->title, $measurement->last()->label);
        $this->assertEquals($report->getKey(), $measurement->last()->report->getKey());
        $this->assertEquals($course2->getKey(), $measurement->last()->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesTopSellingMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesTopSellingMetric::make()->requiredPackage());
    }

    public function testCourseBestRatedMetric(): void
    {
        $course1 = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();
        $questionnaireModelType = $this->getCourseQuestionnaireModelType();
        $questionnaire = Questionnaire::factory()->create();
        $questionnaireModel1 = QuestionnaireModel::factory()->create([
            'model_type_id' => $questionnaireModelType->getKey(),
            'model_id' => $course1->getKey()
        ]);
        $questionnaireModel2 = QuestionnaireModel::factory()->create([
            'model_type_id' => $questionnaireModelType->getKey(),
            'model_id' => $course2->getKey()
        ]);
        $questionnaireModel3 = QuestionnaireModel::factory()->create([
            'model_type_id' => $questionnaireModelType->getKey(),
            'model_id' => $course3->getKey()
        ]);
        $question = Question::factory()->create([
            'questionnaire_id' => $questionnaire->getKey()
        ]);
        $questionAnswer1 = QuestionAnswer::factory()
            ->count(50)
            ->create([
                'question_id' => $question->getKey(),
                'questionnaire_model_id' => $questionnaireModel1->getKey(),
                'rate' => $this->faker->numberBetween(3, 4)
            ]);
        $questionAnswer2 = QuestionAnswer::factory()
            ->count(50)
            ->create([
                'question_id' => $question->getKey(),
                'questionnaire_model_id' => $questionnaireModel2->getKey(),
                'rate' => $this->faker->numberBetween(5, 6)
            ]);
        $questionAnswer3 = QuestionAnswer::factory()
            ->count(50)
            ->create([
                'question_id' => $question->getKey(),
                'questionnaire_model_id' => $questionnaireModel3->getKey(),
                'rate' => $this->faker->numberBetween(1, 2)
            ]);

        $report = CoursesBestRatedMetric::make()->calculateAndStore();

        $measurement = $report->measurements()
            ->where('measurable_type', '=', Course::class)
            ->orderBy('id')
            ->get();

        $this->assertEquals($questionAnswer2->sum(fn ($answer) => $answer->rate), $measurement->first()->value);
        $this->assertEquals($course2->title, $measurement->first()->label);
        $this->assertEquals($questionAnswer3->sum(fn ($answer) => $answer->rate), $measurement->last()->value);
        $this->assertEquals($course3->title, $measurement->last()->label);

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesBestRatedMetric::make()->requiredPackageInstalled());
        $this->assertEquals(class_exists(\EscolaLms\Questionnaire\EscolaLmsQuestionnaireServiceProvider::class), CoursesBestRatedMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesBestRatedMetric::make()->requiredPackage());
    }
}
