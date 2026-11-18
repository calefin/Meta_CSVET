<?php

namespace App\Http\Controllers;

use App\Models\ExternalClassGroup;
use App\Models\ExternalClassParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ExternalClassAccessController extends Controller
{
    protected const GENERIC_ERROR = 'Dados não encontrados ou não conferem. Verifique o RGM e o e-mail informados.';

    /**
     * Tela A: identificação da turma pelo token público (sem nenhum dado
     * pessoal de participantes carregado aqui).
     */
    public function show(Request $request, string $token)
    {
        $group = ExternalClassGroup::where('token', $token)->where('active', true)->firstOrFail();

        if ($this->hasValidSession($request, $group)) {
            return redirect()->route('estagio.documentos', ['token' => $token]);
        }

        return view('estagio.turma_landing', ['group' => $group]);
    }

    public function verify(Request $request, string $token)
    {
        $group = ExternalClassGroup::where('token', $token)->where('active', true)->firstOrFail();

        // Honeypot: campo oculto que só um bot preencheria.
        if ($request->filled('secondary_address')) {
            return redirect()->route('estagio.turma', ['token' => $token]);
        }

        $rgm = trim((string) $request->input('rgm'));
        $email = trim((string) $request->input('email'));

        $ipKey = 'ext-verify-ip:' . $request->ip();
        $rgmKey = 'ext-verify-rgm:' . $group->id . ':' . mb_strtolower($rgm);

        if (RateLimiter::tooManyAttempts($ipKey, 10) || RateLimiter::tooManyAttempts($rgmKey, 5)) {
            return redirect()->route('estagio.turma', ['token' => $token])
                ->withInput()
                ->withErrors(['rgm' => 'Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.']);
        }

        $participant = ($rgm !== '' && $email !== '')
            ? ExternalClassParticipant::validateCredentials($group, $rgm, $email)
            : null;

        if (!$participant) {
            RateLimiter::hit($ipKey, 600);
            RateLimiter::hit($rgmKey, 900);

            return redirect()->route('estagio.turma', ['token' => $token])
                ->withInput(['rgm' => $rgm])
                ->withErrors(['rgm' => self::GENERIC_ERROR]);
        }

        RateLimiter::clear($ipKey);
        RateLimiter::clear($rgmKey);

        $request->session()->put($this->sessionKey($group), $participant->id);

        return redirect()->route('estagio.documentos', ['token' => $token]);
    }

    public function logout(Request $request, string $token)
    {
        $group = ExternalClassGroup::where('token', $token)->firstOrFail();
        $request->session()->forget($this->sessionKey($group));

        return redirect()->route('estagio.turma', ['token' => $token]);
    }

    public static function resolveParticipant(Request $request, ExternalClassGroup $group): ?ExternalClassParticipant
    {
        $participantId = $request->session()->get('ext_participant_' . $group->token);

        if (!$participantId) {
            return null;
        }

        return ExternalClassParticipant::where('id', $participantId)
            ->where('external_class_group_id', $group->id)
            ->first();
    }

    protected function hasValidSession(Request $request, ExternalClassGroup $group): bool
    {
        return self::resolveParticipant($request, $group) !== null;
    }

    protected function sessionKey(ExternalClassGroup $group): string
    {
        return 'ext_participant_' . $group->token;
    }
}
