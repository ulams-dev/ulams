<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The outcome of one nightly catalogue check (`catalogue`) or order sweep (`orders`).
 *
 * @property int $id
 * @property string $kind
 * @property string $provider
 * @property int $drift items that were out of step
 * @property int $repaired items put right in the same run
 * @property array|null $details
 * @property \Illuminate\Support\Carbon $created_at
 */
class DriftReport extends Model
{
    public const CATALOGUE = 'catalogue';
    public const ORDERS = 'orders';

    protected $table = 'commerce_drift_reports';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['drift' => 'integer', 'repaired' => 'integer', 'details' => 'array', 'created_at' => 'datetime'];
}
