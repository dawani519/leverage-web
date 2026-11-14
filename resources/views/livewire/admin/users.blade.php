<div class="mx-auto max-w-6xl space-y-6">
    <flux:heading size="xl">{{ __('Users') }}</flux:heading>
    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" clearable />

    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @foreach ($this->users as $user)
            <div class="flex flex-wrap items-center gap-3 p-3" wire:key="u-{{ $user->id }}">
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ $user->name }}</div>
                    <div class="truncate text-sm text-zinc-500">{{ $user->email }} · {{ __('joined') }} {{ $user->created_at->format('j M Y') }}</div>
                </div>
                @if ($user->isSuspended()) <flux:badge color="red">{{ __('Suspended') }}</flux:badge> @endif
                <flux:badge :color="$user->role === \App\Enums\UserRole::Admin ? 'purple' : 'zinc'">{{ ucfirst($user->role?->value ?? 'user') }}</flux:badge>

                @if ($user->id !== auth()->id())
                    <flux:dropdown>
                        <flux:button size="sm" icon-trailing="chevron-down">{{ __('Role') }}</flux:button>
                        <flux:menu>
                            @foreach (\App\Enums\UserRole::cases() as $role)
                                <flux:menu.item wire:click="setRole({{ $user->id }}, '{{ $role->value }}')">{{ ucfirst($role->value) }}</flux:menu.item>
                            @endforeach
                        </flux:menu>
                    </flux:dropdown>
                    <flux:button size="sm" :variant="$user->isSuspended() ? 'filled' : 'danger'" wire:click="toggleSuspend({{ $user->id }})"
                                 wire:confirm="{{ $user->isSuspended() ? __('Reinstate this user?') : __('Suspend this user?') }}">
                        {{ $user->isSuspended() ? __('Reinstate') : __('Suspend') }}
                    </flux:button>
                @else
                    <flux:text size="sm">{{ __('You') }}</flux:text>
                @endif
            </div>
        @endforeach
    </div>
    <flux:pagination :paginator="$this->users" />
</div>
