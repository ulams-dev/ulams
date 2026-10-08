<?php

namespace Ulams\TopicTypeGift\Http\Requests;

use Ulams\Courses\Models\Topic;
use Ulams\TopicTypeGift\Dtos\QuizAttemptDto;
use Ulams\TopicTypeGift\Enum\TopicTypeGiftPermissionEnum;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="GetActiveAttemptRequest",
 *      required={"topic_gift_quiz_id"},
 *      @OA\Property(
 *          property="topic_gift_quiz_id",
 *          description="topic_gift_quiz_id",
 *          type="number"
 *      )
 * )
 *
 */
class GetActiveAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->getTopic() && !Gate::allows('attend', $this->getTopic())) {
            return false;
        }

        return Gate::allows('createOwn', QuizAttempt::class);
    }

    public function rules(): array
    {
        return [
            'topic_gift_quiz_id' => ['required', 'integer', 'exists:topic_gift_quizzes,id'],
        ];
    }

    public function getQuizAttemptDto(): QuizAttemptDto
    {
        return QuizAttemptDto::instantiateFromRequest($this);
    }

    public function getTopic(): ?Topic
    {
        return GiftQuiz::findOrFail($this->get('topic_gift_quiz_id'))->topic;
    }
}
