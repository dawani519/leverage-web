<div class="mx-auto max-w-6xl space-y-6">
    <flux:heading size="xl">{{ __('Listings') }}</flux:heading>

    <div class="flex gap-3">
        <div class="flex-1"><flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search titles')" clearable /></div>
        <flux:select wire:model.live="status" class="max-w-44">
            <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
            @foreach (['active', 'paused', 'sold', 'removed'] as $s)
                <flux:select.option :value="$s">{{ ucfirst($s) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @foreach ($this->listings as $listing)
            <div class="flex flex-wrap items-center gap-3 p-3" wire:key="l-{{ $listing->id }}">
                <div class="size-12 shrink-0 overflow-hidden rounded bg-zinc-100 dark:bg-zinc-800">
                    @if ($listing->coverUrl()) <img src="{{ $listing->coverUrl() }}" alt="" class="size-full object-cover"> @endif
                </div>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('marketplace.show', $listing) }}" class="block truncate font-medium hover:underline">{{ $listing->title }}</a>
                    <div class="text-sm text-zinc-500">{{ $listing->seller->name }} · {{ \App\Support\Money::naira($listing->price) }}</div>
                </div>
                <flux:badge :color="match($listing->status) { 'active' => 'green', 'sold' => 'blue', 'removed' => 'red', default => 'zinc' }">{{ ucfirst($listing->status) }}</flux:badge>
                @if ($listing->status !== 'sold')
                    <flux:button size="sm" :variant="$listing->status === 'removed' ? 'filled' : 'danger'" wire:click="toggleRemoved({{ $listing->id }})"
                                 wire:confirm="{{ $listing->status === 'removed' ? __('Restore this listing?') : __('Remove this listing from the marketplace?') }}">
                        {{ $listing->status === 'removed' ? __('Restore') : __('Remove') }}
                    </flux:button>
                @endif
            </div>
        @endforeach
    </div>
    <flux:pagination :paginator="$this->listings" />
</div>
