<?php

namespace EscolaLms\TopicTypeGift\Tests\Strategies;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Strategies\GiftQuestionStrategyFactory;
use EscolaLms\TopicTypeGift\Tests\GiftQuestionTesting;
use EscolaLms\TopicTypeGift\Tests\TestCase;

class QuestionStrategyTest extends TestCase
{
    use GiftQuestionTesting;

    private $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $topic = Topic::factory()
            ->for(Lesson::factory()
                ->for(Course::factory()))
            ->create();

        $this->quiz = GiftQuiz::factory()->create();
        $topic->topicable()->associate($this->quiz)->save();
    }

    /**
     * @dataProvider questionDataProvider
     * @throws UnknownGiftTypeException
     */
    public function testShouldReturnCorrectDataForStudent(string $question, string $type, string $title, string $questionForStudent, array $options): void
    {
        /** @var GiftQuestion $question */
        $question = GiftQuestion::factory()
            ->state([
                'value' => $question,
                'type' => $type,
            ])
            ->for($this->quiz)
            ->create();

        $strategy = GiftQuestionStrategyFactory::create($question);
        $this->assertEquals($type, $question->type);
        $this->assertEquals($title, $strategy->getTitle());
        $this->assertEquals($questionForStudent, $strategy->getQuestionForStudent());
        $this->assertEqualsCanonicalizing($options, $strategy->getOptions());
    }
}
