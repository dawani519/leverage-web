<?php

namespace App\Services\Payments;

use App\Exceptions\PaymentException;
use App\Services\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin Paystack client. The secret key comes from encrypted admin settings, never from source or .env.
 * Network failures surface as Illuminate\Http\Client exceptions (retryable); PaymentException is permanent.
 */
class PaystackGateway
{
    private const BASE_URL = 'https://api.paystack.co';

    public function __construct(private SettingsService $settings) {}

    /** @return array{authorization_url: string, access_code: string, reference: string} */
    public function initialize(string $email, int $amountKobo, string $reference, string $callbackUrl): array
    {
        $response = $this->client()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => $amountKobo,
            'currency' => 'NGN',
            'reference' => $reference,
            'callback_url' => $callbackUrl,
        ])->throw();

        $data = $response->json('data');
        if (! $response->json('status') || empty($data['authorization_url'])) {
            throw new PaymentException('Paystack did not return an authorization URL.');
        }

        return $data;
    }

    /** @return array<string, mixed> Paystack's `data` object for the transaction. */
    public function verify(string $reference): array
    {
        $response = $this->client()->get('/transaction/verify/'.rawurlencode($reference))->throw();

        if (! $response->json('status')) {
            throw new PaymentException('Paystack could not verify the transaction.');
        }

        return $response->json('data');
    }

    /** Constant-time check of the x-paystack-signature header (HMAC-SHA512 of the raw body). */
    public function isValidSignature(string $rawBody, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $rawBody, $this->secret()), $signature);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)->withToken($this->secret())->acceptJson()->timeout(15);
    }

    private function secret(): string
    {
        $key = $this->settings->get('paystack.secret_key');

        if (! is_string($key) || $key === '') {
            throw new PaymentException('Paystack is not configured.');
        }

        return $key;
    }
}
