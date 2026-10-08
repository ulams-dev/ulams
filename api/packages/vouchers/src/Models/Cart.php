<?php

namespace EscolaLms\Vouchers\Models;

use EscolaLms\Cart\Models\Cart as BaseCart;
use EscolaLms\Vouchers\Services\CartManager;
use EscolaLms\Vouchers\Services\Contracts\ShopServiceContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * EscolaLms\Vouchers\Models\Cart
 *
 * @property int $id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $coupon_id
 * @property-read \EscolaLms\Vouchers\Models\Coupon|null $coupon
 * @property-read int $additional_discount
 * @property-read CartManager $cart_manager
 * @property-read int $subtotal
 * @property-read int $tax
 * @property-read int $total
 * @property-read int $total_pre_discount
 * @property-read int $total_with_tax
 * @property-read int $cart_discount
 * @property-read \Treestoneit\ShoppingCart\Models\CartItemCollection|\EscolaLms\Vouchers\Models\CartItem[] $items
 * @property-read int|null $items_count
 * @property-read \EscolaLms\Cart\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|Cart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Cart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Cart query()
 * @method static \Illuminate\Database\Eloquent\Builder|Cart whereCouponId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cart whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cart whereUserId($value)
 * @mixin \Eloquent
 */
class Cart extends BaseCart
{
    /** @var array<int, string> */
    protected $guarded = ['id', 'cartManager'];

    /**
     * @return BelongsTo<Coupon, self>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<CartItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function getCartManagerAttribute(): CartManager
    {
        return app(ShopServiceContract::class)->cartManagerForCart($this);
    }

    public function getAdditionalDiscountAttribute(): int
    {
        return $this->cartManager->additionalDiscount();
    }

    public function getTotalPreDiscountAttribute(): int
    {
        return $this->cartManager->totalPreAdditionalDiscount();
    }

    public function getCartDiscountAttribute(): int
    {
        return $this->items->sum('discount');
    }
}
