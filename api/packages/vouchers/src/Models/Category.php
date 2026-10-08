<?php

namespace EscolaLms\Vouchers\Models;

use EscolaLms\Cart\Models\Category as BaseCategory;
use EscolaLms\Vouchers\Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * EscolaLms\Vouchers\Models\Category
 *
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property bool $is_active
 * @property int|null $parent_id
 * @property string|null $icon
 * @property string|null $icon_class
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\EscolaLms\Categories\Models\Category[] $children
 * @property-read int|null $children_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\EscolaLms\Vouchers\Models\Coupon[] $coupons
 * @property-read int|null $coupons_count
 * @property-read string $name_with_breadcrumbs
 * @property-read \EscolaLms\Categories\Models\Category|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection|\EscolaLms\Cart\Models\Product[] $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Illuminate\Foundation\Auth\User[] $users
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereIconClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Category whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Category extends BaseCategory
{
    use HasFactory;

    /**
     * @return BelongsToMany<Coupon>
     */
    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'coupons_categories', 'category_id', 'coupon_id')->using(CouponCategory::class);
    }

    protected static function newFactory(): CategoryFactory
    {
        return new CategoryFactory();
    }
}
