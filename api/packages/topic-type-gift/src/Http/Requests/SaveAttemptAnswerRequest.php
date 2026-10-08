<?php

namespace EscolaLms\TopicTypeGift\Http\Requests;

use EscolaLms\TopicTypeGift\Dtos\SaveAttemptAnswerDto;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Rules\AnswerKeyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="SaveAttemptAnswerRequest",
 *      required={"topic_gift_question_id", "answer"},
 *      @OA\Property(
 *          property="topic_gift_question_id",
 *          description="question id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="topic_gift_quiz_attempt_id",
 *          description="attempt id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="answer",
 *          description="answer",
 *          type="object"
 *      )
 * )
 */
class SaveAttemptAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('addAnswerOwn', $this->getAttempt());
    }

    public function rules(): array
    {
        return [
            'topic_gift_quiz_attempt_id' => ['required', 'integer', 'exists:topic_gift_quiz_attempts,id'],
            'topic_gift_question_id' => ['required', 'integer', 'exists:topic_gift_questions,id'],
            'answer' => ['array', new AnswerKeyRule($this->get('topic_gift_question_id'))],
            'answer.text' => ['sometimes', 'string'],
            'answer.matching' => ['sometimes', 'array'],
            'answer.multiple' => ['sometimes', 'array'],
            'answer.bool' => ['sometimes', 'boolean'],
            'answer.numeric' => ['sometimes', 'numeric'],
        ];
    }

    public function getAttempt(): QuizAttempt
    {
        return QuizAttempt::findOrFail($this->input('topic_gift_quiz_attempt_id'));
    }

    public function toDto(): SaveAttemptAnswerDto
    {
        return SaveAttemptAnswerDto::instantiateFromRequest($this);
    }
}
