<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A family of courses that are variants of one course.
 *
 * @property string $id
 * @property int $default_course_id the base course: the one the catalogue shows
 * @property string|null $default_label how the default course is named in the picker
 * @property string|null $default_level beginner | intermediate | expert
 * @property string|null $default_role
 * @property int|null $pinned_course_id mandatory training counts only this course of the family when set
 * @property bool $lock_assigned a variant assigned by an admin or a rule cannot be changed by the learner
 * @property bool $placement_enabled
 * @property bool $hints_enabled
 */
class VariantGroup extends Model
{
    use HasUlids;

    protected $table = 'course_variant_groups';

    protected $guarded = [];

    protected $casts = [
        'default_course_id' => 'integer',
        'pinned_course_id' => 'integer',
        'lock_assigned' => 'boolean',
        'placement_enabled' => 'boolean',
        'hints_enabled' => 'boolean',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(VariantGroupMember::class, 'group_id')->orderBy('position')->orderBy('id');
    }

    /** @return int[] the default course and every member course */
    public function courseIds(): array
    {
        return array_values(array_unique([(int) $this->default_course_id, ...$this->members->pluck('course_id')->map(fn ($id) => (int) $id)->all()]));
    }
}
