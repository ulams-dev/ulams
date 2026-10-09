<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Whether one blueprint element still matches its sources.
 *
 * @property string $session_id
 * @property string $element_id
 * @property string $status in_sync | pending | dismissed | source_removed
 * @property string|null $element_type
 * @property string|null $label
 * @property string|null $proposal_id
 * @property array|null $fragment_ids
 * @property bool $answer_check
 */
class ElementStatus extends Model
{
    public $timestamps = false;

    protected $table = 'living_course_element_status';

    protected $guarded = [];

    protected $casts = ['fragment_ids' => 'array', 'since' => 'datetime', 'updated_at' => 'datetime', 'answer_check' => 'boolean'];
}
