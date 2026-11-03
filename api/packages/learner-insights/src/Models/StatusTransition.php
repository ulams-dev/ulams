<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only history of status changes.
 *
 * @property int $user_id
 * @property int $course_id
 * @property string $element_key
 * @property string $from
 * @property string $to
 * @property array $reasons
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class StatusTransition extends Model
{
    public $timestamps = false;

    protected $table = 'learner_status_transitions';

    protected $guarded = [];

    protected $casts = ['reasons' => 'array', 'occurred_at' => 'datetime'];
}
