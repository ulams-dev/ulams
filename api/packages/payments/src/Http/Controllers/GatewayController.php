<?php

namespace EscolaLms\Payments\Http\Controllers;

use EscolaLms\Core\Http\Controllers\EscolaLmsBaseController;
use EscolaLms\Payments\Facades\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GatewayController extends EscolaLmsBaseController
{
    public function index(Request $request): JsonResponse
    {
        return $this->sendResponse(Payments::listGatewaysWithRequiredParameters(), __('List of payment gateways with required parameters'));
    }

    public function callback(Request $request): JsonResponse
    {
        $payment = Payments::findPayment((int) $request->route('payment'));

        if (is_null($payment)) {
            Log::error(__('Callback called for undefined payment :id', ['id' => $request->route('payment')]));
            return $this->sendError(__('Payment not found'), 404);
        }

        Payments::processPayment($payment)->callback($request);
        return $this->sendSuccess('OK');
    }

    public function callbackRefund(Request $request): JsonResponse
    {
        $payment = Payments::findPayment((int) $request->route('payment'));

        if (is_null($payment)) {
            Log::error(__('Callback called for undefined payment :id', ['id' => $request->route('payment')]));
            return $this->sendError(__('Payment not found'), 404);
        }

        Payments::processPayment($payment)->callbackRefund($request);
        return $this->sendSuccess('OK');
    }
}
