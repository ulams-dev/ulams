<?php

namespace Ulams\Vouchers\Services;

use Ulams\Cart\Models\Cart as BaseCart;
use Ulams\Cart\Services\CartManager as BaseCartManager;
use Ulams\Vouchers\Exceptions\CouponInactiveException;
use Ulams\Vouchers\Exceptions\CouponNotApplicableException;
use Ulams\Vouchers\Models\Cart;
use Ulams\Vouchers\Models\CartItem;
use Ulams\Vouchers\Models\Coupon;
use Ulams\Vouchers\Services\Contracts\CartManagerContract;
use Ulams\Vouchers\Services\Contracts\CouponServiceContract;
use Ulams\Vouchers\Strategies\Contracts\DiscountStrategyContract;

class CartManager extends BaseCartManager implements CartManagerContract
{
    public function __construct(BaseCart $cart)
    {
        $cart = $cart instanceof Cart ? $cart : Cart::find($cart->getKey());
        parent::__construct($cart);
    }

    public function getModel(): Cart
    {
        return $this->cart instanceof Cart ? $this->cart : Cart::find($this->cart->getKey());
    }

    public function total(): int
    {
        return $this->totalPreAdditionalDiscount() - $this->additionalDiscount();
    }

    public function totalPreAdditionalDiscount(): int
    {
        return parent::total();
    }

    public function additionalDiscount(): int
    {
        return $this->getDiscountStrategy()->calculateAdditionalDiscount($this->getModel());
    }

    public function discountForItem(CartItem $item): int
    {
        return $this->getDiscountStrategy()->calculateDiscountForItem($this->getModel(), $item);
    }

    public function removeCoupon(): self
    {
        $this->cart->refresh();
        // @phpstan-ignore-next-line
        $this->cart->coupon_id = null;
        $this->cart->save();
        $this->cart->refresh();
        return $this;
    }

    public function setCoupon(?Coupon $coupon): self
    {
        // @phpstan-ignore-next-line
        if (!is_null($coupon) && !app(CouponServiceContract::class)->couponCanBeUsedOnCart($coupon, $this->cart)) {
            if (!app(CouponServiceContract::class)->couponIsActive($coupon)) {
                throw new CouponInactiveException();
            }
            throw new CouponNotApplicableException();
        }

        $this->cart->refresh();
        // @phpstan-ignore-next-line
        $this->cart->coupon_id = $coupon->getKey();
        $this->cart->save();
        $this->cart->refresh();
        return $this;
    }

    public function getCoupon(): ?Coupon
    {
        return $this->getModel()->coupon;
    }

    private function getDiscountStrategy(): DiscountStrategyContract
    {
        return app(CouponServiceContract::class)->getDiscountStrategyForCoupon($this->getModel()->coupon);
    }
}
