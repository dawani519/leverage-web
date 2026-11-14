<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Broker;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Broker applications')]
class Brokers extends Component
{
    use RequiresAdmin;

    #[Url]
    public string $tab = 'pending';

    /** @var array<int, string> */
    public array $notes = [];

    #[Computed]
    public function brokers()
    {
        return Broker::with('user:id,name,email')
            ->where('status', in_array($this->tab, ['pending', 'approved', 'rejected'], true) ? $this->tab : 'pending')
            ->latest('id')->limit(50)->get();
    }

    public function review(int $id, string $decision): void
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        $note = trim($this->notes[$id] ?? '');

        if ($decision === 'rejected' && mb_strlen($note) < 5) {
            $this->addError("notes.$id", __('Give a short reason for rejecting.'));

            return;
        }

        $broker = Broker::with('user')->findOrFail($id);
        if ($broker->status !== 'pending') {
            Flux::toast(variant: 'danger', text: __('This application was already reviewed.'));

            return;
        }

        $broker->forceFill([
            'status' => $decision, 'reviewed_by' => Auth::id(), 'review_note' => $note ?: null, 'reviewed_at' => now(),
        ])->save();

        // Approval marks ordinary users as brokers; it never touches an admin's role.
        if ($decision === 'approved' && $broker->user->role === UserRole::User) {
            $broker->user->forceFill(['role' => UserRole::Broker])->save();
        }

        AuditLog::record("broker.{$decision}", Auth::user(), $broker);
        unset($this->brokers, $this->notes[$id]);
        Flux::toast(variant: 'success', text: __('Application :d.', ['d' => $decision]));
    }

    public function render()
    {
        return view('livewire.admin.brokers');
    }
}
