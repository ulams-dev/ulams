<?php

namespace EscolaLms\Vouchers\Models;

use EscolaLms\Cart\Models\CartItem as BaseCartItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * EscolaLms\Vouchers\Models\CartItem
 *
 * @property int $id
 * @property int $cart_id
 * @property string $buyable_type
 * @property int $buyable_id
 * @property int $quantity
 * @property array|null $options
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $buyable
 * @property-read \EscolaLms\Vouchers\Models\Cart $cart
 * @property-read mixed $description
 * @property-read int $discount
 * @property-read int $discount_subtotal
 * @property-read float|int $extra_fees
 * @property-read string $identifier
 * @property-read mixed $price
 * @property-read mixed $basePrice
 * @property-read mixed $subtotal
 * @property-read int $tax
 * @property-read int $tax_rate
 * @property-read mixed $total
 * @property-read int $total_with_tax
 * @method static \Treestoneit\ShoppingCart\Models\CartItemCollection|static[] all($columns = ['*'])
 * @method static \Treestoneit\ShoppingCart\Models\CartItemCollection|static[] get($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem query()
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereBuyableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereBuyableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereCartId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CartItem whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CartItem extends BaseCartItem
{
    /**
     * @return BelongsTo<Cart, self>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function getSubtotalAttribute(): int
    {
        return parent::getSubtotalAttribute() - $this->discountSubtotal;
    }

    public function getPriceAttribute(): float|null
    {
        return parent::getPriceAttribute() - $this->discount;
    }

    public function getBasePriceAttribute(): float|null
    {
        return parent::getPriceAttribute();
    }

    public function getDiscountSubtotalAttribute(): int
    {
        return $this->discount * $this->attributes['quantity'];
    }

    public function getDiscountAttribute(): int
    {
        return $this->cart->cartManager->discountForItem($this);
    }
}
