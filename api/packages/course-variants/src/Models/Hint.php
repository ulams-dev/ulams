<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A suggestion to switch variant, with the reasons. The learner accepts it (a switch) or dismisses it; dismissing
 * stops hints for that family. At most one per learner and family per week.
 *
 * @property int $id
 * @property int $user_id
 * @property string $group_id
 * @property int $course_id the variant the learner was in
 * @property int $suggested_course_id
 * @property string $direction easier | harder
 * @property array $reasons message key + params, rendered in the learner's language
 * @property string $status shown | accepted | dismissed | expired
 */
class Hint extends Model
{
    public const SHOWN = 'shown';
    public const ACCEPTED = 'accepted';
    public const DISMISSED = 'dismissed';
    /** The learner moved on (switched or left the variant it was about) before answering it. */
    public const EXPIRED = 'expired';

    protected $table = 'course_variant_hints';

    protected $guarded = ['id'];

    protected $casts = ['user_id' => 'integer', 'course_id' => 'integer', 'suggested_course_id' => 'integer', 'reasons' => 'array', 'resolved_at' => 'datetime'];
}
