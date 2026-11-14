<?php

namespace App\Livewire\Wallet;

use App\Exceptions\PaymentException;
use App\Models\Deposit;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Services\Payments\DepositService;
use App\Services\SettingsService;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Wallet')]
class Index extends Component
{
    use WithPagination;

    public string $amount = '';

    public const TYPE_LABELS = [
        'deposit' => 'Wallet top-up',
        'escrow_fund' => 'Paid into escrow',
        'escrow_release' => 'Sale proceeds',
        'escrow_refund' => 'Escrow refund',
    ];

    #[Computed]
    public function balance(): int
    {
        return LedgerAccount::forUser(Auth::user())->fresh()->balance;
    }

    #[Computed]
    public function entries()
    {
        return LedgerEntry::where('ledger_account_id', LedgerAccount::forUser(Auth::user())->id)
            ->with('transaction:id,type')
            ->latest('id')
            ->paginate(10);
    }

    #[Computed]
    public function pendingDeposits()
    {
        return Deposit::where('user_id', Auth::id())->where('status', 'pending')->latest('id')->limit(5)->get();
    }

    public function deposit(DepositService $deposits, SettingsService $settings)
    {
        $user = Auth::user();

        $key = 'deposit-start:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, config('limits.money_per_minute'))) {
            $this->addError('amount', __('Too many attempts. Please wait a minute.'));

            return null;
        }
        RateLimiter::hit($key, 60);

        $kobo = Money::parse($this->amount);
        $min = $settings->int('wallet.min_deposit_kobo');
        if ($kobo === null || $kobo < $min) {
            $this->addError('amount', __('Enter an amount of at least :min.', ['min' => Money::naira($min)]));

            return null;
        }

        try {
            $deposit = $deposits->start($user, $kobo, route('wallet.callback'));
        } catch (PaymentException $e) {
            $this->addError('amount', $e->getMessage());

            return null;
        } catch (\Throwable $e) {
            Log::error('Deposit start failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            Flux::toast(variant: 'danger', text: __('We could not start the payment. Please try again.'));

            return null;
        }

        // Off-site checkout: a normal redirect, not a Livewire navigate.
        return $this->redirect($deposit->authorization_url);
    }

    public function render()
    {
        return view('livewire.wallet.index');
    }
}
