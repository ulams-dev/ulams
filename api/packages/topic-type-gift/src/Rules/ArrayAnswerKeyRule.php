<?php

namespace EscolaLms\TopicTypeGift\Rules;

use Illuminate\Contracts\Validation\Rule;

class ArrayAnswerKeyRule implements Rule
{
    private $questionId;
    
    private $message;

    public function __construct($questionId)
    {
        $this->questionId = $questionId;
    }

    public function passes($attribute, $value): bool
    {
        $rule = new AnswerKeyRule($value['topic_gift_question_id']);
        if (!$rule->passes($attribute, $value['answer'])) {
            $this->message = $rule->message();
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->message;
    }
}
