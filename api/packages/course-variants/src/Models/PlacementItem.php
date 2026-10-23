<?php

namespace Ulams\CourseVariants\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One question of the optional placement check: an existing, cited quiz question of one level of the family. Scored by
 * code; never sent to a model.
 *
 * @property int $id
 * @property string $group_id
 * @property string $level beginner | intermediate | expert
 * @property string $element_id the blueprint question it was taken from
 * @property int $source_course_id
 * @property array $question type, stem, options (id, text, correct), explanation, citations, objectiveIds
 * @property int $position
 */
class PlacementItem extends Model
{
    protected $table = 'course_variant_placement_items';

    protected $guarded = ['id'];

    protected $casts = ['source_course_id' => 'integer', 'question' => 'array', 'position' => 'integer'];
}
