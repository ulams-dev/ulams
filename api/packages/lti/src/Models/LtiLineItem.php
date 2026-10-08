<?php

namespace Ulams\Lti\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * AGS line item, scoped to one tool and one course (the LTI context).
 *
 * @property int $id
 * @property int $lti_tool_id
 * @property int $course_id
 * @property ?int $topic_id
 * @property string $label
 * @property float $score_maximum
 * @property ?string $resource_id
 * @property ?string $tag
 */
class LtiLineItem extends Model
{
    protected $table = 'lti_line_items';

    protected $fillable = [
        'lti_tool_id', 'course_id', 'topic_id', 'label', 'score_maximum', 'resource_id', 'tag',
        'start_date_time', 'end_date_time',
    ];

    protected $casts = [
        'score_maximum' => 'float',
        'start_date_time' => 'datetime',
        'end_date_time' => 'datetime',
    ];

    public function tool(): BelongsTo
    {
        return $this->belongsTo(LtiTool::class, 'lti_tool_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(LtiScore::class, 'lti_line_item_id');
    }
}
