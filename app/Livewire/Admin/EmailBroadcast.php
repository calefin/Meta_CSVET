<?php

namespace App\Livewire\Admin;

use App\Mail\AdminBroadcastMail;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.admin')]
class EmailBroadcast extends Component
{
    public string $targetGroup = 'all';

    public string $emailSubject = '';

    public string $emailBody = '';

    public bool $sent = false;

    public int $sentCount = 0;

    protected function rules(): array
    {
        return [
            'targetGroup' => ['required', 'in:all,iniciados,consulentes,admin'],
            'emailSubject' => ['required', 'string', 'max:200'],
            'emailBody' => ['required', 'string'],
        ];
    }

    private function recipients()
    {
        return match ($this->targetGroup) {
            'iniciados' => User::where('role', 'iniciado')->where('status', 'active'),
            'consulentes' => User::where('role', 'consulente')->where('status', 'active'),
            'admin' => User::where('role', 'admin')->where('status', 'active'),
            default => User::where('status', 'active'),
        };
    }

    public function recipientCount(): int
    {
        return $this->recipients()->count();
    }

    public function send(): void
    {
        $validated = $this->validate();

        $recipients = $this->recipients()->get();

        foreach ($recipients as $user) {
            Mail::to($user->email)->queue(new AdminBroadcastMail(
                $user->name,
                $validated['emailSubject'],
                $validated['emailBody']
            ));
        }

        Audit::record('email_broadcast.send');

        $this->sentCount = $recipients->count();
        $this->sent = true;
        $this->reset(['emailSubject', 'emailBody']);
    }

    public function render()
    {
        return view('livewire.admin.email-broadcast', [
            'recipientCount' => $this->recipientCount(),
        ]);
    }
}
