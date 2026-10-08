<?php

namespace EscolaLms\TopicTypeGift\Rules;

use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Strategies\GiftQuestionStrategyFactory;
use Illuminate\Contracts\Validation\Rule;

class AnswerKeyRule implements Rule
{
    private $questionId;
    private $message;

    public function __construct($questionId)
    {
        $this->questionId = $questionId;
    }

    public function passes($attribute, $value): bool
    {
        /** @var GiftQuestion $question */
        $question = GiftQuestion::query()->findOrFail($this->questionId);
        $strategy = GiftQuestionStrategyFactory::create($question);
        $answerKey = $strategy->getAnswerKey();
        if ($answerKey && (!is_array($value) || !array_key_exists($answerKey, $value))) {
            $this->message = __('Field :key is required', ['key' => $answerKey]);
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->message;
    }
}
