<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Users')]
class Users extends Component
{
    use RequiresAdmin;

    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search)).'%';

        return User::query()
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)))
            ->latest('id')
            ->paginate(15);
    }

    /** Admins cannot act on their own account, so the last admin can never lock everyone out. */
    private function target(int $id): ?User
    {
        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            Flux::toast(variant: 'danger', text: __('You cannot change your own account here.'));

            return null;
        }

        return $user;
    }

    public function toggleSuspend(int $id): void
    {
        if (! $user = $this->target($id)) {
            return;
        }

        $user->forceFill(['suspended_at' => $user->isSuspended() ? null : now()])->save();
        AuditLog::record($user->isSuspended() ? 'user.suspended' : 'user.unsuspended', Auth::user(), $user);
        Flux::toast(variant: 'success', text: $user->isSuspended() ? __('User suspended.') : __('User reinstated.'));
    }

    public function setRole(int $id, string $role): void
    {
        if (! $user = $this->target($id)) {
            return;
        }
        $new = UserRole::tryFrom($role);
        abort_unless($new, 422);

        $old = $user->role;
        $user->forceFill(['role' => $new])->save();
        AuditLog::record('user.role_changed', Auth::user(), $user, ['from' => $old?->value, 'to' => $new->value]);
        Flux::toast(variant: 'success', text: __('Role updated.'));
    }

    public function render()
    {
        return view('livewire.admin.users');
    }
}
