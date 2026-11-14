<?php

namespace App\Livewire\Transactions;

use App\Enums\EscrowStatus;
use App\Models\Escrow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Transactions')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $role = 'all';

    #[Url]
    public string $status = '';

    public function updated(string $name): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function escrows()
    {
        $id = Auth::id();

        return Escrow::with(['buyer:id,name', 'seller:id,name'])
            ->where(fn ($q) => match ($this->role) {
                'buying' => $q->where('buyer_id', $id),
                'selling' => $q->where('seller_id', $id),
                default => $q->where('buyer_id', $id)->orWhere('seller_id', $id),
            })
            ->when(EscrowStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s->value))
            ->latest('id')
            ->paginate(10);
    }

    public function render()
    {
        return view('livewire.transactions.index');
    }
}
