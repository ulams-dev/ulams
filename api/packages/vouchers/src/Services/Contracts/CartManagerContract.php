<?php

namespace Ulams\Vouchers\Services\Contracts;

use Ulams\Cart\Services\Contracts\CartManagerContract as BaseCartManagerContract;
use Ulams\Vouchers\Models\CartItem;
use Ulams\Vouchers\Models\Coupon;

interface CartManagerContract extends BaseCartManagerContract
{
    public function setCoupon(?Coupon $coupon): self;
    public function getCoupon(): ?Coupon;
    public function removeCoupon(): self;

    public function additionalDiscount(): int;
    public function totalPreAdditionalDiscount(): int;
    public function discountForItem(CartItem $item): int;
}
