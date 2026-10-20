<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property int $course_id
 * @property string|null $element_key
 * @property string $kind nudge | remediation
 * @property string|null $ref_id
 * @property string $outcome pending | recovered | not_recovered | suppressed
 * @property string|null $detail
 * @property int $window_days
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
class Intervention extends Model
{
    use HasUlids;

    public const NUDGE = 'nudge';
    public const REMEDIATION = 'remediation';
    public const PENDING = 'pending';
    public const RECOVERED = 'recovered';
    public const NOT_RECOVERED = 'not_recovered';
    public const SUPPRESSED = 'suppressed';

    public $timestamps = false;

    protected $table = 'learner_interventions';

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime', 'resolved_at' => 'datetime', 'window_days' => 'integer'];
}
