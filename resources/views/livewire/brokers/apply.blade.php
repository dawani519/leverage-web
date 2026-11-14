<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Become a broker') }}</flux:heading>
        <flux:text>{{ __('Apply to be a verified broker. An administrator reviews every application.') }}</flux:text>
    </div>

    @if ($this->existing)
        @php($b = $this->existing)
        <flux:callout :variant="$b->status === 'approved' ? 'success' : ($b->status === 'rejected' ? 'danger' : 'warning')" icon="identification">
            <flux:callout.heading>{{ __('Application :status', ['status' => $b->status]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ $b->fullName() }} · {{ $b->park_name }}, {{ $b->lga }}, {{ $b->state }}
                @if ($b->status === 'rejected' && $b->review_note) <br>{{ __('Reason') }}: {{ $b->review_note }} @endif
            </flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="save" class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="firstName" :label="__('First name')" required />
                <flux:input wire:model="middleName" :label="__('Middle name')" />
                <flux:input wire:model="lastName" :label="__('Last name')" required />
            </div>
            <flux:input wire:model="dob" type="date" :label="__('Date of birth')" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="state" :label="__('State')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach (\App\Livewire\Brokers\Apply::STATES as $s)
                        <flux:select.option :value="$s">{{ $s }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="lga" :label="__('Local government area')" required />
            </div>
            <flux:input wire:model="parkName" :label="__('Park or hub name')" required />
            <flux:input wire:model="phone" type="tel" :label="__('Phone number')" required />
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">{{ __('Submit application') }}</flux:button>
        </form>
    @endif
</div>
