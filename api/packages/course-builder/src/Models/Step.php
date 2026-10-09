<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resumable unit of a run (`lesson:<element id>`, `quiz:<element id>`, `final_test`,
 * `grounding:<element id>`, `metadata`). A `done` step is skipped when the run resumes.
 *
 * @property string $id
 * @property string $run_id
 * @property string $key
 * @property string $stage
 * @property string $status pending | running | done | failed
 * @property int $attempts
 * @property array|null $output
 * @property string|null $error
 * @property int $cost_micro_usd
 */
class Step extends Model
{
    use HasUlids;

    protected $table = 'course_builder_steps';

    protected $guarded = [];

    protected $casts = ['output' => 'array', 'attempts' => 'integer', 'cost_micro_usd' => 'integer'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class, 'run_id');
    }

    public function elementId(): ?string
    {
        return str_contains($this->key, ':') ? substr($this->key, strpos($this->key, ':') + 1) : null;
    }
}
