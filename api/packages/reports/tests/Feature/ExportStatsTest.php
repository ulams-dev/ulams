<?php

namespace EscolaLms\Reports\Tests\Feature;

use Carbon\Carbon;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsAttemptsSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsInfoSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsSecondsSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsStatusesSheet;
use EscolaLms\Reports\Exports\Stats\Topic\QuizSummaryForTopicTypeGIFTExport;
use EscolaLms\Reports\Stats\Course\FinishedTopics;
use EscolaLms\Reports\Tests\Models\Course;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\TopicTypeGift\Models\AttemptAnswer;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypes\Models\TopicContent\Audio;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use EscolaLms\TopicTypes\Models\TopicContent\PDF;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExportStatsTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    /**
     * @dataProvider finishedTopicsResult
     */
    public function testFinishedTopicsSheets(string $sheet, string $title, array $result): void
    {
        $user1 = User::factory()->state(['email' => 'abc@example.com'])->create();
        $user2 = User::factory()->state(['email' => 'def@example.com'])->create();
        $user3 = User::factory()->state(['email' => 'ghi@example.com'])->create();

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();

        $topic1 = Topic::factory()->state(['lesson_id' => $lesson->getKey(), 'can_skip' => true])->create();
        $topicablePdf = Pdf::factory()->state(['length' => 100, 'page_count'=> 3])->create();
        $topic1->topicable()->associate($topicablePdf)->save();

        $topic2 = Topic::factory()->state(['lesson_id' => $lesson->getKey(), 'can_skip' => false])->create();
        $topicableAudio = Audio::factory()->state(['length' => 321])->create();
        $topic2->topicable()->associate($topicableAudio)->save();

        $topic3 = Topic::factory()->state(['lesson_id' => $lesson->getKey(), 'can_skip' => false])->create();
        $topicable_h5p = H5P::factory()->create();
        $topic3->topicable()->associate($topicable_h5p)->save();

        $course->users()->attach([$user1->getKey(), $user2->getKey(), $user3->getKey()]);

        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user1->getKey(), 'seconds' => 100, 'started_at' => Carbon::now(), 'finished_at' => Carbon::now(), 'attempt' => 0]);
        CourseProgress::create(['topic_id' => $topic2->getKey(), 'user_id' => $user1->getKey(), 'seconds' => 0, 'started_at' => Carbon::now(), 'finished_at' => null, 'attempt' => 2]);
        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user2->getKey(), 'seconds' => 150, 'started_at' => null, 'finished_at' => null, 'attempt' => 1]);

        $stats = FinishedTopics::make($course)->calculate();
        $export = new $sheet(collect($stats));

        $this->assertEquals(__($title), $export->title());

        $this->assertEquals([
            $export->firstHeader,
            'PDF # ' . $topic1->title,
            'Audio # ' . $topic2->title,
            H5PContent::find($topicable_h5p->value)->library->uberName . ' # ' . $topic3->title
        ], $export->headings());

        $this->assertEquals(collect($result), $export->collection());
    }

    public function finishedTopicsResult(): array
    {
        return [
            [
                'sheet' => FinishedTopicsAttemptsSheet::class,
                'title' => 'Attempts',
                [
                    collect(['abc@example.com', 1, 3, 1]),
                    collect(['def@example.com', 2, 1, 1]),
                    collect(['ghi@example.com', 1, 1, 1]),
                ],
            ],
            [
                'sheet' => FinishedTopicsSecondsSheet::class,
                'title' => 'Seconds',
                [
                    collect(['abc@example.com', 100, 0, 0]),
                    collect(['def@example.com', 150, 0, 0]),
                    collect(['ghi@example.com', 0, 0, 0]),
                ],
            ],
            [
                'sheet' => FinishedTopicsStatusesSheet::class,
                'title' => 'Statuses',
                [
                    collect(['abc@example.com', 2, 1, 0]),
                    collect(['def@example.com', 0, 0, 0]),
                    collect(['ghi@example.com', 0, 0, 0]),
                ],
            ],
            [
                'sheet' => FinishedTopicsInfoSheet::class,
                'title' => 'Topic info',
                [
                    collect(['Length', 100, 321, null]),
                    collect(['Page count', 3, null, null]),
                    collect(['Can skip', true, false, false]),
                ],
            ],
        ];
    }

    public function testQuizSummarySheet(): void
    {
        $giftQuiz = GiftQuiz::factory()->create();
        $course = \EscolaLms\Reports\Tests\Models\Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();

        /** @var Topic $topic */
        $topic = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();
        $topic->topicable()->associate($giftQuiz);
        $topic->save();

        $question1 = GiftQuestion::factory()->for($giftQuiz)->create();
        $question2 = GiftQuestion::factory()->for($giftQuiz)->create();

        $attempt1 = QuizAttempt::factory()->for($giftQuiz)->create();
        $answer11 = AttemptAnswer::factory()->for($attempt1, 'attempt')->for($question1, 'question')->create();
        $answer12 = AttemptAnswer::factory()->for($attempt1, 'attempt')->for($question2, 'question')->create();

        $attempt2 = QuizAttempt::factory()->for($giftQuiz)->create();
        $answer21 = AttemptAnswer::factory()->for($attempt2, 'attempt')->for($question1, 'question')->create();
        $answer22 = AttemptAnswer::factory()->for($attempt2, 'attempt')->for($question2, 'question')->create();

        $export = new QuizSummaryForTopicTypeGIFTExport($topic);

        $this->assertEquals([
            __('User'),
            __('Email'),
            __('Name'),
            __('Attempt'),
            __('Attempt Date'),
            __('Time'),
            'Question 1',
            'Question 2',
            __('Summary'),
        ], array_values($export->headings()));


        $collection = $export->collection();

        $row1 = collect($export->map($collection->get(0)));
        $this->assertEquals($attempt1->user_id, $row1->get('user_id'));
        $this->assertEquals($attempt1->user->email, $row1->get('email'));
        $this->assertEquals($attempt1->user->name, $row1->get('name'));
        $this->assertEquals($answer11->score, $row1->get('question_' . $question1->getKey()));
        $this->assertEquals($answer12->score, $row1->get('question_' . $question2->getKey()));

        $row2 = collect($export->map($collection->get(1)));
        $this->assertEquals($attempt2->user_id, $row2->get('user_id'));
        $this->assertEquals($attempt2->user->email, $row2->get('email'));
        $this->assertEquals($attempt2->user->name, $row2->get('name'));
        $this->assertEquals($answer21->score, $row2->get('question_' . $question1->getKey()));
        $this->assertEquals($answer22->score, $row2->get('question_' . $question2->getKey()));
    }
}
