<?php

namespace Ulams\Vouchers\Models;

use Ulams\Cart\Models\Product as BaseProduct;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ulams\Vouchers\Models\Product
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int $price
 * @property int|null $price_old
 * @property int $tax_rate
 * @property int $extra_fees
 * @property bool $purchasable
 * @property string|null $teaser_url
 * @property string|null $description
 * @property string|null $poster_url
 * @property string|null $duration
 * @property int|null $limit_per_user
 * @property int|null $limit_total
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Cart\Models\Category[] $categories
 * @property-read int|null $categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Vouchers\Models\Coupon[] $coupons
 * @property-read int|null $coupons_count
 * @property-read bool $buyable_by_user
 * @property-read bool $owned_by_user
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Cart\Models\ProductProductable[] $productables
 * @property-read int|null $productables_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Tags\Models\Tag[] $tags
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Ulams\Cart\Models\User[] $users
 * @property-read int|null $users_count
 * @method static \Ulams\Cart\Database\Factories\ProductFactory factory(...$parameters)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product newModelQuery()
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product newQuery()
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product query()
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereCreatedAt($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereDescription($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereDoesntHaveProductablesNotBuyableByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereDoesntHaveProductablesNotOwnedByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereDuration($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereExtraFees($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductable(\Illuminate\Database\Eloquent\Model $productable)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductableClass(string $productable_type)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductableClassAndId(string $productable_type, int $productable_id)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductablesBuyableByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductablesNotBuyableByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductablesNotOwnedByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereHasProductablesOwnedByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereId($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereLimitPerUser($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereLimitTotal($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereName($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereOwnedByUser(?\Ulams\Core\Models\User $user = null)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product wherePosterUrl($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product wherePrice($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product wherePriceOld($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product wherePurchasable($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereTaxRate($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereTeaserUrl($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereType($value)
 * @method static \Ulams\Cart\QueryBuilders\ProductModelQueryBuilder|Product whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Product extends BaseProduct
{
    /**
     * @return BelongsToMany<Coupon>
     */
    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupons_products', 'product_id', 'coupon_id')->using(CouponProduct::class);
    }
}
