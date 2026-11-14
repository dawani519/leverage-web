<div class="mx-auto max-w-4xl space-y-6">
    <flux:heading size="xl">{{ __('Broker applications') }}</flux:heading>

    <flux:radio.group wire:model.live="tab" variant="segmented" class="max-w-sm">
        <flux:radio value="pending" :label="__('Pending')" />
        <flux:radio value="approved" :label="__('Approved')" />
        <flux:radio value="rejected" :label="__('Rejected')" />
    </flux:radio.group>

    @forelse ($this->brokers as $broker)
        <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700" wire:key="b-{{ $broker->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="font-medium">{{ $broker->fullName() }}</div>
                <flux:badge :color="match($broker->status) { 'approved' => 'green', 'rejected' => 'red', default => 'amber' }">{{ ucfirst($broker->status) }}</flux:badge>
            </div>
            <flux:text size="sm">
                {{ $broker->user->email }} · {{ $broker->phone }} · {{ __('Born') }} {{ $broker->dob->format('j M Y') }}<br>
                {{ $broker->park_name }}, {{ $broker->lga }}, {{ $broker->state }}
            </flux:text>

            @if ($broker->status === 'pending')
                <flux:input wire:model="notes.{{ $broker->id }}" :placeholder="__('Note (required when rejecting)')" />
                <flux:error name="notes.{{ $broker->id }}" />
                <div class="flex gap-2">
                    <flux:button size="sm" variant="primary" wire:click="review({{ $broker->id }}, 'approved')">{{ __('Approve') }}</flux:button>
                    <flux:button size="sm" variant="danger" wire:click="review({{ $broker->id }}, 'rejected')">{{ __('Reject') }}</flux:button>
                </div>
            @elseif ($broker->review_note)
                <flux:text size="sm">{{ __('Note') }}: {{ $broker->review_note }}</flux:text>
            @endif
        </div>
    @empty
        <flux:text>{{ __('Nothing here.') }}</flux:text>
    @endforelse
</div>
