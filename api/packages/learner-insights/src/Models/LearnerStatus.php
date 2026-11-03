<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Model;
use Ulams\LearnerInsights\Scoring\Reason;

/**
 * Current status of a learner in a course (`element_key` = '') or of one element.
 *
 * @property int $user_id
 * @property int $course_id
 * @property string $element_key
 * @property string $status
 * @property array $reasons
 * @property string $scorer
 * @property string $scorer_version
 * @property \Illuminate\Support\Carbon $since
 * @property \Illuminate\Support\Carbon $evaluated_at
 */
class LearnerStatus extends Model
{
    public $timestamps = false;

    protected $table = 'learner_statuses';

    protected $guarded = [];

    protected $casts = ['reasons' => 'array', 'since' => 'datetime', 'evaluated_at' => 'datetime'];

    /** @return string[] the reasons in the learner's words */
    public function messages(?string $locale = null): array
    {
        return array_map(fn (array $r) => Reason::fromArray($r)->message($locale), $this->reasons);
    }
}
