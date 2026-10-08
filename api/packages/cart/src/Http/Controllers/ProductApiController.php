<?php

namespace Ulams\Cart\Http\Controllers;

use Ulams\Cart\Http\Requests\ProductReadRequest;
use Ulams\Cart\Http\Requests\ProductRecursiveCancelRequest;
use Ulams\Cart\Http\Requests\ProductSearchMyRequest;
use Ulams\Cart\Http\Requests\ProductSearchRequest;
use Ulams\Cart\Http\Resources\MyProductResource;
use Ulams\Cart\Http\Resources\ProductResource;
use Ulams\Cart\Http\Swagger\ProductSwagger;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Ulams\Core\Dtos\OrderDto as SortDto;

class ProductApiController extends UlamsBaseController implements ProductSwagger
{
    protected ProductServiceContract $productService;
    protected ShopServiceContract $shopService;

    public function __construct(ProductServiceContract $productService, ShopServiceContract $shopService)
    {
        $this->productService = $productService;
        $this->shopService = $shopService;
    }

    public function index(ProductSearchRequest $request): JsonResponse
    {
        $sortDto = SortDto::instantiateFromRequest($request);
        $productsSearchDto = $request->toDto();
        $products = $this->productService->searchAndPaginateProducts($productsSearchDto, $sortDto);
        return $this->sendResponseForResource(ProductResource::collection($products));
    }

    public function read(ProductReadRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(ProductResource::make($request->getProduct()), __('Product fetched'));
    }

    public function indexMy(ProductSearchMyRequest $request): JsonResponse
    {
        $results = $this->productService->searchMy($request->getCriteria(), $request->getPage(), $request->getOrder());

        return $this->sendResponseForResource(MyProductResource::collection($results));
    }

    public function cancel(ProductRecursiveCancelRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->productService->cancelActiveRecursiveProduct($request->getProduct(), $user);

        return $this->sendSuccess(__('Subscription cancelled successfully'));
    }
}
