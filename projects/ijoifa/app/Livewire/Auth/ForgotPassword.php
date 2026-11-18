<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.guest', ['title' => 'Recuperar senha', 'noindex' => true])]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $submitted = false;

    public function send(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $throttleKey = 'forgot-password|'.request()->ip();

        // Cap attempts per IP (not per email) to avoid letting someone use
        // this form to spam a victim's inbox with reset emails, while
        // still showing the same generic success message either way.
        if (! RateLimiter::tooManyAttempts($throttleKey, 5)) {
            RateLimiter::hit($throttleKey, 300);

            // Always show the same message regardless of whether the email
            // exists, to avoid leaking which addresses are registered.
            Password::sendResetLink(['email' => $this->email]);
        }

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
