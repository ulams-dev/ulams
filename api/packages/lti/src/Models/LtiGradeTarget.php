<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tool side: the platform's AGS endpoint for one learner in one course, from the launch.
 *
 * @property int $id
 * @property int $lti_platform_id
 * @property int $user_id
 * @property int $course_id
 * @property string $sub
 * @property ?string $lineitem
 * @property ?string $lineitems
 * @property array $scopes
 * @property ?Carbon $last_sent_at
 * @property ?string $last_error
 * @property-read LtiPlatform $platform
 */
class LtiGradeTarget extends Model
{
    protected $table = 'lti_grade_targets';

    protected $fillable = [
        'lti_platform_id', 'user_id', 'course_id', 'sub', 'lineitem', 'lineitems', 'scopes',
        'last_sent_at', 'last_error',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_sent_at' => 'datetime',
    ];

    public function platform(): BelongsTo
    {
        return $this->belongsTo(LtiPlatform::class, 'lti_platform_id');
    }
}
