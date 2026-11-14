<div class="mx-auto max-w-xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('New transaction') }}</flux:heading>
        <flux:text>{{ __('Set up a protected deal with someone who has an account here.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-5">
        <flux:radio.group wire:model="role" :label="__('I am the')" variant="segmented">
            <flux:radio value="buyer" :label="__('Buyer')" />
            <flux:radio value="seller" :label="__('Seller')" />
        </flux:radio.group>

        <flux:input wire:model="email" type="email" :label="__('Other person\'s email')" required />
        <flux:input wire:model="amount" :label="__('Amount (₦)')" inputmode="decimal" placeholder="50,000" required />
        <flux:textarea wire:model="description" :label="__('What is this for?')" rows="3" maxlength="300" required />

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Create transaction') }}</flux:button>
    </form>
</div>
