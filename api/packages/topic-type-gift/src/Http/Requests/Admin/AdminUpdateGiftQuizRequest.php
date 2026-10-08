<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Dtos\QuizDto;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminUpdateGiftQuizRequest",
 *      required={"value"},
 *      @OA\Property(
 *          property="value",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="max_attempts",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="max_execution_time",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="min_pass_score",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="counts_to_grade",
 *          type="boolean"
 *      ),
 *      @OA\Property(
 *          property="weight",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="randomize_order",
 *          type="boolean"
 *      ),
 * )
 */
class AdminUpdateGiftQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getGiftQuiz());
    }

    public function rules(): array
    {
        return GiftQuiz::rules();
    }

    public function getId(): int
    {
        return (int)$this->route('id');
    }

    public function getGiftQuiz(): GiftQuiz
    {
        return GiftQuiz::findOrFail($this->getId());
    }

    public function toDto(): QuizDto
    {
        return QuizDto::instantiateFromRequest($this);
    }
}
