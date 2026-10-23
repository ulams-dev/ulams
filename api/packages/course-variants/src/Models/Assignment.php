<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Which variant of a family a learner is in, and why. One current row per learner and family (`is_current` is true on
 * it and null on history, so the unique key allows many superseded rows); a switch supersedes the old row and keeps it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $group_id
 * @property int $course_id
 * @property string $source self | placement | admin | rule | hint
 * @property array|null $reason {key, params}: a message key the learner's app renders in their language (admin, rule, placement, hint); null for the learner's own choice
 * @property int|null $decided_by
 * @property int|null $rule_id
 * @property \Illuminate\Support\Carbon $decided_at
 * @property \Illuminate\Support\Carbon|null $superseded_at
 */
class Assignment extends Model
{
    public const SELF = 'self';
    public const PLACEMENT = 'placement';
    public const ADMIN = 'admin';
    public const RULE = 'rule';
    public const HINT = 'hint';

    public const SOURCES = [self::SELF, self::PLACEMENT, self::ADMIN, self::RULE, self::HINT];
    /** Sources that mean "your organisation decided". */
    public const ORGANISATION = [self::ADMIN, self::RULE];

    protected $table = 'course_variant_assignments';

    protected $guarded = [];

    protected $casts = ['user_id' => 'integer', 'course_id' => 'integer', 'decided_by' => 'integer', 'rule_id' => 'integer', 'reason' => 'array', 'decided_at' => 'datetime', 'superseded_at' => 'datetime', 'is_current' => 'boolean'];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public function byOrganisation(): bool
    {
        return in_array($this->source, self::ORGANISATION, true);
    }
}
