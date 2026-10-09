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
 */
class ProductLink extends Model
{
    protected $table = 'commerce_product_links';

    protected $guarded = ['id'];

    protected $casts = ['sellable_id' => 'integer', 'amount_minor' => 'integer', 'active' => 'boolean'];
}
