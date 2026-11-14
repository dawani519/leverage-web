<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountType;
use App\Models\Deposit;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\Payments\DepositService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackDepositTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_unit_secret';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->set('paystack.secret_key', self::SECRET);
        $this->user = User::factory()->create();
    }

    private function pendingDeposit(int $amount = 500_000): Deposit
    {
        return Deposit::forceCreate([
            'user_id' => $this->user->id, 'reference' => 'dep_test_'.uniqid(), 'amount' => $amount, 'status' => 'pending',
        ]);
    }

    private function fakeVerify(Deposit $deposit, array $overrides = []): void
    {
        // Http::fake() stacks stubs and the first match wins, so start from a clean factory each time.
        Http::swap(new HttpFactory);

        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => array_merge([
                'status' => 'success', 'reference' => $deposit->reference,
                'amount' => $deposit->amount, 'currency' => 'NGN',
            ], $overrides),
        ])]);
    }

    private function webhook(string $body, ?string $signature = null)
    {
        $signature ??= hash_hmac('sha512', $body, self::SECRET);

        return $this->call('POST', '/webhooks/paystack', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function chargeSuccess(Deposit $deposit): string
    {
        return json_encode(['event' => 'charge.success', 'data' => ['reference' => $deposit->reference]]);
    }

    private function wallet(): int
    {
        return LedgerAccount::forUser($this->user)->fresh()->balance;
    }

    public function test_start_creates_a_pending_deposit_with_the_authorization_url(): void
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'access_code' => 'abc', 'reference' => 'x'],
        ])]);

        $deposit = app(DepositService::class)->start($this->user, 250_000, 'http://leverage-web.test/wallet/deposit/callback');

        $this->assertSame('pending', $deposit->status);
        $this->assertSame('https://checkout.paystack.com/abc', $deposit->authorization_url);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer '.self::SECRET) && $r['amount'] === 250_000);
    }

    public function test_start_rejects_amounts_below_the_minimum_and_failed_init_marks_the_deposit_failed(): void
    {
        try {
            app(DepositService::class)->start($this->user, 50, 'http://x');
            $this->fail('Below-minimum deposit was accepted.');
        } catch (\App\Exceptions\PaymentException) {
        }

        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => false], 400)]);
        try {
            app(DepositService::class)->start($this->user, 250_000, 'http://x');
            $this->fail('Failed init did not throw.');
        } catch (\Throwable) {
        }

        $this->assertSame('failed', Deposit::firstOrFail()->status);
        $this->assertSame(0, LedgerTransaction::count());
    }

    public function test_valid_webhook_credits_the_wallet(): void
    {
        $deposit = $this->pendingDeposit(500_000);
        $this->fakeVerify($deposit);

        $this->webhook($this->chargeSuccess($deposit))->assertOk();

        $this->assertSame(500_000, $this->wallet());
        $this->assertSame('succeeded', $deposit->fresh()->status);
        $this->assertSame(500_000, LedgerAccount::totalBalance(AccountType::GatewayClearing));
    }

    public function test_replayed_webhook_never_credits_twice(): void
    {
        $deposit = $this->pendingDeposit(500_000);
        $this->fakeVerify($deposit);
        $body = $this->chargeSuccess($deposit);

        $this->webhook($body)->assertOk();
        $this->webhook($body)->assertOk();
        $this->webhook($body)->assertOk();

        $this->assertSame(500_000, $this->wallet());
        $this->assertSame(1, LedgerTransaction::where('type', 'deposit')->count());
    }

    public function test_bad_or_missing_signature_is_rejected_without_calling_paystack(): void
    {
        $deposit = $this->pendingDeposit();
        Http::fake();
        $body = $this->chargeSuccess($deposit);

        $this->webhook($body, 'deadbeef')->assertStatus(401);
        $this->webhook($body, '')->assertStatus(401);

        Http::assertNothingSent();
        $this->assertSame(0, $this->wallet());
        $this->assertSame('pending', $deposit->fresh()->status);
    }

    public function test_webhook_is_rejected_when_paystack_is_not_configured(): void
    {
        app(SettingsService::class)->set('paystack.secret_key', null);
        $deposit = $this->pendingDeposit();

        $this->webhook($this->chargeSuccess($deposit), 'anything')->assertStatus(401);
    }

    public function test_amount_mismatch_is_flagged_and_credits_nothing(): void
    {
        $deposit = $this->pendingDeposit(500_000);
        $this->fakeVerify($deposit, ['amount' => 100]); // paid far less than the deposit says

        $this->webhook($this->chargeSuccess($deposit))->assertOk();

        $this->assertSame(0, $this->wallet());
        $this->assertSame('flagged', $deposit->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deposit.flagged']);
    }

    public function test_wrong_currency_or_reference_from_paystack_is_flagged(): void
    {
        $a = $this->pendingDeposit();
        $this->fakeVerify($a, ['currency' => 'USD']);
        $this->webhook($this->chargeSuccess($a))->assertOk();

        $b = $this->pendingDeposit();
        $this->fakeVerify($b, ['reference' => 'someone_elses_ref']);
        $this->webhook($this->chargeSuccess($b))->assertOk();

        $this->assertSame(0, $this->wallet());
        $this->assertSame('flagged', $a->fresh()->status);
        $this->assertSame('flagged', $b->fresh()->status);
    }

    public function test_failed_payment_does_not_credit_and_abandoned_stays_open(): void
    {
        $failed = $this->pendingDeposit();
        $this->fakeVerify($failed, ['status' => 'failed']);
        $this->webhook($this->chargeSuccess($failed))->assertOk();

        $abandoned = $this->pendingDeposit();
        $this->fakeVerify($abandoned, ['status' => 'abandoned']);
        $this->webhook($this->chargeSuccess($abandoned))->assertOk();

        $this->assertSame(0, $this->wallet());
        $this->assertSame('failed', $failed->fresh()->status);
        $this->assertSame('pending', $abandoned->fresh()->status);
    }

    public function test_unknown_reference_is_acknowledged_so_paystack_stops_retrying(): void
    {
        Http::fake();
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => 'never_heard_of_it']]);

        $this->webhook($body)->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, LedgerTransaction::count());
    }

    public function test_paystack_outage_during_verification_returns_5xx_so_it_is_retried(): void
    {
        $deposit = $this->pendingDeposit();
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response('boom', 503)]);

        $this->webhook($this->chargeSuccess($deposit))->assertStatus(500);

        $this->assertSame('pending', $deposit->fresh()->status);
        $this->assertSame(0, $this->wallet());
    }

    public function test_other_events_are_acknowledged_and_ignored(): void
    {
        Http::fake();

        $this->webhook(json_encode(['event' => 'transfer.success', 'data' => ['reference' => 'r']]))->assertOk();

        Http::assertNothingSent();
    }

    public function test_browser_callback_settles_only_the_logged_in_users_own_deposit(): void
    {
        $mine = $this->pendingDeposit(300_000);
        $this->fakeVerify($mine);
        $other = User::factory()->create();
        $theirs = Deposit::forceCreate([
            'user_id' => $other->id, 'reference' => 'dep_theirs', 'amount' => 300_000, 'status' => 'pending',
        ]);

        $this->actingAs($this->user)->get('/wallet/deposit/callback?reference='.$theirs->reference)
            ->assertRedirect(route('dashboard'))->assertSessionHas('status', 'deposit-unknown');
        $this->assertSame('pending', $theirs->fresh()->status);

        $this->actingAs($this->user)->get('/wallet/deposit/callback?reference='.$mine->reference)
            ->assertRedirect(route('dashboard'))->assertSessionHas('status', 'deposit-succeeded');
        $this->assertSame(300_000, $this->wallet());
    }

    public function test_secret_key_is_encrypted_at_rest(): void
    {
        $raw = \DB::table('settings')->where('key', 'paystack.secret_key')->value('value');

        $this->assertNotSame(self::SECRET, $raw);
        $this->assertStringNotContainsString(self::SECRET, $raw);
        $this->assertSame(self::SECRET, app(SettingsService::class)->get('paystack.secret_key'));
    }
}
