<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The proposal for one course element.
 *
 * @property string $id
 * @property string $proposal_id
 * @property string $group_key
 * @property string $element_id
 * @property string $element_type
 * @property string|null $label
 * @property string $kind update | citation_remap | remove | no_change | manual | uncovered
 * @property array|null $change_ids
 * @property array|null $fragment_ids
 * @property string|null $reason
 * @property string $severity
 * @property array|null $before
 * @property array|null $after
 * @property string|null $change_class
 * @property string|null $answer_status
 * @property bool $answer_check
 * @property string $status pending | accepted | rejected | conflict | stale
 * @property array|null $flags
 * @property int $regenerations
 * @property array|null $ai_call_ids
 */
class ProposalItem extends Model
{
    use HasUlids;

    protected $table = 'living_course_proposal_items';

    protected $guarded = [];

    protected $casts = [
        'change_ids' => 'array',
        'fragment_ids' => 'array',
        'before' => 'array',
        'after' => 'array',
        'flags' => 'array',
        'ai_call_ids' => 'array',
        'answer_check' => 'boolean',
        'regenerations' => 'integer',
        'decided_at' => 'datetime',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class, 'proposal_id');
    }
}
