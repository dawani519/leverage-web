<?php

namespace App\Livewire\Marketplace;

use App\Exceptions\EscrowException;
use App\Models\Conversation;
use App\Models\Listing;
use App\Services\Escrow\EscrowService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::public')]
class Show extends Component
{
    public Listing $listing;

    public function mount(Listing $listing): void
    {
        $user = Auth::user();

        // Paused, removed and sold listings are only visible to their seller and admins.
        if ($listing->status !== 'active') {
            abort_unless($user && ($user->id === $listing->seller_id || $user->isAdmin()), 404);
        }

        $this->listing = $listing->load('seller');
    }

    public function buyWithEscrow(EscrowService $escrows)
    {
        $user = Auth::user();
        if (! $user) {
            return $this->redirectRoute('login', navigate: true);
        }
        if (! $user->hasVerifiedEmail()) {
            return $this->redirectRoute('verification.notice', navigate: true);
        }

        $key = 'escrow-create:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, config('limits.money_per_minute'))) {
            Flux::toast(variant: 'danger', text: __('Too many attempts. Please wait a minute.'));

            return null;
        }
        RateLimiter::hit($key, 60);

        if ($this->listing->status !== 'active') {
            Flux::toast(variant: 'danger', text: __('This listing is no longer available.'));

            return null;
        }

        try {
            $escrow = $escrows->create($user, $this->listing->seller, $this->listing->price, $this->listing, $this->listing->title);
        } catch (EscrowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return null;
        }

        return $this->redirectRoute('transactions.show', $escrow->uuid, navigate: true);
    }

    public function messageSeller()
    {
        $user = Auth::user();
        if (! $user) {
            return $this->redirectRoute('login', navigate: true);
        }
        if ($user->id === $this->listing->seller_id) {
            return null;
        }

        $conversation = Conversation::where([
            'listing_id' => $this->listing->id, 'buyer_id' => $user->id, 'seller_id' => $this->listing->seller_id,
        ])->first() ?? Conversation::forceCreate([
            'listing_id' => $this->listing->id, 'buyer_id' => $user->id, 'seller_id' => $this->listing->seller_id,
        ]);

        return $this->redirectRoute('chat.index', ['c' => $conversation->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.marketplace.show')->title($this->listing->title);
    }
}
