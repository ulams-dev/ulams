<?php

namespace EscolaLms\TopicTypeGift\Http\Requests\Admin;

use EscolaLms\TopicTypeGift\Http\Requests\ReadQuizAttemptRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminUpdateQuizAttemptFeedbackRequest",
 *      @OA\Property(
 *          property="feedback",
 *          description="Tutor feedback (light rich text or video link). Send null or empty to remove it.",
 *          type="string"
 *      )
 * )
 *
 */
class AdminUpdateQuizAttemptFeedbackRequest extends ReadQuizAttemptRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getAttempt());
    }

    public function rules(): array
    {
        return [
            'feedback' => ['nullable', 'string'],
        ];
    }

    public function getId(): int
    {
        return $this->route('id');
    }

    public function getFeedback(): ?string
    {
        return $this->input('feedback');
    }
}
