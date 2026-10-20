<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $remediation_id
 * @property string $element_key
 * @property array $reasons copied from the status at the time
 * @property bool $new_generation this assignment caused a new generation (counts against the daily cap)
 * @property \Illuminate\Support\Carbon|null $shown_at
 * @property string|null $feedback helpful | not_helpful
 * @property \Illuminate\Support\Carbon|null $hidden_at
 */
class RemediationAssignment extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $table = 'learner_remediation_assignments';

    protected $guarded = [];

    protected $casts = ['reasons' => 'array', 'new_generation' => 'boolean', 'created_at' => 'datetime', 'shown_at' => 'datetime', 'hidden_at' => 'datetime'];

    public function remediation(): BelongsTo
    {
        return $this->belongsTo(Remediation::class, 'remediation_id');
    }
}
