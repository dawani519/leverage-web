<div class="mx-auto max-w-2xl space-y-8">
    <div>
        <flux:heading size="xl">{{ __('Platform settings') }}</flux:heading>
        <flux:text>{{ __('Changes apply to new transactions. Open transactions keep the fee they were created with.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-8">
        <div class="space-y-4">
            <flux:heading>{{ __('Escrow') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="feePercent" :label="__('Platform fee (%)')" inputmode="decimal" />
                <flux:input wire:model="autoReleaseDays" :label="__('Auto-release after (days)')" inputmode="numeric" />
                <flux:input wire:model="minAmount" :label="__('Minimum amount (₦)')" inputmode="decimal" />
                <flux:input wire:model="maxAmount" :label="__('Maximum amount (₦)')" inputmode="decimal" />
            </div>
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Wallet') }}</flux:heading>
            <flux:input wire:model="minDeposit" :label="__('Minimum deposit (₦)')" inputmode="decimal" class="max-w-xs" />
        </div>

        <div class="space-y-4">
            <flux:heading>{{ __('Paystack') }}</flux:heading>
            <flux:input wire:model="paystackPublicKey" :label="__('Public key')" autocomplete="off" />
            <flux:input wire:model="paystackSecret" type="password" :label="__('Secret key')" autocomplete="new-password"
                        :placeholder="$secretIsSet ? __('A key is saved. Type a new one to replace it.') : __('Not set')" />
            <div class="flex items-center gap-3">
                @if ($secretIsSet)
                    <flux:badge color="green">{{ __('Secret key saved') }}</flux:badge>
                    <flux:button size="sm" variant="danger" wire:click="clearSecret" wire:confirm="{{ __('Remove the Paystack secret key? Deposits will stop working.') }}">{{ __('Remove') }}</flux:button>
                @else
                    <flux:badge color="red">{{ __('No secret key: deposits are disabled') }}</flux:badge>
                @endif
            </div>
            <flux:text size="sm">{{ __('Webhook URL to give Paystack:') }} <code>{{ route('webhooks.paystack') }}</code></flux:text>
        </div>

        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save settings') }}</flux:button>
    </form>
</div>
