<?php

namespace EscolaLms\TopicTypeGift\Http\Requests;

use EscolaLms\TopicTypeGift\Dtos\SaveAllAttemptAnswersDto;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Rules\ArrayAnswerKeyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="SaveAllAttemptAnswerRequest",
 *      required={"topic_gift_quiz_attempt_id", "answers"},
 *      @OA\Property(
 *          property="topic_gift_quiz_attempt_id",
 *          description="attempt id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="answers",
 *          description="answers",
 *          type="array",
 *          @OA\Items(
 *              @OA\Property(
 *                  property="topic_gift_question_id",
 *                  description="topic_gift_question_id",
 *                  type="number"
 *              ),
 *              @OA\Property(
 *                  property="answer",
 *                  description="answer",
 *                  type="object"
 *             ),
 *          )
 *      )
 * )
 */
class SaveAllAttemptAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('addAnswerOwn', $this->getAttempt());
    }

    public function rules(): array
    {
        return [
            'topic_gift_quiz_attempt_id' => ['required', 'integer', 'exists:topic_gift_quiz_attempts,id'],
            'answers' => ['required', 'array'],
            'answers.*' => ['required', new ArrayAnswerKeyRule($this->get('answers'))],
            'answers.*.answer.text' => ['sometimes', 'string'],
            'answers.*.answer.matching' => ['sometimes', 'array'],
            'answers.*.answer.multiple' => ['sometimes', 'array'],
            'answers.*.answer.bool' => ['sometimes', 'boolean'],
            'answers.*.answer.numeric' => ['sometimes', 'numeric'],
        ];
    }

    public function getAttempt(): QuizAttempt
    {
        return QuizAttempt::findOrFail($this->input('topic_gift_quiz_attempt_id'));
    }

    public function toDto(): SaveAllAttemptAnswersDto
    {
        return SaveAllAttemptAnswersDto::instantiateFromRequest($this);
    }
}
