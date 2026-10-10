<?php

namespace Ulams\CourseBuilder\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One critic's verdict on one element in one iteration of the quality loop (ADR 0051).
 *
 * @property string $id
 * @property string $session_id
 * @property string|null $version_id the content version the loop produced
 * @property string $element_id the lesson the critics looked at
 * @property string $critic pedagogy | grounding | mechanics | ux | accessibility | solvability
 * @property int $iteration 0 = the first look, n = after the n-th fix
 * @property string $verdict pass | fail | skipped
 * @property array|null $issues [{elementId, problem}]
 * @property string|null $ai_call_id
 */
class Critique extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const CRITICS = ['pedagogy', 'grounding', 'mechanics', 'ux', 'accessibility', 'solvability'];

    protected $table = 'course_builder_critiques';

    protected $guarded = [];

    protected $casts = ['issues' => 'array', 'iteration' => 'integer'];
}
