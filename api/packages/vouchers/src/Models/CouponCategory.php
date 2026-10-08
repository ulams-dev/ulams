<?php

namespace EscolaLms\Vouchers\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * EscolaLms\Vouchers\Models\CouponCategory
 *
 * @property int $id
 * @property int $coupon_id
 * @property int $category_id
 * @property bool $excluded
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \EscolaLms\Vouchers\Models\Category|null $category
 * @property-read \EscolaLms\Vouchers\Models\Coupon|null $coupon
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory whereCouponId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory whereExcluded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CouponCategory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CouponCategory extends Pivot
{
    use HasTimestamps;

    /**
     * @var array<int, string>
     */
    public $guarded = ['id'];

    protected $table = 'coupons_categories';

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
     * @return BelongsTo<Category, self>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
