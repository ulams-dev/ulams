<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One AG-UI run: an ingestion, an interview turn, an outline, a generation, a patch or an apply.
 *
 * @property string $id
 * @property string $session_id
 * @property string $kind ingest | interview | outline | generate | patch | apply | action
 * @property string $status queued | running | needs_attention | finished | failed | cancelled
 * @property string|null $stage
 * @property array|null $input
 * @property string|null $error
 * @property int|null $user_id
 */
class Run extends Model
{
    use HasUlids;

    public const ACTIVE = ['queued', 'running', 'needs_attention'];

    protected $table = 'course_builder_runs';

    protected $guarded = [];

    protected $casts = ['input' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(Step::class, 'run_id')->orderBy('created_at');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }
}
