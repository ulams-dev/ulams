<?php

namespace EscolaLms\Vouchers\Http\Controllers;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Vouchers\Http\Controllers\Swagger\VouchersAdminApiControllerSwagger;
use EscolaLms\Vouchers\Http\Requests\CreateCouponRequest;
use EscolaLms\Vouchers\Http\Requests\DeleteCouponRequest;
use EscolaLms\Vouchers\Http\Requests\ListCouponsRequest;
use EscolaLms\Vouchers\Http\Requests\ReadCouponRequest;
use EscolaLms\Vouchers\Http\Requests\UpdateCouponRequest;
use EscolaLms\Vouchers\Http\Resources\CouponResource;
use EscolaLms\Vouchers\Services\Contracts\CouponServiceContract;
use Illuminate\Http\JsonResponse;

class VouchersAdminApiController extends EscolaLmsBaseController implements VouchersAdminApiControllerSwagger
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
