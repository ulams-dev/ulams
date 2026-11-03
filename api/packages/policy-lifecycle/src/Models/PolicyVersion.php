<?php

namespace Ulams\PolicyLifecycle\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded state of a policy.
 *
 * @property string $id
 * @property string $policy_id
 * @property int $number
 * @property string $status draft | in_review | applying | approved | rejected | superseded
 * @property string $change_class initial | minor | major
 * @property string|null $summary
 * @property string $file_name
 * @property string $file_path path on the Course Builder private disk
 * @property string $file_sha256
 * @property int $file_size
 * @property int|null $submitted_by
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $effective_at
 * @property string|null $suggested_class what the proposals suggested
 * @property string|null $class_reason why the owner chose another class
 * @property int|null $rejected_by
 * @property string|null $reject_reason
 * @property \Illuminate\Support\Carbon|null $rolled_out_at
 */
class PolicyVersion extends Model
{
    use HasUlids;

    public const DRAFT = 'draft';
    public const IN_REVIEW = 'in_review';
    /** Approved by the owner; the department courses are being updated and the rollout has not run yet. */
    public const APPLYING = 'applying';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const SUPERSEDED = 'superseded';

    public const INITIAL = 'initial';
    public const MINOR = 'minor';
    public const MAJOR = 'major';

    protected $table = 'policy_versions';

    protected $guarded = [];

    protected $casts = [
        'number' => 'integer',
        'file_size' => 'integer',
        'submitted_by' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'effective_at' => 'datetime',
        'rolled_out_at' => 'datetime',
        'rejected_by' => 'integer',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(Policy::class, 'policy_id');
    }
}
