<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Which product of which provider sells which sellable (one per sellable and provider).
 *
 * @property int $id
 * @property string $sellable_type
 * @property int $sellable_id
 * @property string $provider
 * @property string $external_id
 * @property int $amount_minor
 * @property string $currency
 * @property bool $active
 * @property string|null $variant_code
 * @property string|null $channel_code
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property string|null $sync_error
 * @property string|null $payload_hash
 */
class ProductLink extends Model
{
    protected $table = 'commerce_product_links';

    protected $guarded = ['id'];

    protected $casts = ['sellable_id' => 'integer', 'amount_minor' => 'integer', 'active' => 'boolean', 'synced_at' => 'datetime'];
}
