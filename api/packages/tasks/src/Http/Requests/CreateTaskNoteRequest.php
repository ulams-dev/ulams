<?php

namespace EscolaLms\Tasks\Http\Requests;

use EscolaLms\Tasks\Dtos\CreateTaskNoteDto;
use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Support\Facades\Gate;


/**
 * @OA\Schema(
 *      schema="TaskNoteCreateRequest",
 *      required={"note", "task_id"},
 *      @OA\Property(
 *          property="note",
 *          description="note",
 *          type="string"
 *      ),
 *      @OA\Property(
 *          property="task_id",
 *          description="task_id",
 *          type="integer"
 *      ),
 * )
 *
 */
class CreateTaskNoteRequest extends TaskNoteRequest
{
    public function authorize(): bool
    {
        $task = $this->getTask($this->input('task_id'));

        return Gate::allows('createOwn', TaskNote::class) && ($this->isCreator($task) || $this->isAssigned($task));
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'note' => ['required', 'string'],
        ];
    }

    public function toDto(): CreateTaskNoteDto
    {
        return CreateTaskNoteDto::instantiateFromRequest($this);
    }
}
