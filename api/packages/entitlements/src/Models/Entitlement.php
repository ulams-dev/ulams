<?php

namespace Ulams\Entitlements\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One reason a user may open a subject (ADR 0130). `course_user` stays the projection that policies read;
 * this row records what granted it, so revoking one source never removes access another source still grants.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $status
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property string $source_type
 * @property string $source_key
 * @property array|null $source_ref
 * @property Carbon|null $revoked_at
 * @property string|null $revoke_reason
 */
class Entitlement extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    public const SUBJECT_COURSE = 'course';

    public const SOURCE_ORDER_ITEM = 'order_item';
    public const SOURCE_MIGRATION = 'migration';

    protected $table = 'entitlements';

    protected $guarded = [];

    protected $casts = [
        'source_ref' => 'array',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** Active and inside its validity window right now. */
    public function scopeInForce(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(fn (Builder $q) => $q->whereNull('valid_until')->orWhere('valid_until', '>', now()));
    }
}
