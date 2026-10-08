<?php

namespace EscolaLms\Vouchers\Services\Contracts;

use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Models\User;
use EscolaLms\Vouchers\Dtos\CouponSearchDto;
use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\CartItem;
use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Strategies\Contracts\DiscountStrategyContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CouponServiceContract
{
    /**
     * @return LengthAwarePaginator<Coupon>
     */
    public function searchAndPaginateCoupons(CouponSearchDto $searchDto, ?OrderDto $orderDto = null): LengthAwarePaginator;

    public function getDiscountStrategyForCoupon(?Coupon $coupon): DiscountStrategyContract;

    /**
     * @param array<string, mixed> $data
     */
    public function createCoupon(array $data): Coupon;

    /**
     * @param Coupon $coupon
     * @param array<string, mixed> $data
     * @return Coupon
     */
    public function updateCoupon(Coupon $coupon, array $data): Coupon;

    public function couponCanBeUsedOnCart(Coupon $coupon, Cart $cart): bool;
    public function couponIsActive(Coupon $coupon): bool;
    public function couponInPriceRange(Coupon $coupon, int $price): bool;

    public function cartContainsItemsIncludedInCoupon(Coupon $coupon, Cart $cart): bool;

    /**
     * @param Coupon $coupon
     * @param Cart $cart
     * @return Collection<int, \Treestoneit\ShoppingCart\Models\CartItem|CartItem>
     */
    public function cartItemsIncludedInCoupon(Coupon $coupon, Cart $cart): Collection;
    public function cartItemIsIncludedInCoupon(Coupon $coupon, CartItem $item): bool;
    public function productIsIncludedInCoupon(Coupon $coupon, Product $product): bool;
    public function productCategoriesAreIncludedInCoupon(Coupon $coupon, Product $product): bool;

    public function cartContainsItemsNotExcludedFromCoupon(Coupon $coupon, Cart $cart): bool;

    /**
     * @param Coupon $coupon
     * @param Cart $cart
     * @return Collection<int, \Treestoneit\ShoppingCart\Models\CartItem|CartItem>
     */
    public function cartItemsWithoutExcludedFromCoupon(Coupon $coupon, Cart $cart): Collection;
    public function cartItemIsExcludedFromCoupon(Coupon $coupon, CartItem $item): bool;
    public function productIsExcludedFromCoupon(Coupon $coupon, Product $product): bool;
    public function productCategoriesAreExcludedFromCoupon(Coupon $coupon, Product $product): bool;

    public function couponTimesUsed(Coupon $coupon): int;
    public function couponTimesUsedByUser(Coupon $coupon, ?User $user = null): int;
}
