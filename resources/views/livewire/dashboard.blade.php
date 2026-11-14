<div class="mx-auto max-w-4xl space-y-6">
    @if ($flash)
        <flux:callout :variant="$flash[0]" icon="information-circle">
            <flux:callout.text>{{ __($flash[1]) }}</flux:callout.text>
        </flux:callout>
    @endif

    <flux:heading size="xl">{{ __('Hello, :name', ['name' => auth()->user()->name]) }}</flux:heading>

    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('wallet.index') }}" wire:navigate class="rounded-lg border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text size="sm">{{ __('Wallet balance') }}</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::naira($this->balance) }}</div>
        </a>
        <a href="{{ route('transactions.index') }}" wire:navigate class="rounded-lg border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text size="sm">{{ __('Open transactions') }}</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ $this->openTransactions->count() }}</div>
        </a>
        <a href="{{ route('listings.mine') }}" wire:navigate class="rounded-lg border border-zinc-200 p-5 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
            <flux:text size="sm">{{ __('Active listings') }}</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ $this->activeListings }}</div>
        </a>
    </div>

    @if ($this->openTransactions->isNotEmpty())
        <div>
            <flux:heading>{{ __('Needs your attention') }}</flux:heading>
            <div class="mt-3 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->openTransactions as $escrow)
                    <a href="{{ route('transactions.show', $escrow->uuid) }}" wire:navigate wire:key="open-{{ $escrow->id }}" class="flex items-center justify-between p-3 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                        <span class="truncate">{{ $escrow->description ?: __('Transaction #:id', ['id' => $escrow->id]) }}</span>
                        <flux:badge :color="$escrow->status->color()">{{ $escrow->status->label() }}</flux:badge>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('marketplace.index')" icon="shopping-bag" wire:navigate>{{ __('Browse marketplace') }}</flux:button>
        <flux:button :href="route('marketplace.sell')" icon="plus" wire:navigate>{{ __('Sell an item') }}</flux:button>
        <flux:button :href="route('brokers.apply')" icon="identification" wire:navigate>{{ __('Become a broker') }}</flux:button>
    </div>
</div>
