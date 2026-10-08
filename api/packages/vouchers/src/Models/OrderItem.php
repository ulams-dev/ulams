<?php

namespace EscolaLms\Vouchers\Models;

use EscolaLms\Cart\Models\OrderItem as BaseOrderItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EscolaLms\Vouchers\Models\OrderItem
 *
 * @property int $id
 * @property int $order_id
 * @property string $buyable_type
 * @property int $buyable_id
 * @property int $quantity
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $price
 * @property int $extra_fees
 * @property int $tax_rate
 * @property string|null $name
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $buyable
 * @property-read string|null $description
 * @property-read int $subtotal
 * @property-read int $tax
 * @property-read int $total
 * @property-read int $total_with_tax
 * @property-read \EscolaLms\Vouchers\Models\Order $order
 * @method static \EscolaLms\Cart\Support\OrderItemCollection|static[] all($columns = ['*'])
 * @method static \EscolaLms\Cart\Support\OrderItemCollection|static[] get($columns = ['*'])
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem newModelQuery()
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem newQuery()
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem query()
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereBuyableClassAndId(string $buyable_type, int $buyable_id)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereBuyableId($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereBuyableType($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereCreatedAt($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereExtraFees($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereHasProductable(\Illuminate\Database\Eloquent\Model $productable)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereHasProductableClassAndId(string $productable_type, int $productable_id)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereId($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereName($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereOptions($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereOrderId($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem wherePrice($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereProductId(int $product_id)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereQuantity($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereTaxRate($value)
 * @method static \EscolaLms\Cart\QueryBuilders\OrderItemModelQueryBuilder|OrderItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OrderItem extends BaseOrderItem
{
    /**
     * @return BelongsTo<Order, self>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
