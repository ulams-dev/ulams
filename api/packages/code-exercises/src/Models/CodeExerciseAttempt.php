<?php

namespace Ulams\CodeExercises\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One run of an exercise's tests reported by the learner's browser. `verified` is false for every
 * practice attempt: the browser is untrusted, so these never count for certificates or compliance.
 *
 * @property int $id
 * @property int $user_id
 * @property int $topic_id
 * @property int $attempt_no
 * @property string $mode
 * @property bool $verified
 * @property ?string $files_hash
 * @property int $passed
 * @property int $total
 * @property int $score
 * @property array<int, array{name: string, status: string, message: ?string}> $results
 * @property ?string $runner
 * @property ?int $duration_ms
 * @property ?Carbon $created_at
 */
class CodeExerciseAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'code_exercise_attempts';

    protected $fillable = ['user_id', 'topic_id', 'attempt_no', 'mode', 'verified', 'files_hash', 'passed', 'total', 'score', 'results', 'runner', 'duration_ms'];

    protected $casts = [
        'verified' => 'boolean',
        'passed' => 'integer',
        'total' => 'integer',
        'score' => 'integer',
        'attempt_no' => 'integer',
        'results' => 'array',
        'duration_ms' => 'integer',
    ];
}
