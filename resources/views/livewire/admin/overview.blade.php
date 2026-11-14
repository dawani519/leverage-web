@php($s = $this->stats)
<div class="mx-auto max-w-6xl space-y-6">
    <flux:heading size="xl">{{ __('Admin overview') }}</flux:heading>

    @if (! $s['escrow_matches'])
        <flux:callout variant="danger" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Escrow holdings do not match open escrows') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Run "php artisan ledger:audit" immediately and investigate before processing more payouts.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Users', number_format($s['users'])],
            ['Active listings', number_format($s['active_listings'])],
            ['Held in escrow', \App\Support\Money::naira($s['escrow_held'])],
            ['Customer wallets', \App\Support\Money::naira($s['wallet_liability'])],
            ['Platform revenue', \App\Support\Money::naira($s['platform_revenue'])],
        ] as [$label, $value])
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text size="sm">{{ __($label) }}</flux:text>
                <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.disputes') }}" wire:navigate class="rounded-lg border p-4 {{ $s['open_disputes'] ? 'border-red-500' : 'border-zinc-200 dark:border-zinc-700' }}">
            <flux:text size="sm">{{ __('Open disputes') }}</flux:text><div class="mt-1 text-2xl font-semibold">{{ $s['open_disputes'] }}</div>
        </a>
        <a href="{{ route('admin.brokers') }}" wire:navigate class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Broker applications') }}</flux:text><div class="mt-1 text-2xl font-semibold">{{ $s['pending_brokers'] }}</div>
        </a>
        <a href="{{ route('admin.deposits') }}" wire:navigate class="rounded-lg border p-4 {{ $s['flagged_deposits'] ? 'border-red-500' : 'border-zinc-200 dark:border-zinc-700' }}">
            <flux:text size="sm">{{ __('Flagged deposits') }}</flux:text><div class="mt-1 text-2xl font-semibold">{{ $s['flagged_deposits'] }}</div>
        </a>
        <div class="rounded-lg border p-4 {{ $s['failed_jobs'] ? 'border-amber-500' : 'border-zinc-200 dark:border-zinc-700' }}">
            <flux:text size="sm">{{ __('Failed queue jobs') }}</flux:text><div class="mt-1 text-2xl font-semibold">{{ $s['failed_jobs'] }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading size="sm">{{ __('Transactions by status') }}</flux:heading>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (\App\Enums\EscrowStatus::cases() as $case)
                <flux:badge :color="$case->color()">{{ $case->label() }}: {{ $s['escrow_by_status'][$case->value] ?? 0 }}</flux:badge>
            @endforeach
        </div>
    </div>
</div>
