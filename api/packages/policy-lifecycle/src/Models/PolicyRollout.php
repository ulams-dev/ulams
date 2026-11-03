<?php

namespace Ulams\PolicyLifecycle\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One department's share of a policy change: the Living Course proposal that updates its course and where it stands.
 *
 * @property string $id
 * @property string $policy_version_id
 * @property string $variant_id
 * @property string|null $proposal_id
 * @property string $status pending | no_change | ready | applying | applied | failed | rejected
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $rolled_out_at
 */
class PolicyRollout extends Model
{
    use HasUlids;

    public const PENDING = 'pending';
    public const NO_CHANGE = 'no_change';
    public const READY = 'ready';
    public const APPLYING = 'applying';
    public const APPLIED = 'applied';
    public const FAILED = 'failed';
    public const REJECTED = 'rejected';

    protected $table = 'policy_rollouts';

    protected $guarded = [];

    protected $casts = ['rolled_out_at' => 'datetime'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(PolicyVersion::class, 'policy_version_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(PolicyVariant::class, 'variant_id');
    }
}
