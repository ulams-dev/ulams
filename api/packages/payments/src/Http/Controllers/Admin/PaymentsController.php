<?php

namespace Ulams\Payments\Http\Controllers\Admin;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Payments\Dtos\PaymentFilterCriteriaDto;
use Ulams\Payments\Enums\ExportFormatEnum;
use Ulams\Payments\Exports\PaymentsExport;
use Ulams\Payments\Facades\Payments;
use Ulams\Payments\Http\Controllers\Admin\Swagger\PaymentsSwagger;
use Ulams\Payments\Http\Requests\Admin\PaymentExportRequest;
use Ulams\Payments\Http\Requests\Admin\PaymentsSearchAdminRequest;
use Ulams\Payments\Http\Requests\PaymentShowRequest;
use Ulams\Payments\Http\Resources\PaymentCollection;
use Ulams\Payments\Http\Resources\PaymentResource;
use Ulams\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentsController extends UlamsBaseController implements PaymentsSwagger
{
    public function search(PaymentsSearchAdminRequest $request): JsonResponse
    {
        $paymentFilterDto = PaymentFilterCriteriaDto::instantiateFromRequest($request);
        $orderDto = OrderDto::instantiateFromRequest($request);
        return $this->sendResponseForResource(PaymentCollection::make(Payments::searchPayments($paymentFilterDto, $orderDto)), __("Search payments results"));
    }

    public function show(PaymentShowRequest $request, Payment $payment): JsonResponse
    {
        return $this->sendResponseForResource(PaymentResource::make($request->getPayment()), __("Payment details"));
    }

    public function export(PaymentExportRequest $request): BinaryFileResponse
    {
        $paymentFilterDto = PaymentFilterCriteriaDto::instantiateFromRequest($request);
        $orderDto = OrderDto::instantiateFromRequest($request);
        $format = ExportFormatEnum::fromValue($request->input('format', ExportFormatEnum::CSV));
        return Excel::download(
            new PaymentsExport(Payments::searchPaymentsForExport($paymentFilterDto, $orderDto)),
            $format->getFilename('payments'),
            $format->getWriterType()
        );
    }
}
