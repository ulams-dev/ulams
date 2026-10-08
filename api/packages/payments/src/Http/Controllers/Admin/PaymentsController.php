<?php

namespace EscolaLms\Payments\Http\Controllers\Admin;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Payments\Dtos\PaymentFilterCriteriaDto;
use EscolaLms\Payments\Enums\ExportFormatEnum;
use EscolaLms\Payments\Exports\PaymentsExport;
use EscolaLms\Payments\Facades\Payments;
use EscolaLms\Payments\Http\Controllers\Admin\Swagger\PaymentsSwagger;
use EscolaLms\Payments\Http\Requests\Admin\PaymentExportRequest;
use EscolaLms\Payments\Http\Requests\Admin\PaymentsSearchAdminRequest;
use EscolaLms\Payments\Http\Requests\PaymentShowRequest;
use EscolaLms\Payments\Http\Resources\PaymentCollection;
use EscolaLms\Payments\Http\Resources\PaymentResource;
use EscolaLms\Payments\Models\Payment;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentsController extends EscolaLmsBaseController implements PaymentsSwagger
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
