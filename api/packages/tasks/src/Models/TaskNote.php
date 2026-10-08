<?php

namespace EscolaLms\Tasks\Models;

use EscolaLms\Tasks\Database\Factories\TaskNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 * Class TaskNote
 *
 * @package EscolaLms\Tasks\Models
 *
 * @property int $id
 * @property string $note
 * @property int $user_id
 * @property int $task_id
 * @property Carbon $created_at
 * @property Carbon $update_at
 *
 * @property User $user
 * @property Task $task
 *
 */
class TaskNote extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function notifyTo(): User
    {
        if ($this->user_id === $this->task->user_id) {
            return $this->task->createdBy;
        }

        return $this->task->user;
    }

    protected static function newFactory(): TaskNoteFactory
    {
        return TaskNoteFactory::new();
    }
}
