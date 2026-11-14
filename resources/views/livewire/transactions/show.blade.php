@php
    $buyer = $this->isBuyer();
    $seller = $this->isSeller();
@endphp
<div class="mx-auto max-w-3xl space-y-6">
    <flux:button :href="route('transactions.index')" variant="subtle" size="sm" icon="arrow-left" wire:navigate>{{ __('All transactions') }}</flux:button>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading size="xl">{{ $escrow->description ?: __('Transaction #:id', ['id' => $escrow->id]) }}</flux:heading>
            <flux:text>
                {{ __('Buyer') }}: {{ $escrow->buyer->name }} · {{ __('Seller') }}: {{ $escrow->seller->name }}
            </flux:text>
        </div>
        <flux:badge size="lg" :color="$status->color()" data-test="escrow-status">{{ $status->label() }}</flux:badge>
    </div>

    <div class="grid gap-4 rounded-lg border border-zinc-200 p-5 sm:grid-cols-3 dark:border-zinc-700">
        <div><flux:text size="sm">{{ __('Amount') }}</flux:text><div class="text-xl font-semibold">{{ \App\Support\Money::naira($escrow->amount) }}</div></div>
        <div><flux:text size="sm">{{ __('Platform fee') }}</flux:text><div class="text-xl font-semibold">{{ \App\Support\Money::naira($escrow->fee) }}</div></div>
        <div><flux:text size="sm">{{ __('Seller receives') }}</flux:text><div class="text-xl font-semibold">{{ \App\Support\Money::naira($escrow->sellerPayout()) }}</div></div>
    </div>

    <ol class="space-y-1 text-sm text-zinc-500">
        <li>{{ __('Created') }}: {{ $escrow->created_at->format('j M Y, g:i a') }}</li>
        @if ($escrow->funded_at) <li>{{ __('Paid into escrow') }}: {{ $escrow->funded_at->format('j M Y, g:i a') }}</li> @endif
        @if ($escrow->delivered_at) <li>{{ __('Marked delivered') }}: {{ $escrow->delivered_at->format('j M Y, g:i a') }}</li> @endif
        @if ($escrow->auto_release_at && $status === $S::Delivered)
            <li>{{ __('Funds release automatically on') }}: {{ $escrow->auto_release_at->format('j M Y, g:i a') }}</li>
        @endif
        @if ($escrow->closed_at) <li>{{ __('Closed') }}: {{ $escrow->closed_at->format('j M Y, g:i a') }}</li> @endif
    </ol>

    @if ($buyer || $seller)
        <div class="space-y-4">
            @if ($status === $S::PendingPayment)
                @if ($buyer)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text>{{ __('Your wallet balance') }}: <strong>{{ \App\Support\Money::naira($this->walletBalance()) }}</strong></flux:text>
                        @if ($this->walletBalance() < $escrow->amount)
                            <flux:text class="mt-1">
                                {{ __('You need') }} {{ \App\Support\Money::naira($escrow->amount - $this->walletBalance()) }} {{ __('more.') }}
                                <flux:link :href="route('wallet.index')" wire:navigate>{{ __('Add money') }}</flux:link>
                            </flux:text>
                        @endif
                        <div class="mt-3 flex gap-2">
                            <flux:button variant="primary" wire:click="act('fund')" wire:loading.attr="disabled" wire:target="act">{{ __('Pay from wallet') }}</flux:button>
                            <flux:button wire:click="act('cancel')" wire:confirm="{{ __('Cancel this transaction?') }}">{{ __('Cancel') }}</flux:button>
                        </div>
                    </div>
                @else
                    <flux:text>{{ __('Waiting for the buyer to pay.') }}</flux:text>
                    <flux:button wire:click="act('cancel')" wire:confirm="{{ __('Cancel this transaction?') }}">{{ __('Cancel') }}</flux:button>
                @endif
            @endif

            @if (in_array($status, [$S::Funded, $S::Delivered]))
                <div class="flex flex-wrap gap-2">
                    @if ($seller && $status === $S::Funded)
                        <flux:button variant="primary" wire:click="act('deliver')" wire:loading.attr="disabled" wire:target="act">{{ __('Mark as delivered') }}</flux:button>
                    @endif
                    @if ($buyer)
                        <flux:button variant="primary" wire:click="act('release')" wire:confirm="{{ __('Release the money to the seller? This cannot be undone.') }}">{{ __('I received it, release payment') }}</flux:button>
                    @endif
                    @if ($seller)
                        <flux:button wire:click="act('refund')" wire:confirm="{{ __('Refund the buyer in full?') }}">{{ __('Refund buyer') }}</flux:button>
                    @endif
                </div>

                <form wire:submit="openDispute" class="space-y-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Something wrong?') }}</flux:heading>
                    <flux:textarea wire:model="disputeReason" rows="3" :placeholder="__('Describe the problem (at least 10 characters)')" />
                    <flux:button type="submit" variant="danger" size="sm">{{ __('Open a dispute') }}</flux:button>
                </form>
            @endif

            @if ($status === $S::Disputed)
                <flux:callout variant="warning" icon="scale">
                    <flux:callout.heading>{{ __('This transaction is in dispute') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('The money is frozen while an administrator reviews it.') }}</flux:callout.text>
                </flux:callout>
                @foreach ($escrow->disputes as $dispute)
                    <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                        <div class="font-medium">{{ __('Reason') }}</div>
                        <div class="whitespace-pre-line">{{ $dispute->reason }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    @endif
</div>
