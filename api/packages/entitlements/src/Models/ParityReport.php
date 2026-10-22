<?php

namespace Ulams\Entitlements\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Outcome of one parity check or backfill run (ADR 0130): are `course_user` and the entitlements in agreement?
 *
 * @property int $id
 * @property string $kind
 * @property string $status
 * @property int $pairs_checked
 * @property int $differences
 * @property int $entitlements_created
 * @property array|null $details
 * @property string|null $snapshot_before
 * @property string|null $snapshot_after
 * @property Carbon $created_at
 */
class ParityReport extends Model
{
    public const KIND_CHECK = 'check';
    public const KIND_BACKFILL = 'backfill';
    public const KIND_DRY_RUN = 'dry_run';

    public const STATUS_PASSED = 'passed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'entitlement_parity_reports';

    protected $guarded = [];

    protected $casts = ['details' => 'array'];
}
