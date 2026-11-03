<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One learner signal. Append-only: rows are inserted by SignalRecorder and removed only by the
 * retention and erasure services; there is no update path (a PostgreSQL trigger rejects UPDATE).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $course_id
 * @property int|null $topic_id
 * @property string|null $element_key blueprint element ULID, else `topic:<id>` or `question:<id>`
 * @property string|null $element_id
 * @property string|null $session_id
 * @property string $type
 * @property float|null $value
 * @property array|null $data
 * @property string $source
 * @property string $source_ref
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon $recorded_at
 */
class Signal extends Model
{
    public $timestamps = false;

    protected $table = 'learner_signals';

    protected $guarded = [];

    protected $casts = [
        'value' => 'float',
        'data' => 'array',
        'occurred_at' => 'datetime',
        'recorded_at' => 'datetime',
    ];
}
