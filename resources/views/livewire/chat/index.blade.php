<div class="mx-auto grid max-w-5xl gap-4 md:grid-cols-3" wire:poll.5s>
    <div class="space-y-2 md:col-span-1">
        <flux:heading size="lg">{{ __('Messages') }}</flux:heading>

        @forelse ($this->conversations as $conversation)
            @php($other = $conversation->otherParty(auth()->user()))
            <button type="button" wire:click="select({{ $conversation->id }})" wire:key="conv-{{ $conversation->id }}"
                    class="{{ $conversationId === $conversation->id ? 'bg-zinc-100 dark:bg-zinc-700' : 'hover:bg-zinc-50 dark:hover:bg-zinc-900' }} block w-full rounded-lg border border-zinc-200 p-3 text-start dark:border-zinc-700">
                <div class="truncate font-medium">{{ $other->name }}</div>
                <div class="truncate text-xs text-zinc-500">{{ $conversation->listing?->title ?? __('Direct message') }}</div>
            </button>
        @empty
            <flux:text>{{ __('No conversations yet. Open a listing and tap "Message seller".') }}</flux:text>
        @endforelse
    </div>

    <div class="md:col-span-2">
        @if ($this->current)
            @php($other = $this->current->otherParty(auth()->user()))
            <div class="flex h-[70vh] flex-col rounded-lg border border-zinc-200 dark:border-zinc-700">
                <div class="border-b border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="font-medium">{{ $other->name }}</div>
                    @if ($this->current->listing)
                        <a href="{{ route('marketplace.show', $this->current->listing_id) }}" wire:navigate class="text-xs text-zinc-500 hover:underline">{{ $this->current->listing->title }}</a>
                    @endif
                </div>

                <div class="flex-1 space-y-2 overflow-y-auto p-3">
                    @forelse ($this->messages as $message)
                        @php($mine = $message->sender_id === auth()->id())
                        <div class="{{ $mine ? 'justify-end' : 'justify-start' }} flex" wire:key="msg-{{ $message->id }}">
                            <div class="{{ $mine ? 'bg-blue-600 text-white' : 'bg-zinc-200 dark:bg-zinc-700' }} max-w-[80%] whitespace-pre-line break-words rounded-2xl px-3 py-2 text-sm">{{ $message->body }}</div>
                        </div>
                    @empty
                        <flux:text class="text-center">{{ __('Say hello.') }}</flux:text>
                    @endforelse
                </div>

                <form wire:submit="send" class="flex gap-2 border-t border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex-1"><flux:input wire:model="body" :placeholder="__('Type a message')" maxlength="2000" autocomplete="off" /></div>
                    <flux:button type="submit" variant="primary" icon="paper-airplane" />
                </form>
                <flux:error name="body" class="px-3 pb-2" />
            </div>
        @else
            <div class="flex h-[40vh] items-center justify-center rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600">
                <flux:text>{{ __('Choose a conversation.') }}</flux:text>
            </div>
        @endif
    </div>
</div>
