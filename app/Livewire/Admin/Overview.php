<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Enums\AccountType;
use App\Enums\EscrowStatus;
use App\Models\Broker;
use App\Models\Deposit;
use App\Models\Dispute;
use App\Models\Escrow;
use App\Models\LedgerAccount;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Admin overview')]
class Overview extends Component
{
    use RequiresAdmin;

    #[Computed]
    public function stats(): array
    {
        $holding = LedgerAccount::totalBalance(AccountType::EscrowHolding);
        $openEscrowTotal = (int) Escrow::whereIn('status', [EscrowStatus::Funded->value, EscrowStatus::Delivered->value, EscrowStatus::Disputed->value])->sum('amount');

        return [
            'users' => User::count(),
            'active_listings' => Listing::active()->count(),
            'escrow_by_status' => Escrow::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status')->all(),
            'open_disputes' => Dispute::where('status', 'open')->count(),
            'pending_brokers' => Broker::where('status', 'pending')->count(),
            'flagged_deposits' => Deposit::where('status', 'flagged')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'platform_revenue' => LedgerAccount::totalBalance(AccountType::PlatformRevenue),
            'wallet_liability' => (int) LedgerAccount::where('type', AccountType::UserWallet->value)->sum('balance'),
            'escrow_held' => $holding,
            // A quick integrity signal; the full check is `php artisan ledger:audit`.
            'escrow_matches' => $holding === $openEscrowTotal,
        ];
    }

    public function render()
    {
        return view('livewire.admin.overview');
    }
}
