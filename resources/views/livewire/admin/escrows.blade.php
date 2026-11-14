<div class="mx-auto max-w-6xl space-y-6">
    <flux:heading size="xl">{{ __('All transactions') }}</flux:heading>

    <flux:select wire:model.live="status" class="max-w-60">
        <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
        @foreach (\App\Enums\EscrowStatus::cases() as $case)
            <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
        @endforeach
    </flux:select>

    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse ($this->escrows as $escrow)
            <a href="{{ route('transactions.show', $escrow->uuid) }}" wire:navigate wire:key="e-{{ $escrow->id }}" class="flex flex-wrap items-center gap-3 p-3 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ $escrow->description ?: __('Transaction #:id', ['id' => $escrow->id]) }}</div>
                    <div class="text-sm text-zinc-500">{{ $escrow->buyer->name }} → {{ $escrow->seller->name }} · {{ $escrow->created_at->format('j M Y') }}</div>
                </div>
                <div class="font-semibold">{{ \App\Support\Money::naira($escrow->amount) }}</div>
                <flux:badge :color="$escrow->status->color()">{{ $escrow->status->label() }}</flux:badge>
            </a>
        @empty
            <div class="p-6 text-center"><flux:text>{{ __('Nothing here.') }}</flux:text></div>
        @endforelse
    </div>
    <flux:pagination :paginator="$this->escrows" />
</div>
