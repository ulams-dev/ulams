<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeGift\Dtos\GiftQuestionDto;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *      schema="AdminGiftQuestionRequest",
 *      required={"topic_gift_quiz_id", "value", "score"},
 *      @OA\Property(
 *          property="topic_gift_quiz_id",
 *          description="topic_gift_quiz_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="value",
 *          description="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="score",
 *          description="max score",
 *          type="number",
 *          example="1"
 *      ),
 *      @OA\Property(
 *          property="order",
 *          description="order",
 *          type="integer",
 *          example="1"
 *      ),
 *      @OA\Property(
 *          property="category_id",
 *          description="category_id",
 *          type="integer",
 *          example="1"
 *      ),
 * )
 *
 */
class AdminGiftQuestionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'topic_gift_quiz_id' => ['required', 'integer', 'exists:topic_gift_quizzes,id'],
            'value' => ['required', 'string'],
            'score' => ['required', 'integer', 'min:0'],
            'order' => ['nullable', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function getGiftQuestionDto(): GiftQuestionDto
    {
        return GiftQuestionDto::instantiateFromRequest($this);
    }

    public function getTopic(): ?Topic
    {
        return GiftQuiz::findOrFail($this->get('topic_gift_quiz_id'))->topic;
    }
}
