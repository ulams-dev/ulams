<?php

namespace EscolaLms\Vouchers\Models;

use EscolaLms\Cart\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * EscolaLms\Vouchers\Models\CouponProduct
 *
 * @property int $id
 * @property int $coupon_id
 * @property bool $excluded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $product_id
 * @property-read \EscolaLms\Vouchers\Models\Coupon|null $coupon
 * @property-read Product|null $product
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct whereCouponId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct whereExcluded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponProduct whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CouponProduct extends Pivot
{
    use HasTimestamps;

    /**
     * @var array<int, string>
     */
    public $guarded = ['id'];

    protected $table = 'coupons_products';

    protected $casts = [
        'excluded' => 'bool'
    ];

    /**
     * @return BelongsTo<Coupon, self>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return BelongsTo<Product, self>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
