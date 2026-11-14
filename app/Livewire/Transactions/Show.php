<?php

namespace App\Livewire\Transactions;

use App\Enums\EscrowStatus;
use App\Exceptions\EscrowException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Services\Escrow\EscrowService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Transaction')]
class Show extends Component
{
    public Escrow $escrow;

    public string $disputeReason = '';

    public function mount(Escrow $escrow): void
    {
        $user = Auth::user();

        // Only the two parties and admins may see a transaction. 404 rather than 403 so ids are not probeable.
        abort_unless($escrow->buyer_id === $user->id || $escrow->seller_id === $user->id || $user->isAdmin(), 404);

        $this->escrow = $escrow->load(['buyer', 'seller', 'listing', 'disputes']);
    }

    public function isBuyer(): bool
    {
        return $this->escrow->buyer_id === Auth::id();
    }

    public function isSeller(): bool
    {
        return $this->escrow->seller_id === Auth::id();
    }

    public function walletBalance(): int
    {
        return LedgerAccount::forUser(Auth::user())->fresh()->balance;
    }

    /** One entry point for the simple actions. EscrowService re-checks actor and state on every call. */
    public function act(string $action, EscrowService $service): void
    {
        $user = Auth::user();

        // The buttons are only convenience: a non-party can never act, even by crafting a request.
        abort_unless($this->isBuyer() || $this->isSeller(), 403);

        try {
            match ($action) {
                'fund' => $service->fund($this->escrow, $user),
                'deliver' => $service->markDelivered($this->escrow, $user),
                'release' => $service->release($this->escrow, $user),
                'refund' => $service->refund($this->escrow, $user),
                'cancel' => $service->cancel($this->escrow, $user),
                default => abort(422),
            };
        } catch (InsufficientFundsException) {
            Flux::toast(variant: 'danger', text: __('Your wallet balance is too low. Add money in your wallet first.'));

            return;
        } catch (EscrowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->escrow->refresh()->load(['buyer', 'seller', 'listing', 'disputes']);
        Flux::toast(variant: 'success', text: __('Done.'));
    }

    public function openDispute(EscrowService $service): void
    {
        $this->validate(['disputeReason' => ['required', 'string', 'min:10', 'max:2000']]);
        abort_unless($this->isBuyer() || $this->isSeller(), 403);

        try {
            $service->openDispute($this->escrow, Auth::user(), $this->disputeReason);
        } catch (EscrowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->disputeReason = '';
        $this->escrow->refresh()->load(['buyer', 'seller', 'listing', 'disputes']);
        Flux::toast(variant: 'success', text: __('Dispute opened. An administrator will review it.'));
    }

    public function render()
    {
        return view('livewire.transactions.show', ['status' => $this->escrow->status, 'S' => EscrowStatus::class]);
    }
}
