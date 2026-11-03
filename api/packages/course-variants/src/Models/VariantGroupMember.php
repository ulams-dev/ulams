<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A variant course of a family.
 *
 * @property int $id
 * @property string $group_id
 * @property int $course_id
 * @property string $variant_key
 * @property string $label shown to learners, e.g. "Expert, developer"
 * @property string|null $level beginner | intermediate | expert
 * @property string|null $role
 * @property int $position
 * @property \Illuminate\Support\Carbon|null $retired_at not offered to new learners
 */
class VariantGroupMember extends Model
{
    protected $table = 'course_variant_group_members';

    protected $guarded = [];

    protected $casts = ['course_id' => 'integer', 'position' => 'integer', 'retired_at' => 'datetime'];
}
