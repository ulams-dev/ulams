<?php

namespace EscolaLms\Payments\Http\Controllers;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Payments\Dtos\PaymentFilterCriteriaDto;
use EscolaLms\Payments\Facades\Payments;
use EscolaLms\Payments\Http\Controllers\Swagger\PaymentsSwagger;
use EscolaLms\Payments\Http\Requests\PaymentShowRequest;
use EscolaLms\Payments\Http\Requests\PaymentsSearchRequest;
use EscolaLms\Payments\Http\Resources\PaymentCollection;
use EscolaLms\Payments\Http\Resources\PaymentResource;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentsController extends EscolaLmsBaseController implements PaymentsSwagger
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
