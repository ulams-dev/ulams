<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Dtos\AdminSortQuestionDto;
use EscolaLms\TopicTypeGift\Models\GiftQuestion;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminSortGiftQuestionRequest",
 *      @OA\Property(
 *          property="orders",
 *          description="orders",
 *          type="array",
 *          @OA\Items(
 *              @OA\Schema(
 *                  @OA\Property(
 *                      property="id",
 *                      description="id",
 *                      type="integer"
 *                  ),
 *                  @OA\Property(
 *                      property="order",
 *                      description="order",
 *                      type="integer"
 *                  )
 *              )
 *         )
 *      )
 * )
 *
 */
class AdminSortGiftQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $questions = $this->getQuestions();
        $quizId = $questions->pluck('topic_gift_quiz_id')->unique();
        $quiz = GiftQuiz::findOrFail($quizId)->first();

        if ($quiz->topic && !Gate::allows('update', $quiz->topic)) {
            return false;
        }

        return $quizId->count() === 1;
    }

    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.id' => ['required', 'integer', 'exists:topic_gift_questions,id'],
            'orders.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function getQuestions(): Collection
    {
        return GiftQuestion::whereIn('id', collect($this->get('orders'))
            ->pluck('id'))
            ->get();
    }

    public function toDto(): AdminSortQuestionDto
    {
        return AdminSortQuestionDto::instantiateFromRequest($this);
    }
}
