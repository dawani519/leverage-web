<?php

namespace App\Services\Escrow;

use App\Enums\AccountType;
use App\Enums\Direction;
use App\Enums\EscrowStatus;
use App\Exceptions\EscrowException;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Models\Listing;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Services\SettingsService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Escrow lifecycle. Rules enforced here, not in controllers or UI:
 *  - every transition runs in a DB transaction with the escrow row locked and the status re-read;
 *  - only the allowed actor can trigger each transition;
 *  - money only moves through LedgerService, with per-escrow idempotency keys.
 */
class EscrowService
{
    public function __construct(
        private LedgerService $ledger,
        private SettingsService $settings,
    ) {}

    public function create(User $buyer, User $seller, int $amount, ?Listing $listing = null, ?string $description = null): Escrow
    {
        if ($buyer->is($seller)) {
            throw new EscrowException('Buyer and seller must be different users.');
        }
        if ($buyer->isSuspended() || $seller->isSuspended()) {
            throw new EscrowException('A suspended account cannot take part in a transaction.');
        }
        if ($amount < $this->settings->int('escrow.min_amount_kobo') || $amount > $this->settings->int('escrow.max_amount_kobo')) {
            throw new EscrowException('Amount is outside the allowed range.');
        }
        if ($listing && $listing->seller_id !== $seller->id) {
            throw new EscrowException('The listing does not belong to this seller.');
        }

        $bps = $this->settings->int('escrow.fee_bps');
        $uuid = (string) Str::uuid();

        return Escrow::forceCreate([
            'uuid' => $uuid,
            'shard' => LedgerAccount::shardFor($uuid), // fixed for life: spreads lock contention, never remapped
            'listing_id' => $listing?->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'description' => $description,
            'amount' => $amount,
            'fee' => intdiv($amount * $bps + 5_000, 10_000), // round half up
            'fee_bps' => $bps,
            'status' => EscrowStatus::PendingPayment,
        ]);
    }

    /** Buyer pays from their wallet into escrow. */
    public function fund(Escrow $escrow, User $actor): Escrow
    {
        return $this->transition($escrow, EscrowStatus::Funded, function (Escrow $e) use ($actor) {
            $this->assertActor($actor, $e->buyer_id, 'Only the buyer can fund this escrow.');
            $this->assertListingAvailable($e);

            $this->ledger->post('escrow_fund', "escrow:{$e->id}:fund", [
                ['account' => LedgerAccount::forUser($e->buyer), 'direction' => Direction::Debit, 'amount' => $e->amount],
                ['account' => LedgerAccount::system(AccountType::EscrowHolding, $e->shard), 'direction' => Direction::Credit, 'amount' => $e->amount],
            ], $e);

            $e->funded_at = now();
        });
    }

    /** Seller states the goods or service were delivered; starts the auto-release clock. */
    public function markDelivered(Escrow $escrow, User $actor): Escrow
    {
        return $this->transition($escrow, EscrowStatus::Delivered, function (Escrow $e) use ($actor) {
            $this->assertActor($actor, $e->seller_id, 'Only the seller can mark this delivered.');

            $e->delivered_at = now();
            $e->auto_release_at = now()->addDays($this->settings->int('escrow.auto_release_days'));
        });
    }

    /** Buyer confirms receipt, or an admin overrides. Pays the seller minus the platform fee. */
    public function release(Escrow $escrow, User $actor): Escrow
    {
        return $this->transition($escrow, EscrowStatus::Released, function (Escrow $e) use ($actor) {
            if ($e->status === EscrowStatus::Disputed) {
                throw new EscrowException('A disputed escrow can only be settled through dispute resolution.');
            }
            $this->assertActor($actor, $e->buyer_id, 'Only the buyer or an admin can release funds.', allowAdmin: true);
            $this->payOut($e);
        });
    }

    /** Called by the scheduler once the buyer's window has passed without a dispute. */
    public function autoRelease(Escrow $escrow): Escrow
    {
        return $this->transition($escrow, EscrowStatus::Released, function (Escrow $e) {
            if ($e->status !== EscrowStatus::Delivered || $e->auto_release_at === null || $e->auto_release_at->isFuture()) {
                throw new EscrowException('This escrow is not due for automatic release.');
            }
            $this->payOut($e);
        });
    }

    /** Seller voluntarily refunds, or an admin refunds. Full amount back to the buyer, no fee. */
    public function refund(Escrow $escrow, User $actor): Escrow
    {
        return $this->transition($escrow, EscrowStatus::Refunded, function (Escrow $e) use ($actor) {
            if ($e->status === EscrowStatus::Disputed) {
                throw new EscrowException('A disputed escrow can only be settled through dispute resolution.');
            }
            $this->assertActor($actor, $e->seller_id, 'Only the seller or an admin can refund.', allowAdmin: true);
            $this->payBack($e);
        });
    }

    public function cancel(Escrow $escrow, User $actor): Escrow
    {
        return $this->transition($escrow, EscrowStatus::Cancelled, function (Escrow $e) use ($actor) {
            if (! in_array($actor->id, [$e->buyer_id, $e->seller_id], true) && ! $actor->isAdmin()) {
                throw new EscrowException('You are not a party to this escrow.');
            }
        });
    }

