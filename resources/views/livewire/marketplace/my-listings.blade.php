<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('My listings') }}</flux:heading>
        <flux:button :href="route('marketplace.sell')" variant="primary" icon="plus" wire:navigate>{{ __('New listing') }}</flux:button>
    </div>

    @if ($this->listings->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:text>{{ __('You have not listed anything yet.') }}</flux:text>
        </div>
    @else
        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @foreach ($this->listings as $listing)
                <div class="flex items-center gap-4 p-3" wire:key="my-{{ $listing->id }}">
                    <div class="size-16 shrink-0 overflow-hidden rounded bg-zinc-100 dark:bg-zinc-800">
                        @if ($listing->coverUrl()) <img src="{{ $listing->coverUrl() }}" alt="" class="size-full object-cover"> @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('marketplace.show', $listing) }}" wire:navigate class="block truncate font-medium hover:underline">{{ $listing->title }}</a>
                        <div class="text-sm text-zinc-500">{{ \App\Support\Money::naira($listing->price) }}</div>
                    </div>
                    <flux:badge :color="match($listing->status) { 'active' => 'green', 'sold' => 'blue', 'removed' => 'red', default => 'zinc' }">{{ ucfirst($listing->status) }}</flux:badge>
                    @if (in_array($listing->status, ['active', 'paused']))
                        <flux:button size="sm" wire:click="togglePause({{ $listing->id }})">{{ $listing->status === 'active' ? __('Pause') : __('Resume') }}</flux:button>
                    @endif
                    <flux:button size="sm" variant="danger" wire:click="delete({{ $listing->id }})" wire:confirm="{{ __('Delete this listing?') }}">{{ __('Delete') }}</flux:button>
                </div>
            @endforeach
        </div>
        <flux:pagination :paginator="$this->listings" />
    @endif
</div>
