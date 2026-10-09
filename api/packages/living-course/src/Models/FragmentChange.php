<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One fragment-level change between two revisions.
 *
 * @property int $id
 * @property string $from_revision_id
 * @property string $to_revision_id
 * @property string $kind changed | moved | removed | added
 * @property string|null $old_fragment_id
 * @property string|null $new_fragment_id
 * @property string $magnitude trivial | minor | substantive
 * @property float $similarity
 * @property array|null $signals
 * @property array|null $word_diff
 */
class FragmentChange extends Model
{
    public $timestamps = false;

    protected $table = 'living_course_fragment_changes';

    protected $guarded = [];

    protected $casts = ['signals' => 'array', 'word_diff' => 'array', 'similarity' => 'float'];
}
