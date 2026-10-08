<?php

namespace Ulams\Cart\Services\Contracts;

use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Dtos\PageDto;
use Ulams\Cart\Dtos\ProductSearchMyCriteriaDto;
use Ulams\Cart\Dtos\ProductsSearchDto;
use Ulams\Cart\Models\Product;
use Ulams\Cart\Models\ProductProductable;
use Ulams\Cart\Models\ProductUser;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface ProductServiceContract
{
    public function registerProductableClass(string $productableClass): void;
    public function isProductableClassRegistered(string $productableClass): bool;
    public function listRegisteredProductableClasses(): array;
    public function listRegisteredMorphClasses(): array;
    public function listAllProductables(): Collection;
    public function canonicalProductableClass(string $productableClass): ?string;

    public function findSingleProductForProductable(Productable $productable): ?Product;
    public function findProductable(string $productableClass, $productId): ?Productable;

    public function mapProductProductableToJsonResource(ProductProductable $productProductable): JsonResource;

    public function searchAndPaginateProducts(ProductsSearchDto $searchDto, ?OrderDto $orderDto = null): LengthAwarePaginator;

    public function productIsPurchasableOrOwnedByUser(Product $product, User $user): bool;
    public function productIsBuyableByUser(Product $product, User $user, bool $check_productables = false, int $quantity = 1);
    public function productIsOwnedByUser(Product $product, User $user, bool $check_productables = false);
    public function productProductablesAllOwnedByUser(Product $product, User $user): bool;
    public function productProductablesAllBuyableByUser(Product $product, User $user): bool;

    public function create(array $data): Product;
    public function update(Product $product, array $data): Product;

    public function attachProductToUser(Product $product, User $user, int $quantity = 1): void;
    public function detachProductFromUser(Product $product, User $user, int $quantity = 1): void;
    public function attachProductableToUser(Productable $productable, User $user, int $quantity = 1, ?Product $product = null): void;
    public function detachProductableFromUser(Productable $productable, User $user, int $quantity = 1, ?Product $product = null): void;

    public function productableIsOwnedByUserThroughProduct(Productable $productable, User $user): bool;
    public function canDetachProductableFromUser(Productable $productable, User $user): bool;
    public function searchMy(ProductSearchMyCriteriaDto $dto, PageDto $pageDto, OrderDto $orderDto): LengthAwarePaginator;

    public function hasActiveSubscriptionAllIn(User $user): ?Product;

    public function getRecursiveProductUserBeforeExpiredEndDate(Carbon $start, Carbon $end): Collection;

    public function cancelActiveRecursiveProduct(Product $product, User $user): void;
}
