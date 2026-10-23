<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * "A user whose attributes match all conditions is put in this variant" (the same conditions as the enrolment rules of
 * the compliance package). Applied once, when nothing else decided yet: a rule never overrides a learner's own choice
 * or an admin's assignment.
 *
 * @property int $id
 * @property string $group_id
 * @property int $course_id the variant course it assigns
 * @property string $name
 * @property list<array{attribute: string, operator: string, value?: mixed}> $conditions
 * @property bool $active
 * @property int $position
 */
class Rule extends Model
{
    protected $table = 'course_variant_rules';

    protected $guarded = ['id'];

    protected $casts = ['course_id' => 'integer', 'conditions' => 'array', 'active' => 'boolean', 'position' => 'integer'];
}
