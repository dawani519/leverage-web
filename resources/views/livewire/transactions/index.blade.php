<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Transactions') }}</flux:heading>
        <flux:button :href="route('transactions.create')" variant="primary" icon="plus" wire:navigate>{{ __('New transaction') }}</flux:button>
    </div>

    <div class="flex gap-3">
        <flux:select wire:model.live="role" class="max-w-40">
            <flux:select.option value="all">{{ __('All') }}</flux:select.option>
            <flux:select.option value="buying">{{ __('Buying') }}</flux:select.option>
            <flux:select.option value="selling">{{ __('Selling') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="status" class="max-w-56">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (\App\Enums\EscrowStatus::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->escrows->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:text>{{ __('No transactions yet.') }}</flux:text>
        </div>
    @else
        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->escrows as $escrow)
                @php($buying = $escrow->buyer_id === auth()->id())
                <a href="{{ route('transactions.show', $escrow->uuid) }}" wire:navigate wire:key="tx-{{ $escrow->id }}"
                   class="flex items-center gap-4 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $escrow->description ?: __('Transaction #:id', ['id' => $escrow->id]) }}</div>
                        <div class="text-sm text-zinc-500">
                            {{ $buying ? __('Buying from :name', ['name' => $escrow->seller->name]) : __('Selling to :name', ['name' => $escrow->buyer->name]) }}
                            · {{ $escrow->created_at->format('j M Y') }}
                        </div>
                    </div>
                    <div class="font-semibold">{{ \App\Support\Money::naira($escrow->amount) }}</div>
                    <flux:badge :color="$escrow->status->color()">{{ $escrow->status->label() }}</flux:badge>
                </a>
            @endforeach
        </div>
        <flux:pagination :paginator="$this->escrows" />
    @endif
</div>
