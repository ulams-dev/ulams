<?php

namespace Ulams\Payments\Http\Controllers;

use Ulams\Core\Http\Controllers\UlamsBaseController;
use Ulams\Payments\Facades\PaymentGateway;
use Ulams\Payments\Facades\Payments;
use Ulams\Payments\Gateway\Drivers\StripeDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class GatewayController extends UlamsBaseController
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

        $processor = Payments::processPayment($payment)->callback($request);

        if ($processor->isCallbackRejected()) {
            return $this->sendError(__('Callback rejected'), 400);
        }

        return $this->sendSuccess('OK');
    }

    /**
     * Endpoint for the Stripe webhook (one URL per Stripe account). The event is only trusted after its signature
     * was verified; the payment is then looked up from the PaymentIntent metadata and processed like a callback.
     */
    public function stripeWebhook(Request $request): JsonResponse
    {
        try {
            $driver = PaymentGateway::driver('stripe');
        } catch (Throwable $exception) {
            return $this->sendError(__('Stripe payments gateway is not available'), 400);
        }

        $event = $driver instanceof StripeDriver ? $driver->verifiedEvent($request) : null;

        if (is_null($event)) {
            Log::warning('Stripe webhook rejected: invalid signature');
            return $this->sendError(__('Invalid signature'), 400);
        }

        $object = $event['data']['object'] ?? [];
        $paymentId = is_array($object) && ($object['object'] ?? null) === 'payment_intent'
            ? ($object['metadata']['payment_id'] ?? null)
            : null;

        // Acknowledge verified events we do not handle, otherwise Stripe keeps retrying them.
        if (!is_numeric($paymentId)) {
            return $this->sendSuccess('Ignored');
        }

        $payment = Payments::findPayment((int) $paymentId);

        if (is_null($payment) || strtolower((string) $payment->driver) !== 'stripe') {
            return $this->sendSuccess('Ignored');
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

        $processor = Payments::processPayment($payment)->callbackRefund($request);

        if ($processor->isCallbackRejected()) {
            return $this->sendError(__('Callback rejected'), 400);
        }

        return $this->sendSuccess('OK');
    }
}
