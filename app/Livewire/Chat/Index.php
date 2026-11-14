<?php

namespace App\Livewire\Chat;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Messages')]
class Index extends Component
{
    /** Client-modifiable, so every read re-scopes to the signed-in user. */
    #[Url(as: 'c')]
    public ?int $conversationId = null;

    public string $body = '';

    #[Computed]
    public function conversations()
    {
        return Conversation::for(Auth::user())
            ->with(['listing:id,title', 'buyer:id,name', 'seller:id,name'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function current(): ?Conversation
    {
        if (! $this->conversationId) {
            return null;
        }

        // Scoped to the user: a tampered id for someone else's thread simply returns null.
        return Conversation::for(Auth::user())->with(['listing:id,title', 'buyer:id,name', 'seller:id,name'])->find($this->conversationId);
    }

    #[Computed]
    public function messages()
    {
        $conversation = $this->current;
        if (! $conversation) {
            return collect();
        }

        // Mark the other person's messages as read.
        $conversation->messages()->where('sender_id', '!=', Auth::id())->whereNull('read_at')->update(['read_at' => now()]);

        return $conversation->messages()->latest('id')->limit(100)->get()->reverse()->values();
    }

    public function select(int $id): void
    {
        $this->conversationId = Conversation::for(Auth::user())->whereKey($id)->value('id');
        unset($this->current, $this->messages);
    }

    public function send(): void
    {
        $conversation = $this->current;
        abort_unless($conversation, 404);

        $this->validate(['body' => ['required', 'string', 'max:2000']]);

        $key = 'chat-send:'.Auth::id();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            $this->addError('body', __('You are sending messages too fast.'));

            return;
        }
        RateLimiter::hit($key, 60);

        Message::forceCreate(['conversation_id' => $conversation->id, 'sender_id' => Auth::id(), 'body' => trim($this->body)]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        $this->body = '';
        unset($this->messages, $this->conversations);
    }

    public function render()
    {
        return view('livewire.chat.index');
    }
}
