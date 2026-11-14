<div class="mx-auto max-w-4xl space-y-6">
    <flux:heading size="xl">{{ __('Disputes') }}</flux:heading>

    <flux:radio.group wire:model.live="tab" variant="segmented" class="max-w-xs">
        <flux:radio value="open" :label="__('Open')" />
        <flux:radio value="resolved" :label="__('Resolved')" />
    </flux:radio.group>

    @forelse ($this->disputes as $dispute)
        @php($e = $dispute->escrow)
        <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="d-{{ $dispute->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <a href="{{ route('transactions.show', $e->uuid) }}" class="font-medium hover:underline">
                    {{ $e->description ?: __('Transaction #:id', ['id' => $e->id]) }}
                </a>
                <span class="font-semibold">{{ \App\Support\Money::naira($e->amount) }}</span>
            </div>
            <flux:text size="sm">{{ __('Buyer') }}: {{ $e->buyer->name }} · {{ __('Seller') }}: {{ $e->seller->name }} · {{ __('Opened by') }} {{ $dispute->opener->name }}</flux:text>
            <div class="whitespace-pre-line rounded bg-zinc-100 p-3 text-sm dark:bg-zinc-800">{{ $dispute->reason }}</div>

            @if ($dispute->status === 'open')
                <flux:textarea wire:model="notes.{{ $dispute->id }}" rows="2" :placeholder="__('Resolution note (recorded in the audit log)')" />
                <flux:error name="notes.{{ $dispute->id }}" />
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="primary" wire:click="resolve({{ $dispute->id }}, 'release')"
                                 wire:confirm="{{ __('Pay the seller (minus the platform fee)? This cannot be undone.') }}">{{ __('Pay seller') }}</flux:button>
                    <flux:button size="sm" wire:click="resolve({{ $dispute->id }}, 'refund')"
                                 wire:confirm="{{ __('Refund the buyer in full? This cannot be undone.') }}">{{ __('Refund buyer') }}</flux:button>
                </div>
            @else
                <flux:text size="sm">
                    {{ __('Resolved') }}: {{ $dispute->outcome === 'release' ? __('seller paid') : __('buyer refunded') }}
                    · {{ $dispute->resolved_at?->format('j M Y') }} — {{ $dispute->resolution_note }}
                </flux:text>
            @endif
        </div>
    @empty
        <flux:text>{{ __('Nothing here.') }}</flux:text>
    @endforelse
</div>
