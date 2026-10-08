<?php

namespace Ulams\TopicTypeGift\Tests\Strategies;

use Ulams\Courses\Models\Course;
use Ulams\Courses\Models\Lesson;
use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Strategies\GiftQuestionStrategyFactory;
use Ulams\TopicTypeGift\Tests\GiftQuestionTesting;
use Ulams\TopicTypeGift\Tests\TestCase;

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
