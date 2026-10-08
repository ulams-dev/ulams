<?php

namespace Ulams\Cart\Http\Controllers\Admin;

use Ulams\Cart\Http\Requests\Admin\ProductAttachRequest;
use Ulams\Cart\Http\Requests\Admin\ProductCreateRequest;
use Ulams\Cart\Http\Requests\Admin\ProductDeleteRequest;
use Ulams\Cart\Http\Requests\Admin\ProductDetachRequest;
use Ulams\Cart\Http\Requests\Admin\ProductManuallyTriggerRequest;
use Ulams\Cart\Http\Requests\Admin\ProductReadRequest;
use Ulams\Cart\Http\Requests\Admin\ProductSearchRequest;
use Ulams\Cart\Http\Requests\Admin\ProductUpdateRequest;
use Ulams\Cart\Http\Resources\ProductDetailedResource;
use Ulams\Cart\Http\Resources\ProductResource;
use Ulams\Cart\Http\Swagger\Admin\ProductAdminSwagger;
use Ulams\Cart\Services\Contracts\ProductServiceContract;
use Ulams\Cart\Services\Contracts\ShopServiceContract;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Templates\Services\Contracts\EventServiceContract;
use Exception;
use Illuminate\Http\JsonResponse;

class ProductAdminApiController extends UlamsBaseController implements ProductAdminSwagger
{
    protected ProductServiceContract $productService;
    protected ShopServiceContract $shopService;
    protected EventServiceContract $eventService;

    public function __construct(
        ProductServiceContract $productService,
        ShopServiceContract $shopService,
        EventServiceContract $eventService
    ) {
        $this->productService = $productService;
        $this->shopService = $shopService;
        $this->eventService = $eventService;
    }

    public function index(ProductSearchRequest $request): JsonResponse
    {
        $products = $this->productService->searchAndPaginateProducts($request->toDto(), OrderDto::instantiateFromRequest($request));
        return $this->sendResponseForResource(ProductResource::collection($products));
    }

    public function create(ProductCreateRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->create($request->validated());
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
        return $this->sendResponseForResource(ProductResource::make($product), __('Product created'));
    }

    public function read(ProductReadRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(ProductDetailedResource::make($request->getProduct()), __('Product fetched'));
    }

    public function update(ProductUpdateRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->update($request->getProduct(), $request->validated());
        } catch (Exception $ex) {
            return $this->sendError($ex->getMessage(), 400);
        }
        return $this->sendResponseForResource(ProductResource::make($product), __('Product updated'));
    }

    public function delete(ProductDeleteRequest $request): JsonResponse
    {
        $request->getProduct()->delete();
        return $this->sendResponse([], __('Product deleted'));
    }

    public function attach(ProductAttachRequest $request): JsonResponse
    {
        $this->productService->attachProductToUser($request->getProduct(), $request->getCartUser());
        return $this->sendSuccess(__('Product attached to user'));
    }

    public function detach(ProductDetachRequest $request): JsonResponse
    {
        $this->productService->detachProductFromUser($request->getProduct(), $request->getCartUser());
        return $this->sendSuccess(__('Product detached from user'));
    }

    public function triggerEventManuallyForUsers(ProductManuallyTriggerRequest $request): JsonResponse
    {
        $userIds = $request->getProduct()->users->pluck('id');
        $template = $request->getTemplate();

        if (!$template->is_valid) {
            return $this->sendError(__('Template is invalid.'), 400);
        }

        $this->eventService->dispatchEventManuallyForUsers($userIds->toArray(), $template, null, $request->getId());

        return $this->sendSuccess(__('Event triggered successfully for users of the product'));
    }
}
