<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaystackCharge;
use App\Services\Payments\PaystackGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    /**
     * Authenticate and enqueue only. No Paystack API call and no ledger work happens in the request,
     * so Paystack gets its 200 immediately and a slow upstream cannot exhaust web workers.
     */
    public function __invoke(Request $request, PaystackGateway $gateway): Response
    {
        // Signature is computed over the exact raw bytes, so read the body before any parsing.
        $raw = $request->getContent();

        try {
            $valid = $gateway->isValidSignature($raw, $request->header('x-paystack-signature'));
        } catch (PaymentException) {
            $valid = false; // not configured: reject rather than accept unsigned traffic
        }

        if (! $valid) {
            return response('invalid signature', 401);
        }

        $event = json_decode($raw, true);

        if (($event['event'] ?? null) === 'charge.success' && is_string($reference = $event['data']['reference'] ?? null)) {
            ProcessPaystackCharge::dispatch($reference);
        }

        return response('ok', 200);
    }
}
