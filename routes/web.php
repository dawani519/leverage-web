<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Payments\DepositCallbackController;
use App\Http\Controllers\Payments\PaystackWebhookController;
use App\Livewire\Brokers\Apply as BrokerApply;
use App\Livewire\Chat\Index as ChatIndex;
use App\Livewire\Dashboard;
use App\Livewire\Marketplace\Index as MarketplaceIndex;
use App\Livewire\Marketplace\MyListings;
use App\Livewire\Marketplace\Sell;
use App\Livewire\Marketplace\Show as ListingShow;
use App\Livewire\Transactions\Create as TransactionCreate;
use App\Livewire\Transactions\Index as TransactionIndex;
use App\Livewire\Transactions\Show as TransactionShow;
use App\Livewire\Wallet\Index as WalletIndex;
use Illuminate\Support\Facades\Route;

// The marketplace is the public front door.
Route::livewire('/', MarketplaceIndex::class)->name('home');

Route::get('health', HealthController::class)->withoutMiddleware('throttle:web')->name('health');

Route::post('webhooks/paystack', PaystackWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.paystack');

Route::prefix('marketplace')->name('marketplace.')->group(function () {
    Route::livewire('/', MarketplaceIndex::class)->name('index');

    Route::middleware(['auth', 'verified', 'not.suspended'])->group(function () {
        Route::livewire('sell', Sell::class)->name('sell');
    });

    Route::livewire('{listing}', ListingShow::class)->whereNumber('listing')->name('show');
});

Route::middleware(['auth', 'verified', 'not.suspended'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');
    Route::livewire('my-listings', MyListings::class)->name('listings.mine');

    Route::livewire('wallet', WalletIndex::class)->name('wallet.index');
    Route::get('wallet/deposit/callback', DepositCallbackController::class)
        ->middleware('throttle:money')
        ->name('wallet.callback');

    Route::livewire('transactions', TransactionIndex::class)->name('transactions.index');
    Route::livewire('transactions/new', TransactionCreate::class)->name('transactions.create');
    Route::livewire('transactions/{escrow:uuid}', TransactionShow::class)->name('transactions.show');

    Route::livewire('messages', ChatIndex::class)->name('chat.index');
    Route::livewire('become-a-broker', BrokerApply::class)->name('brokers.apply');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
