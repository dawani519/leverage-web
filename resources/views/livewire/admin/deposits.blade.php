<div class="mx-auto max-w-6xl space-y-6">
    <flux:heading size="xl">{{ __('Deposits') }}</flux:heading>
    <flux:text>{{ __('Flagged deposits were paid but did not match what we expected. They are never credited automatically: investigate with Paystack using the reference.') }}</flux:text>

    <flux:select wire:model.live="status" class="max-w-44">
        <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
        @foreach (['pending', 'succeeded', 'failed', 'flagged'] as $s)
            <flux:select.option :value="$s">{{ ucfirst($s) }}</flux:select.option>
        @endforeach
    </flux:select>

    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse ($this->deposits as $deposit)
            <div class="flex flex-wrap items-center gap-3 p-3" wire:key="dep-{{ $deposit->id }}">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-mono text-sm">{{ $deposit->reference }}</div>
                    <div class="text-sm text-zinc-500">{{ $deposit->user->name }} ({{ $deposit->user->email }}) · {{ $deposit->created_at->format('j M Y, g:i a') }}</div>
                </div>
                <div class="font-semibold">{{ \App\Support\Money::naira($deposit->amount) }}</div>
                <flux:badge :color="match($deposit->status) { 'succeeded' => 'green', 'flagged' => 'red', 'failed' => 'zinc', default => 'amber' }">{{ ucfirst($deposit->status) }}</flux:badge>
            </div>
        @empty
            <div class="p-6 text-center"><flux:text>{{ __('Nothing here.') }}</flux:text></div>
        @endforelse
    </div>
    <flux:pagination :paginator="$this->deposits" />
</div>
