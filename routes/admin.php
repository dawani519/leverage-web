<?php

use App\Livewire\Admin;
use Illuminate\Support\Facades\Route;

// Every route here is admin-only. EnsureAdmin answers 404 to everyone else.
Route::middleware(['auth', 'verified', 'not.suspended', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::livewire('/', Admin\Overview::class)->name('overview');
        Route::livewire('users', Admin\Users::class)->name('users');
        Route::livewire('listings', Admin\Listings::class)->name('listings');
        Route::livewire('escrows', Admin\Escrows::class)->name('escrows');
        Route::livewire('disputes', Admin\Disputes::class)->name('disputes');
        Route::livewire('deposits', Admin\Deposits::class)->name('deposits');
        Route::livewire('brokers', Admin\Brokers::class)->name('brokers');
        Route::livewire('settings', Admin\Settings::class)->name('settings');
        Route::livewire('audit-log', Admin\AuditLogs::class)->name('audit');
    });
