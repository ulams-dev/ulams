<?php

namespace EscolaLms\Tasks\Http\Requests\Admin;

use EscolaLms\Tasks\Dtos\AdminCreateTaskDto;
use EscolaLms\Tasks\Http\Requests\TaskRequest;
use EscolaLms\Tasks\Models\Task;
use Illuminate\Support\Facades\Gate;


/**
 * @OA\Schema(
 *      schema="AdminTaskCreateRequest",
 *      required={"title", "user_id"},
 *      @OA\Property(
 *          property="title",
 *          description="title",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="description",
 *          description="description",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="type",
 *          description="type",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="related_type",
 *          description="related_type",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="related_id",
 *          description="related_id",
 *          type="integer"
 *      ),
 *     @OA\Property(
 *          property="user_id",
 *          description="user_id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="due_date",
 *          description="due_date",
 *          type="string",
 *          format="date-time"
 *      ),
 * )
 *
 */
class AdminCreateTaskRequest extends TaskRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'related_type' => ['nullable', 'string', 'required_with:related_id'],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'due_date' => ['date', 'after_or_equal:now'],
        ];
    }

    public function toDto(): AdminCreateTaskDto
    {
        return AdminCreateTaskDto::instantiateFromRequest($this);
    }
}
