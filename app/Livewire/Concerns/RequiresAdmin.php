<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Defence in depth for admin components. Route middleware only guards the first page load; Livewire
 * actions arrive later as separate requests. This hook runs on every request to the component (initial
 * render and every action), so a demoted or suspended admin loses access immediately, not at next reload.
 */
trait RequiresAdmin
{
    public function bootRequiresAdmin(): void
    {
        $user = Auth::user();

        abort_unless($user && $user->isAdmin() && ! $user->isSuspended(), 404);
    }
}
