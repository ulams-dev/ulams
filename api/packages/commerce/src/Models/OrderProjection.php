<?php

namespace Ulams\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $external_order_id
 * @property int|null $user_id
 * @property string|null $state
 * @property string|null $payment_state
 * @property int $last_sequence
 */
class OrderProjection extends Model
{
    protected $table = 'commerce_orders';

    protected $guarded = ['id'];

    protected $casts = ['user_id' => 'integer', 'total_minor' => 'integer', 'tax_minor' => 'integer', 'last_sequence' => 'integer', 'synced_at' => 'datetime'];
}
