<?php

namespace Ulams\KnowledgeExport\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One knowledge pack run. `scope` course: `manifest.json` + `chunks.jsonl`; tenant: `index.json` plus one
 * course pack per published course under `courses/{id}/`.
 *
 * @property string $id
 * @property string $scope course | tenant
 * @property int|null $course_id
 * @property string $status queued | running | ready | failed
 * @property string $trigger manual | auto
 * @property int|null $requested_by
 * @property string|null $pack_hash
 * @property int $courses
 * @property int $chunks
 * @property int $uncited
 * @property string|null $directory
 * @property string|null $error
 */
class KnowledgeExport extends Model
{
    use HasUlids;

    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const COURSE = 'course';

    public const TENANT = 'tenant';

    protected $table = 'knowledge_exports';

    protected $guarded = [];

    protected $casts = [
        'course_id' => 'integer',
        'requested_by' => 'integer',
        'courses' => 'integer',
        'chunks' => 'integer',
        'uncited' => 'integer',
        'finished_at' => 'datetime',
    ];
}
