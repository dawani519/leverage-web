<div class="mx-auto max-w-5xl">
    @if ($listing->status !== 'active')
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-4">
            <flux:callout.heading>{{ __('This listing is :status', ['status' => $listing->status]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('Only you and administrators can see it.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-8 md:grid-cols-2">
        <div class="space-y-3">
            @php($images = $listing->imageUrls())
            <div class="aspect-square overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                @if ($images)
                    <img src="{{ $images[0] }}" alt="{{ $listing->title }}" class="size-full object-cover">
                @endif
            </div>
            @if (count($images) > 1)
                <div class="grid grid-cols-5 gap-2">
                    @foreach (array_slice($images, 1) as $url)
                        <img src="{{ $url }}" alt="" loading="lazy" class="aspect-square rounded object-cover">
                    @endforeach
                </div>
            @endif
        </div>

        <div class="space-y-5">
            <div>
                <flux:heading size="xl">{{ $listing->title }}</flux:heading>
                <div class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::naira($listing->price) }}</div>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:badge>{{ \App\Models\Listing::CATEGORIES[$listing->category] ?? $listing->category }}</flux:badge>
                <flux:badge>{{ \App\Models\Listing::CONDITIONS[$listing->condition] ?? $listing->condition }}</flux:badge>
                @if ($listing->offers_shipping) <flux:badge color="blue">{{ __('Shipping offered') }}</flux:badge> @endif
                @if ($listing->leverage_fee_supported) <flux:badge color="green">{{ __('Escrow protected') }}</flux:badge> @endif
            </div>

            <flux:text class="whitespace-pre-line">{{ $listing->description }}</flux:text>

            <div class="text-sm text-zinc-500">
                {{ $listing->location }} · {{ __('Seller') }}: {{ $listing->seller->name }}
            </div>

            @if (auth()->id() !== $listing->seller_id && $listing->status === 'active')
                <div class="flex flex-col gap-2 sm:flex-row">
                    <flux:button variant="primary" wire:click="buyWithEscrow" wire:loading.attr="disabled" icon="shield-check" class="flex-1">
                        {{ __('Buy with escrow') }}
                    </flux:button>
                    <flux:button wire:click="messageSeller" icon="chat-bubble-left-right" class="flex-1">{{ __('Message seller') }}</flux:button>
                </div>
                <flux:text size="sm">{{ __('Your payment is held safely until you confirm you received the item.') }}</flux:text>
            @endif
        </div>
    </div>
</div>
