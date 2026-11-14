<?php

namespace App\Livewire\Marketplace;

use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('My listings')]
class MyListings extends Component
{
    use WithPagination;

    #[Computed]
    public function listings()
    {
        return Listing::where('seller_id', Auth::id())->latest('id')->paginate(10);
    }

    /** Sellers may pause and resume their own listings. Removed (moderated) and sold ones stay as they are. */
    public function togglePause(int $id): void
    {
        $listing = Listing::where('seller_id', Auth::id())->findOrFail($id);

        if (! in_array($listing->status, ['active', 'paused'], true)) {
            Flux::toast(variant: 'danger', text: __('This listing cannot be changed.'));

            return;
        }

        $listing->forceFill(['status' => $listing->status === 'active' ? 'paused' : 'active'])->save();
    }

    public function delete(int $id): void
    {
        $listing = Listing::where('seller_id', Auth::id())->findOrFail($id);

        // Cannot delete while money is held for it.
        if (\App\Models\Escrow::where('listing_id', $listing->id)->whereIn('status', ['funded', 'delivered', 'disputed'])->exists()) {
            Flux::toast(variant: 'danger', text: __('A sale is in progress for this listing.'));

            return;
        }

        $listing->delete();
        Flux::toast(variant: 'success', text: __('Listing deleted.'));
    }

    public function render()
    {
        return view('livewire.marketplace.my-listings');
    }
}
