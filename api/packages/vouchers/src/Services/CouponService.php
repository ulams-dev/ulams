<?php

namespace EscolaLms\Vouchers\Services;

use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Models\User;
use EscolaLms\Vouchers\Dtos\CouponSearchDto;
use EscolaLms\Vouchers\Enums\CouponTypeEnum;
use EscolaLms\Vouchers\Models\Cart;
use EscolaLms\Vouchers\Models\Coupon;
use EscolaLms\Vouchers\Models\CouponCategory;
use EscolaLms\Vouchers\Models\CouponProduct;
use EscolaLms\Vouchers\Models\CouponUser;
use EscolaLms\Vouchers\Services\Contracts\CouponServiceContract;
use EscolaLms\Vouchers\Strategies\Contracts\DiscountStrategyContract;
use EscolaLms\Vouchers\Strategies\NoneDiscountStrategy;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Treestoneit\ShoppingCart\Models\CartItem;

class CouponService implements CouponServiceContract
{
    /**
     * @param CouponSearchDto $searchDto
     * @param OrderDto|null $orderDto
     * @return LengthAwarePaginator<Coupon>
     */
    public function searchAndPaginateCoupons(CouponSearchDto $searchDto, ?OrderDto $orderDto = null): LengthAwarePaginator
    {
        $query = Coupon::query();

        if (!is_null($searchDto->getName())) {
            $query->where('name', 'LIKE', $searchDto->getName() . '%');
        }

        if (!is_null($searchDto->getCode())) {
            $query->where('code', 'LIKE', Str::upper($searchDto->getCode()) . '%');
        }

        if (!is_null($searchDto->getType())) {
            $query->where('type', '=', $searchDto->getType());
        }

        if (!is_null($searchDto->getActiveFrom())) {
            $query->whereDate('active_from', '>=', $searchDto->getActiveFrom())
                ->orWhere(
                    fn (Builder $subquery) =>
                    $subquery->whereNull('active_from')
                        ->whereDate('active_to', '>=', $searchDto->getActiveFrom())
                );
        }

        if (!is_null($searchDto->getActiveTo())) {
            $query->whereDate('active_to', '<=', $searchDto->getActiveFrom())
                ->orWhere(
                    fn (Builder $subquery) =>
                    $subquery->whereNull('active_to')
                        ->whereDate('active_from', '<=', $searchDto->getActiveFrom())
                );
        }

        if (!is_null($orderDto) && !is_null($orderDto->getOrder())) {
            $query = $query->orderBy($orderDto->getOrderBy(), $orderDto->getOrder());
        }

        return $query->paginate($searchDto->getPerPage() ?? 15);
    }

    /**
     * @param array<string, mixed> $data
     * @return Coupon
     */
    public function createCoupon(array $data): Coupon
    {
        $coupon = new Coupon([
            'name' => Arr::get($data, 'name'),
            'code' => Arr::get($data, 'code'),
            'type' => Arr::get($data, 'type'),
            'active' => Arr::get($data, 'active', false),
            'active_from' => Arr::get($data, 'active_from'),
            'active_to' => Arr::get($data, 'active_to'),
            'limit_usage' => Arr::get($data, 'limit_usage'),
            'limit_per_user' => Arr::get($data, 'limit_per_user'),
            'min_cart_price' => Arr::get($data, 'min_cart_price'),
            'max_cart_price' => Arr::get($data, 'max_cart_price'),
            'amount' => Arr::get($data, 'amount'),
            'exclude_promotions' => Arr::get($data, 'exclude_promotions', true),
        ]);
        $coupon->save();

        foreach ($data['included_products'] ?? [] as $product) {
            CouponProduct::create([
                'coupon_id' => $coupon->getKey(),
                'product_id' => $product,
                'excluded' => false,
            ]);
        }
        foreach ($data['excluded_products'] ?? []  as $product) {
            CouponProduct::create([
                'coupon_id' => $coupon->getKey(),
                'product_id' => $product,
                'excluded' => true,
            ]);
        }
        foreach ($data['included_categories'] ?? [] as $category) {
            CouponCategory::create([
                'coupon_id' => $coupon->getKey(),
                'category_id' => $category,
                'excluded' => false,
            ]);
        }
        foreach ($data['excluded_categories'] ?? [] as $category) {
            CouponCategory::create([
                'coupon_id' => $coupon->getKey(),
                'category_id' => $category,
                'excluded' => true,
            ]);
        }

        foreach ($data['users'] ?? []  as $user) {
            CouponUser::create([
                'coupon_id' => $coupon->getKey(),
                'user_id' => $user,
            ]);
        }

        return $coupon->refresh();
    }

