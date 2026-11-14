<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Enums\EscrowStatus;
use App\Models\Escrow;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('All transactions')]
class Escrows extends Component
{
    use RequiresAdmin;

    use WithPagination;

    #[Url]
    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function escrows()
    {
        return Escrow::with(['buyer:id,name', 'seller:id,name'])
            ->when(EscrowStatus::tryFrom($this->status), fn ($q, $s) => $q->where('status', $s->value))
            ->latest('id')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.admin.escrows');
    }
}
