<?php

namespace EscolaLms\TopicTypeProject\Http\Requests\Admin;

use EscolaLms\TopicTypeProject\Dtos\GradeProjectSolutionDto;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="AdminGradeProjectSolutionRequest",
 *      required={"score"},
 *      @OA\Property(
 *          property="score",
 *          type="number"
 *      ),
 * )
 */
class AdminGradeProjectSolutionRequest extends FormRequest
{
    private ?ProjectSolution $solution = null;

    public function authorize(): bool
    {
        return Gate::allows('update', $this->getProjectSolution());
    }

    public function rules(): array
    {
        $rules = ['score' => ['required', 'numeric', 'min:0']];

        $maxScore = $this->getMaxScore();
        if ($maxScore !== null) {
            $rules['score'][] = 'max:' . $maxScore;
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->getMaxScore() === null) {
                $validator->errors()->add('score', __('Project max score is not set.'));
            }
        });
    }

    public function getProjectSolution(): ProjectSolution
    {
        if ($this->solution === null) {
            $this->solution = ProjectSolution::with('topic.topicable')->findOrFail((int) $this->route('id'));
        }

        return $this->solution;
    }

    public function getGradeProjectSolutionDto(): GradeProjectSolutionDto
    {
        return GradeProjectSolutionDto::instantiateFromRequest($this);
    }

    private function getMaxScore(): ?int
    {
        $project = $this->getProjectSolution()->topic->topicable ?? null;

        return $project ? $project->max_score : null;
    }
}
