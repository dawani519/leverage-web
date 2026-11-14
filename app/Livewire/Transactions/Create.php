<?php

namespace App\Livewire\Transactions;

use App\Exceptions\EscrowException;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('New transaction')]
class Create extends Component
{
    public string $role = 'buyer';

    public string $email = '';

    public string $amount = '';

    public string $description = '';

    protected function rules(): array
    {
        return [
            'role' => ['required', 'in:buyer,seller'],
            'email' => ['required', 'email'],
            'amount' => ['required', 'string'],
            'description' => ['required', 'string', 'max:300'],
        ];
    }

    public function save(EscrowService $escrows)
    {
        $this->validate();
        $me = Auth::user();

        $key = 'escrow-create:'.$me->id;
        if (RateLimiter::tooManyAttempts($key, config('limits.money_per_minute'))) {
            $this->addError('email', __('Too many attempts. Please wait a minute.'));

            return null;
        }
        RateLimiter::hit($key, 60);

        $kobo = Money::parse($this->amount);
        $other = User::where('email', $this->email)->first();

        // One message for "no such user" and "that is you", so this form is not a way to probe for accounts.
        if ($kobo === null || ! $other || $other->is($me)) {
            $this->addError('email', __('We could not set up a transaction with those details.'));

            return null;
        }

        [$buyer, $seller] = $this->role === 'buyer' ? [$me, $other] : [$other, $me];

        try {
            $escrow = $escrows->create($buyer, $seller, $kobo, null, trim($this->description));
        } catch (EscrowException $e) {
            $this->addError('amount', $e->getMessage());

            return null;
        }

        return $this->redirectRoute('transactions.show', $escrow->uuid, navigate: true);
    }

    public function render()
    {
        return view('livewire.transactions.create');
    }
}
