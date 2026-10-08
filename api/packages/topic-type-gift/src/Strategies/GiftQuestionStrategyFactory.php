<?php

namespace EscolaLms\TopicTypeGift\Strategies;

use EscolaLms\TopicTypeGift\Enum\QuestionTypeEnum;
use EscolaLms\TopicTypeGift\Exceptions\UnknownGiftTypeException;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Strategies\Contracts\QuestionStrategyContract;

final class GiftQuestionStrategyFactory
{
    /**
     * @throws UnknownGiftTypeException
     */
    public static function create(GiftQuestion $question, ?int $optionsSeed = null): QuestionStrategyContract
    {
        switch ($question->type) {
            case QuestionTypeEnum::MULTIPLE_CHOICE:
                return new MultipleChoiceQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::MULTIPLE_CHOICE_WITH_MULTIPLE_RIGHT_ANSWERS:
                return new MultipleChoiceWithMultipleAnswersQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::TRUE_FALSE:
                return new TrueFalseQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::SHORT_ANSWERS:
                return new ShortAnswerQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::MATCHING:
                return new MatchingQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::NUMERICAL_QUESTION:
                return new NumericalQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::ESSAY:
                return new EssayQuestionStrategy($question, $optionsSeed);
            case QuestionTypeEnum::DESCRIPTION:
                return new DescriptionQuestionStrategy($question, $optionsSeed);
            default:
                throw new UnknownGiftTypeException($question->type);
        }
    }
}
