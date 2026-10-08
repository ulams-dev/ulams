<?php

namespace EscolaLms\Tasks\Http\Resources;

use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="TaskNoteResource",
 *      required={"id", "note", "user", "task_id"},
 *      @OA\Property(
 *          property="id",
 *          description="id",
 *          type="integer"
 *      ),
 *      @OA\Property(
 *          property="note",
 *          description="note",
 *          type="string"
 *      ),
 *     @OA\Property(
 *          property="created_at",
 *          description="created_at",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="user",
 *          ref="#/components/schemas/UserResource"
 *      ),
 *      @OA\Property(
 *          property="task_id",
 *          description="task_id",
 *          type="integer"
 *      ),
 * )
 *
 * @mixin TaskNote
 */
class TaskNoteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'user' => UserResource::make($this->user),
            'task_id' => $this->task_id,
        ];
    }
}
