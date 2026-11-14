<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Sell an item') }}</flux:heading>
        <flux:text>{{ __('Add at least 3 clear photos. Buyers pay into escrow, so you are paid once they confirm delivery.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-5">
        <flux:input wire:model="title" :label="__('Title')" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:select wire:model="category" :label="__('Category')" required>
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach (\App\Models\Listing::CATEGORIES as $key => $label)
                    <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="condition" :label="__('Condition')">
                @foreach (\App\Models\Listing::CONDITIONS as $key => $label)
                    <flux:select.option :value="$key">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="price" :label="__('Price (₦)')" inputmode="decimal" placeholder="25,000" required />
            <flux:input wire:model="location" :label="__('Location')" required />
        </div>

        <flux:textarea wire:model="description" :label="__('Description')" rows="5" required />

        <div class="space-y-2">
            <flux:switch wire:model="offersShipping" :label="__('I can ship this item')" />
            <flux:switch wire:model="escrowProtected" :label="__('Offer escrow protection')" />
        </div>

        <flux:field>
            <flux:label>{{ __('Photos (3 to 10)') }}</flux:label>
            <input type="file" wire:model="photos" multiple accept="image/jpeg,image/png,image/webp"
                   class="block w-full text-sm file:me-3 file:rounded file:border-0 file:bg-zinc-200 file:px-3 file:py-2 dark:file:bg-zinc-700">
            <div wire:loading wire:target="photos" class="text-sm text-zinc-500">{{ __('Uploading…') }}</div>
            <flux:error name="photos" />
            <flux:error name="photos.*" />
        </flux:field>

        @if ($photos)
            <div class="grid grid-cols-5 gap-2">
                @foreach ($photos as $i => $photo)
                    <div class="relative" wire:key="photo-{{ $i }}">
                        {{-- temporaryUrl() throws for non-images, so only preview files Livewire says are previewable. --}}
                        @if ($photo->isPreviewable())
                            <img src="{{ $photo->temporaryUrl() }}" alt="" class="aspect-square rounded object-cover">
                        @else
                            <div class="flex aspect-square items-center justify-center rounded border border-red-500 p-1 text-center text-xs text-red-400">{{ __('Not an image') }}</div>
                        @endif
                        <button type="button" wire:click="removePhoto({{ $i }})"
                                class="absolute end-1 top-1 rounded-full bg-black/70 px-1.5 text-xs text-white" aria-label="{{ __('Remove photo') }}">×</button>
                    </div>
                @endforeach
            </div>
        @endif

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Publish listing') }}</flux:button>
    </form>
</div>
