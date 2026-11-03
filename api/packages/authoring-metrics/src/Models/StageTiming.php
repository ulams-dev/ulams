<?php

namespace Ulams\AuthoringMetrics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How long one pipeline stage of a Course Builder run took (wall time, not summed model latency).
 *
 * @property int $id
 * @property string $session_id
 * @property string $run_id
 * @property string $run_kind
 * @property string $stage
 * @property \Carbon\CarbonImmutable $started_at
 * @property \Carbon\CarbonImmutable|null $finished_at
 * @property string|null $seconds decimal, three places
 * @property string $quality exact | coarse | incomplete
 */
class StageTiming extends Model
{
    public const EXACT = 'exact';

    public const COARSE = 'coarse';

    public const INCOMPLETE = 'incomplete';

    public $timestamps = false;

    protected $table = 'authoring_stage_timings';

    protected $guarded = [];

    // the unique key and the seconds figure need the milliseconds
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $casts = ['started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
}
