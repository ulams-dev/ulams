<?php

namespace Ulams\Payments\Http\Controllers;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Payments\Dtos\PaymentFilterCriteriaDto;
use Ulams\Payments\Facades\Payments;
use Ulams\Payments\Http\Controllers\Swagger\PaymentsSwagger;
use Ulams\Payments\Http\Requests\PaymentShowRequest;
use Ulams\Payments\Http\Requests\PaymentsSearchRequest;
use Ulams\Payments\Http\Resources\PaymentCollection;
use Ulams\Payments\Http\Resources\PaymentResource;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentsController extends UlamsBaseController implements PaymentsSwagger
{
    public function search(PaymentsSearchRequest $request): JsonResponse
    {
        $paymentFilterDto = PaymentFilterCriteriaDto::instantiateFromRequest($request);
        $orderDto = OrderDto::instantiateFromRequest($request);

        return $this->sendResponseForResource(PaymentCollection::make(Payments::searchPayments($paymentFilterDto, $orderDto)), __("Your payments search results"));
    }

    public function show(PaymentShowRequest $request, Payment $payment): JsonResponse
    {
        return $this->sendResponseForResource(PaymentResource::make($request->getPayment()), __("Payment details"));
    }
}
