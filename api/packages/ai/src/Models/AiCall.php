<?php

namespace Ulams\Ai\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One HTTP call to a model (or a replay): tokens, cache tokens, cost in micro-USD, latency.
 *
 * @property string $id
 * @property string $task
 * @property int $cost_micro_usd
 */
class AiCall extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'ai_calls';

    protected $guarded = [];

    protected $casts = [
        'prompt_version' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cache_creation_tokens' => 'integer',
        'cache_read_tokens' => 'integer',
        'cost_micro_usd' => 'integer',
        'latency_ms' => 'integer',
        'attempt' => 'integer',
        'user_id' => 'integer',
    ];

    public function scopeForSubject(Builder $query, string $type, string $id): Builder
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }
}
