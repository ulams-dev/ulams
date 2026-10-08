<?php

namespace Ulams\Vouchers\Models;

use Ulams\Cart\Models\Order as BaseOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ulams\Vouchers\Models\Order
 *
 * @property int $id
 * @property int|null $user_id
 * @property int $status
 * @property int $total
 * @property int $subtotal
 * @property int $tax
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $coupon_id
 * @property int $discount
 * @property string|null $client_name
 * @property string|null $client_street
 * @property string|null $client_postal
 * @property string|null $client_city
 * @property string|null $client_country
 * @property string|null $client_company
 * @property string|null $client_taxid
 * @property-read \Ulams\Vouchers\Models\Coupon|null $coupon
 * @property-read int $quantity
 * @property-read string $status_name
 * @property-read \Ulams\Cart\Support\OrderItemCollection|\Ulams\Cart\Models\OrderItem[] $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Payments\Models\Payment[] $payments
 * @property-read int|null $payments_count
 * @property-read \Ulams\Cart\Models\User|null $user
 * @method static \Ulams\Cart\Database\Factories\OrderFactory factory(...$parameters)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order newModelQuery()
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order newQuery()
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order query()
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientCity($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientCompany($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientCountry($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientName($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientPostal($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientStreet($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereClientTaxid($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereCouponId($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereCreatedAt($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereDiscount($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereHasBuyable(string $buyable_type, int $buyable_id)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereHasProduct(int $product_id)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereHasProductable(\Illuminate\Database\Eloquent\Model $productable)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereHasProductableClass(string $productable_type)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereHasProductableClassAndId(string $productable_type, int $productable_id)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereId($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereStatus($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereSubtotal($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereTax($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereTotal($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereUpdatedAt($value)
 * @method static \Ulams\Cart\QueryBuilders\OrderModelQueryBuilder|Order whereUserId($value)
 * @mixin \Eloquent
 */
class Order extends BaseOrder
{
    /**
     * @return BelongsTo<Coupon, self>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