    /**
     * @param Coupon $coupon
     * @param array<string, mixed> $data
     * @return Coupon
     */
    public function updateCoupon(Coupon $coupon, array $data): Coupon
    {
        if (!isset($data['included_products'])) {
            $data['included_products'] = $coupon->includedProducts->pluck('id');
        }
        if (!isset($data['excluded_products'])) {
            $data['excluded_products'] = $coupon->excludedProducts->pluck('id');
        }
        if (!isset($data['included_categories'])) {
            $data['included_categories'] = $coupon->includedCategories->pluck('id');
        }
        if (!isset($data['excluded_categories'])) {
            $data['excluded_categories'] = $coupon->excludedCategories->pluck('id');
        }

        $products = [];
        foreach ($data['included_products'] as $id) {
            $products[$id] = ['excluded' => false];
        }
        foreach ($data['excluded_products'] as $id) {
            $products[$id] = ['excluded' => true];
        }
        $coupon->products()->sync($products);

        $categories = [];
        foreach ($data['included_categories'] as $id) {
            $categories[$id] = ['excluded' => false];
        }
        foreach ($data['excluded_categories'] as $id) {
            $categories[$id] = ['excluded' => true];
        }
        $coupon->categories()->sync($categories);

        if (isset($data['users'])) {
            CouponUser::where('coupon_id', $coupon->getKey())
                ->whereNotIn('user_id', $data['users'])
                ->delete();
        }
        foreach ($data['users'] ?? [] as $user) {
            CouponUser::query()->firstOrCreate([
                'coupon_id' => $coupon->getKey(),
                'user_id' => $user,
            ]);
        }

        unset($data['users']);
        unset($data['included_products']);
        unset($data['excluded_products']);
        unset($data['included_categories']);
        unset($data['excluded_categories']);

        $coupon->fill($data);
        $coupon->save();

        return $coupon->refresh();
    }

    public function getDiscountStrategyForCoupon(?Coupon $coupon): DiscountStrategyContract
    {
        if (is_null($coupon)) {
            return new NoneDiscountStrategy;
        }

        $className = 'EscolaLms\\Vouchers\\Strategies\\' . Str::studly($coupon->type) . 'DiscountStrategy';

        if (!class_exists($className)) {
            throw new \RuntimeException($className . ' strategy does not exist.');
        }

        return new $className($coupon);
    }

    public function couponCanBeUsedOnCart(Coupon $coupon, Cart $cart): bool
    {
        $coupon->load('includedCategories', 'excludedCategories', 'includedProducts', 'excludedProducts');
        $cart->load('items', 'items.buyable');

        $cartManager = new CartManager($cart);
        return $this->couponIsActive($coupon)
            && $this->couponInPriceRange($coupon, $cartManager->totalPreAdditionalDiscount())
            && $this->cartFulfilPromotionConditions($coupon, $cart)
            && $this->cartContainsItemsIncludedInCoupon($coupon, $cart)
            && $this->cartContainsItemsNotExcludedFromCoupon($coupon, $cart)
            && $this->userIncludedInCoupon($coupon);
    }

    public function couponIsActive(Coupon $coupon): bool
    {
        return $coupon->active
            && (is_null($coupon->active_from) || Carbon::now()->greaterThanOrEqualTo($coupon->active_from))
            && (is_null($coupon->active_to) || Carbon::now()->lessThanOrEqualTo($coupon->active_to))
            && (is_null($coupon->limit_usage) || $coupon->limit_usage > $this->couponTimesUsed($coupon))
            && (is_null($coupon->limit_per_user) || $coupon->limit_per_user > $this->couponTimesUsedByUser($coupon));
    }

    public function couponInPriceRange(Coupon $coupon, int $price): bool
    {
        return (is_null($coupon->min_cart_price) || $price >= $coupon->min_cart_price)
            && (is_null($coupon->max_cart_price) || $price <= $coupon->max_cart_price);
    }

    public function cartContainsItemsIncludedInCoupon(Coupon $coupon, Cart $cart): bool
    {
        return ($coupon->includedProducts()->count() === 0 && $coupon->includedCategories()->count() === 0) || $this->cartItemsIncludedInCoupon($coupon, $cart)->count() > 0;
    }

