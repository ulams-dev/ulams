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
 */
class VariantGroup extends Model
{
    use HasUlids;

    protected $table = 'course_variant_groups';

    protected $guarded = [];

    protected $casts = ['default_course_id' => 'integer'];

    public function members(): HasMany
    {
        return $this->hasMany(VariantGroupMember::class, 'group_id')->orderBy('position')->orderBy('id');
    }
}
