<?php

namespace Ulams\Interactive\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $topic_id
 * @property int $user_id
 * @property ?string $last_step
 * @property float $max_progress
 * @property ?float $score_raw
 * @property ?float $score_max
 * @property ?\Illuminate\Support\Carbon $completed_at
 */
class InteractiveProgress extends Model
{
    public const CREATED_AT = null;

    protected $table = 'interactive_progress';

    protected $fillable = ['topic_id', 'user_id', 'last_step', 'max_progress', 'score_raw', 'score_max', 'completed_at'];

    protected $casts = [
        'max_progress' => 'float',
        'score_raw' => 'float',
        'score_max' => 'float',
        'completed_at' => 'datetime',
    ];
}
