<?php

namespace EscolaLms\Tasks\Http\Resources;

use EscolaLms\Tasks\Models\Task;
use EscolaLms\Tasks\Models\TaskNote;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *      schema="TaskDetailsResource",
 *      required={"title", "user", "created_by"},
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
 *          property="related",
 *          description="realted",
 *          type="object"
 *      ),
 *      @OA\Property(
 *          property="user",
 *          ref="#/components/schemas/UserResource"
 *      ),
 *      @OA\Property(
 *          property="created_by",
 *          ref="#/components/schemas/UserResource"
 *      ),
 *      @OA\Property(
 *          property="due_date",
 *          description="due_date",
 *          type="string",
 *          format="date-time"
 *      ),
 *     @OA\Property(
 *          property="completed_at",
 *          description="completed_at",
 *          type="string",
 *          format="date-time"
 *      ),
 *      @OA\Property(
 *          property="notes",
 *          type="array",
 *          @OA\Items(ref="#/components/schemas/TaskNoteResource")
 *      ),
 * )
 *
 * @mixin Task
 */
class TaskDetailsResource extends JsonResource
{
    public function toArray($request): array
    {
        $resourceClass = ('\EscolaLms\Tasks\Http\Resources\\' . class_basename($this->related_type) . 'Resource');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'user' => UserResource::make($this->user),
            'created_by' => UserResource::make($this->createdBy),
            'due_date' => $this->due_date,
            'completed_at' => $this->completed_at,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'related' => class_exists($resourceClass) && class_exists($this->related_type) && $this->related ? $resourceClass::make($this->related) : null,
            'notes' => TaskNoteResource::collection($this->taskNotes)
        ];
    }
}
