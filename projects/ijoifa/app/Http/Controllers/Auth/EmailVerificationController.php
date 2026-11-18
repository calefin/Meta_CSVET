<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Verifies via a signed link rather than Laravel's default auth-gated
     * flow, since our newly registered users can't log in yet (they're
     * pending admin approval) when the link arrives.
     */
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals($hash, sha1($user->email)), 403);

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
            \App\Support\Audit::record('email.verified', $user, userId: $user->id);
        }

        return redirect()->route('login')->with('status_message', 'E-mail confirmado com sucesso! Voce ja pode entrar quando seu cadastro for aprovado.');
    }
}
