<?php

namespace Ulams\LearnerInsights\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * A cached piece of extra help for one struggle pattern of one element version. Holds no learner
 * data: any learner with the same pattern gets the same row.
 *
 * @property string $id
 * @property int $course_id
 * @property int|null $topic_id
 * @property string $session_id
 * @property string $element_id
 * @property string|null $applied_version_id
 * @property string $pattern_key
 * @property string $kind explanation | worked_example | practice | elaboration
 * @property string $language
 * @property array|null $document
 * @property array|null $citations fragment ids
 * @property string $status generating | ready | failed | retired
 * @property array|null $flags
 * @property array|null $ai_call_ids
 */
class Remediation extends Model
{
    use HasUlids;

    public const GENERATING = 'generating';
    public const READY = 'ready';
    public const FAILED = 'failed';
    public const RETIRED = 'retired';
    public const SUBJECT_TYPE = 'learner_remediation';

    public $timestamps = false;

    protected $table = 'learner_remediations';

    protected $guarded = [];

    protected $casts = ['document' => 'array', 'citations' => 'array', 'flags' => 'array', 'ai_call_ids' => 'array', 'created_at' => 'datetime', 'generated_at' => 'datetime'];
}
