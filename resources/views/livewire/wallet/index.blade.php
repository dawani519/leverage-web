<div class="mx-auto max-w-3xl space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Wallet') }}</flux:heading>
        <flux:text>{{ __('Money in your wallet can pay for escrow purchases. Sale proceeds land here.') }}</flux:text>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:text size="sm">{{ __('Available balance') }}</flux:text>
            <div class="mt-1 text-3xl font-semibold" data-test="wallet-balance">{{ \App\Support\Money::naira($this->balance) }}</div>
        </div>

        <form wire:submit="deposit" class="space-y-3 rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:input wire:model="amount" :label="__('Add money (₦)')" inputmode="decimal" placeholder="10,000" />
            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="deposit">{{ __('Pay with Paystack') }}</flux:button>
        </form>
    </div>

    @if ($this->pendingDeposits->isNotEmpty())
        <flux:callout icon="clock">
            <flux:callout.text>{{ __('You have a payment still being confirmed. Your balance updates as soon as it clears.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div>
        <flux:heading>{{ __('History') }}</flux:heading>
        @if ($this->entries->isEmpty())
            <flux:text class="mt-2">{{ __('No activity yet.') }}</flux:text>
        @else
            <div class="mt-3 divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @foreach ($this->entries as $entry)
                    @php($credit = $entry->direction === \App\Enums\Direction::Credit)
                    <div class="flex items-center justify-between p-3" wire:key="entry-{{ $entry->id }}">
                        <div>
                            <div class="font-medium">{{ \App\Livewire\Wallet\Index::TYPE_LABELS[$entry->transaction->type] ?? ucfirst(str_replace('_', ' ', $entry->transaction->type)) }}</div>
                            <div class="text-xs text-zinc-500">{{ $entry->created_at->format('j M Y, g:i a') }}</div>
                        </div>
                        <div class="{{ $credit ? 'text-green-500' : 'text-red-400' }} font-semibold">
                            {{ $credit ? '+' : '-' }}{{ \App\Support\Money::naira($entry->amount) }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-3"><flux:pagination :paginator="$this->entries" /></div>
        @endif
    </div>
</div>