    /**
     * @param Coupon $coupon
     * @param Cart $cart
     * @return Collection<int, CartItem|\EscolaLms\Vouchers\Models\CartItem>
     */
    public function cartItemsIncludedInCoupon(Coupon $coupon, Cart $cart): Collection
    {
        /** @var Collection<int, CartItem|\EscolaLms\Vouchers\Models\CartItem> $items */
        // @phpstan-ignore-next-line
        $items = $cart->items->filter(fn (CartItem $item) => $this->cartItemIsIncludedInCoupon($coupon, $item));
        return $items;
    }

    public function cartItemIsIncludedInCoupon(Coupon $coupon, CartItem $item): bool
    {
        return $item->buyable instanceof Product && $this->productIsNotOnPromotion($coupon, $item->buyable) && ($this->productIsIncludedInCoupon($coupon, $item->buyable) || $this->productCategoriesAreIncludedInCoupon($coupon, $item->buyable));
    }

    public function productIsIncludedInCoupon(Coupon $coupon, Product $product): bool
    {
        return $coupon->includedProducts->contains($product);
    }

    public function productCategoriesAreIncludedInCoupon(Coupon $coupon, Product $product): bool
    {
        return $coupon->includedCategories->whereIn('id', $product->categories->pluck('id')->toArray())->count() > 0;
    }

    public function cartContainsItemsNotExcludedFromCoupon(Coupon $coupon, Cart $cart): bool
    {
        return ($coupon->excludedProducts()->count() === 0 && $coupon->excludedCategories()->count() === 0) || $this->cartItemsWithoutExcludedFromCoupon($coupon, $cart)->count() > 0;
    }

    /**
     * @param Coupon $coupon
     * @param Cart $cart
     * @return Collection<int, CartItem|\EscolaLms\Vouchers\Models\CartItem>
     */
    public function cartItemsWithoutExcludedFromCoupon(Coupon $coupon, Cart $cart): Collection
    {
        /** @var Collection<int, CartItem|\EscolaLms\Vouchers\Models\CartItem> $result */
        // @phpstan-ignore-next-line
        $result = $cart->items->filter(fn (CartItem $item) => !$this->cartItemIsExcludedFromCoupon($coupon, $item));
        return $result;
    }

    public function cartItemIsExcludedFromCoupon(Coupon $coupon, CartItem $item): bool
    {
        return $item->buyable instanceof Product && (!$this->productIsNotOnPromotion($coupon, $item->buyable) || ($this->productIsExcludedFromCoupon($coupon, $item->buyable) || $this->productCategoriesAreExcludedFromCoupon($coupon, $item->buyable)));
    }

    public function cartFulfilPromotionConditions(Coupon $coupon, Cart $cart): bool
    {
        // @phpstan-ignore-next-line
        return !$coupon->exclude_promotions || $cart->items->filter(fn (CartItem $item) => $this->productOnPromotion($coupon, $item))->count() === 0;
    }

    public function productOnPromotion(Coupon $coupon, CartItem $item): bool
    {
        return $item->buyable instanceof Product && !$this->productIsNotOnPromotion($coupon, $item->buyable);
    }

    public function productIsNotOnPromotion(Coupon $coupon, Product $product): bool
    {
        return !$coupon->exclude_promotions || is_null($product->price_old) || $product->price_old === $product->price;
    }

    public function productIsExcludedFromCoupon(Coupon $coupon, Product $product): bool
    {
        return $coupon->excludedProducts->contains($product);
    }

    public function productCategoriesAreExcludedFromCoupon(Coupon $coupon, Product $product): bool
    {
        return $coupon->excludedCategories->whereIn('id', $product->categories->pluck('id')->toArray())->count() > 0;
    }

    public function couponTimesUsed(Coupon $coupon): int
    {
        return $coupon->orders_count ?? $coupon->orders()->count();
    }

    public function couponTimesUsedByUser(Coupon $coupon, ?User $user = null): int
    {
        if (empty($user)) {
            /** @var User $user */
            $user = Auth::user();
        }
        return $coupon->orders()->where('user_id', $user->getKey())->count();
    }

    public function userIncludedInCoupon(Coupon $coupon, ?User $user = null): bool
    {
        if (empty($user)) {
            /** @var User $user */
            $user = Auth::user();
        }
        return $coupon->users->count() === 0 || CouponUser::where('user_id', $user->getKey())->where('coupon_id', $coupon->getKey())->exists();
    }
}
