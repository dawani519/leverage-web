<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Models\Deposit;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Deposits')]
class Deposits extends Component
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
    public function deposits()
    {
        return Deposit::with('user:id,name,email')
            ->when(in_array($this->status, ['pending', 'succeeded', 'failed', 'flagged'], true), fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(20);
    }

    public function render()
    {
        return view('livewire.admin.deposits');
    }
}
