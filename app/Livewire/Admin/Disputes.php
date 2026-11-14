<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Exceptions\EscrowException;
use App\Models\Dispute;
use App\Services\Escrow\EscrowService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Disputes')]
class Disputes extends Component
{
    use RequiresAdmin;

    #[Url]
    public string $tab = 'open';

    /** @var array<int, string> keyed by dispute id */
    public array $notes = [];

    #[Computed]
    public function disputes()
    {
        return Dispute::with(['escrow.buyer:id,name', 'escrow.seller:id,name', 'opener:id,name'])
            ->where('status', $this->tab === 'resolved' ? 'resolved' : 'open')
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function resolve(int $id, string $outcome, EscrowService $service): void
    {
        $note = trim($this->notes[$id] ?? '');
        if (mb_strlen($note) < 10) {
            $this->addError("notes.$id", __('Write a resolution note of at least 10 characters.'));

            return;
        }

        try {
            // EscrowService re-checks that the actor is an admin and the dispute is still open.
            $service->resolveDispute(Dispute::findOrFail($id), Auth::user(), $outcome, $note);
        } catch (EscrowException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        unset($this->notes[$id], $this->disputes);
        Flux::toast(variant: 'success', text: __('Dispute resolved.'));
    }

    public function render()
    {
        return view('livewire.admin.disputes');
    }
}
