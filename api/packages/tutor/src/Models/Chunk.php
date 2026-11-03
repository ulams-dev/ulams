<?php

namespace Ulams\Tutor\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One searchable piece of a course: a lesson block with its cited source text, or a section of a rich
 * text topic. Quiz questions, options, explanations and final tests are never indexed.
 *
 * @property int $id
 * @property int $course_id
 * @property int|null $topic_id
 * @property string|null $element_id
 * @property array|null $fragment_ids
 * @property string $label
 * @property string $text
 * @property string $language
 * @property string $source_signature
 */
class Chunk extends Model
{
    public $timestamps = false;

    protected $table = 'tutor_chunks';

    protected $guarded = [];

    protected $casts = ['fragment_ids' => 'array'];
}
