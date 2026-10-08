<?php

namespace EscolaLms\TopicTypeProject\Http\Requests;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\TopicTypeProject\Dtos\CreateProjectSolutionDto;
use EscolaLms\TopicTypeProject\Models\Project;
use EscolaLms\TopicTypeProject\Models\ProjectSolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * @OA\Schema(
 *      schema="CreateProjectSolutionRequest",
 *      required={"topic_id", "file"},
 *      @OA\Property(
 *          property="topic_id",
 *          description="topic_id",
 *          type="number"
 *      ),
 *      @OA\Property(
 *          property="file",
 *          description="file",
 *          type="file"
 *      ),
 * )
 *
 */
class CreateProjectSolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $topic = $this->getTopic();

        return Gate::allows('createOwn', ProjectSolution::class)
            && Gate::allows('attend', $topic)
            && $topic->topicable_type === Project::class;
    }

    public function rules(): array
    {
        return [
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'file' => ['required', 'file'],
        ];
    }

    public function getTopic(): Topic
    {
        return Topic::findOrFail($this->get('topic_id'));
    }

    public function getCreateProjectSolutionDto(): CreateProjectSolutionDto
    {
        return CreateProjectSolutionDto::instantiateFromRequest($this);
    }
}
