<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\RequiresAdmin;

use App\Models\AuditLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Audit log')]
class AuditLogs extends Component
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
    public function logs()
    {
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($this->search)).'%';

        return AuditLog::with('user:id,name')
            ->when(trim($this->search) !== '', fn ($q) => $q->where('action', 'like', $like))
            ->latest('id')
            ->paginate(25);
    }

    public function render()
    {
        return view('livewire.admin.audit-logs');
    }
}
