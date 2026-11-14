<?php

namespace Tests\Feature\Payments;

use App\Exceptions\PaymentException;
use App\Jobs\ProcessPaystackCharge;
use App\Models\Deposit;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Payments\DepositService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_queue_secret';

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->set('paystack.secret_key', self::SECRET);
    }

    private function webhook(string $body, ?string $signature = null)
    {
        $signature ??= hash_hmac('sha512', $body, self::SECRET);

        return $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function charge(string $reference): string
    {
        return json_encode(['event' => 'charge.success', 'data' => ['reference' => $reference]]);
    }

    public function test_webhook_only_enqueues_and_never_calls_paystack_in_the_request(): void
    {
        Queue::fake();
        Http::fake();

        $this->webhook($this->charge('dep_abc123'))->assertOk();

        Queue::assertPushed(ProcessPaystackCharge::class, fn ($job) => $job->reference === 'dep_abc123');
        Http::assertNothingSent();
    }

    public function test_unsigned_webhook_enqueues_nothing(): void
    {
        Queue::fake();

        $this->webhook($this->charge('dep_abc123'), 'forged')->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_non_charge_events_are_not_enqueued(): void
    {
        Queue::fake();

        $this->webhook(json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'r']]))->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_job_credits_the_wallet_when_it_runs(): void
    {
        $user = User::factory()->create();
        $deposit = Deposit::forceCreate(['user_id' => $user->id, 'reference' => 'dep_job1', 'amount' => 700_000, 'status' => 'pending']);
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => ['status' => 'success', 'reference' => 'dep_job1', 'amount' => 700_000, 'currency' => 'NGN'],
        ])]);

        (new ProcessPaystackCharge('dep_job1'))->handle(app(DepositService::class));
        (new ProcessPaystackCharge('dep_job1'))->handle(app(DepositService::class)); // duplicate delivery

        $this->assertSame(700_000, LedgerAccount::forUser($user)->fresh()->balance);
        $this->assertSame('succeeded', $deposit->fresh()->status);
    }

    public function test_job_retries_on_paystack_outage_but_drops_permanent_failures(): void
    {
        $user = User::factory()->create();
        Deposit::forceCreate(['user_id' => $user->id, 'reference' => 'dep_job2', 'amount' => 100_000, 'status' => 'pending']);

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response('down', 503)]);
        try {
            (new ProcessPaystackCharge('dep_job2'))->handle(app(DepositService::class));
            $this->fail('An outage must throw so the queue retries.');
        } catch (RequestException) {
        }

        // An unknown reference is permanent: handled quietly, no exception, so no pointless retries.
        (new ProcessPaystackCharge('dep_does_not_exist'))->handle(app(DepositService::class));
        $this->addToAssertionCount(1);

        $this->assertSame(0, LedgerAccount::forUser($user)->fresh()->balance);
    }

    public function test_job_declares_retry_backoff_and_per_reference_uniqueness(): void
    {
        $job = new ProcessPaystackCharge('dep_x');

        $this->assertSame(8, $job->tries);
        $this->assertSame('paystack-charge:dep_x', $job->uniqueId());
        $this->assertNotEmpty($job->backoff());
        $this->assertSame($job->backoff(), collect($job->backoff())->sort()->values()->all(), 'Backoff should not decrease.');
    }

    public function test_webhook_is_rate_limited_per_ip(): void
    {
        Queue::fake();
        config(['limits.webhook_per_minute' => 3]);

        foreach (range(1, 3) as $i) {
            $this->webhook($this->charge("dep_{$i}"))->assertOk();
        }

        $this->webhook($this->charge('dep_4'))->assertStatus(429);
    }
}
