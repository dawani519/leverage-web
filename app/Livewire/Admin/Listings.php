<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Models\AuditLog;
use App\Models\Listing;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Listings')]
class Listings extends Component
{
    use RequiresAdmin;

    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public function updated(string $name): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function listings()
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search)).'%';

        return Listing::with('seller:id,name')
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', $like))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(15);
    }

    /** Moderation: take a listing down, or restore it. Sold listings are history and stay as they are. */
    public function toggleRemoved(int $id): void
    {
        $listing = Listing::findOrFail($id);

        if ($listing->status === 'sold') {
            Flux::toast(variant: 'danger', text: __('A sold listing cannot be changed.'));

            return;
        }

        $to = $listing->status === 'removed' ? 'active' : 'removed';
        $listing->forceFill(['status' => $to])->save();
        AuditLog::record($to === 'removed' ? 'listing.removed' : 'listing.restored', Auth::user(), $listing);
        Flux::toast(variant: 'success', text: $to === 'removed' ? __('Listing removed.') : __('Listing restored.'));
    }

    public function render()
    {
        return view('livewire.admin.listings');
    }
}
