<?php

namespace Ulams\TopicTypeGift\Http\Requests\Admin;

use Ulams\TopicTypeGift\Dtos\AdminUpdateAttemptAnswerDto;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminUpdateAttemptAnswerRequest",
 *      required={"score"},
 *      @OA\Property(
 *          property="score",
 *          description="score",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="feedback",
 *          description="feedback",
 *          type="string"
 *      )
 * )
 *
 */
class AdminUpdateAttemptAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getAttemptAnswer()->attempt);
    }

    public function rules(): array
    {
        return [
            'score' => ['required', 'numeric'],
            'feedback' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function getId(): int
    {
        return $this->route('id');
    }

    public function toDto(): AdminUpdateAttemptAnswerDto
    {
        return AdminUpdateAttemptAnswerDto::instantiateFromRequest($this);
    }

    private function getAttemptAnswer(): AttemptAnswer
    {
        return AttemptAnswer::findOrFail($this->getId());
    }
}
