<?php

namespace Ulams\PolicyLifecycle\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a policy change did to one person: the certificate it revoked and the exam or course it assigned.
 *
 * @property int $id
 * @property string $rollout_id
 * @property int $user_id
 * @property string $kind exam | course
 * @property int|null $assignment_id
 * @property int|null $revoked_certificate_id
 * @property \Illuminate\Support\Carbon|null $notified_at
 */
class PolicyReassignment extends Model
{
    public const EXAM = 'exam';
    public const COURSE = 'course';

    protected $table = 'policy_reassignments';

    protected $guarded = ['id'];

    protected $casts = ['user_id' => 'integer', 'assignment_id' => 'integer', 'revoked_certificate_id' => 'integer', 'notified_at' => 'datetime'];
}
