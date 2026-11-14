<?php

namespace App\Services\Payments;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Exceptions\PaymentException;
use App\Models\AuditLog;
use App\Models\Deposit;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Wallet top-ups. The browser callback and the webhook both end up in settle(), and settle() never
 * trusts either: it re-verifies with Paystack, checks amount/currency/reference, then credits once.
 */
class DepositService
{
    public function __construct(
        private PaystackGateway $gateway,
        private LedgerService $ledger,
        private SettingsService $settings,
    ) {}

    public function start(User $user, int $amountKobo, string $callbackUrl): Deposit
    {
        if ($amountKobo < $this->settings->int('wallet.min_deposit_kobo')) {
            throw new PaymentException('Amount is below the minimum deposit.');
        }

        $deposit = Deposit::forceCreate([
            'user_id' => $user->id,
            'reference' => 'dep_'.Str::lower((string) Str::ulid()),
            'amount' => $amountKobo,
            'status' => 'pending',
        ]);

        try {
            $init = $this->gateway->initialize($user->email, $amountKobo, $deposit->reference, $callbackUrl);
        } catch (\Throwable $e) {
            $deposit->forceFill(['status' => 'failed'])->save();
            throw $e;
        }

        $deposit->forceFill(['authorization_url' => $init['authorization_url']])->save();

        return $deposit;
    }

    /**
     * Verify with Paystack and credit the wallet exactly once.
     *
     * @throws PaymentException permanent problem: unknown reference or a verification mismatch
     */
    public function settle(string $reference): Deposit
    {
        $deposit = Deposit::where('reference', $reference)->first();
        if (! $deposit) {
            throw new PaymentException('Unknown deposit reference.');
        }
        if ($deposit->status === 'succeeded') {
            return $deposit;
        }

        // Network call stays outside the DB transaction so we never hold row locks across HTTP.
        $data = $this->gateway->verify($reference);

        if (($data['status'] ?? null) !== 'success') {
            if (($data['status'] ?? null) === 'failed') {
                $deposit->forceFill(['status' => 'failed', 'gateway_payload' => $data])->save();
            }

            return $deposit; // abandoned/pending: leave it open, a later webhook may succeed
        }

        if (($data['reference'] ?? null) !== $deposit->reference
            || (int) ($data['amount'] ?? -1) !== $deposit->amount
            || ($data['currency'] ?? null) !== 'NGN') {
            $deposit->forceFill(['status' => 'flagged', 'gateway_payload' => $data])->save();
            AuditLog::record('deposit.flagged', null, $deposit, ['reason' => 'verification mismatch']);

            throw new PaymentException('Verified payment does not match the deposit.');
        }

        return DB::transaction(function () use ($deposit, $data) {
            $locked = Deposit::whereKey($deposit->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'succeeded') {
                return $locked;
            }

            // The ledger idempotency key is the second line of defence against a double credit.
            $this->ledger->post('deposit', "deposit:{$locked->reference}", [
                ['account' => LedgerAccount::systemFor(AccountType::GatewayClearing, $locked->reference), 'direction' => Direction::Debit, 'amount' => $locked->amount],
                ['account' => LedgerAccount::forUser($locked->user), 'direction' => Direction::Credit, 'amount' => $locked->amount],
            ], $locked);

            $locked->forceFill(['status' => 'succeeded', 'paid_at' => now(), 'gateway_payload' => $data])->save();

            return $locked;
        });
    }
}
