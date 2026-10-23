<?php

namespace Ulams\LivingCourse\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One update proposal: everything that follows from one source revision, reviewed as one diff.
 *
 * @property string $id
 * @property int $number
 * @property string $kind source | insights (ADR 0181) | style (ADR 0196)
 * @property array|null $evidence aggregated learner evidence of an insights proposal
 * @property string $session_id
 * @property string|null $source_id
 * @property string|null $from_revision_id
 * @property string|null $to_revision_id
 * @property string|null $base_version_id
 * @property string|null $result_version_id
 * @property string|null $run_id
 * @property string $status
 * @property string $trigger
 * @property array|null $counts
 * @property int $estimated_cost_micro_usd
 * @property int $cost_micro_usd
 * @property string|null $learner_note
 * @property string|null $error
 */
class Proposal extends Model
{
    use HasUlids;

    /** Statuses of a proposal that still waits for the author. */
    public const OPEN = ['analysing', 'ready', 'awaiting_analysis', 'budget_blocked', 'failed'];

    public const KIND_SOURCE = 'source';

    public const KIND_INSIGHTS = 'insights';

    /** A restyle after a style profile changed (ADR 0196): like insights it has no source revision. */
    public const KIND_STYLE = 'style';

    public const SUBJECT_TYPE = 'living_course_proposal';

    protected $table = 'living_course_proposals';

    protected $guarded = [];

    protected $casts = [
        'counts' => 'array',
        'evidence' => 'array',
        'number' => 'integer',
        'estimated_cost_micro_usd' => 'integer',
        'cost_micro_usd' => 'integer',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProposalItem::class, 'proposal_id')->orderBy('created_at')->orderBy('id');
    }

    public function fromRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'from_revision_id');
    }

    public function toRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'to_revision_id');
    }

    public function isInsights(): bool
    {
        return $this->kind === self::KIND_INSIGHTS;
    }

    public function isStyle(): bool
    {
        return $this->kind === self::KIND_STYLE;
    }

    /** No source revision on either side: an improvement from learner insights or a restyle. They read the live fragments and promote nothing. */
    public function isSourceless(): bool
    {
        return $this->isInsights() || $this->isStyle();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** @return array{type:string,id:string} */
    public function subject(): array
    {
        return ['type' => self::SUBJECT_TYPE, 'id' => $this->id];
    }
}
