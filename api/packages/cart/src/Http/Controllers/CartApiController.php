<?php

namespace Ulams\Cart\Http\Controllers;

use Ulams\Cart\Http\Requests\AddMissingProductsRequest;
use Ulams\Cart\Http\Requests\ProductableAddToCartRequest;
use Ulams\Cart\Http\Requests\ProductRemoveFromCartRequest;
use Ulams\Cart\Http\Requests\ProductSetQuantityInCartRequest;
use Ulams\Cart\Http\Swagger\CartSwagger;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartApiController extends UlamsBaseController implements CartSwagger
{
    protected ProductServiceContract $productService;
    protected ShopServiceContract $shopService;

    public function __construct(ProductServiceContract $productService, ShopServiceContract $shopService)
    {
        $this->productService = $productService;
        $this->shopService = $shopService;
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart = $this->shopService->cartForUser($user);
        return $this->sendResponseForResource($this->shopService->cartAsJsonResource($cart), __("Cart data fetched"));
    }

    public function setProductQuantity(ProductSetQuantityInCartRequest $request): JsonResponse
    {
        $product = $request->getProduct();
        /** @var User $user */
        $user = $request->user();
        $cart = $this->shopService->cartForUser($user);
        if (!$this->productService->productIsBuyableByUser($product, $user, false, $request->getQuantity())) {
            return $this->sendError(__("You can not add this product to cart"), 422);
        }
        return $this->sendResponse(
            $this->shopService->updateProductQuantity($cart, $product, $request->getQuantity()),
            __('Product quantity changed')
        );
    }

    public function addMissingProducts(AddMissingProductsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cart = $this->shopService->cartForUser($user);
        $this->shopService->addMissingProductsToCart($cart, $request->input('products', []));
        return $this->sendSuccess(__('Missing (buyable) products added to Cart'));
    }

    public function addProductable(ProductableAddToCartRequest $request): JsonResponse
    {
        $productable = $this->productService->findProductable($request->getProductableType(), $request->getProductableId());
        $product = $this->productService->findSingleProductForProductable($productable);
        if (!$product) {
            return $this->sendError(__('Single Product for this productable does not exist'), 404);
        }
        /** @var User $user */
        $user = $request->user();
        $cart = $this->shopService->cartForUser($user);
        if (!$this->productService->productIsBuyableByUser($product, $user)) {
            return $this->sendError(__("You can not add this product to cart"), 403);
        }
        $this->shopService->addProductToCart($cart, $product, 1);
        return $this->sendSuccess(__("Product added to cart"));
    }

    public function remove(ProductRemoveFromCartRequest $request): JsonResponse
    {
        $product = $request->getProduct();
        /** @var User $user */
        $user = $request->user();
        $cart = $this->shopService->cartForUser($user);
        return $this->sendResponse(
            $this->shopService->updateProductQuantity($cart, $product, 0),
            __('Product removed from cart')
        );
    }
}
