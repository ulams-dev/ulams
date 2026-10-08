<?php

namespace EscolaLms\Vouchers\Services\Contracts;

use EscolaLms\Cart\Services\Contracts\CartManagerContract as BaseCartManagerContract;
use EscolaLms\Vouchers\Models\CartItem;
use EscolaLms\Vouchers\Models\Coupon;

interface CartManagerContract extends BaseCartManagerContract
{
    public function setCoupon(?Coupon $coupon): self;
    public function getCoupon(): ?Coupon;
    public function removeCoupon(): self;

    public function additionalDiscount(): int;
    public function totalPreAdditionalDiscount(): int;
    public function discountForItem(CartItem $item): int;
}
