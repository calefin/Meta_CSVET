<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            session([
                'google_signup' => [
                    'name' => $googleUser->getName() ?: $googleUser->getNickname(),
                    'email' => $googleUser->getEmail(),
                ],
            ]);

            return redirect()->route('register.complete');
        }

        if ($user->status !== 'active') {
            return redirect()->route('login')->with(
                'status_message',
                $user->status === 'pending'
                    ? 'Seu cadastro ainda esta aguardando aprovacao do administrador.'
                    : 'Sua conta esta suspensa. Entre em contato com a administracao.'
            );
        }

        Auth::login($user);
        request()->session()->regenerate();
        Audit::record('login.google', userId: $user->id);

        return redirect()->route('dashboard');
    }
}
