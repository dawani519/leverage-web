<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search listings')" clearable />
        </div>
        <flux:select wire:model.live="category" class="sm:max-w-48">
            <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
            @foreach (\App\Models\Listing::CATEGORIES as $key => $label)
                <flux:select.option :value="$key">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="condition" class="sm:max-w-40">
            <flux:select.option value="">{{ __('Any condition') }}</flux:select.option>
            @foreach (\App\Models\Listing::CONDITIONS as $key => $label)
                <flux:select.option :value="$key">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="sort" class="sm:max-w-44">
            <flux:select.option value="newest">{{ __('Newest') }}</flux:select.option>
            <flux:select.option value="price_asc">{{ __('Price: low to high') }}</flux:select.option>
            <flux:select.option value="price_desc">{{ __('Price: high to low') }}</flux:select.option>
        </flux:select>
    </div>

    <div wire:loading.delay class="text-sm text-zinc-500">{{ __('Loading…') }}</div>

    @if ($this->listings->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-600">
            <flux:heading>{{ __('No listings found') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Try a different search or clear the filters.') }}</flux:text>
        </div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            @foreach ($this->listings as $listing)
                <a href="{{ route('marketplace.show', $listing) }}" wire:navigate wire:key="listing-{{ $listing->id }}"
                   class="group overflow-hidden rounded-lg border border-zinc-200 bg-white transition hover:shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="aspect-square bg-zinc-100 dark:bg-zinc-800">
                        @if ($listing->coverUrl())
                            <img src="{{ $listing->coverUrl() }}" alt="{{ $listing->title }}" loading="lazy" class="size-full object-cover">
                        @endif
                    </div>
                    <div class="space-y-1 p-3">
                        <div class="truncate font-medium">{{ $listing->title }}</div>
                        <div class="font-semibold">{{ \App\Support\Money::naira($listing->price) }}</div>
                        <div class="flex items-center justify-between text-xs text-zinc-500">
                            <span class="truncate">{{ $listing->location }}</span>
                            @if ($listing->leverage_fee_supported)
                                <flux:badge size="sm" color="green">{{ __('Escrow') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$this->listings" />
    @endif
</div>
