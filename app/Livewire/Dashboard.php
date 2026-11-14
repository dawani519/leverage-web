<?php

namespace App\Livewire;

use App\Enums\EscrowStatus;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Models\Listing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public const FLASH = [
        'deposit-succeeded' => ['success', 'Payment received. Your wallet has been topped up.'],
        'deposit-pending' => ['warning', 'Your payment is still being confirmed. Your balance will update shortly.'],
        'deposit-failed' => ['danger', 'That payment did not go through.'],
        'deposit-flagged' => ['danger', 'We could not verify that payment. Contact support with your reference.'],
        'deposit-unknown' => ['danger', 'We could not find that payment.'],
    ];

    #[Computed]
    public function balance(): int
    {
        return LedgerAccount::forUser(Auth::user())->fresh()->balance;
    }

    #[Computed]
    public function openTransactions()
    {
        $id = Auth::id();

        return Escrow::where(fn ($q) => $q->where('buyer_id', $id)->orWhere('seller_id', $id))
            ->whereNotIn('status', [EscrowStatus::Released->value, EscrowStatus::Refunded->value, EscrowStatus::Cancelled->value])
            ->latest('id')->limit(5)->get();
    }

    #[Computed]
    public function activeListings(): int
    {
        return Listing::where('seller_id', Auth::id())->active()->count();
    }

    public function render()
    {
        return view('livewire.dashboard', ['flash' => self::FLASH[session('status')] ?? null]);
    }
}
