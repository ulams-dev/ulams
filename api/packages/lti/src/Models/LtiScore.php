<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One score posted by a tool. Append-only: the history of a learner's scores is kept.
 *
 * @property int $id
 * @property int $lti_line_item_id
 * @property int $user_id
 * @property ?float $score_given
 * @property ?float $score_maximum
 * @property string $activity_progress
 * @property string $grading_progress
 * @property ?string $comment
 */
class LtiScore extends Model
{
    protected $table = 'lti_scores';

    protected $fillable = [
        'lti_line_item_id', 'user_id', 'score_given', 'score_maximum', 'activity_progress',
        'grading_progress', 'comment', 'timestamp',
    ];

    protected $casts = [
        'score_given' => 'float',
        'score_maximum' => 'float',
        'timestamp' => 'datetime',
    ];

    public function lineItem(): BelongsTo
    {
        return $this->belongsTo(LtiLineItem::class, 'lti_line_item_id');
    }
}