    public function openDispute(Escrow $escrow, User $actor, string $reason): Escrow
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new EscrowException('A dispute needs a reason.');
        }

        return $this->transition($escrow, EscrowStatus::Disputed, function (Escrow $e) use ($actor, $reason) {
            if (! in_array($actor->id, [$e->buyer_id, $e->seller_id], true)) {
                throw new EscrowException('Only the buyer or seller can open a dispute.');
            }

            Dispute::forceCreate(['escrow_id' => $e->id, 'opened_by' => $actor->id, 'reason' => $reason, 'status' => 'open']);
            $e->auto_release_at = null; // a dispute freezes the auto-release clock
        });
    }

    /** Admin decides: 'release' pays the seller (minus fee), 'refund' returns everything to the buyer. */
    public function resolveDispute(Dispute $dispute, User $admin, string $outcome, string $note): Escrow
    {
        if (! $admin->isAdmin()) {
            throw new EscrowException('Only an admin can resolve a dispute.');
        }
        if (! in_array($outcome, ['release', 'refund'], true)) {
            throw new EscrowException('Outcome must be "release" or "refund".');
        }

        $target = $outcome === 'release' ? EscrowStatus::Released : EscrowStatus::Refunded;

        return $this->transition($dispute->escrow, $target, function (Escrow $e) use ($dispute, $admin, $outcome, $note, $target) {
            if ($e->status !== EscrowStatus::Disputed) {
                throw new EscrowException('This escrow is not in dispute.');
            }

            $fresh = Dispute::where('id', $dispute->id)->lockForUpdate()->firstOrFail();
            if ($fresh->status !== 'open') {
                throw new EscrowException('This dispute is already resolved.');
            }

            $target === EscrowStatus::Released ? $this->payOut($e) : $this->payBack($e);

            $fresh->forceFill([
                'status' => 'resolved', 'outcome' => $outcome, 'resolved_by' => $admin->id,
                'resolution_note' => $note, 'resolved_at' => now(),
            ])->save();

            AuditLog::record('dispute.resolved', $admin, $fresh, ['outcome' => $outcome, 'escrow_id' => $e->id]);
        });
    }

    /**
     * Lock, re-read, validate the state machine, run the side effects, then persist the new status.
     * The callback runs inside the same transaction, so a ledger failure leaves the status untouched.
     */
    private function transition(Escrow $escrow, EscrowStatus $to, Closure $effects): Escrow
    {
        return DB::transaction(function () use ($escrow, $to, $effects) {
            $e = Escrow::whereKey($escrow->id)->lockForUpdate()->firstOrFail();

            if (! $e->status->canTransitionTo($to)) {
                throw new EscrowException("Cannot move escrow from {$e->status->value} to {$to->value}.");
            }

            $effects($e);

            $e->status = $to;
            if ($to->isTerminal()) {
                $e->closed_at = now();
            }
            $e->save();

            return $e;
        });
    }

    /**
     * A single-item listing can only be sold once. The listing row is locked so two buyers paying at the
     * same moment cannot both succeed: the second sees the first's funded escrow and is refused.
     */
    private function assertListingAvailable(Escrow $e): void
    {
        if (! $e->listing_id) {
            return;
        }

        $listing = Listing::whereKey($e->listing_id)->lockForUpdate()->first();
        if (! $listing || $listing->listing_type !== 'single') {
            return;
        }

        if ($listing->status !== 'active') {
            throw new EscrowException('This listing is no longer available.');
        }

        $taken = Escrow::where('listing_id', $listing->id)
            ->where('id', '!=', $e->id)
            ->whereIn('status', [EscrowStatus::Funded->value, EscrowStatus::Delivered->value, EscrowStatus::Disputed->value, EscrowStatus::Released->value])
            ->exists();

        if ($taken) {
            throw new EscrowException('This listing already has a sale in progress.');
        }
    }

    private function payOut(Escrow $e): void
    {
        if ($e->listing_id && $e->listing?->listing_type === 'single') {
            $e->listing->forceFill(['status' => 'sold'])->save();
        }

        $lines = [
            ['account' => LedgerAccount::system(AccountType::EscrowHolding, $e->shard), 'direction' => Direction::Debit, 'amount' => $e->amount],
            ['account' => LedgerAccount::forUser($e->seller), 'direction' => Direction::Credit, 'amount' => $e->sellerPayout()],
        ];
        if ($e->fee > 0) {
            $lines[] = ['account' => LedgerAccount::system(AccountType::PlatformRevenue, $e->shard), 'direction' => Direction::Credit, 'amount' => $e->fee];
        }

        $this->ledger->post('escrow_release', "escrow:{$e->id}:release", $lines, $e);
    }

    private function payBack(Escrow $e): void
    {
        $this->ledger->post('escrow_refund', "escrow:{$e->id}:refund", [
            ['account' => LedgerAccount::system(AccountType::EscrowHolding, $e->shard), 'direction' => Direction::Debit, 'amount' => $e->amount],
            ['account' => LedgerAccount::forUser($e->buyer), 'direction' => Direction::Credit, 'amount' => $e->amount],
        ], $e);
    }

    private function assertActor(User $actor, int $expectedId, string $message, bool $allowAdmin = false): void
    {
        if ($actor->id === $expectedId || ($allowAdmin && $actor->isAdmin())) {
            return;
        }

        throw new EscrowException($message);
    }
}
