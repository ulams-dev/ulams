<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A learner's placement result: scores per level and the variant it suggests. Advisory: it decides nothing by itself,
 * the learner confirms or picks another variant. The answers are not kept.
 *
 * @property string $id
 * @property int $user_id
 * @property string $group_id
 * @property array $scores level => {correct, total}
 * @property int|null $suggested_course_id
 * @property array $reason {key, params}
 */
class PlacementAttempt extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'course_variant_placement_attempts';

    protected $guarded = [];

    protected $casts = ['user_id' => 'integer', 'scores' => 'array', 'suggested_course_id' => 'integer', 'reason' => 'array', 'created_at' => 'datetime'];
}
