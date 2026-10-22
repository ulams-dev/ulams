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

    /** A paid line of a Sylius order (`sylius:<channel>:order_item:<id>`). */
    public const SOURCE_ORDER_ITEM = 'order_item';
    /** A seat of a seat package (CM-9). Reserved: nothing writes it yet. */
    public const SOURCE_SEAT = 'seat';
    /** An admin attached the course (the default for anything that writes through `CourseAccessService`). */
    public const SOURCE_ADMIN = 'admin';
    /** A compliance assignment opened the course. */
    public const SOURCE_COMPLIANCE = 'compliance';
    /** An LTI launch enrolled the user. */
    public const SOURCE_LTI = 'lti';
    /** Access through a group (`course_group`). Reserved: group access stays outside `course_user` and is not recorded. */
    public const SOURCE_GROUP_LEGACY = 'group_legacy';
    /** A purchase through the Wellms cart (shadow listener and backfill). */
    public const SOURCE_WELLMS_ORDER = 'wellms_order';
    /** A `course_user` row that no known source explains (backfill, or adopted before a first purchase). */
    public const SOURCE_MIGRATION = 'migration';
    /** A Wellms voucher that granted access. Reserved for the cutover (CM-10). */
    public const SOURCE_VOUCHER_LEGACY = 'voucher_legacy';

    public const SOURCE_TYPES = [
        self::SOURCE_ORDER_ITEM, self::SOURCE_SEAT, self::SOURCE_ADMIN, self::SOURCE_COMPLIANCE, self::SOURCE_LTI,
        self::SOURCE_GROUP_LEGACY, self::SOURCE_WELLMS_ORDER, self::SOURCE_MIGRATION, self::SOURCE_VOUCHER_LEGACY,
    ];

    /** Sources that are written by a person or a process through `CourseAccessService`, not by an order. */
    public const MANUAL_SOURCES = [self::SOURCE_ADMIN, self::SOURCE_COMPLIANCE, self::SOURCE_LTI];

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
