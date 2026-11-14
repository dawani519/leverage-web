<?php

namespace App\Services\Ledger;

use App\Enums\Direction;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\LedgerException;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only code path allowed to change a ledger balance.
 *
 * post() is atomic, balanced (sum of debits equals sum of credits), idempotent per key,
 * and locks every touched account in ascending id order so concurrent postings cannot deadlock.
 */
class LedgerService
{
    /**
     * @param  array<int, array{account: LedgerAccount, direction: Direction, amount: int}>  $lines
     */
    public function post(string $type, string $idempotencyKey, array $lines, ?Model $subject = null, array $meta = []): LedgerTransaction
    {
        $this->assertBalanced($lines);

        if ($existing = LedgerTransaction::where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($type, $idempotencyKey, $lines, $subject, $meta) {
                $tx = LedgerTransaction::create([
                    'uuid' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'type' => $type,
                    'subject_type' => $subject?->getMorphClass(),
                    'subject_id' => $subject?->getKey(),
                    'meta' => $meta ?: null,
                ]);

                $ids = collect($lines)->map(fn ($l) => $l['account']->id)->unique()->sort()->values();
                $locked = LedgerAccount::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                foreach ($lines as $line) {
                    $account = $locked[$line['account']->id];
                    $newBalance = $account->balance + $this->signedDelta($account, $line['direction'], $line['amount']);

                    if ($newBalance < 0 && $account->type->forbidsOverdraft()) {
                        throw new InsufficientFundsException("Insufficient funds in account #{$account->id}.");
                    }

                    $account->balance = $newBalance;
                    $account->save();

                    LedgerEntry::create([
                        'ledger_transaction_id' => $tx->id,
                        'ledger_account_id' => $account->id,
                        'direction' => $line['direction']->value,
                        'amount' => $line['amount'],
                        'balance_after' => $newBalance,
                    ]);
                }

                return $tx;
            });
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request won the race on the same idempotency key. Replay its result.
            return LedgerTransaction::where('idempotency_key', $idempotencyKey)->firstOr(fn () => throw $e);
        }
    }

    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new LedgerException('A ledger transaction needs at least two lines.');
        }

        $debits = 0;
        $credits = 0;
        foreach ($lines as $line) {
            if (! is_int($line['amount']) || $line['amount'] <= 0) {
                throw new LedgerException('Ledger amounts must be positive integers (kobo).');
            }
            $line['direction'] === Direction::Debit ? $debits += $line['amount'] : $credits += $line['amount'];
        }

        if ($debits !== $credits) {
            throw new LedgerException("Unbalanced ledger transaction: debits {$debits} != credits {$credits}.");
        }
    }

    private function signedDelta(LedgerAccount $account, Direction $direction, int $amount): int
    {
        $increases = $account->type->isDebitNormal() ? Direction::Debit : Direction::Credit;

        return $direction === $increases ? $amount : -$amount;
    }
}
