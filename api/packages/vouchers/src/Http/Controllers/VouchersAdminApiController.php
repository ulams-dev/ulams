<?php

namespace Ulams\Vouchers\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Vouchers\Http\Controllers\Swagger\VouchersAdminApiControllerSwagger;
use Ulams\Vouchers\Http\Requests\CreateCouponRequest;
use Ulams\Vouchers\Http\Requests\DeleteCouponRequest;
use Ulams\Vouchers\Http\Requests\ListCouponsRequest;
use Ulams\Vouchers\Http\Requests\ReadCouponRequest;
use Ulams\Vouchers\Http\Requests\UpdateCouponRequest;
use Ulams\Vouchers\Http\Resources\CouponResource;
use Ulams\Vouchers\Services\Contracts\CouponServiceContract;
use Illuminate\Http\JsonResponse;

class VouchersAdminApiController extends UlamsBaseController implements VouchersAdminApiControllerSwagger
{
    private CouponServiceContract $couponsService;

    public function __construct(CouponServiceContract $couponsService)
    {
        $this->couponsService = $couponsService;
    }

    public function index(ListCouponsRequest $request): JsonResponse
    {
        $orderDto = OrderDto::instantiateFromRequest($request);
        $searchCouponsDto = $request->toDto();
        $paginatedResults = $this->couponsService->searchAndPaginateCoupons($searchCouponsDto, $orderDto);
        return $this->sendResponseForResource(CouponResource::collection($paginatedResults), ('Coupons search results'));
    }

    public function create(CreateCouponRequest $request): JsonResponse
    {
        $coupon = $this->couponsService->createCoupon($request->validated());
        return $this->sendResponseForResource(CouponResource::make($coupon));
    }

    public function read(ReadCouponRequest $request): JsonResponse
    {
        return $this->sendResponseForResource(CouponResource::make($request->getCoupon()));
    }

    public function update(UpdateCouponRequest $request): JsonResponse
    {
        $coupon = $this->couponsService->updateCoupon($request->getCoupon(), $request->validated());
        return $this->sendResponseForResource(CouponResource::make($coupon));
    }

    public function delete(DeleteCouponRequest $request): JsonResponse
    {
        $request->getCoupon()->delete();
        return $this->sendSuccess(__('Coupon was deleted'));
    }
}
