<?php

namespace EscolaLms\TopicTypeProject\Http\Requests\Admin;

use EscolaLms\TopicTypeProject\Http\Requests\ReadProjectSolutionRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminUpdateProjectSolutionFeedbackRequest",
 *      @OA\Property(
 *          property="feedback",
 *          description="Tutor feedback (rich text). Send null or empty to remove it.",
 *          type="string"
 *      )
 * )
 *
 */
class AdminUpdateProjectSolutionFeedbackRequest extends ReadProjectSolutionRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->getProjectSolution());
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
