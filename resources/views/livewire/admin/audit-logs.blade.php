<div class="mx-auto max-w-6xl space-y-6">
    <flux:heading size="xl">{{ __('Audit log') }}</flux:heading>
    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Filter by action, e.g. dispute or settings')" clearable />

    <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse ($this->logs as $log)
            <div class="flex flex-wrap items-center gap-3 p-3 text-sm" wire:key="a-{{ $log->id }}">
                <span class="w-40 shrink-0 text-zinc-500">{{ $log->created_at->format('j M Y, g:i a') }}</span>
                <flux:badge>{{ $log->action }}</flux:badge>
                <span>{{ $log->user?->name ?? __('system') }}</span>
                @if ($log->subject_type)
                    <span class="text-zinc-500">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>
                @endif
                @if ($log->meta)
                    <code class="min-w-0 break-all text-xs text-zinc-500">{{ json_encode($log->meta) }}</code>
                @endif
                <span class="ms-auto text-xs text-zinc-500">{{ $log->ip }}</span>
            </div>
        @empty
            <div class="p-6 text-center"><flux:text>{{ __('No entries.') }}</flux:text></div>
        @endforelse
    </div>
    <flux:pagination :paginator="$this->logs" />
</div>
