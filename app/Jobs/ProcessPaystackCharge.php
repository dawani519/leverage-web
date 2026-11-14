<?php

namespace App\Jobs;

use App\Exceptions\PaymentException;
use App\Services\Payments\DepositService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Settles one Paystack charge off the request path. The webhook only authenticates and enqueues,
 * so a slow Paystack API can never tie up web workers.
 *
 * Permanent problems (unknown reference, verification mismatch) are logged and dropped.
 * Anything else (network, 5xx) throws so the queue retries with backoff.
 */
class ProcessPaystackCharge implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /** Seconds a duplicate webhook for the same reference is ignored while one is queued or running. */
    public int $uniqueFor = 300;

    public function __construct(public string $reference) {}

    public function uniqueId(): string
    {
        return 'paystack-charge:'.$this->reference;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300, 600, 1800];
    }

    public function handle(DepositService $deposits): void
    {
        try {
            $deposits->settle($this->reference);
        } catch (PaymentException $e) {
            Log::warning('Paystack charge not applied', ['reference' => $this->reference, 'reason' => $e->getMessage()]);
        }
    }
}
