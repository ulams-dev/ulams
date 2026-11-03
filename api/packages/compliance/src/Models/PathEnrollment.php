<?php

namespace Ulams\Compliance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Ulams\Core\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property int $path_id
 * @property string $source manual or rule
 * @property int|null $rule_id
 * @property \Illuminate\Support\Carbon $enrolled_at
 * @property \Illuminate\Support\Carbon|null $due_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
class PathEnrollment extends Model
{
    protected $table = 'compliance_path_enrollments';

    protected $guarded = ['id'];

    protected $casts = ['enrolled_at' => 'datetime', 'due_at' => 'datetime', 'completed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'path_id');
    }
}
