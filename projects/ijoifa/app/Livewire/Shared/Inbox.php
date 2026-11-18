<?php

namespace App\Livewire\Shared;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Inbox extends Component
{
    public ?int $selectedUserId = null;

    public bool $showComposeForm = false;

    public string $newRecipientId = '';

    public string $subject = '';

    public string $body = '';

    public function mount(): void
    {
        if (request()->has('user_id')) {
            $userId = (int) request()->query('user_id');
            $recipient = User::find($userId);
            if ($recipient && Auth::user()->canMessage($recipient)) {
                $this->openConversation($userId);
            }
        }
    }

    public function openConversation(int $userId): void
    {
        $this->selectedUserId = $userId;
        $this->showComposeForm = false;

        Message::where('sender_id', $userId)
            ->where('recipient_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function openCompose(): void
    {
        $this->selectedUserId = null;
        $this->showComposeForm = true;
        $this->reset(['newRecipientId', 'subject', 'body']);
    }

    public function send(): void
    {
        $validated = $this->validate([
            'newRecipientId' => ['required', 'exists:users,id'],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        $recipient = User::findOrFail($validated['newRecipientId']);

        abort_unless(Auth::user()->canMessage($recipient), 403);

        Message::create([
            'sender_id' => Auth::id(),
            'recipient_id' => $recipient->id,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
        ]);

        $this->selectedUserId = $recipient->id;
        $this->showComposeForm = false;
        $this->reset(['newRecipientId', 'subject', 'body']);
    }

    public function reply(): void
    {
        $validated = $this->validate(['body' => ['required', 'string']]);

        $recipient = User::findOrFail($this->selectedUserId);

        abort_unless(Auth::user()->canMessage($recipient), 403);

        Message::create([
            'sender_id' => Auth::id(),
            'recipient_id' => $recipient->id,
            'body' => $validated['body'],
        ]);

        $this->body = '';
    }

    public function render()
    {
        $user = Auth::user();

        $conversationUserIds = Message::where('sender_id', $user->id)->pluck('recipient_id')
            ->merge(Message::where('recipient_id', $user->id)->pluck('sender_id'))
            ->unique();

        $conversations = User::whereIn('id', $conversationUserIds)
            ->get()
            ->map(function (User $other) use ($user) {
                $other->unread_count = Message::where('sender_id', $other->id)
                    ->where('recipient_id', $user->id)
                    ->whereNull('read_at')
                    ->count();
                $other->last_message_at = Message::where(function ($q) use ($other, $user) {
                    $q->where('sender_id', $user->id)->where('recipient_id', $other->id);
                })->orWhere(function ($q) use ($other, $user) {
                    $q->where('sender_id', $other->id)->where('recipient_id', $user->id);
                })->latest()->value('created_at');

                return $other;
            })
            ->sortByDesc('last_message_at');

        $thread = $this->selectedUserId
            ? Message::where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)->where('recipient_id', $this->selectedUserId);
            })->orWhere(function ($q) use ($user) {
                $q->where('sender_id', $this->selectedUserId)->where('recipient_id', $user->id);
            })->orderBy('created_at')->get()
            : collect();

        $allowedRecipients = match ($user->role) {
            'admin' => User::where('id', '!=', $user->id)->orderBy('name')->get(),
            'iniciado' => User::where('id', '!=', $user->id)
                ->whereIn('role', ['iniciado', 'admin'])
                ->orderBy('name')->get(),
            default => User::where('role', 'admin')->orderBy('name')->get(),
        };

        $layout = match ($user->role) {
            'admin' => 'components.layouts.admin',
            'iniciado' => 'components.layouts.iniciado',
            default => 'components.layouts.consulente',
        };

        return view('livewire.shared.inbox', [
            'conversations' => $conversations,
            'thread' => $thread,
            'selectedUser' => $this->selectedUserId ? User::find($this->selectedUserId) : null,
            'allowedRecipients' => $allowedRecipients,
        ])->layout($layout, ['title' => 'Mensagens']);
    }
}
